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
<!-- File: privacy.html -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Notezy - Privacy Policy</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../CSS/main.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    .policy-section {
      padding: 30px;
      background-color: #f9f9f9;
      border-radius: 12px;
      margin-bottom: 25px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
    .policy-section h4 {
      color: #0d6efd;
      font-weight: bold;
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
      <h1 class="text-center mb-5">Privacy Policy</h1>

      <div class="policy-section">
        <h4>1. What Information We Collect</h4>
        <p>Notezy collects only the essential information required to provide a personalized and secure experience. This includes your email address for account creation, encrypted credentials for login authentication, and usage data such as device type and operating system. We do not collect or store your location, contacts, or any third-party data unless explicitly authorized by you.</p>
      </div>

      <div class="policy-section">
        <h4>2. How We Use Your Data</h4>
        <p>The data collected is used strictly for the following purposes: user authentication, synchronization of notes across devices, and delivery of personalized features like labels and note filters. We do not use your data for advertising or profiling, and we do not allow third-party tracking within the app.</p>
      </div>

      <div class="policy-section">
        <h4>3. Data Encryption and Storage</h4>
        <p>Your notes and personal data are encrypted both in transit and at rest. We employ AES-256 encryption for data storage and TLS protocols for secure transmission. This ensures that even if data is intercepted or accessed illegally, it remains unreadable without your unique credentials.</p>
      </div>

      <div class="policy-section">
        <h4>4. Access and Control</h4>
        <p>You have full control over your data at all times. You can view, update, export, or permanently delete your account and all associated content through the account settings interface. Once deleted, your data is removed from our servers and cannot be recovered.</p>
      </div>

      <div class="policy-section">
        <h4>5. Third-party Integration</h4>
        <p>Notezy does not share or sell your data to third parties. If integration with services like Google Drive or Dropbox becomes available, it will be strictly opt-in, and users will retain full control over which files are accessed or synced. All third-party access will comply with the most stringent data protection standards.</p>
      </div>

      <div class="policy-section">
        <h4>6. Cookies and Tracking</h4>
        <p>Notezy does not use cookies to track user behavior across sites. Any cookies used are limited to essential functionality such as session persistence and language preferences. We do not use analytics software that profiles user activity.</p>
      </div>

      <div class="policy-section">
        <h4>7. Children’s Privacy</h4>
        <p>Notezy is not intended for use by individuals under the age of 13. We do not knowingly collect personal information from children without verifiable parental consent. If you believe your child has provided us with personal data, please contact us immediately to have it removed.</p>
      </div>

      <div class="policy-section">
        <h4>8. Data Breach Protocol</h4>
        <p>In the unlikely event of a data breach, Notezy commits to notifying all affected users within 72 hours. We will provide full transparency regarding the nature of the breach, affected data, and steps taken to mitigate the issue. Our servers are regularly monitored and updated with the latest security patches to prevent such incidents.</p>
      </div>

      <div class="policy-section">
        <h4>9. Policy Updates</h4>
        <p>This privacy policy is reviewed periodically and updated as necessary to reflect operational changes or legal requirements. Major changes will be communicated via in-app notifications and email alerts. Users will always have access to the most recent version of this policy on our website.</p>
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
