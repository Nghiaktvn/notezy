<?php
require_once __DIR__ . '/includes/session.php';
require_once('account_db.php');
require_once('sendmail.php');

session_set_cookie_params([
    'httponly' => true,
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax',
]);
notezy_session_start();

$error = '';
$display_name = '';
$first_name = '';
$last_name = '';
$email = '';
$user = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $display_name = trim($_POST['display_name'] ?? '');
    $first_name = $display_name;
    $last_name = '';
    $email = trim($_POST['email'] ?? '');
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['pass'] ?? '';
    $pass_confirm = $_POST['pass-confirm'] ?? '';

    if (empty($display_name)) {
        $error = 'Vui lòng nhập tên hiển thị';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,40}$/', $user)) {
        $error = 'Tên tài khoản phải có 3–40 ký tự, chỉ gồm chữ, số và dấu gạch dưới.';
    } elseif (empty($email)) {
        $error = 'Please enter your email';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'This is not a valid email address';
    } elseif (empty($pass)) {
        $error = 'Please enter your password';
    } elseif (strlen($pass) < 6) {
        $error = 'Password must have at least 6 characters';
    } elseif ($pass !== $pass_confirm) {
        $error = 'Password does not match';
    } else {
        $result = register($user, $first_name, $last_name, $email, $pass);
        if (is_array($result) && !empty($result['success'])) {
            // Gửi OTP kích hoạt tài khoản
            $baseUrl = rtrim((string) (getenv('APP_URL') ?: 'http://localhost:8080'), '/');
            $activationLink = $baseUrl . '/verify_activation.php?token=' . urlencode($result['activation_token']);
            $mailRes = sendActivationEmail($email, $result['otp'], $first_name, $activationLink);
            if ($mailRes !== true) {
                $_SESSION['otp_mail_warning'] = ' (Lưu ý hệ thống email: ' . $mailRes . ')';
            }
            $redirect_url = 'verify_activation.php?email=' . urlencode($email);

            $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
                || (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
                || isset($_POST['ajax']);

            if ($is_ajax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true, 'redirect' => $redirect_url, 'email' => $email]);
                exit();
            }

            header('Location: ' . $redirect_url);
            exit();
        }
        $error = is_array($result) ? ($result['error'] ?? 'Lỗi không xác định') : $result;
        
        $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
            || isset($_POST['ajax']);

        if ($is_ajax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => $error]);
            exit();
        }
    }
}
?>


<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Tạo tài khoản | Notezy</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" />
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <link rel="stylesheet" href="CSS/register-refresh.css?v=1">
</head>

<body class="register-refresh">
  <header class="register-header">
    <a class="register-brand" href="index.php"><span class="register-brand__mark"><i class="bi bi-journal-richtext" aria-hidden="true"></i></span>Notezy</a>
    <a class="register-header__login" href="index.php?auth=login">Đăng nhập <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
  </header>
  <main class="register-main">
    <section class="register-story" aria-labelledby="registerStoryTitle">
      <span class="register-eyebrow">NOTEZY · GHI CHÚ THÔNG MINH</span>
      <h1 id="registerStoryTitle">Một nơi dịu dàng<br>cho mọi <em>ý tưởng.</em></h1>
      <p>Sắp xếp những điều quan trọng, chia sẻ với bạn bè và khám phá thêm góc nhìn cùng trợ lý AI.</p>
      <img src="assets/notezy-notebooks-2026.png" alt="Những cuốn sổ tay Notezy với gam màu dịu" width="1536" height="1024">
    </section>
    <section class="register-form-panel" aria-labelledby="registerTitle">
      <img class="register-mascot" src="assets/notezy-mascot-2026.png" alt="Linh vật sổ tay Notezy chào đón bạn" width="1302" height="1208">
      <span class="register-eyebrow">BẮT ĐẦU MIỄN PHÍ</span>
      <h2 id="registerTitle">Tạo tài khoản</h2>
      <p class="register-intro">Sổ ghi chú đầu tiên của bạn đang chờ được viết.</p>
      <?php if ($error !== ''): ?>
        <p class="register-error" role="alert">Lỗi: <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
      <?php endif; ?>
      <form method="post" action="" id="form-res">
        <div class="register-field">
          <label for="username">Tên tài khoản</label>
          <input value="<?= htmlspecialchars($user, ENT_QUOTES, 'UTF-8') ?>" name="username" required minlength="3" maxlength="40" pattern="[A-Za-z0-9_]+" class="form-control" type="text" placeholder="Ví dụ: nguyenvana" id="username" autocomplete="username">
        </div>
        <div class="register-field">
          <label for="display_name">Tên hiển thị</label>
          <input value="<?= htmlspecialchars($display_name, ENT_QUOTES, 'UTF-8') ?>" name="display_name" required class="form-control" type="text" placeholder="Tên bạn muốn hiển thị" id="display_name" autocomplete="name">
        </div>
        <div class="register-field">
          <label for="email">Email</label>
          <input value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" name="email" required class="form-control" type="email" placeholder="ban@example.com" id="email" autocomplete="email">
        </div>
        <div class="register-field">
          <label for="pass">Mật khẩu</label>
          <input name="pass" required minlength="6" class="form-control" type="password" placeholder="Tối thiểu 6 ký tự" id="pass" autocomplete="new-password">
        </div>
        <div class="register-field">
          <label for="pass2">Nhập lại mật khẩu</label>
          <input name="pass-confirm" required minlength="6" class="form-control" type="password" placeholder="Nhập lại mật khẩu" id="pass2" autocomplete="new-password">
        </div>
        <button type="submit" class="register-submit" id="register">Tạo tài khoản <i class="bi bi-arrow-right" aria-hidden="true"></i></button>
        <p class="register-signin">Đã có tài khoản? <a href="index.php?auth=login">Đăng nhập</a></p>
      </form>
    </section>
  </main>
  <footer class="register-footer">© 2026 Notezy · <a href="privacy.php">Quyền riêng tư</a> · <a href="contact.php">Liên hệ</a></footer>
</body>
</html>
