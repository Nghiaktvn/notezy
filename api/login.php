<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../account_db.php';

header('Content-Type: application/json; charset=utf-8');

function login_response(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST, OPTIONS');
    login_response(405, ['status' => 'error', 'message' => 'Method not allowed']);
}

$data = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($data)) {
    login_response(400, ['status' => 'error', 'message' => 'Dữ liệu đăng nhập không hợp lệ']);
}

$username = trim((string) ($data['username'] ?? $data['email'] ?? ''));
$password = (string) ($data['password'] ?? '');
if ($username === '' || $password === '') {
    login_response(422, ['status' => 'error', 'message' => 'Vui lòng nhập email hoặc tên tài khoản và mật khẩu']);
}

try {
    $result = login($username, $password);
} catch (Throwable $error) {
    error_log('Notezy API login failed: ' . $error->getMessage());
    login_response(503, ['status' => 'error', 'message' => 'Máy chủ đăng nhập tạm thời không khả dụng']);
}

if (!is_array($result) || empty($result['success']) || empty($result['user'])) {
    login_response(401, ['status' => 'error', 'message' => 'Email/tên tài khoản hoặc mật khẩu không đúng']);
}

$user = $result['user'];
notezy_session_start();
session_regenerate_id(true);
$_SESSION['id'] = (int) $user['id'];
$_SESSION['theme'] = $user['theme'] ?? 'light';
$_SESSION['language'] = $user['language'] ?? 'vi';

login_response(200, [
    'status' => 'success',
    'message' => 'Đăng nhập thành công',
    'unverified' => !empty($result['unverified']),
    'user' => [
        'id' => (int) $user['id'],
        'username' => (string) ($user['username'] ?? ''),
        'avatar' => (string) ($user['avatar'] ?? ''),
        'theme' => (string) ($user['theme'] ?? 'light'),
        'language' => (string) ($user['language'] ?? 'vi'),
    ],
]);
