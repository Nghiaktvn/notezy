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

$error   = '';
$success = '';
$email   = trim($_GET['email'] ?? $_POST['email'] ?? '');
$activationToken = trim((string) ($_GET['token'] ?? ''));
$mailWarning = $_SESSION['otp_mail_warning'] ?? '';
unset($_SESSION['otp_mail_warning']);

// Activation link flow: the raw token exists only in the email; the database
// stores its SHA-256 digest and a 24-hour expiry.
if ($activationToken !== '') {
    $conn = create_connect();
    $tokenHash = hash('sha256', $activationToken);
    $stmt = $conn->prepare('SELECT id, email, theme, language FROM users WHERE activation_token_hash = ? AND activation_token_expires_at >= NOW() LIMIT 1');
    $stmt->bind_param('s', $tokenHash);
    $stmt->execute();
    $tokenUser = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($tokenUser) {
        $activate = $conn->prepare('UPDATE users SET activated = 1, activation_token_hash = NULL, activation_token_expires_at = NULL, activation_otp_hash = NULL, activation_otp_expires_at = NULL, activation_otp_attempts = 0 WHERE id = ?');
        $activate->bind_param('i', $tokenUser['id']);
        if ($activate->execute()) {
            session_regenerate_id(true);
            $_SESSION['id'] = (int) $tokenUser['id'];
            $_SESSION['theme'] = $tokenUser['theme'] ?? 'light';
            $_SESSION['language'] = $tokenUser['language'] ?? 'vi';
            unset($_SESSION['demo_otp']);
            header('Location: index_notezy.php?activated=1');
            exit();
        }
        $error = 'Không thể kích hoạt tài khoản. Vui lòng thử lại.';
    } else {
        $error = 'Liên kết kích hoạt không hợp lệ hoặc đã hết hạn.';
    }
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'verify_otp') {
        $inputOtp = trim($_POST['otp'] ?? '');
        $conn = create_connect();
        $user = getUserByEmail($conn, $email);

        if (!$user) {
            $error = 'Email không tồn tại.';
        } elseif ($user['activated']) {
            $success = 'Tài khoản đã được kích hoạt. <a href="index.php">Đăng nhập</a>';
        } elseif ($user['activation_otp_attempts'] >= 5) {
            $error = 'Bạn đã nhập sai OTP quá nhiều lần. Vui lòng yêu cầu gửi lại OTP.';
        } elseif (empty($user['activation_otp_hash'])) {
            $error = 'Không tìm thấy mã OTP. Vui lòng yêu cầu gửi lại OTP.';
        } elseif (new DateTime() > new DateTime($user['activation_otp_expires_at'])) {
            $error = 'Mã OTP đã hết hạn. Vui lòng yêu cầu gửi lại OTP.';
        } elseif (!password_verify($inputOtp, $user['activation_otp_hash'])) {
            // Increment attempt count
            $stmt = $conn->prepare("UPDATE users SET activation_otp_attempts = activation_otp_attempts + 1 WHERE id = ?");
            $stmt->bind_param('i', $user['id']);
            $stmt->execute();
            $remaining = 4 - $user['activation_otp_attempts'];
            $error = "Mã OTP không đúng. Bạn còn " . max(0, $remaining) . " lần thử.";
        } else {
            // OTP correct — activate account and clear OTP fields
            $stmt = $conn->prepare("UPDATE users SET activated = 1, activation_otp_hash = NULL, activation_otp_expires_at = NULL, activation_otp_attempts = 0, activated_tokens = NULL WHERE id = ?");
            $stmt->bind_param('i', $user['id']);
            if ($stmt->execute()) {
                unset($_SESSION['demo_otp']);
                session_regenerate_id(true);
                $_SESSION['id'] = $user['id'];
                $_SESSION['theme'] = $user['theme'] ?? 'light';
                $_SESSION['language'] = $user['language'] ?? 'vi';
                header('Location: index_notezy.php');
                exit();
            } else {
                $error = 'Có lỗi xảy ra. Vui lòng thử lại.';
            }
        }
    } elseif ($action === 'resend_otp') {
        // Resend OTP — cooldown check via session
        $lastResend = $_SESSION['otp_resend_at_' . md5($email)] ?? 0;
        if (time() - $lastResend < 60) {
            $error = 'Vui lòng chờ 60 giây trước khi gửi lại OTP.';
        } else {
            $conn = create_connect();
            $user = getUserByEmail($conn, $email);
            if (!$user) {
                $error = 'Email không tồn tại.';
            } elseif ($user['activated']) {
                $success = 'Tài khoản đã được kích hoạt. <a href="index.php">Đăng nhập</a>';
            } else {
                // Generate new OTP (5 phút)
                $otp     = (string) random_int(100000, 999999);
                $otpHash = password_hash($otp, PASSWORD_DEFAULT);
                $expires = date('Y-m-d H:i:s', time() + 300);

                $token = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);
                $tokenExpires = date('Y-m-d H:i:s', time() + 86400);
                $stmt = $conn->prepare("UPDATE users SET activation_otp_hash = ?, activation_otp_expires_at = ?, activation_otp_attempts = 0, activation_token_hash = ?, activation_token_expires_at = ? WHERE id = ?");
                $stmt->bind_param('ssssi', $otpHash, $expires, $tokenHash, $tokenExpires, $user['id']);
                $stmt->execute();

                $baseUrl = rtrim((string) (getenv('APP_URL') ?: 'http://localhost:8080'), '/');
                $activationLink = $baseUrl . '/verify_activation.php?token=' . urlencode($token);
                $mailResult = sendActivationEmail($email, $otp, $user['firstname'], $activationLink);
                $_SESSION['demo_otp'] = $otp;
                if ($mailResult === true) {
                    $_SESSION['otp_resend_at_' . md5($email)] = time();
                    $success = 'Đã gửi lại mã OTP mới (hiệu lực 5 phút) đến email của bạn.';
                } else {
                    $error = 'Không thể gửi email. ' . $mailResult;
                }
            }
        }
    }
}

