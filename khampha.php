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
<!-- File: explore.html -->
<!DOCTYPE html>\n<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Notezy - Khám phá tính năng</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="CSS/main.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    .card {
      border-radius: 15px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
      transition: transform 0.2s;
    }
    .card:hover {
      transform: translateY(-5px);
    }
    .card-body {
      padding: 30px;
    }
    .card-title {
      font-weight: bold;
      color: #0d6efd;
    }
  </style>
</head>
<body>
<header>
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <img src="logo.svg" alt="Notezy" width="50" height="50" class="me-2" />
            <a class="navbar-brand logo" href="index_notezy.php">Notezy</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="index_notezy.php"><i class="fas fa-home me-1"></i>Trang chủ</a></li>
                    <li class="nav-item"><a class="nav-link" href="introduce.php"><i class="fas fa-info-circle me-1"></i>Giới thiệu</a></li>
                    <li class="nav-item"><a class="nav-link" href="khampha.php"><i class="fas fa-star me-1"></i>Tính năng</a></li>
                    <li class="nav-item"><a class="nav-link" href="privacy.php"><i class="fas fa-shield-alt me-1"></i>Bảo mật</a></li>
                    <li class="nav-item"><a class="nav-link" href="contact.php"><i class="fas fa-envelope me-1"></i>Liên hệ</a></li>
                </ul>
            </div>
        </div>
    </nav>
</header>


  <main class="py-5" style="margin-top: 80px;">
    <div class="container">
      <h1 class="text-center mb-5">Khám phá tính năng Notezy</h1>
      <div class="row g-4">

        <div class="col-md-6 col-lg-4">
          <div class="card h-100">
            <div class="card-body">
              <h5 class="card-title">Dạng danh sách và dạng lưới</h5>
              <p class="card-text">Chuyển giữa dạng danh sách và dạng lưới để xem ghi chú theo cách phù hợp với bạn.</p>
            </div>
          </div>
        </div>

        <div class="col-md-6 col-lg-4">
          <div class="card h-100">
            <div class="card-body">
              <h5 class="card-title">Tạo và sửa ghi chú</h5>
              <p class="card-text">Tạo ghi chú với tiêu đề, nội dung, nhãn và hình thức hiển thị. Trang chỉnh sửa hỗ trợ tự động lưu khi có kết nối.</p>
            </div>
          </div>
        </div>

        <div class="col-md-6 col-lg-4">
          <div class="card h-100">
            <div class="card-body">
              <h5 class="card-title">Xác nhận trước khi xóa</h5>
              <p class="card-text">Ứng dụng yêu cầu xác nhận trước khi xóa. Ghi chú đã lưu trữ có thể khôi phục; thao tác xóa vĩnh viễn cần được cân nhắc.</p>
            </div>
          </div>
        </div>

        <div class="col-md-6 col-lg-4">
          <div class="card h-100">
            <div class="card-body">
              <h5 class="card-title">Đính kèm hình ảnh</h5>
              <p class="card-text">Đính kèm hình ảnh hoặc tệp để bổ sung thông tin cho ghi chú. Mỗi loại tệp có giới hạn dung lượng hiển thị trong biểu mẫu.</p>
            </div>
          </div>
        </div>

        <div class="col-md-6 col-lg-4">
          <div class="card h-100">
            <div class="card-body">
              <h5 class="card-title">Ghim ghi chú quan trọng</h5>
              <p class="card-text">Ghim các ghi chú quan trọng để ưu tiên hiển thị và dễ tìm lại.</p>
            </div>
          </div>
        </div>

        <div class="col-md-6 col-lg-4">
          <div class="card h-100">
            <div class="card-body">
              <h5 class="card-title">Tìm kiếm ghi chú</h5>
              <p class="card-text">Tìm ghi chú theo tiêu đề hoặc nội dung. Nội dung đang khóa không được hiển thị trước khi mở khóa hợp lệ.</p>
            </div>
          </div>
        </div>

        <div class="col-md-6 col-lg-4">
          <div class="card h-100">
            <div class="card-body">
              <h5 class="card-title">Quản lý nhãn</h5>
              <p class="card-text">Tạo nhãn riêng và gắn nhãn vào ghi chú để sắp xếp việc học, công việc hoặc thông tin cá nhân.</p>
            </div>
          </div>
        </div>

        <div class="col-md-6 col-lg-4">
          <div class="card h-100">
            <div class="card-body">
              <h5 class="card-title">Lọc theo nhãn</h5>
              <p class="card-text">Chọn nhãn trên trang chủ để thu hẹp danh sách ghi chú cần xem.</p>
            </div>
          </div>
        </div>

        <div class="col-md-6 col-lg-4">
          <div class="card h-100">
            <div class="card-body">
              <h5 class="card-title">Bảo vệ ghi chú</h5>
              <p class="card-text">Bảo vệ ghi chú bằng mã bảo mật sáu số hoặc mật khẩu. Nếu có cả hai khóa, cần xác minh đầy đủ trước khi xem nội dung.</p>
            </div>
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
          <p>Người bạn đồng hành trong việc ghi chú.</p>
        </div>
        <div class="col-md-4">
          <h5 class="text-white">Liên kết</h5>
          <ul class="list-unstyled">
                    <li><a href="index_notezy.php" class="text-white-50">Trang chủ</a></li>
                    <li><a href="introduce.php" class="text-white-50">Giới thiệu</a></li>
                    <li><a href="khampha.php" class="text-white-50">Khám phá tính năng</a></li>
                    <li><a href="privacy.php" class="text-white-50">Chính sách bảo mật</a></li>
                    <li><a href="contact.php" class="text-white-50">Liên hệ</a></li>
          </ul>
        </div>
        <div class="col-md-4">
          <h5 class="text-white">Liên hệ</h5>
          <p><i class="fas fa-envelope"></i> support@notezy.com</p>
          <p><i class="fas fa-phone"></i> +84 123 456 789</p>
        </div>
      </div>
      <div class="text-center border-top pt-3 mt-3">
        <small>&copy; 2025 Notezy. Bảo lưu mọi quyền.</small>
      </div>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
