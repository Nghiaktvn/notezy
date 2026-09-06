<?php
require_once __DIR__ . '/includes/session.php';
require_once('db.php');
notezy_session_start();

if (!isset($_SESSION['id'])) {
    header('Location: index.php');
    exit();
}

$user_id = (int) $_SESSION['id'];  // 🛠️ Thêm dòng này để gán giá trị user_id

$sql1 = "SELECT avatar FROM users WHERE id = ?";
$stm1 = $conn->prepare($sql1);

if (!$stm1) {
    die("Prepare failed: " . $conn->error);
}

$stm1->bind_param('i', $user_id);

if (!$stm1->execute()) {
    die("Execute failed: " . $stm1->error);
}

$result1 = $stm1->get_result();

if (!$result1) {
    die("Get result failed: " . $stm1->error);
}

$kq = $result1->fetch_assoc();
$avatar = $kq && isset($kq['avatar']) ? $kq['avatar'] : 'default.png'; // fallback nếu không có avatar
?>

<!-- File: contact.html -->
<!-- File: contact.html -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Notezy - Contact Us</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../CSS/main.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    .contact-box {
      background-color: #f8f9fa;
      border-radius: 12px;
      padding: 30px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
    .contact-icon {
      font-size: 1.5rem;
      color: #0d6efd;
      width: 40px;
    }
  </style>
</head>
<body>
  <header>
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <img src="logo.png" alt="Notezy" width="50" height="50" class="me-2" />
            <a class="navbar-brand logo" href="index_notezy.php">Notezy</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="index_notezy.php"><i class="fas fa-home me-1"></i>Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="introduce.php"><i class="fas fa-info-circle me-1"></i>About Us</a></li>
                    <li class="nav-item"><a class="nav-link" href="khampha.php"><i class="fas fa-star me-1"></i>Features</a></li>
                    <li class="nav-item"><a class="nav-link" href="privacy.php"><i class="fas fa-shield-alt me-1"></i>Privacy</a></li>
                    <li class="nav-item"><a class="nav-link" href="contact.php"><i class="fas fa-envelope me-1"></i>Contact</a></li>
                </ul>
            </div>
        </div>
    </nav>
</header>

  <main class="py-5" style="margin-top: 80px;">
    <div class="container">
      <h1 class="text-center mb-4">Contact Notezy</h1>
      <p class="text-center text-muted mb-5">We'd love to hear from you. Feel free to reach out through any of the following methods:</p>

      <div class="row justify-content-center">
        <div class="col-lg-6">
          <div class="contact-box">
            <ul class="list-unstyled">
              <li class="mb-3 d-flex align-items-center">
                <i class="fas fa-envelope contact-icon"></i> <span>Email: <strong>support@notezy.com</strong></span>
              </li>
              <li class="mb-3 d-flex align-items-center">
                <i class="fas fa-link contact-icon"></i> <span>Link dự phòng: <a href="https://backup.notezy.com" target="_blank">backup.notezy.com</a></span>
              </li>
              <li class="mb-3 d-flex align-items-center">
                <i class="fab fa-facebook-f contact-icon"></i> <span>Facebook: <em>(coming soon)</em></span>
              </li>
              <li class="mb-3 d-flex align-items-center">
                <i class="fas fa-comment-dots contact-icon"></i> <span>Zalo: <em>(coming soon)</em></span>
              </li>
              <li class="mb-3 d-flex align-items-center">
                <i class="fas fa-phone contact-icon"></i> <span>Phone: <em>(coming soon)</em></span>
              </li>
            </ul>
            <p class="text-muted mt-4">All contact channels will be updated soon. Please check back later or follow our official announcements.</p>
          </div>
        </div>
      </div>
    </div>
  </main>

  <footer class="text-white-50 bg-dark pt-5 pb-3">
    <div class="container">
      <div class="row">
        <div class="col-md-4">
          <h5 class="text-white">Notezy</h5>
          <p>Your smart note-taking partner.</p>
        </div>
        <div class="col-md-4">
          <h5 class="text-white">Links</h5>
          <ul class="list-unstyled">
            <li><a href="index_notezy.php" class="text-white-50">Home</a></li>
                    <li><a href="introduce.php" class="text-white-50">About Us</a></li>
                    <li><a href="khampha.php" class="text-white-50">Explore Features</a></li>
                    <li><a href="privacy.php" class="text-white-50">Privacy Policy</a></li>
                    <li><a href="contact.php" class="text-white-50">Contact Us</a></li>
          </ul>
        </div>
        <div class="col-md-4">
          <h5 class="text-white">Contact</h5>
          <p><i class="fas fa-envelope"></i> support@notezy.com</p>
          <p><i class="fas fa-phone"></i> +84 123 456 789</p>
        </div>
      </div>
      <div class="text-center border-top pt-3 mt-3">
        <small>&copy; 2025 Notezy. All rights reserved.</small>
      </div>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>