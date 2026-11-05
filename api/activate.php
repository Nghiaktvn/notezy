<?php
require_once __DIR__ . '/db.php';
require_once dirname(__DIR__) . '/includes/activation.php';
header('Content-Type: application/json; charset=utf-8');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$data = $method === 'POST' ? json_decode(file_get_contents('php://input'), true) : $_GET;
if (!is_array($data)) $data = [];
$email = is_string($data['email'] ?? null) ? trim($data['email']) : '';
$action = is_string($data['action'] ?? null) ? $data['action'] : '';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Thư điện tử không hợp lệ.']);
    exit;
}
if (($method === 'GET' || $method === 'POST') && $action === 'resend') {
    $result = notezy_activation_resend($conn, $email);
    if (isset($result['retry_after'])) header('Retry-After: ' . (int)$result['retry_after']);
    if ($result['status'] === 'error') http_response_code(isset($result['retry_after']) && empty($result['delivery_status']) ? 429 : 503);
} elseif ($method === 'POST') {
    $otp = is_string($data['otp'] ?? null) ? trim($data['otp']) : '';
    $result = notezy_activation_verify($conn, $email, $otp);
    if ($result['status'] !== 'success') http_response_code(422);
} else {
    http_response_code(405);
    $result = ['status' => 'error', 'message' => 'Phương thức yêu cầu không được hỗ trợ.'];
}
echo json_encode($result, JSON_UNESCAPED_UNICODE);
