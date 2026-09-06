<?php
require_once dirname(__DIR__) . '/includes/session.php';
/**
 * api/collab_presence.php
 * Tracks active collaborators viewing or editing a note in real time.
 * Provides typing indicators and online collaborator list.
 */
require_once 'db.php';
notezy_session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit();
}

$user_id = (int)$_SESSION['id'];
$note_id = isset($_REQUEST['note_id']) ? (int)$_REQUEST['note_id'] : 0;
$is_typing = !empty($_REQUEST['is_typing']) ? 1 : 0;

if (!$note_id) {
    echo json_encode(["success" => false, "message" => "Missing note_id"]);
    exit();
}

// 1. Check permission: owner OR shared user
$auth = $conn->prepare(
    "SELECT note_id FROM notes WHERE note_id = ? AND (
        user_id = ? OR note_id IN (SELECT note_id FROM note_shares WHERE shared_with_user_id = ?)
    ) LIMIT 1"
);
$auth->bind_param("iii", $note_id, $user_id, $user_id);
$auth->execute();
$has_access = $auth->get_result()->fetch_assoc();
$auth->close();

if (!$has_access) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Không có quyền truy cập ghi chú này"]);
    exit();
}

// 2. Ensure note_collab_presence table exists
$conn->query("
    CREATE TABLE IF NOT EXISTS `note_collab_presence` (
        `note_id` INT NOT NULL,
        `user_id` INT NOT NULL,
        `is_typing` TINYINT(1) NOT NULL DEFAULT 0,
        `last_seen` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`note_id`, `user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// 3. Update current user's presence & typing status
$stmt = $conn->prepare("
    INSERT INTO note_collab_presence (note_id, user_id, is_typing, last_seen)
    VALUES (?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE is_typing = VALUES(is_typing), last_seen = NOW()
");
$stmt->bind_param("iii", $note_id, $user_id, $is_typing);
$stmt->execute();
$stmt->close();

// 4. Garbage-collect inactive sessions (> 15 seconds)
$conn->query("DELETE FROM note_collab_presence WHERE last_seen < NOW() - INTERVAL 15 SECOND");

// 5. Query active collaborators (within last 10 seconds)
$q = $conn->prepare("
    SELECT p.user_id, p.is_typing, u.username, u.firstname, u.lastname
    FROM note_collab_presence p
    JOIN users u ON p.user_id = u.id
    WHERE p.note_id = ? AND p.last_seen >= NOW() - INTERVAL 10 SECOND
    ORDER BY p.user_id = ? DESC, p.last_seen DESC
");
$q->bind_param("ii", $note_id, $user_id);
$q->execute();
$res = $q->get_result();

$collaborators = [];
$typing_users = [];

while ($row = $res->fetch_assoc()) {
    $is_me = ((int)$row['user_id'] === $user_id);
    $displayName = trim($row['firstname'] . ' ' . $row['lastname']) ?: $row['username'];
    $collaborators[] = [
        'user_id' => (int)$row['user_id'],
        'username' => $row['username'],
        'name' => $displayName,
        'is_me' => $is_me,
        'is_typing' => (bool)$row['is_typing']
    ];
    if (!$is_me && $row['is_typing']) {
        $typing_users[] = $displayName;
    }
}
$q->close();

echo json_encode([
    'success' => true,
    'note_id' => $note_id,
    'collaborators' => $collaborators,
    'typing_users' => $typing_users,
    'count' => count($collaborators)
]);
