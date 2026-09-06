<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/AiServices.php';

ai_check_origin();
$user_id = ai_require_auth();
$conversations = new AiConversationManager($conn, $user_id);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id > 0) {
        $history = $conversations->history($id, 50);
        $safe = [];
        foreach ($history as $row) {
            $meta = [];
            if (!empty($row['metadata'])) {
                $meta = is_array($row['metadata']) ? $row['metadata'] : (json_decode($row['metadata'], true) ?: []);
            }
            $resp = $meta['response'] ?? null;
            if (is_array($resp)) {
                unset($resp['confirmation_token']);
            }
            $safe[] = [
                'role' => $row['role'],
                'content' => $row['content'],
                'response' => $resp,
            ];
        }
        ai_json(200, ['status' => 'success', 'conversation_id' => $id, 'messages' => $safe]);
    }
    ai_json(200, ['status' => 'success', 'conversations' => $conversations->listConversations()]);
}

ai_json(405, ['status' => 'error', 'message' => 'Method not allowed']);
