<?php
require_once dirname(__DIR__) . '/includes/session.php';
require_once 'db.php';
notezy_session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

$user_id = (int)$_SESSION['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['image'], $_POST['note_id'])) {
    echo json_encode(["status" => "error", "message" => "Invalid request"]);
    exit();
}

$note_id = (int)$_POST['note_id'];

// Verify note belongs to user — prepared statement
$chk = $conn->prepare("SELECT note_id FROM notes WHERE note_id = ? AND user_id = ?");
$chk->bind_param("ii", $note_id, $user_id);
$chk->execute();
if ($chk->get_result()->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Note not found or unauthorized"]);
    exit();
}

$file = $_FILES['image'];

// ── Size validation (max 5 MB) ────────────────────────────────────────────
$max_bytes = 5 * 1024 * 1024;
if ($file['size'] > $max_bytes) {
    echo json_encode(["status" => "error", "message" => "File quá lớn. Tối đa 5 MB."]);
    exit();
}
if ($file['error'] !== UPLOAD_ERR_OK) {
    $error_msg = 'Lỗi không xác định (' . $file['error'] . ')';
    switch ($file['error']) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            $error_msg = 'File quá lớn.';
            break;
        case UPLOAD_ERR_PARTIAL:
            $error_msg = 'File chỉ được tải lên một phần.';
            break;
        case UPLOAD_ERR_NO_FILE:
            $error_msg = 'Không có file nào được tải lên.';
            break;
        case UPLOAD_ERR_NO_TMP_DIR:
            $error_msg = 'Thiếu thư mục tạm.';
            break;
        case UPLOAD_ERR_CANT_WRITE:
            $error_msg = 'Lỗi ghi file lên đĩa.';
            break;
        case UPLOAD_ERR_EXTENSION:
            $error_msg = 'Một phần mở rộng PHP đã chặn upload file.';
            break;
    }
    echo json_encode(["status" => "error", "message" => "Upload lỗi: " . $error_msg]);
    exit();
}

// ── Extension whitelist ───────────────────────────────────────────────────
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowed_extensions, true)) {
    echo json_encode(["status" => "error", "message" => "Định dạng file không được phép. Chỉ cho phép: " . implode(', ', $allowed_extensions)]);
    exit();
}

// ── MIME type validation (actual file content, not just extension) ────────
$allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
if (!in_array($mime, $allowed_mimes, true)) {
    echo json_encode(["status" => "error", "message" => "File không phải ảnh hợp lệ (MIME không được phép)."]);
    exit();
}

// ── Target directory — auto-create if missing (XAMPP or Docker) ──────────
$target_dir = __DIR__ . '/../uploads/';
if (!is_dir($target_dir)) {
    mkdir($target_dir, 0775, true);
}

// Randomize filename to prevent enumeration & override
$safe_name   = bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
$target_file = $target_dir . $safe_name;
$image_path  = 'uploads/' . $safe_name;

if (!move_uploaded_file($file['tmp_name'], $target_file)) {
    echo json_encode(["status" => "error", "message" => "Không thể lưu file"]);
    exit();
}

// ── Store old image path to delete it ────────────────────────────────────
$old = $conn->prepare("SELECT image_path FROM notes WHERE note_id = ?");
$old->bind_param("i", $note_id);
$old->execute();
$old_row = $old->get_result()->fetch_assoc();
if ($old_row && !empty($old_row['image_path'])) {
    $old_full = __DIR__ . '/../' . $old_row['image_path'];
    if (file_exists($old_full)) @unlink($old_full);
}

// ── Update DB ─────────────────────────────────────────────────────────────
$upd = $conn->prepare("UPDATE notes SET image_path = ? WHERE note_id = ?");
$upd->bind_param("si", $image_path, $note_id);
if ($upd->execute()) {
    echo json_encode(["status" => "success", "message" => "Image uploaded", "image_path" => $image_path]);
} else {
    // Roll back file on DB failure
    @unlink($target_file);
    echo json_encode(["status" => "error", "message" => "Failed to update DB"]);
}
?>
