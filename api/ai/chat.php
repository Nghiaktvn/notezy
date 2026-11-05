<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/AiServices.php';

ai_check_origin();
ai_require_post();
$user_id = ai_require_auth();
ai_rate_limit($conn, $user_id);

$data = ai_read_json();
$message = trim((string) ($data['message'] ?? ''));
$max_chars = ai_env_int('AI_MAX_MESSAGE_CHARS', 4000);
if ($message === '') {
    ai_json(400, ['status' => 'error', 'message' => 'Vui lòng nhập tin nhắn']);
}
if (mb_strlen($message, 'UTF-8') > $max_chars) {
    ai_json(400, ['status' => 'error', 'message' => 'Tin nhắn quá dài']);
}

// Account secrets are entered only in a separate form, never sent to the LLM
// or persisted as chat content.
$normalized = mb_strtolower($message, 'UTF-8');
if (preg_match('/(?:đổi|thay đổi|doi|thay doi|change|update|reset).*(?:mật khẩu|mat khau|password)/u', $normalized)
    && !preg_match('/(?:ghi chú|note|pin)/u', $normalized)) {
    $_SESSION['ai_password_form'] = ['token' => bin2hex(random_bytes(32)), 'expires' => time() + 600];
    ai_json(200, [
        'status' => 'success',
        'conversation_id' => null,
        'response' => ['type' => 'password_change_form', 'content' => 'Nhập mật khẩu trong biểu mẫu riêng để đổi mật khẩu tài khoản.',
                       'form_token' => $_SESSION['ai_password_form']['token'], 'requires_confirmation' => false],
    ]);
}

$client_context = is_array($data['context'] ?? null) ? $data['context'] : [];
$conversations = new AiConversationManager($conn, $user_id);
$conversation_id = $conversations->getOrCreate(
    isset($data['conversation_id']) ? (int) $data['conversation_id'] : null,
    $message
);
$conversations->addMessage($conversation_id, 'user', $message);

$history = $conversations->history($conversation_id, 20);
$context = (new AiContextManager($conn, $user_id))->build($client_context);
$tools = new AiNoteTools($conn, $user_id);
$pending = new AiPendingStore($conn, $user_id);

$llm_messages = ai_history_for_llm($history);
$tool_results = null;
$final_response = null;
$max_loops = 4;

function ai_response_references(?array $toolResults): array {
    $references = [];
    foreach ($toolResults ?? [] as $entry) {
        $result = is_array($entry['result'] ?? null) ? $entry['result'] : [];
        $candidates = is_array($result['notes'] ?? null) ? $result['notes'] : [$result];
        foreach ($candidates as $note) {
            $noteId = (int) ($note['note_id'] ?? 0);
            $title = trim((string) ($note['title'] ?? ''));
            if ($noteId <= 0 || $title === '') continue;
            $references[$noteId] = ['note_id' => $noteId, 'title' => $title, 'url' => 'notepass.php?id=' . $noteId];
            if (count($references) >= 5) break 2;
        }
    }
    return array_values($references);
}

