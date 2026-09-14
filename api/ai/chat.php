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
    ai_json(400, ['status' => 'error', 'message' => 'Message is required']);
}
if (mb_strlen($message, 'UTF-8') > $max_chars) {
    ai_json(400, ['status' => 'error', 'message' => 'Message is too long']);
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

/**
 * Keep the assistant useful when the optional Python service is restarting or
 * temporarily unavailable.  This fallback only reads the already-authorized
 * context assembled for the current user; it never creates, changes, shares,
 * or deletes a note.
 */
function ai_local_fallback_response(array $context, string $message): array {
    $notes = is_array($context['recent_notes'] ?? null) ? $context['recent_notes'] : [];
    if (!$notes) {
        return [
            'type' => 'message',
            'content' => 'Trợ lý AI đang chuyển sang chế độ nội bộ. Bạn chưa có ghi chú nào để tóm tắt; hãy tạo một ghi chú rồi thử lại.',
            'requires_confirmation' => false,
            'references' => [],
        ];
    }

    $note = $notes[0];
    $title = trim((string) ($note['title'] ?? 'Ghi chú gần đây'));
    $body = trim(preg_replace('/\s+/u', ' ', (string) ($note['content'] ?? '')));
    $sentences = preg_split('/(?<=[.!?。])\s+/u', $body, 3) ?: [];
    $points = array_values(array_filter(array_map('trim', $sentences)));
    if (!$points && $body !== '') {
        $points = [mb_substr($body, 0, 260, 'UTF-8')];
    }
    if (!$points) {
        $points = ['Ghi chú chưa có nội dung để phân tích.'];
    }

    $content = "Trợ lý AI đang dùng chế độ nội bộ do dịch vụ AI tạm thời chưa sẵn sàng.\n\n"
        . "Tóm tắt « {$title} »:\n"
        . implode("\n", array_map(static fn(string $point, int $index): string => '- ' . ($index + 1) . '. ' . $point, $points, array_keys($points)));

    $noteId = (int) ($note['note_id'] ?? 0);
    return [
        'type' => 'message',
        'content' => $content,
        'requires_confirmation' => false,
        'references' => $noteId > 0 ? [[
            'note_id' => $noteId,
            'title' => $title,
            'url' => 'notepass.php?id=' . $noteId,
        ]] : [],
    ];
}

for ($i = 0; $i < $max_loops; $i++) {
    $agent = ai_call_agent([
        'messages' => $llm_messages,
        'context' => $context,
        'tool_results' => $tool_results,
    ]);
    if (empty($agent['ok'])) {
        $final_response = ai_local_fallback_response($context, $message);
        break;
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
            // what lets the assistant say "đã tạo note" in the same turn the user
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
]);
