<?php
require_once __DIR__ . '/includes/session.php';
notezy_session_start();
require_once __DIR__ . '/includes/csrf.php';
require_once('db.php');
require_once('sendmail.php');

$error   = '';
$success = '';
$email   = $_SESSION['reset_otp_email'] ?? '';
$step    = $_SESSION['reset_step'] ?? 1; // 1: Email, 2: OTP, 3: New Password

// Handle timeout (15 mins) — invalidate session if expired
if (isset($_SESSION['reset_otp_time']) && time() - $_SESSION['reset_otp_time'] > 900) {
    $_SESSION = array_diff_key($_SESSION, array_flip([
        'reset_otp_email', 'reset_otp_hash', 'reset_otp_time', 'reset_otp_attempts', 'reset_step', 'reset_verified'
    ]));
    $step  = 1;
    $error = "Mã OTP đã hết hạn. Vui lòng thử lại.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang và thử lại.';
    } else {
    $action = $_POST['action'] ?? '';

    // ── STEP 1: Send OTP ───────────────────────────────────────────────────
    if ($action === 'send_email') {
        $input_email = trim($_POST['email'] ?? '');
        if (empty($input_email) || !filter_var($input_email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email không hợp lệ.';
        } else {
            $conn = create_connect();
            $stmt = $conn->prepare("SELECT id, firstname FROM users WHERE email = ?");
            $stmt->bind_param('s', $input_email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

            // FIX #11: Generic message — không tiết lộ email có tồn tại hay không
            $success = 'Nếu email tồn tại trong hệ thống, mã OTP sẽ được gửi đến email đó.';
            $step = 1;

            if ($user) {
                // FIX #9: Dùng random_int() thay mt_rand()
                $otp     = (string) random_int(100000, 999999);
                // FIX #10: Lưu OTP hash thay plaintext
                $otpHash = password_hash($otp, PASSWORD_DEFAULT);

                // Session only tracks the reset flow. OTP state is persisted in
                // the database so verification remains server-authoritative.
                $_SESSION['reset_otp_email']    = $input_email;
                $_SESSION['reset_otp_time']     = time();
                $_SESSION['reset_step']         = 2;
                $_SESSION['reset_verified']     = false;

                $expires = date('Y-m-d H:i:s', time() + 900);
                $otpStmt = $conn->prepare('UPDATE users SET reset_otp_hash = ?, reset_otp_expires_at = ?, reset_otp_attempts = 0 WHERE id = ?');
                $otpStmt->bind_param('ssi', $otpHash, $expires, $user['id']);
                $otpStmt->execute();
                $otpStmt->close();

                $email = $input_email;
                $step  = 2;

                $mailResult = sendResetPasswordEmail($input_email, $otp, $user['firstname']);
                if ($mailResult !== true) {
                    // Mail failed — don't expose email existence, but revert step
                    $step = 1;
                    unset($_SESSION['reset_otp_email'],
                          $_SESSION['reset_otp_time'],
                          $_SESSION['reset_step'], $_SESSION['reset_verified']);
                    $success = '';
                    $error   = 'Không thể gửi email. Vui lòng thử lại sau.';
                }
            }
        }
    }

    // ── STEP 2: Verify OTP ─────────────────────────────────────────────────
    elseif ($action === 'verify_otp' && $step == 2) {
        $input_otp   = trim($_POST['otp'] ?? '');
        $conn = create_connect();
        $otpStmt = $conn->prepare('SELECT reset_otp_hash, reset_otp_expires_at, reset_otp_attempts FROM users WHERE email = ? LIMIT 1');
        $otpStmt->bind_param('s', $_SESSION['reset_otp_email']);
        $otpStmt->execute();
        $otpData = $otpStmt->get_result()->fetch_assoc();
        $otpStmt->close();
        $otp_attempts = (int) ($otpData['reset_otp_attempts'] ?? 5);

        if ($otp_attempts >= 5) {
            $error = "Bạn đã nhập sai OTP quá nhiều lần. Vui lòng thử lại từ đầu.";
            // Clear reset session
            $_SESSION = array_diff_key($_SESSION, array_flip([
                'reset_otp_email', 'reset_otp_hash', 'reset_otp_time', 'reset_otp_attempts', 'reset_step', 'reset_verified'
            ]));
            $step = 1;
        } elseif (!$otpData || empty($otpData['reset_otp_hash']) || new DateTimeImmutable($otpData['reset_otp_expires_at']) < new DateTimeImmutable('now')) {
            $error = "Phiên đã hết hạn. Vui lòng thử lại.";
            $step  = 1;
        } elseif (preg_match('/^\d{6}$/', $input_otp) && password_verify($input_otp, $otpData['reset_otp_hash'])) {
            // FIX: OTP is invalidated immediately after successful verify
            $clear = $conn->prepare('UPDATE users SET reset_otp_hash = NULL, reset_otp_expires_at = NULL, reset_otp_attempts = 0 WHERE email = ?');
            $clear->bind_param('s', $_SESSION['reset_otp_email']);
            $clear->execute();
            $clear->close();
            $_SESSION['reset_step']     = 3;
            $_SESSION['reset_verified'] = true;
            $step    = 3;
            $success = "Xác nhận OTP thành công. Vui lòng nhập mật khẩu mới.";
        } else {
            $inc = $conn->prepare('UPDATE users SET reset_otp_attempts = reset_otp_attempts + 1 WHERE email = ?');
            $inc->bind_param('s', $_SESSION['reset_otp_email']);
            $inc->execute();
            $inc->close();
            $remaining = max(0, 4 - $otp_attempts);
            $error = "Sai mã OTP. Bạn còn $remaining lần thử.";
        }
    }

    // ── STEP 3: Reset Password ─────────────────────────────────────────────
    elseif ($action === 'reset_password' && $step == 3) {
        // Guard: must have passed OTP verification
        if (empty($_SESSION['reset_verified']) || empty($_SESSION['reset_otp_email'])) {
            $error = "Phiên không hợp lệ. Vui lòng bắt đầu lại.";
            $step  = 1;
        } else {
            $new_pass         = $_POST['new_pass'] ?? '';
            $new_pass_confirm = $_POST['new_pass_confirm'] ?? '';

            if (empty($new_pass) || strlen($new_pass) < 6) {
                $error = "Mật khẩu phải có ít nhất 6 ký tự.";
            } elseif ($new_pass !== $new_pass_confirm) {
                $error = "Mật khẩu xác nhận không khớp.";
            } else {
                $conn   = create_connect();
                $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
                $stmt   = $conn->prepare("UPDATE users SET password_hash = ?, pass = NULL, reset_otp_hash = NULL, reset_otp_expires_at = NULL, reset_otp_attempts = 0 WHERE email = ?");
                $stmt->bind_param('ss', $hashed, $_SESSION['reset_otp_email']);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    $success = "Đổi mật khẩu thành công. <a href='index.php'>Đăng nhập ngay</a>";
                    // A reset must not authenticate the requester. End any
                    // existing login state so the new password is required.
                    unset($_SESSION['id'], $_SESSION['theme'], $_SESSION['language'], $_SESSION['pin_unlocked_notes']);
                    session_regenerate_id(true);
                    // Clear entire reset session
                    $_SESSION = array_diff_key($_SESSION, array_flip(['reset_otp_email', 'reset_otp_time', 'reset_step', 'reset_verified']));
                    $step = 4; // Done
                } else {
                    $error = "Có lỗi xảy ra. Vui lòng thử lại.";
                }
            }
        }
    }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reset Password — Notezy</title>
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
            <h3 class="text-center mt-5 mb-3">Khôi phục mật khẩu</h3>
            <div class="border rounded w-100 mb-5 mx-auto px-4 pt-4 pb-4 bg-white shadow-sm">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?= $success ?></div>
                <?php endif; ?>

                <?php if ($step == 1): ?>
                <!-- STEP 1: Enter email -->
                <form method="post" id="form-reset-email">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="send_email">
                    <div class="mb-3">
                        <label class="form-label">Nhập Email của bạn</label>
                        <input name="email" type="email" class="form-control" required id="reset-email-input">
                    </div>
                    <button class="btn btn-success w-100" id="btn-send-otp">Gửi mã OTP</button>
                </form>

                <?php elseif ($step == 2): ?>
                <!-- STEP 2: Enter OTP -->
                <p class="text-muted small mb-3">Mã OTP đã được gửi đến email của bạn (hiệu lực 15 phút).</p>
                <form method="post" id="form-verify-reset-otp">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="verify_otp">
                    <div class="mb-3">
                        <label class="form-label">Nhập mã OTP</label>
                        <input name="otp" type="text" class="form-control form-control-lg text-center"
                               placeholder="______" maxlength="6" required pattern="\d{6}" id="reset-otp-input">
                    </div>
                    <button class="btn btn-primary w-100" id="btn-verify-reset-otp">Xác nhận OTP</button>
                </form>

                <?php elseif ($step == 3): ?>
                <!-- STEP 3: New password -->
                <form method="post" id="form-new-password">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="reset_password">
                    <div class="mb-3">
                        <label class="form-label">Mật khẩu mới</label>
                        <input name="new_pass" type="password" class="form-control" required minlength="6" id="new-pass-input">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Xác nhận mật khẩu</label>
                        <input name="new_pass_confirm" type="password" class="form-control" required minlength="6" id="new-pass-confirm-input">
                    </div>
                    <button class="btn btn-warning w-100" id="btn-reset-password">Đổi mật khẩu</button>
                </form>
                <?php endif; ?>

                <div class="mt-3 text-center">
                    <a href="index.php" class="text-muted small">← Quay lại đăng nhập</a>
                </div>
            </div>
        </div>
    </div>
</main>
</body>
</html>
