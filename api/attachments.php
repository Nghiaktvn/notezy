<?php
/**
 * Multi-attachment API for images, videos and ordinary documents.
 * A note may have any number of attachments. Files are validated by MIME
 * content, are stored under a random server-side name, and are scoped to a
 * note the requester can edit or read.
 */
require_once dirname(__DIR__) . '/includes/session.php';
require_once 'db.php';
notezy_session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập.']);
    exit;
}
$userId = (int) $_SESSION['id'];
$method = $_SERVER['REQUEST_METHOD'];
$noteId = (int) ($_REQUEST['note_id'] ?? 0);

function attachment_note_access(mysqli $conn, int $noteId, int $userId, bool $write): bool {
    $permission = $write ? "AND ns.permission = 'write'" : '';
    $sql = "SELECT n.note_id FROM notes n LEFT JOIN note_shares ns ON ns.note_id = n.note_id AND ns.shared_with_user_id = ? WHERE n.note_id = ? AND (n.user_id = ? OR (ns.note_id IS NOT NULL $permission)) LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iii', $userId, $noteId, $userId);
    $stmt->execute();
    $ok = $stmt->get_result()->num_rows === 1;
    $stmt->close();
    return $ok;
}

if (!$noteId || !attachment_note_access($conn, $noteId, $userId, $method !== 'GET')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Bạn không có quyền với ghi chú này.']);
    exit;
}

// Files are part of a protected note's content. Do not let the attachment API
// bypass an existing per-note password or PIN gate.
$lockStmt = $conn->prepare('SELECT password_hash, pin_hash FROM notes WHERE note_id = ?');
$lockStmt->bind_param('i', $noteId);
$lockStmt->execute();
$locks = $lockStmt->get_result()->fetch_assoc() ?: [];
$lockStmt->close();
if ((!empty($locks['password_hash']) && empty($_SESSION['accessed_notes'][$noteId]))
    || (!empty($locks['pin_hash']) && empty($_SESSION['pin_unlocked_notes'][$noteId]))) {
    http_response_code(423);
    echo json_encode(['success' => false, 'message' => 'Hãy mở khóa ghi chú trước khi truy cập tệp đính kèm.']);
    exit;
}

if ($method === 'GET') {
    $stmt = $conn->prepare('SELECT attachment_id, original_name, mime_type, file_size, attachment_type, created_at FROM note_attachments WHERE note_id = ? ORDER BY created_at DESC');
    $stmt->bind_param('i', $noteId);
    $stmt->execute();
    echo json_encode(['success' => true, 'attachments' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
    exit;
}

if ($method === 'DELETE') {
    $attachmentId = (int) ($_REQUEST['attachment_id'] ?? 0);
    $stmt = $conn->prepare('SELECT na.stored_name, n.user_id FROM note_attachments na JOIN notes n ON n.note_id = na.note_id WHERE na.attachment_id = ? AND na.note_id = ?');
    $stmt->bind_param('ii', $attachmentId, $noteId);
    $stmt->execute();
    $file = $stmt->get_result()->fetch_assoc();
    if (!$file || (int)$file['user_id'] !== $userId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Chỉ chủ ghi chú có thể xóa tệp.']);
        exit;
    }
    $delete = $conn->prepare('DELETE FROM note_attachments WHERE attachment_id = ? AND note_id = ?');
    $delete->bind_param('ii', $attachmentId, $noteId);
    $delete->execute();
    $path = dirname(__DIR__) . '/uploads/attachments/' . basename($file['stored_name']);
    if (is_file($path)) @unlink($path);
    echo json_encode(['success' => true]);
    exit;
}

if ($method !== 'POST' || empty($_FILES['attachments'])) {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST multipart attachments[] required.']);
    exit;
}

$allowed = [
    'image/jpeg' => ['image', 'jpg', 5 * 1024 * 1024],
    'image/png' => ['image', 'png', 5 * 1024 * 1024],
    'image/gif' => ['image', 'gif', 5 * 1024 * 1024],
    'image/webp' => ['image', 'webp', 5 * 1024 * 1024],
    'video/mp4' => ['video', 'mp4', 25 * 1024 * 1024],
    'video/webm' => ['video', 'webm', 25 * 1024 * 1024],
    'video/ogg' => ['video', 'ogv', 25 * 1024 * 1024],
    'application/pdf' => ['file', 'pdf', 10 * 1024 * 1024],
    'text/plain' => ['file', 'txt', 2 * 1024 * 1024],
    'application/zip' => ['file', 'zip', 10 * 1024 * 1024],
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['file', 'docx', 10 * 1024 * 1024],
];
$files = $_FILES['attachments'];
$count = is_array($files['name']) ? count($files['name']) : 1;
if ($count > 10) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Tối đa 10 tệp cho mỗi lần tải.']);
    exit;
}
$dir = dirname(__DIR__) . '/uploads/attachments';
if (!is_dir($dir)) mkdir($dir, 0775, true);
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$saved = [];
for ($i = 0; $i < $count; $i++) {
    $name = is_array($files['name']) ? $files['name'][$i] : $files['name'];
    $tmp = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
    $size = (int) (is_array($files['size']) ? $files['size'][$i] : $files['size']);
    $error = (int) (is_array($files['error']) ? $files['error'][$i] : $files['error']);
    if ($error !== UPLOAD_ERR_OK || $size <= 0) continue;
    $mime = finfo_file($finfo, $tmp);
    if (!isset($allowed[$mime]) || $size > $allowed[$mime][2]) continue;
    [$type, $ext] = $allowed[$mime];
    $stored = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($tmp, $dir . '/' . $stored)) continue;
    $safeOriginal = mb_substr(basename((string)$name), 0, 255);
    $stmt = $conn->prepare('INSERT INTO note_attachments (note_id, uploaded_by_user_id, original_name, stored_name, mime_type, file_size, attachment_type) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('iisssis', $noteId, $userId, $safeOriginal, $stored, $mime, $size, $type);
    if ($stmt->execute()) $saved[] = ['attachment_id' => $conn->insert_id, 'name' => $safeOriginal, 'type' => $type];
}
finfo_close($finfo);
echo json_encode(['success' => true, 'attachments' => $saved]);
