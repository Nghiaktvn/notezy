<?php
/**
 * api/activate.php — OTP-based account activation (JSON API)
 *
 * POST body: { "email": "...", "otp": "123456" }
 * or GET:    ?action=resend&email=...
 */
require_once 'db.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

// ── RESEND OTP (GET) ──────────────────────────────────────────────────────
if ($method === 'GET') {
    $action = trim($_GET['action'] ?? '');
    $email  = trim($_GET['email'] ?? '');

    if ($action !== 'resend' || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(["status" => "error", "message" => "Yêu cầu không hợp lệ"]);
        exit();
    }

    $stmt = $conn->prepare("SELECT id, firstname, activated, activation_otp_hash FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user || $user['activated']) {
        echo json_encode(["status" => "error", "message" => "Email không hợp lệ hoặc đã kích hoạt"]);
        exit();
    }

    $otp     = (string) random_int(100000, 999999);
    $otpHash = password_hash($otp, PASSWORD_DEFAULT);
    $expires = date('Y-m-d H:i:s', time() + 900);

    $upd = $conn->prepare("UPDATE users SET activation_otp_hash = ?, activation_otp_expires_at = ?, activation_otp_attempts = 0 WHERE id = ?");
    $upd->bind_param('ssi', $otpHash, $expires, $user['id']);
    $upd->execute();

    require_once '../sendmail.php';
    $mailResult = sendActivationEmail($email, $otp, $user['firstname']);

    if ($mailResult === true) {
        echo json_encode(["status" => "success", "message" => "Đã gửi lại mã OTP"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Không thể gửi email"]);
    }
    exit();
}

// ── VERIFY OTP (POST) ─────────────────────────────────────────────────────
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
    exit();
}

$data  = json_decode(file_get_contents("php://input"), true);
$email = trim($data['email'] ?? '');
$otp   = trim($data['otp']   ?? '');

if (empty($email) || empty($otp)) {
    echo json_encode(["status" => "error", "message" => "Email và OTP là bắt buộc"]);
    exit();
}

$stmt = $conn->prepare("SELECT id, activated, activation_otp_hash, activation_otp_expires_at, activation_otp_attempts FROM users WHERE email = ?");
$stmt->bind_param('s', $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    echo json_encode(["status" => "error", "message" => "Email không tồn tại"]);
    exit();
}
if ($user['activated']) {
    echo json_encode(["status" => "success", "message" => "Tài khoản đã được kích hoạt"]);
    exit();
}
if ($user['activation_otp_attempts'] >= 5) {
    echo json_encode(["status" => "error", "message" => "Quá nhiều lần thử. Vui lòng yêu cầu gửi lại OTP"]);
    exit();
}
if (empty($user['activation_otp_hash'])) {
    echo json_encode(["status" => "error", "message" => "Không tìm thấy OTP. Vui lòng yêu cầu gửi lại"]);
    exit();
}
if (new DateTime() > new DateTime($user['activation_otp_expires_at'])) {
    echo json_encode(["status" => "error", "message" => "OTP đã hết hạn. Vui lòng yêu cầu gửi lại"]);
    exit();
}
if (!password_verify($otp, $user['activation_otp_hash'])) {
    $inc = $conn->prepare("UPDATE users SET activation_otp_attempts = activation_otp_attempts + 1 WHERE id = ?");
    $inc->bind_param('i', $user['id']);
    $inc->execute();
    $remaining = max(0, 4 - $user['activation_otp_attempts']);
    echo json_encode(["status" => "error", "message" => "Sai mã OTP. Còn $remaining lần thử"]);
    exit();
}

// OTP correct — activate
$act = $conn->prepare("UPDATE users SET activated = 1, activation_otp_hash = NULL, activation_otp_expires_at = NULL, activation_otp_attempts = 0, activated_tokens = NULL WHERE id = ?");
$act->bind_param('i', $user['id']);
if ($act->execute()) {
    echo json_encode(["status" => "success", "message" => "Kích hoạt tài khoản thành công"]);
} else {
    echo json_encode(["status" => "error", "message" => "Có lỗi xảy ra"]);
}
?>
