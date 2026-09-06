<?php
require_once dirname(__DIR__) . '/includes/session.php';
/**
 * api/collab_sync.php
 * Realtime synchronization endpoint for note collaboration.
 * Returns note state (title, content, updated_at, saved_by) and active collaborators.
 * Can be polled rapidly (every 1s) or called on-demand.
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
$note_id = isset($_GET['note_id']) ? (int)$_GET['note_id'] : 0;
$client_updated_at = isset($_GET['since']) ? trim($_GET['since']) : '';
$is_typing = !empty($_GET['typing']) ? 1 : 0;

if (!$note_id) {
    echo json_encode(["success" => false, "message" => "Missing note_id"]);
    exit();
}

// 1. Check authorization: owner OR shared user (read or write)
$stmt = $conn->prepare(
    "SELECT n.note_id, n.user_id as owner_id, n.title, n.content, n.updated_at,
            ns.permission as share_permission,
            u.username as owner_username, u.firstname as owner_firstname, u.lastname as owner_lastname
     FROM notes n
     JOIN users u ON n.user_id = u.id
     LEFT JOIN note_shares ns ON n.note_id = ns.note_id AND ns.shared_with_user_id = ?
     WHERE n.note_id = ? AND (n.user_id = ? OR ns.note_id IS NOT NULL)
     LIMIT 1"
);
$stmt->bind_param("iii", $user_id, $note_id, $user_id);
$stmt->execute();
$note = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$note) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Không tìm thấy ghi chú hoặc không có quyền truy cập."]);
    exit();
}

// 2. Touch presence for current user
$conn->query("
    CREATE TABLE IF NOT EXISTS `note_collab_presence` (
        `note_id` INT NOT NULL,
        `user_id` INT NOT NULL,
        `is_typing` TINYINT(1) NOT NULL DEFAULT 0,
        `last_seen` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`note_id`, `user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$p_stmt = $conn->prepare("
    INSERT INTO note_collab_presence (note_id, user_id, is_typing, last_seen)
    VALUES (?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE is_typing = VALUES(is_typing), last_seen = NOW()
");
$p_stmt->bind_param("iii", $note_id, $user_id, $is_typing);
$p_stmt->execute();
$p_stmt->close();

// 3. Query active collaborators in last 12 seconds
$q = $conn->prepare("
    SELECT p.user_id, p.is_typing, u.username, u.firstname, u.lastname
    FROM note_collab_presence p
    JOIN users u ON p.user_id = u.id
    WHERE p.note_id = ? AND p.last_seen >= NOW() - INTERVAL 12 SECOND
    ORDER BY p.last_seen DESC
");
$q->bind_param("i", $note_id);
$q->execute();
$pres_res = $q->get_result();

$collaborators = [];
$typing_users = [];

while ($row = $pres_res->fetch_assoc()) {
    $uid = (int)$row['user_id'];
    $is_me = ($uid === $user_id);
    $displayName = trim($row['firstname'] . ' ' . $row['lastname']) ?: $row['username'];

    $collaborators[] = [
        'user_id' => $uid,
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

$has_newer = !empty($client_updated_at) && ($note['updated_at'] > $client_updated_at);

echo json_encode([
    'success' => true,
    'note_id' => (int)$note['note_id'],
    'title' => $note['title'],
    'content' => $note['content'],
    'updated_at' => $note['updated_at'],
    'has_newer' => $has_newer,
    'collaborators' => $collaborators,
    'typing_users' => $typing_users,
    'active_count' => count($collaborators)
]);
