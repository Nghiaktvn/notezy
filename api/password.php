<?php
require_once dirname(__DIR__) . '/includes/session.php';
require_once 'db.php';
notezy_session_start();
header('Content-Type: application/json');

// Retired: note passwords are intentionally unavailable; use api/note_pin.php.
http_response_code(410);
echo json_encode(["status" => "error", "message" => "Khóa bằng mật khẩu đã được thay bằng PIN 6 số."]);
exit();

if (!isset($_SESSION['id'])) {
    echo json_encode(["status" => "error", "message" => "Bạn chưa đăng nhập hoặc không có quyền truy cập"]);
    exit();
}
$user_id = (int)$_SESSION['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Phương thức yêu cầu không được hỗ trợ"]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);
if (!isset($data['note_id'])) {
    echo json_encode(["status" => "error", "message" => "Thiếu mã ghi chú"]);
    exit();
}

$note_id = (int)$data['note_id'];
$action  = $data['action'] ?? '';

// FIX: verify ownership with prepared statement (was raw query interpolation)
$chk = $conn->prepare("SELECT note_id, password_hash FROM notes WHERE note_id = ? AND user_id = ?");
$chk->bind_param("ii", $note_id, $user_id);
$chk->execute();
$res = $chk->get_result();
if ($res->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Bạn chưa đăng nhập hoặc không có quyền truy cập"]);
    exit();
}
$note = $res->fetch_assoc();

if ($action === 'set') {
    if (empty($data['password'])) {
        echo json_encode(["status" => "error", "message" => "Mật khẩu không được để trống"]);
        exit();
    }
    $hashed = password_hash($data['password'], PASSWORD_DEFAULT);
    // FIX: prepared statement (was string interpolation)
    $stmt = $conn->prepare("UPDATE notes SET password_hash = ? WHERE note_id = ? AND user_id = ?");
    $stmt->bind_param("sii", $hashed, $note_id, $user_id);
    $stmt->execute();
    echo json_encode(["status" => "success", "message" => "Đã đặt mật khẩu"]);

} elseif ($action === 'remove') {
    if (empty($note['password_hash'])) {
        echo json_encode(["status" => "error", "message" => "Ghi chú chưa có mật khẩu"]);
        exit();
    }
    if (!password_verify($data['password'] ?? '', $note['password_hash'])) {
        echo json_encode(["status" => "error", "message" => "Mật khẩu không đúng"]);
        exit();
    }
    $stmt = $conn->prepare("UPDATE notes SET password_hash = NULL WHERE note_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $note_id, $user_id);
    $stmt->execute();
    echo json_encode(["status" => "success", "message" => "Đã gỡ mật khẩu"]);

} elseif ($action === 'unlock') {
    if (empty($note['password_hash'])) {
        echo json_encode(["status" => "success", "message" => "Ghi chú chưa có mật khẩu"]);
        exit();
    }
    if (!password_verify($data['password'] ?? '', $note['password_hash'])) {
        echo json_encode(["status" => "error", "message" => "Mật khẩu không đúng"]);
        exit();
    }
    if (!isset($_SESSION['unlocked_notes']) || !is_array($_SESSION['unlocked_notes'])) {
        $_SESSION['unlocked_notes'] = [];
    }
    if (!in_array($note_id, $_SESSION['unlocked_notes'])) {
        $_SESSION['unlocked_notes'][] = $note_id;
    }
    echo json_encode(["status" => "success", "message" => "Đã mở khóa ghi chú"]);

} else {
    echo json_encode(["status" => "error", "message" => "Thao tác không hợp lệ"]);
}
?>
