<?php
require_once dirname(__DIR__) . '/includes/session.php';
/**
 * api/note_autosave.php
 * Lightweight endpoint for autosaving title + content of a note.
 * Returns JSON {success, updated_at} for the client to track.
 * Only the note owner OR a shared user with 'write' permission may save.
 */
require_once 'db.php';
notezy_session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "POST required"]);
    exit();
}

$user_id = (int)$_SESSION['id'];
$note_id = isset($_POST['note_id']) ? (int)$_POST['note_id'] : 0;
$title   = isset($_POST['title'])   ? trim($_POST['title'])   : null;
$content = isset($_POST['content']) ? trim($_POST['content']) : null;

if (!$note_id) {
    echo json_encode(["success" => false, "message" => "Missing note_id"]);
    exit();
}

$client_updated_at = isset($_POST['client_updated_at']) ? trim($_POST['client_updated_at']) : '';
$force_save        = !empty($_POST['force_save']);

// Authorisation & Fetch current state: owner OR shared-write
$auth = $conn->prepare(
    "SELECT n.note_id, n.title, n.content, n.updated_at, n.password_hash, n.pin_hash, u.username as owner_name
     FROM notes n
     JOIN users u ON n.user_id = u.id
     WHERE n.note_id = ?
       AND (n.user_id = ?
            OR n.note_id IN (
                SELECT note_id FROM note_shares
                WHERE shared_with_user_id = ? AND permission = 'write'
            ))"
);
$auth->bind_param("iii", $note_id, $user_id, $user_id);
$auth->execute();
$current_note = $auth->get_result()->fetch_assoc();
$auth->close();

if (!$current_note) {
    echo json_encode(["success" => false, "message" => "Bạn không có quyền chỉnh sửa ghi chú này."]);
    exit();
}
if ((!empty($current_note['password_hash']) && empty($_SESSION['accessed_notes'][$note_id]))
    || (!empty($current_note['pin_hash']) && empty($_SESSION['pin_unlocked_notes'][$note_id]))) {
    http_response_code(423);
    echo json_encode(["success" => false, "message" => "Hãy mở khóa ghi chú trước khi lưu tự động."]);
    exit();
}

// ── Conflict Detection (Chống ghi đè dữ liệu mới hơn từ cộng tác viên) ──
if (!$force_save && !empty($client_updated_at) && !empty($current_note['updated_at'])) {
    if ($current_note['updated_at'] > $client_updated_at) {
        echo json_encode([
            "success" => false,
            "conflict" => true,
            "message" => "Ghi chú đã được cộng tác viên cập nhật phiên bản mới hơn.",
            "server_note" => [
                "title" => $current_note['title'],
                "content" => $current_note['content'],
                "updated_at" => $current_note['updated_at'],
                "saved_by" => $current_note['owner_name'] ?? 'Cộng tác viên'
            ]
        ]);
        exit();
    }
}

// Build SET dynamically — only fields that were sent
$sets   = [];
$types  = '';
$values = [];

if ($title !== null) {
    $sets[]  = "title = ?";
    $types  .= 's';
    $values[] = $title;
}
if ($content !== null) {
    $sets[]  = "content = ?";
    $types  .= 's';
    $values[] = $content;
}

if (empty($sets)) {
    echo json_encode(["success" => false, "message" => "Nothing to save"]);
    exit();
}

// Always bump updated_at so collaborators detect the change
$sets[]  = "updated_at = NOW()";
$sql     = "UPDATE notes SET " . implode(', ', $sets) . " WHERE note_id = ?";
$types  .= 'i';
$values[] = $note_id;

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$values);

if ($stmt->execute()) {
    // Return the fresh updated_at so client can track it
    $ts_stmt = $conn->prepare("SELECT updated_at FROM notes WHERE note_id = ?");
    $ts_stmt->bind_param("i", $note_id);
    $ts_stmt->execute();
    $ts_row = $ts_stmt->get_result()->fetch_assoc();
    $ts_stmt->close();

    // Lấy username người vừa lưu
    $u_stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $u_stmt->bind_param("i", $user_id);
    $u_stmt->execute();
    $u_row = $u_stmt->get_result()->fetch_assoc();
    $u_stmt->close();

    echo json_encode([
        "success" => true,
        "updated_at" => $ts_row['updated_at'],
        "saved_by" => $u_row['username'] ?? 'Bạn'
    ]);
} else {
    echo json_encode(["success" => false, "message" => "DB error: " . $conn->error]);
}
$stmt->close();
