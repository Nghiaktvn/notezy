<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Chỉ chạy bằng dòng lệnh.'); }
require_once dirname(__DIR__) . '/config/database.php';
$db = create_connect(false);
if (!$db || !notezy_ensure_user_role_column($db)) exit(1);
$existing = $db->query("SELECT role FROM users WHERE username = 'admin' LIMIT 1")->fetch_assoc();
if ($existing) {
    if ($existing['role'] !== 'admin') { fwrite(STDERR, "Tên admin đã được sử dụng; không thay đổi quyền tài khoản hiện có.\n"); exit(1); }
    echo "Giữ nguyên tài khoản quản trị hiện có.\n";
    exit;
}
$password = getenv('NOTEZY_DEMO_ADMIN_PASSWORD') ?: '';
if (strlen($password) < 12) { fwrite(STDERR, "Cần cấu hình mật khẩu quản trị ít nhất 12 ký tự.\n"); exit(1); }
$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $db->prepare("INSERT INTO users (username, firstname, lastname, email, password_hash, activated, role) VALUES ('admin', 'Quản trị', 'Trình diễn', 'admin-demo@notezy.example', ?, 1, 'admin')");
$stmt->bind_param('s', $hash);
if (!$stmt->execute()) exit(1);
echo "Đã tạo tài khoản quản trị trình diễn; không ghi mật khẩu vào nhật ký.\n";
