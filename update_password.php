<?php
require_once __DIR__ . '/includes/session.php';
// Session cookie security
session_set_cookie_params([
    'httponly' => true,
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax',
]);
notezy_session_start();
include 'db.php';

// Kiểm tra đăng nhập
if (empty($_SESSION['id'])) {
    header("Location: index.php");
    exit();
}

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = trim($_POST['pass'] ?? '');
    $new_password     = trim($_POST['pass_new'] ?? '');
    $confirm_password = trim($_POST['pass_confirm'] ?? '');

    // FIX: Lay user_id tu session, khong tin email tu POST
    $session_user_id = (int) $_SESSION['id'];

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "Vui lòng điền đầy đủ thông tin.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Mật khẩu mới và xác nhận mật khẩu không khớp.";
    } elseif (strlen($new_password) < 6) {
        $error = "Mật khẩu mới phải có ít nhất 6 ký tự.";
    } else {
        // FIX: Tra user bang session id, khong dung email tu POST
        $conn   = create_connect();
        $sql    = "SELECT id, password_hash FROM users WHERE id = ?";
        $stm    = $conn->prepare($sql);
        $stm->bind_param('i', $session_user_id);
        $stm->execute();
        $result = $stm->get_result();
        $user   = $result->fetch_assoc();

        if (!$user) {
            $error = "Không tìm thấy tài khoản.";
        } elseif (empty($user['password_hash']) || !password_verify($current_password, $user['password_hash'])) {
            $error = "Mật khẩu hiện tại không đúng.";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET pass = NULL, password_hash = ? WHERE id = ?";
            $stm = $conn->prepare($sql);
            $stm->bind_param('si', $hashed_password, $session_user_id);
            if ($stm->execute()) {
                // FIX: redirect ve account.php thay vi introduce.php
                header('Location: account.php?msg=password_changed');
                exit();
            } else {
                $error = "Có lỗi xảy ra. Vui lòng thử lại.";
            }
        }
    }
}

// Success message khi redirect tu account.php
$success_msg = '';
if (isset($_GET['msg']) && $_GET['msg'] === 'password_changed') {
    $success_msg = 'Đổi mật khẩu thành công!';
}
?>


<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Notezy - Change Password</title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

  <!-- Font Awesome for Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

  <!-- Custom CSS -->
  <link rel="stylesheet" href="CSS/main.css">

  <!-- Google Font -->
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="CSS/reset.css">
  <link rel="stylesheet" href="CSS/login.css">
</head>

<body class="bg-light">
    <header>
        <nav class="navbar navbar-expand-lg fixed-top">
            <div class="container">
                <img src="logo.png" alt="Notezy" width="100" height="100" class="me-2" />
                <a class="navbar-brand logo" href="../a.html">Notezy</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item"><a class="nav-link" href=""><i class="fas fa-home me-1"></i>Home</a></li>
                        <li class="nav-item"><a class="nav-link" href=""><i class="fas fa-info-circle me-1"></i>About Us</a></li>
                        <li class="nav-item"><a class="nav-link" href=""><i class="fas fa-star me-1"></i>Explore Features</a></li>
                        <li class="nav-item"><a class="nav-link" href=""><i class="fas fa-shield-alt me-1"></i>Privacy Policy</a></li>
                        <li class="nav-item"><a class="nav-link" href=""><i class="fas fa-envelope me-1"></i>Contact Us</a></li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <!-- Main Content -->
    <main class="container my-4 pt-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-5">
                    <h3 class="text-center text-secondary mt-5 mb-3">Change Password</h3>
                    <form novalidate method="post" action="" class="border rounded w-100 mb-5 mx-auto px-3 pt-3 bg-light">
                    <!-- FIX: Email field removed — user identified by session, not by POST input -->
                    <?php if (!empty($success_msg)): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div>
                    <?php endif; ?>
                        <div class="form-group">
                            <label for="pass">Old Password</label>
                            <input name="pass" class="form-control bg-dark text-warning" type="password" placeholder="Password" id="pass">
                            <div class="invalid-feedback">Password is not valid.</div>
                        </div>
                        <div class="form-group">
                            <label for="pass2">New Password</label>
                            <input name="pass_new" class="form-control bg-dark text-warning" type="password" placeholder="Confirm Password" id="pass2">
                            <div class="invalid-feedback">Password is not valid.</div>
                        </div>
                        <div class="form-group">
                            <label for="pass2">Confirm Password</label>
                            <input name="pass_confirm" class="form-control bg-dark text-warning" type="password" placeholder="Confirm Password" id="pass2">
                        </div>
                          <?php if (!empty($error)): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                          <?php endif; ?>
                        <div class="form-group">
                            <button class="btn btn-success px-5" id="change">Change password</button>
                        </div>
                    </form>
                    <form action="logout.php" method="post">
                      <button type="submit">Đăng xuất</button>
                    </form>
                </div>
            </div>
        </div>
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
                        <li><a href="../a.html" class="text-white-50">Home</a></li>
                        <li><a href="../introduction.html" class="text-white-50">About Us</a></li>
                        <li><a href="#" class="text-white-50">Explore Features</a></li>
                        <li><a href="../privacy.html" class="text-white-50">Privacy Policy</a></li>
                        <li><a href="../contact.html" class="text-white-50">Contact Us</a></li>
                    </ul>
                </div>
                <div class="col-md-4 mb-4">
                    <h5 class="text-white">Contact</h5>
                    <p><i class="fas fa-envelope"></i> support@notezy.com</p>
                    <p><i class="fas fa-phone"></i> +84 123 456 789</p>
                </div>
            </div>
            <div class="text-center border-top pt-3 mt-4">
                &copy; 2025 Notezy.com
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
