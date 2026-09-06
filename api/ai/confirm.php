<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/AiServices.php';

ai_check_origin();
ai_require_post();
$user_id = ai_require_auth();
ai_rate_limit($conn, $user_id);

$data = ai_read_json();
$token = (string) ($data['confirmation_token'] ?? '');
$edits = is_array($data['edits'] ?? null) ? $data['edits'] : [];
if ($token === '' || strlen($token) > 128) {
    ai_json(400, ['status' => 'error', 'message' => 'Invalid confirmation token']);
}

$store = new AiPendingStore($conn, $user_id);
$pending = $store->consume($token);
if (!$pending) {
    ai_json(400, ['status' => 'error', 'message' => 'This action expired or was already used']);
}

$action = (string) $pending['action'];
$payload = $pending['payload'];
if ($edits) {
    if (isset($edits['title'])) {
        $payload['title'] = substr(trim((string) $edits['title']), 0, 255);
    }
    if (isset($edits['content'])) {
        $payload['content'] = (string) $edits['content'];
    }
    if (isset($edits['labels']) && is_array($edits['labels'])) {
        $payload['labels'] = $edits['labels'];
    }
}

$risk = ai_risk_for($action);
if ($risk === 'unknown' || $risk === 'read') {
    ai_json(400, ['status' => 'error', 'message' => 'This action cannot be confirmed']);
}

$tools = new AiNoteTools($conn, $user_id);
$result = $tools->execute($action, $payload);
if (empty($result['ok'])) {
    ai_json(400, [
        'status' => 'error',
        'message' => $result['error'] ?? 'Action failed',
        'conversation_id' => (int) $pending['conversation_id'],
    ]);
}

if ($action === 'create_note' && !empty($result['note_id'])) {
    $_SESSION['ai_last_created_note_id'] = (int) $result['note_id'];
}

$conversations = new AiConversationManager($conn, $user_id);
$summary = match ($action) {
    'create_note' => 'Đã tạo ghi chú #' . ($result['note_id'] ?? '') . ': ' . ($result['title'] ?? ''),
    'update_note' => 'Đã cập nhật ghi chú #' . ($result['note_id'] ?? ''),
    'delete_note' => 'Đã xóa ghi chú #' . ($result['note_id'] ?? ''),
    'create_label' => 'Đã tạo nhãn: ' . ($result['name'] ?? ''),
    default => 'Đã thực hiện thao tác.',
};
$conversations->addMessage((int) $pending['conversation_id'], 'assistant', $summary, [
    'type' => 'action_result',
    'action' => $action,
    'result' => $result,
]);

ai_json(200, [
    'status' => 'success',
    'conversation_id' => (int) $pending['conversation_id'],
    'response' => [
        'type' => 'message',
        'content' => $summary,
        'action_result' => $result,
        'requires_confirmation' => false,
    ],
]);
