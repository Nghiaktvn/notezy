<?php
/** Secure attachment download/view endpoint. */
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/db.php';
notezy_session_start();

if (empty($_SESSION['id'])) {
    http_response_code(401);
    exit('Vui lòng đăng nhập.');
}
$attachmentId = (int) ($_GET['attachment_id'] ?? 0);
$userId = (int) $_SESSION['id'];
if ($attachmentId <= 0) {
    http_response_code(400);
    exit('Tệp không hợp lệ.');
}
$stmt = $conn->prepare("SELECT na.original_name, na.stored_name, na.mime_type, n.note_id, n.password_hash, n.pin_hash FROM note_attachments na JOIN notes n ON n.note_id = na.note_id LEFT JOIN note_shares ns ON ns.note_id = n.note_id AND ns.shared_with_user_id = ? WHERE na.attachment_id = ? AND (n.user_id = ? OR ns.note_id IS NOT NULL) LIMIT 1");
$stmt->bind_param('iii', $userId, $attachmentId, $userId);
$stmt->execute();
$file = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$file) {
    http_response_code(404);
    exit('Không tìm thấy tệp hoặc bạn không có quyền truy cập.');
}
if ((!empty($file['password_hash']) && empty($_SESSION['accessed_notes'][$file['note_id']]))
    || (!empty($file['pin_hash']) && empty($_SESSION['pin_unlocked_notes'][$file['note_id']]))) {
    http_response_code(423);
    exit('Hãy mở khóa ghi chú trước khi tải tệp.');
}
$path = __DIR__ . '/uploads/attachments/' . basename($file['stored_name']);
if (!is_file($path)) {
    http_response_code(404);
    exit('Tệp không còn trên máy chủ.');
}
$safeName = str_replace(["\r", "\n", '"'], '', $file['original_name']);
header('Content-Type: ' . $file['mime_type']);
header('Content-Length: ' . filesize($path));
header("Content-Disposition: attachment; filename=\"{$safeName}\"");
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
