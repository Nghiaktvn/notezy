<?php
require_once 'db.php';
require_once '../account_db.php';
require_once '../sendmail.php';
require_once '../includes/session.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

if (!is_array($data) || !isset($data['password'], $data['email'])) {
    echo json_encode(["status" => "error", "message" => "Vui lòng điền đầy đủ thông tin"]);
    exit();
}

$displayName = trim((string) ($data['display_name'] ?? trim((string) (($data['firstname'] ?? '') . ' ' . ($data['lastname'] ?? '')))));
$username  = trim((string) ($data['username'] ?? ''));
$password  = $data['password'];
$email     = trim($data['email']);
$firstname = trim((string) ($data['firstname'] ?? $displayName));
$lastname  = trim((string) ($data['lastname'] ?? ''));
if ($displayName === '') $displayName = $firstname !== '' ? $firstname : 'User';
if ($username === '') {
    $slug = trim((string) preg_replace('/[^a-z0-9]+/i', '_', $displayName), '_');
    $username = substr($slug !== '' ? strtolower($slug) : 'user', 0, 40) . '_' . substr(bin2hex(random_bytes(4)), 0, 7);
}

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

$baseUrl = rtrim((string) (getenv('APP_URL') ?: 'http://localhost:8080'), '/');
$activationLink = $baseUrl . '/verify_activation.php?token=' . urlencode($result['activation_token']);
$mailResult = sendActivationEmail($email, $result['otp'], trim($firstname . ' ' . $lastname), $activationLink);
notezy_session_start();
session_regenerate_id(true);
$_SESSION['id'] = (int) $result['user_id'];
$payload = [
    "status"  => "success",
    "message" => "Đăng ký thành công. Bạn đã được đăng nhập; hãy dùng liên kết email hoặc OTP để kích hoạt tài khoản.",
    "email"   => $email,
    "redirect" => "index_notezy.php?unverified=1",
];
if ($mailResult !== true) {
    $payload["status"] = "warning";
    $payload["message"] = "Tài khoản đã được lưu. Không gửi được email OTP, vui lòng yêu cầu gửi lại.";
}
echo json_encode($payload);
?>
