<?php
require_once __DIR__ . '/bootstrap.php';
ai_check_origin();
ai_require_post();
$userId = ai_require_auth();
$data = ai_read_json();
$form = $_SESSION['ai_password_form'] ?? [];
if (empty($form['token']) || (int)($form['expires'] ?? 0) < time()
    || !hash_equals($form['token'], (string)($data['form_token'] ?? ''))) {
    ai_json(403, ['status' => 'error', 'message' => 'Biểu mẫu đã hết hạn. Hãy yêu cầu đổi mật khẩu lại.']);
}
$attempts = $_SESSION['ai_password_attempts'] ?? ['since' => time(), 'count' => 0];
if (time() - $attempts['since'] > 60) $attempts = ['since' => time(), 'count' => 0];
$attempts['count']++;
$_SESSION['ai_password_attempts'] = $attempts;
if ($attempts['count'] > 5) ai_json(429, ['status' => 'error', 'message' => 'Vui lòng đợi một phút trước khi thử lại.']);
$old = (string)($data['current_password'] ?? '');
$new = (string)($data['new_password'] ?? '');
$confirm = (string)($data['confirm_password'] ?? '');
if ($old === '' || mb_strlen($new, 'UTF-8') < 6 || strlen($new) > 72 || $new !== $confirm) {
    ai_json(422, ['status' => 'error', 'message' => 'Nhập mật khẩu cũ và mật khẩu mới ít nhất 6 ký tự và tối đa 72 byte; hai lần nhập mới phải khớp.']);
}
$stmt = $conn->prepare('SELECT password_hash FROM users WHERE id = ? AND activated = 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
if (!$row || !password_verify($old, (string)$row['password_hash'])) {
    ai_json(422, ['status' => 'error', 'message' => 'Mật khẩu hiện tại không đúng.']);
}
$hash = password_hash($new, PASSWORD_DEFAULT);
$update = $conn->prepare('UPDATE users SET password_hash = ?, pass = NULL WHERE id = ? AND password_hash = ?');
$update->bind_param('sis', $hash, $userId, $row['password_hash']);
$update->execute();
if ($update->affected_rows !== 1) ai_json(409, ['status' => 'error', 'message' => 'Thông tin tài khoản đã thay đổi. Vui lòng đăng nhập lại.']);
unset($_SESSION['ai_password_form'], $_SESSION['ai_password_attempts']);
session_regenerate_id(true);
ai_json(200, ['status' => 'success', 'message' => 'Đã đổi mật khẩu tài khoản thành công.']);