// Calculate remaining cooldown for client UI
$lastResend = $_SESSION['otp_resend_at_' . md5($email)] ?? 0;
$cooldownRemaining = max(0, 60 - (time() - $lastResend));
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
            <div class="alert alert-warning">Tài khoản đã được lưu. Nếu chưa nhận được email, hãy bấm Gửi lại OTP. <?= htmlspecialchars($mailWarning) ?></div>
          <?php endif; ?>
          <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?= $success ?></div>
          <?php endif; ?>

          <?php if (!empty($_SESSION['demo_otp']) && empty($success)): ?>
            <div class="alert alert-info py-2 shadow-sm border-info-subtle">
              <div class="d-flex align-items-center justify-content-between">
                <div>
                  <i class="fas fa-key text-primary me-1"></i>
                  <strong>Mã OTP xác thực (Môi trường Demo / Local):</strong>
                </div>
                <span class="badge bg-primary fs-6 px-3 py-2"><?= htmlspecialchars($_SESSION['demo_otp']) ?></span>
              </div>
              <small class="text-muted d-block mt-1">Mã đã được tạo an toàn. Bạn có thể nhập ngay 6 số trên vào ô bên dưới để kích hoạt tài khoản.</small>
            </div>
          <?php endif; ?>

          <?php if (empty($success)): ?>
          <p class="text-muted mb-3">
            Mã OTP 6 số (hiệu lực 5 phút) đã được gửi đến email <strong><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></strong>.<br>
            Vui lòng kiểm tra hộp thư (và thư mục Spam).
          </p>

          <!-- Verify OTP form -->
          <form method="post" id="form-verify-otp">
            <input type="hidden" name="action" value="verify_otp">
            <input type="hidden" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>">
            <div class="mb-3">
              <label for="otp-input" class="form-label">Mã OTP</label>
              <input type="text" name="otp" id="otp-input" class="form-control form-control-lg text-center fw-bold"
                     placeholder="______" maxlength="6" autocomplete="one-time-code" required
                     pattern="\d{6}" title="Nhập đúng 6 chữ số" autofocus>
            </div>
            <button type="submit" class="btn btn-success w-100 mb-2" id="btn-verify-otp">Xác nhận OTP</button>
          </form>

          <!-- Resend OTP form with 60s cooldown -->
          <form method="post" id="form-resend-otp">
            <input type="hidden" name="action" value="resend_otp">
            <input type="hidden" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="btn btn-outline-secondary w-100" id="btn-resend-otp">
              <?= $cooldownRemaining > 0 ? "Gửi lại OTP ({$cooldownRemaining}s)" : "Gửi lại OTP" ?>
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
            resendBtn.textContent = 'Gửi lại OTP';
          } else {
            resendBtn.textContent = `Gửi lại OTP (${cooldown}s)`;
          }
        }, 1000);
      }
    })();
  </script>
</body>
</html>
