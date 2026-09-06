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
$first_name = '';
$last_name = '';
$email = '';
$user = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first'] ?? '');
    $last_name = trim($_POST['last'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $user = trim($_POST['user'] ?? '');
    $pass = $_POST['pass'] ?? '';
    $pass_confirm = $_POST['pass-confirm'] ?? '';

    if (empty($first_name)) {
        $error = 'Please enter your first name';
    } elseif (empty($last_name)) {
        $error = 'Please enter your last name';
    } elseif (empty($email)) {
        $error = 'Please enter your email';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'This is not a valid email address';
    } elseif (empty($user)) {
        $error = 'Please enter your username';
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
            $mailRes = sendActivationEmail($email, $result['otp'], $first_name);
            $_SESSION['demo_otp'] = $result['otp'];
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
  <title>Register</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" />
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <link rel="stylesheet" href="CSS/register.css">
  <link rel="stylesheet" href="CSS/login.css">
  <link rel="stylesheet" href="CSS/main.css">
</head>

<body class="bg-light">
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <div class="container-fluid">
      <a class="navbar-brand" href="/">
        <img src="logo.png" alt="Notezy" width="50" height="50" class="me-2" />
        <span id="mytext">Website ghi chú trực tuyến</span>
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
        aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
  
      <!-- Menu thu gọn -->
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle text-dark fw-bold" href="#" role="button" data-bs-toggle="dropdown" style="background-color: white;">
              <i class="bi bi-box-arrow-in-right me-1"></i>Login/Register
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow">
              <li><a class="dropdown-item text-dark fw-bold" href="register.php">Register</a></li>
              <li><a class="dropdown-item text-dark fw-bold" href="index.php">Login</a></li>
            </ul>
          </li>
        </ul>
      </div>      
    </div>
  </nav>
  <!-- Main Content -->
  <main>
      <div class="card-body">
        <h2 class="text-center mb-4">Register</h2>
        <?php if ($error !== ''): ?>
          <p style="color:red;">Lỗi: <?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <form method="post" action="" novalidate id="form-res">
          <div class="form-row">
              <div class="form-group col-md-8">
                  <label for="firstname">First name</label>
                  <input value="<?= htmlspecialchars($first_name, ENT_QUOTES, 'UTF-8') ?>" name="first" required class="form-control" type="text" placeholder="First name" id="firstname">
              </div>
              <div class="form-group col-md-8">
                  <label for="lastname">Last name</label>
                  <input value="<?= htmlspecialchars($last_name, ENT_QUOTES, 'UTF-8') ?>" name="last" required class="form-control" type="text" placeholder="Last name" id="lastname">
                  <div class="invalid-tooltip">Last name is required</div>
              </div>
          </div>
          <div class="form-group">
              <label for="email">Email</label>
              <input value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" name="email" required class="form-control" type="email" placeholder="Email" id="email">
          </div>
          <div class="form-group">
              <label for="user">Username</label>
              <input value="<?= htmlspecialchars($user, ENT_QUOTES, 'UTF-8') ?>" name="user" required class="form-control" type="text" placeholder="Username" id="user">
              <div class="invalid-feedback">Please enter your username</div>
          </div>
          <div class="form-group">
              <label for="pass">Password</label>
              <!-- FIX: Do NOT repopulate password into HTML -->
              <input name="pass" required class="form-control" type="password" placeholder="Password" id="pass">
              <div class="invalid-feedback">Password is not valid.</div>
          </div>
          <div class="form-group">
              <label for="pass2">Confirm Password</label>
              <!-- FIX: Do NOT repopulate password into HTML -->
              <input name="pass-confirm" required class="form-control" type="password" placeholder="Confirm Password" id="pass2">
              <div class="invalid-feedback">Password is not valid.</div>
          </div>

          <div class="form-group">
              <button type="submit" class="btn btn-success px-5 mt-3 mr-2" id="register">Register</button>
              <button type="reset" class="btn btn-outline-success px-5 mt-3" id="reset">Reset</button>
          </div>
          <div class="form-group">
              <p>Already have an account? <a href="index.php" style="font-weight: bold; color: white; font-size: 18px;text-shadow: 0 0 2px goldenrod, 0 0 4px orange;text-decoration: none;">Login</a> now.</p>
          </div>
      </form>
          
            
  </main>

  <!-- Footer -->
  <footer id="contact" class="text-white-50 bg-dark pt-5 pb-3">
    <div class="container">
      <div class="row text-center text-md-start">
        <div class="col-md-4 mb-4">
          <div class="footer-logo text-white fw-bold mb-2">Notezy</div>
          <p>Take notes online – simple and free.</p>
          <div class="social-links d-flex justify-content-center justify-content-md-start gap-3">
            <a href="#" class="text-white-50"><i class="fab fa-facebook-f"></i></a>
            <a href="#" class="text-white-50"><i class="fab fa-instagram"></i></a>
            <a href="#" class="text-white-50"><i class="fab fa-youtube"></i></a>
            <a href="#" class="text-white-50"><i class="fab fa-twitter"></i></a>
          </div>
        </div>
        <div class="col-md-4 mb-4">
          <h5 class="text-white">Links</h5>
          <ul class="list-unstyled">
            <li><a href="#" class="text-white-50">Home</a></li>
            <li><a href="#" class="text-white-50">About us</a></li>
            <li><a href="#" class="text-white-50">Explore Features</a></li>
            <li><a href="#" class="text-white-50">Privacy Policy</a></li>
            <li><a href="#" class="text-white-50">Contact us</a></li>
          </ul>
        </div>
        <div class="col-md-4 mb-4">
          <h5 class="text-white">Contact</h5>
          <p><i class="fas fa-map-marker-alt me-2"></i>123 Main Street, Vietnam</p>
          <p><i class="fas fa-phone me-2"></i>+84 123 456 789</p>
          <p><i class="fas fa-envelope me-2"></i>support@notezy.com</p>
          <p><i class="fas fa-clock me-2"></i>Mon - Fri: 9:00 - 18:00</p>
        </div>
      </div>
      <div class="text-center border-top pt-3 mt-4">
        &copy; 2025 Notezy.com
      </div>
    </div>
  </footer>
</body>
</html>
