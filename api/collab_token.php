<?php
/** Issue a short-lived, note-scoped token for the Docker WebSocket relay. */
require_once dirname(__DIR__) . '/includes/session.php';
require_once 'db.php';
notezy_session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$noteId = (int) ($_GET['note_id'] ?? 0);
$userId = (int) $_SESSION['id'];
$stmt = $conn->prepare("SELECT n.note_id FROM notes n LEFT JOIN note_shares ns ON ns.note_id = n.note_id AND ns.shared_with_user_id = ? WHERE n.note_id = ? AND (n.user_id = ? OR ns.note_id IS NOT NULL) LIMIT 1");
$stmt->bind_param('iii', $userId, $noteId, $userId);
$stmt->execute();
if (!$stmt->get_result()->fetch_assoc()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}
$secret = (string) getenv('AI_AGENT_SHARED_SECRET');
if ($secret === '') {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Collaboration service is not configured']);
    exit;
}
$expires = time() + 300;
$payload = $userId . ':' . $noteId . ':' . $expires;
$token = $payload . ':' . hash_hmac('sha256', $payload, $secret);
echo json_encode(['success' => true, 'token' => $token, 'expires_at' => $expires]);
