<?php
require_once __DIR__ . '/includes/session.php';
session_set_cookie_params([
    'httponly' => true,
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax',
]);
notezy_session_start();
require_once('db.php');
require_once('sendmail.php');
require_once __DIR__ . '/config/demo.php';
require_once __DIR__ . '/includes/activation.php';

$error   = '';
$success = '';
$otpSent = false;
$email   = trim($_GET['email'] ?? $_POST['email'] ?? '');
$activationToken = trim((string) ($_GET['token'] ?? ''));
$mailWarning = $_SESSION['otp_mail_warning'] ?? '';
unset($_SESSION['otp_mail_warning']);

// Activation now requires the email OTP; an old link cannot bypass that check.
if ($activationToken !== '') {
    $error = 'Hãy mở trang xác thực bằng thư điện tử và nhập mã mới nhất trong thư.';
}
// A bad activation link is rendered as a friendly validation page instead of
// terminating with a raw error. OTP flow still requires a valid email.
if ((empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) && $activationToken === '') {
    die('❌ Yêu cầu không hợp lệ. <a href="register.php">Đăng ký lại</a>.');
}
if ((empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) && $activationToken !== '') {
    $email = '';
}

// Fetch user by email
function getUserByEmail($conn, $email) {
    $stmt = $conn->prepare("SELECT id, firstname, activated, theme, language, activation_otp_hash, activation_otp_expires_at, activation_otp_attempts FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $action = $_POST['action'] ?? '';
    $conn = create_connect();
    if ($action === 'verify_otp') {
        $result = notezy_activation_verify($conn, $email, trim((string)($_POST['otp'] ?? '')));
        if ($result['status'] === 'success') {
            header('Location: index.php?auth=login&activated=1');
            exit();
        }
        $error = $result['message'];
    } elseif ($action === 'resend_otp') {
        $result = notezy_activation_resend($conn, $email);
        if ($result['status'] === 'success') {
            $success = $result['message'];
            $otpSent = true;
        } else $error = $result['message'];
    }
}

// Calculate remaining cooldown for client UI
$cooldownRemaining = 0;
$verificationUser = null;
if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $verificationUser = getUserByEmail(create_connect(), $email);
    if ($verificationUser) $cooldownRemaining = notezy_activation_cooldown($verificationUser);
}
$deliveryUnavailable = !notezy_demo_otp_enabled() && $verificationUser && (empty($verificationUser['activation_otp_hash']) || (int)$verificationUser['activation_otp_attempts'] >= 5) && !(int)$verificationUser['activated'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Xác thực OTP — Notezy</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="CSS/main.css">
  <link rel="stylesheet" href="CSS/login.css">
</head>
<body class="bg-light">
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <div class="container-fluid">
      <a class="navbar-brand" href="/">Notezy</a>
    </div>
  </nav>

  <main class="container my-4 pt-5">
    <div class="row justify-content-center">
      <div class="col-md-6 col-lg-5">
        <h3 class="text-center mt-5 mb-3">Xác thực tài khoản</h3>
        <div class="border rounded w-100 mb-5 mx-auto px-4 pt-4 pb-4 bg-white shadow-sm">

          <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
          <?php endif; ?>
          <?php if (!empty($mailWarning)): ?>
            <div class="alert alert-warning">Tài khoản đã được lưu. Nếu chưa nhận được email, hãy bấm Gửi lại mã xác thực. <?= htmlspecialchars($mailWarning) ?></div>
          <?php endif; ?>
          <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?= $success ?></div>
          <?php endif; ?>

          <?php if (empty($success) || $otpSent): ?>
          <p class="text-muted mb-3">
            <?php if (notezy_demo_otp_enabled()): ?>
            <strong>Chế độ trình diễn:</strong> Nhập <strong>123456</strong> để kích hoạt tài khoản. Không gửi thư thật. Mã có hiệu lực 5 phút; hết hạn thì chọn “Gửi lại mã xác thực”.
            <?php elseif ($deliveryUnavailable): ?>
            Tài khoản chưa được kích hoạt. Hãy chọn “Gửi lại mã xác thực”, đợi thư đến rồi nhập mã mới nhất.
            <?php else: ?>
            Mã xác thực 6 số (hiệu lực 5 phút) đã được gửi đến email <strong><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></strong>.<br>
            Vui lòng kiểm tra hộp thư (và thư rác).
            <?php endif; ?>
          </p>

          <!-- Verify OTP form -->
          <form method="post" id="form-verify-otp">
            <input type="hidden" name="action" value="verify_otp">
            <input type="hidden" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>">
            <div class="mb-3">
              <label for="otp-input" class="form-label">Mã xác thực</label>
              <input type="text" name="otp" id="otp-input" class="form-control form-control-lg text-center fw-bold"
                     placeholder="______" maxlength="6" autocomplete="one-time-code" required
                     pattern="\d{6}" title="Nhập đúng 6 chữ số" autofocus>
            </div>
            <button type="submit" class="btn btn-success w-100 mb-2" id="btn-verify-otp">Xác nhận mã</button>
          </form>

          <!-- Resend OTP form with 60s cooldown -->
          <form method="post" id="form-resend-otp">
            <input type="hidden" name="action" value="resend_otp">
            <input type="hidden" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="btn btn-outline-secondary w-100" id="btn-resend-otp">
              <?= $cooldownRemaining > 0 ? "Gửi lại mã xác thực ({$cooldownRemaining}s)" : "Gửi lại mã xác thực" ?>
            </button>
          </form>
          <?php endif; ?>

          <div class="mt-3 text-center">
            <a href="register.php" class="text-muted small">← Quay lại đăng ký</a>
          </div>
        </div>
      </div>
    </div>
  </main>

  <script>
    (function() {
      let cooldown = <?= (int)$cooldownRemaining ?>;
      const resendBtn = document.getElementById('btn-resend-otp');
      if (resendBtn && cooldown > 0) {
        resendBtn.disabled = true;
        const interval = setInterval(() => {
          cooldown--;
          if (cooldown <= 0) {
            clearInterval(interval);
            resendBtn.disabled = false;
            resendBtn.textContent = 'Gửi lại mã xác thực';
          } else {
            resendBtn.textContent = `Gửi lại mã xác thực (${cooldown}s)`;
          }
        }, 1000);
      }
    })();
  </script>
</body>
</html>
