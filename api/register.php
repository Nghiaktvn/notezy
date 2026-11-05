<?php
require_once 'db.php';
require_once '../account_db.php';
require_once '../sendmail.php';
require_once dirname(__DIR__) . '/includes/activation.php';

header('Content-Type: application/json');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Chỉ chấp nhận yêu cầu đăng ký bằng POST.']);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);

if (!is_array($data) || !isset($data['password'], $data['email'])) {
    echo json_encode(["status" => "error", "message" => "Vui lòng điền đầy đủ thông tin"]);
    exit();
}
foreach (['password', 'email', 'username', 'display_name', 'firstname', 'lastname'] as $field) {
    if (isset($data[$field]) && !is_string($data[$field])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Thông tin đăng ký không hợp lệ.']);
        exit();
    }
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
    $username = substr($slug !== '' ? strtolower($slug) : 'user', 0, 31) . '_' . substr(bin2hex(random_bytes(4)), 0, 7);
}
if (!preg_match('/^[A-Za-z0-9_]{3,40}$/D', $username)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Tên tài khoản cần 3–40 ký tự chữ, số hoặc dấu gạch dưới.']);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["status" => "error", "message" => "Thư điện tử không hợp lệ"]);
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

$mailResult = !empty($result['pending']) ? null : notezy_activation_deliver($conn, $email, $result['otp'], trim($firstname . ' ' . $lastname));
$payload = [
    "status"  => "success",
    "message" => "Đã lưu yêu cầu đăng ký. Nhập đúng mã xác thực được gửi về thư điện tử để kích hoạt trước khi đăng nhập.",
    "email"   => $email,
    "redirect" => "verify_activation.php?email=" . urlencode($email),
];
if (notezy_demo_otp_enabled()) {
    $payload['message'] = 'Đăng ký thành công. Chế độ trình diễn: nhập mã 123456 để kích hoạt, không gửi thư thật.';
    $payload['demo_otp'] = '123456';
}
$payload['delivery_status'] = notezy_demo_otp_enabled() ? 'demo' : ($mailResult === true ? 'sent' : 'failed');
if (!empty($result['pending'])) {
    $payload['delivery_status'] = 'pending';
    $payload['message'] = 'Yêu cầu đăng ký đang chờ xác thực. Hãy kiểm tra thư hoặc yêu cầu gửi lại mã.';
    $payload['retry_after'] = $result['retry_after'];
}
if ($mailResult === false) {
    $payload["status"] = "warning";
    $payload["message"] = "Tài khoản đã được lưu. Không gửi được email OTP, vui lòng yêu cầu gửi lại.";
}
echo json_encode($payload);
?>
