<?php
require_once 'db.php';

// Only accept token-based activation — email-only bypass is NOT supported
$token = trim($_GET['token'] ?? '');

if (!$token) {
    die('❌ Link kích hoạt không hợp lệ.');
}

$sql  = "SELECT id, activated FROM users WHERE activated_tokens = ? AND activated = 0";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user   = $result->fetch_assoc();
    $update = $conn->prepare("UPDATE users SET activated = 1, activated_tokens = NULL WHERE id = ?");
    $update->bind_param("i", $user['id']);
    if ($update->execute()) {
        echo "✅ Kích hoạt thành công! <a href='index.php'>Đăng nhập ngay</a>.";
    } else {
        echo "❌ Có lỗi khi kích hoạt. Vui lòng thử lại.";
    }
} else {
    echo "❌ Link kích hoạt không hợp lệ hoặc tài khoản đã được kích hoạt.";
}
?>
