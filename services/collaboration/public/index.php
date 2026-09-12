<?php
declare(strict_types=1);
require __DIR__ . '/../../shared/bootstrap.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($path === '/health' || $path === '/ready') svc_health('collaboration', true);
$userId = svc_bearer_user();
$db = svc_db();

function collab_can_access(PDO $db, int $noteId, int $userId): bool {
    $stmt = $db->prepare("SELECT n.note_id FROM notes n LEFT JOIN note_shares ns ON ns.note_id=n.note_id AND ns.shared_with_user_id=? WHERE n.note_id=? AND (n.user_id=? OR ns.note_id IS NOT NULL) LIMIT 1");
    $stmt->execute([$userId, $noteId, $userId]);
    return (bool) $stmt->fetchColumn();
}

// This REST service owns authorization and short-lived room-token issuance;
// the TCP WebSocket relay in collaboration_ws.py owns frame transport.
if ($path === '/token' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $noteId = (int) ($_GET['note_id'] ?? 0);
    if ($noteId < 1 || !collab_can_access($db, $noteId, $userId)) svc_json(403, ['error' => 'NOTE_ACCESS_DENIED']);
    $expires = time() + 300;
    $payload = $userId . ':' . $noteId . ':' . $expires;
    $secret = svc_env('COLLAB_SECRET', 'notezy-local-collaboration-secret');
    svc_json(200, [
        'token' => $payload . ':' . hash_hmac('sha256', $payload, $secret),
        'expires_at' => $expires,
        'transport' => 'websocket',
        'ws_url' => svc_env('COLLAB_WS_PUBLIC_URL', 'ws://localhost:8766'),
    ]);
}

if ($path === '/presence' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = svc_input();
    $noteId = (int) ($data['note_id'] ?? 0);
    if ($noteId < 1 || !collab_can_access($db, $noteId, $userId)) svc_json(403, ['error' => 'NOTE_ACCESS_DENIED']);
    $stmt = $db->prepare('INSERT INTO note_collab_presence (note_id,user_id,is_typing,last_seen) VALUES (?,?,?,NOW()) ON DUPLICATE KEY UPDATE is_typing=VALUES(is_typing),last_seen=NOW()');
    $stmt->execute([$noteId, $userId, !empty($data['typing']) ? 1 : 0]);
    svc_json(200, ['status' => 'ok', 'transport' => 'websocket']);
}

svc_json(404, ['error' => 'ROUTE_NOT_FOUND']);