for ($i = 0; $i < $max_loops; $i++) {
    $agent = ai_call_agent([
        'messages' => $llm_messages,
        'context' => $context,
        'tool_results' => $tool_results,
    ]);
    if (empty($agent['ok'])) {
        ai_json((int)($agent['status'] ?? 503), [
            'status' => 'error',
            'message' => (string)($agent['error'] ?? 'Trợ lý tạm thời không phản hồi. Vui lòng thử lại.'),
            'conversation_id' => $conversation_id,
        ]);
    }
    $payload = $agent['data'] ?? [];
    $tool_calls = $payload['tool_calls'] ?? [];
    $content = trim((string) ($payload['content'] ?? ''));

    if (!$tool_calls) {
        $final_response = [
            'type' => 'message',
            'content' => $content !== '' ? $content : 'Tôi có thể giúp tìm, tóm tắt hoặc tạo ghi chú.',
            'requires_confirmation' => false,
            'references' => ai_response_references($tool_results),
        ];
        break;
    }

    $read_results = [];
    foreach ($tool_calls as $call) {
        $name = (string) ($call['name'] ?? '');
        $args = is_array($call['arguments'] ?? null) ? $call['arguments'] : [];
        unset($args['user_id'], $args['userId']);
        if (!in_array($name, ai_allowed_tools(), true)) {
            $read_results[] = [
                'tool_call_id' => $call['id'] ?? $name,
                'name' => $name,
                'result' => ['ok' => false, 'error' => 'Tool not allowed'],
            ];
            continue;
        }
        $risk = ai_risk_for($name);
        if ($risk === 'read') {
            $result = $tools->execute($name, $args);
            $read_results[] = [
                'tool_call_id' => $call['id'] ?? $name,
                'name' => $name,
                'result' => $result,
            ];
            continue;
        }

        if ($name === 'delete_note') {
            $note_id = (int) ($args['note_id'] ?? 0);
            $preview = $tools->execute('get_note', ['note_id' => $note_id]);
            $title = $preview['ok'] ? ($preview['note']['title'] ?? 'this note') : 'this note';
            if (!$preview['ok']) {
                $final_response = [
                    'type' => 'message',
                    'content' => 'Không tìm thấy ghi chú này hoặc bạn không có quyền xóa.',
                    'requires_confirmation' => false,
                ];
                break 2;
            }
            $token = $pending->create($conversation_id, $name, $risk, $args);
            $final_response = [
                'type' => 'action_confirmation',
                'action' => 'delete_note',
                'note_id' => $note_id,
                'title' => $title,
                'content' => "Thao tác này sẽ xóa vĩnh viễn ghi chú « {$title} ». Bạn có muốn tiếp tục?",
                'confirmation_token' => $token,
                'requires_confirmation' => true,
            ];
            break 2;
        }

        if ($name === 'create_note') {
            // Auto-create: low risk (fully reversible via delete), so we execute
            // immediately instead of holding a pending confirmation token. This is
            // what lets the assistant say "đã tạo ghi chú" in the same turn the user
            // asked for it, instead of a manual "xác nhận" click.
            $result = $tools->execute('create_note', $args);
            if (!$result['ok']) {
                $final_response = [
                    'type' => 'message',
                    'content' => 'Không tạo được ghi chú: ' . ($result['error'] ?? 'lỗi không xác định'),
                    'requires_confirmation' => false,
                ];
                break 2;
            }
            $_SESSION['ai_last_created_note_id'] = (int)$result['note_id'];
            $final_response = [
                'type' => 'note_created',
                'action' => 'create_note',
                'note_id' => $result['note_id'],
                'title' => $result['title'],
                'note_content' => $result['content'],
                'labels' => $result['labels'],
                'note_type' => $result['note_type'],
                'background_color' => $result['background_color'],
                'text_color' => $result['text_color'],
                'reminder_at' => $result['reminder_at'],
                'content' => "Đã tạo ghi chú « {$result['title']} ».",
                'requires_confirmation' => false,
            ];
            break 2;
        }

        if ($name === 'create_schedule') {
            $result = $tools->execute('create_schedule', $args);
            if (!$result['ok']) {
                $final_response = [
                    'type' => 'message',
                    'content' => 'Không tạo được lịch học: ' . ($result['error'] ?? 'lỗi không xác định'),
                    'requires_confirmation' => false,
                ];
                break 2;
            }
            $when = $result['specific_date'] ?: ('thứ ' . ((int) $result['day_of_week'] + 1) . ' hằng tuần');
            $final_response = [
                'type' => 'schedule_created',
                'action' => 'create_schedule',
                'schedule_id' => $result['schedule_id'],
                'title' => $result['title'],
                'content' => 'Đã thêm “' . $result['title'] . '” vào lịch: ' . $when . ', ' . $result['start_time'] . '–' . $result['end_time'] . '.',
                'requires_confirmation' => false,
            ];
            break 2;
        }

        if ($name === 'update_note') {
            $note_id = (int) ($args['note_id'] ?? 0);
            $existing = $tools->execute('get_note', ['note_id' => $note_id]);
            if (!$existing['ok']) {
                $final_response = [
                    'type' => 'message',
                    'content' => 'Không tìm thấy ghi chú cần sửa hoặc bạn không có quyền.',
                    'requires_confirmation' => false,
                ];
                break 2;
            }
            $token = $pending->create($conversation_id, $name, $risk, $args);
            $final_response = [
                'type' => 'note_preview',
                'action' => 'update_note',
                'note_id' => $note_id,
                'title' => (string) ($args['title'] ?? $existing['note']['title']),
                'content' => (string) ($args['content'] ?? $existing['note']['content']),
                'labels' => is_array($args['labels'] ?? null) ? $args['labels'] : ($existing['note']['labels'] ?? []),
                'confirmation_token' => $token,
                'requires_confirmation' => true,
            ];
            break 2;
        }

        if ($name === 'share_note') {
            $noteId = (int)($args['note_id'] ?? 0);
            $existing = $tools->execute('get_note', ['note_id' => $noteId]);
            if (empty($existing['ok']) || trim((string)($args['recipient'] ?? '')) === '') {
                $final_response = ['type' => 'message', 'content' => 'Cần ghi chú thuộc sở hữu đã mở khóa và tên tài khoản hoặc thư điện tử người nhận.', 'requires_confirmation' => false];
                break 2;
            }
            $permission = ($args['permission'] ?? 'read') === 'write' ? 'được chỉnh sửa' : 'chỉ xem';
            $token = $pending->create($conversation_id, $name, $risk, $args);
            $final_response = ['type' => 'action_confirmation', 'action' => $name, 'note_id' => $noteId,
                'title' => $existing['note']['title'],
                'content' => 'Chia sẻ «' . $existing['note']['title'] . '» cho ' . $args['recipient'] . ' với quyền ' . $permission . '?',
                'confirmation_token' => $token, 'requires_confirmation' => true];
            break 2;
        }

        if ($name === 'create_label') {
            $token = $pending->create($conversation_id, $name, $risk, $args);
            $final_response = [
                'type' => 'action_confirmation',
                'action' => 'create_label',
                'title' => (string) ($args['name'] ?? ''),
                'content' => 'Tạo nhãn « ' . ($args['name'] ?? '') . ' »?',
                'confirmation_token' => $token,
                'requires_confirmation' => true,
            ];
            break 2;
        }
    }

    if ($final_response) {
        break;
    }

    $llm_messages[] = [
        'role' => 'assistant',
        'content' => $content,
        'tool_calls' => $tool_calls,
    ];
    $tool_results = $read_results;
}

if (!$final_response) {
    $final_response = [
        'type' => 'message',
        'content' => 'Tôi chưa hoàn tất được yêu cầu. Vui lòng thử lại.',
        'requires_confirmation' => false,
    ];
}

$stored = $final_response;
unset($stored['confirmation_token']);
$conversations->addMessage(
    $conversation_id,
    'assistant',
    (string) ($final_response['content'] ?? $final_response['title'] ?? ''),
    ['response' => $stored, 'type' => $final_response['type']]
);

ai_json(200, [
    'status' => 'success',
    'conversation_id' => $conversation_id,
    'response' => $final_response,
    'provider' => $payload['provider'] ?? (getenv('LLM_PROVIDER') ?: 'unknown'),
    'model' => $payload['model'] ?? null,
]);
