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
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Notezy - Chính sách bảo mật</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="CSS/main.css">
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
      <h1 class="text-center mb-5">Chính sách bảo mật</h1>

      <div class="policy-section"><h4>1. Thông tin được lưu</h4><p>Ứng dụng lưu thông tin tài khoản, ghi chú, nhãn, lịch và dữ liệu chia sẻ mà bạn tạo. Mật khẩu tài khoản được lưu dưới dạng giá trị băm.</p></div>

      <div class="policy-section"><h4>2. Mục đích sử dụng</h4><p>Thông tin tài khoản dùng để đăng nhập. Nội dung được dùng cho các chức năng ghi chú, tìm kiếm, lịch, chia sẻ và trợ lý theo thao tác của bạn.</p></div>

      <div class="policy-section"><h4>3. Bảo vệ kết nối và nội dung</h4><p>Bản công khai sử dụng kết nối web bảo mật và kết nối cơ sở dữ liệu có xác minh chứng chỉ. Mã bảo mật và mật khẩu ghi chú kiểm soát truy cập; ứng dụng không cung cấp mã hóa đầu cuối cho nội dung ghi chú.</p></div>

      <div class="policy-section"><h4>4. Quản lý dữ liệu</h4><p>Bạn có thể sửa, lưu trữ hoặc xóa ghi chú của mình và thu hồi quyền chia sẻ. Quản trị viên quản lý tài khoản người dùng qua trang quản trị. Xóa vĩnh viễn có thể làm mất dữ liệu liên quan.</p></div>

      <div class="policy-section"><h4>5. Dịch vụ bên ngoài</h4><p>Bản triển khai sử dụng Render cho ứng dụng và Aiven cho cơ sở dữ liệu. Chức năng thư điện tử và mô hình trợ lý bên ngoài phụ thuộc cấu hình triển khai.</p></div>

      <div class="policy-section"><h4>6. Dữ liệu trong trình duyệt</h4><p>Ứng dụng dùng phiên đăng nhập và bộ nhớ trình duyệt để duy trì trạng thái và hỗ trợ một số thao tác ngoại tuyến. Nên đăng xuất khi dùng máy chung.</p></div>

      <div class="policy-section"><h4>7. Phạm vi trình diễn</h4><p>Đây là sản phẩm đồ án phục vụ học tập và trình diễn. Tài khoản dùng chung và dữ liệu mẫu không phù hợp để lưu thông tin riêng tư.</p></div>

      <div class="policy-section"><h4>8. Mã xác thực mô phỏng</h4><p>Khi bật chế độ trình diễn, mã kích hoạt tài khoản là 123456 và không gửi thư thật. Mã này không phải mã khôi phục mật khẩu hay mã bảo mật của ghi chú.</p></div>

      <div class="policy-section"><h4>9. Thay đổi cấu hình</h4><p>Thông tin trên trang này mô tả bản trình diễn. Khi thay đổi dịch vụ lưu trữ, gửi thư hoặc trợ lý, nhóm phát triển cần cập nhật nội dung tương ứng.</p></div>

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
