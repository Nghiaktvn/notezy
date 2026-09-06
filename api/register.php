<?php
require_once 'db.php';
require_once '../account_db.php';
require_once '../sendmail.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['username'], $data['password'], $data['email'], $data['firstname'], $data['lastname'])) {
    echo json_encode(["status" => "error", "message" => "Vui lòng điền đầy đủ thông tin"]);
    exit();
}

$username  = trim($data['username']);
$password  = $data['password'];
$email     = trim($data['email']);
$firstname = trim($data['firstname']);
$lastname  = trim($data['lastname']);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["status" => "error", "message" => "Email không hợp lệ"]);
    exit();
}
if (strlen($password) < 6) {
    echo json_encode(["status" => "error", "message" => "Mật khẩu tối thiểu 6 ký tự"]);
    exit();
}

$result = register($username, $firstname, $lastname, $email, $password);
if (!is_array($result) || empty($result['success'])) {
    echo json_encode([
        "status"  => "error",
        "message" => is_array($result) ? ($result['error'] ?? 'Có lỗi xảy ra khi tạo tài khoản') : $result
    ]);
    exit();
}

$mailResult = sendActivationEmail($email, $result['otp'], $firstname . ' ' . $lastname);
$payload = [
    "status"  => "success",
    "message" => "Đăng ký thành công. Vui lòng nhập mã OTP đã gửi đến email để kích hoạt tài khoản.",
    "email"   => $email,
];
if ($mailResult !== true) {
    $payload["status"] = "warning";
    $payload["message"] = "Tài khoản đã được lưu. Không gửi được email OTP, vui lòng yêu cầu gửi lại.";
}
echo json_encode($payload);
?>
