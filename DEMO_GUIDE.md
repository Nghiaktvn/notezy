# Notezy — hướng dẫn demo XAMPP, Docker và phpMyAdmin

## Tài khoản demo

| Tài khoản | Email | Mật khẩu demo | Mục đích |
|---|---|---|---|
| `user1` | `user1@example.com` | `Password123!` | Chủ sở hữu ghi chú |
| `user2` | `user2@example.com` | `Password123!` | Người nhận/chỉnh sửa ghi chú được chia sẻ |

Hai tài khoản này chỉ dành cho môi trường demo cục bộ. Trong bảng `users`, cột
`password_hash` và cột tương thích cũ `pass` lưu **bcrypt hash**, không lưu mật
khẩu chữ thường. Đây là bằng chứng đúng yêu cầu bảo mật; mật khẩu demo ở bảng
trên dùng để đăng nhập trên giao diện.

## Demo bằng XAMPP

1. Bật Apache và MySQL trong XAMPP. MySQL của dự án dùng cổng `3307`.
2. Mở ứng dụng: <http://localhost/notezy/>.
3. Mở phpMyAdmin: <http://localhost/phpmyadmin/>.
4. Trong phpMyAdmin chọn database `notezy`, sau đó chọn view
   `demo_account_overview` và bấm **Browse**.
5. Đối chiếu `user1`/`user2`, email, `Activated`, vai trò, chuỗi
   `password_hash` bắt đầu bằng `$2y$` và trạng thái `Bcrypt hash (secure)`.
6. Đăng nhập ứng dụng bằng `user1` / `Password123!`.

Nếu cần tạo lại dữ liệu demo:

```powershell
php tests/seed_demo_accounts.php
php tests/seed_demo_showcase.php
```

## Demo bằng Docker

Không chạy MySQL XAMPP đồng thời với Docker vì cả hai cùng dùng cổng `3307`.

```powershell
docker compose -f docker-compose.yml -f docker-compose.services.yml --profile tools up -d --build
docker compose -f docker-compose.yml -f docker-compose.services.yml exec -T web php tests/seed_demo_accounts.php
docker compose -f docker-compose.yml -f docker-compose.services.yml exec -T web php tests/seed_demo_showcase.php
```

- Ứng dụng: <http://localhost:8080/>
- phpMyAdmin: <http://localhost:8081/>
- phpMyAdmin Docker: user `root`, mật khẩu database lấy từ `DB_PASSWORD` trong
  `.env` (không phải mật khẩu Gmail).

## Trình tự demo theo rubric

1. Đăng ký một tài khoản mới, cho thấy kiểm tra trùng username/email và mật khẩu
   được băm trong phpMyAdmin.
2. Kích hoạt tài khoản bằng OTP; thử đăng nhập sai và đúng; mở hồ sơ, đổi avatar,
   giao diện/ngôn ngữ; trình bày quên/đặt lại mật khẩu.
3. Với `user1`, tạo, xem, sửa, tìm kiếm, ghim, gắn nhãn, đổi màu, đặt nhắc việc,
   chuyển thùng rác, khôi phục và xóa ghi chú.
4. Trình bày ghi chú hình ảnh/tệp/video, ghi chú có mật khẩu và chỉ báo trạng thái
   đặc biệt.
5. Chia sẻ một ghi chú cho `user2` với quyền Viewer rồi Editor; mở hai phiên để
   trình bày cộng tác thời gian thực và xử lý xung đột.
6. Mở trợ lý AI để hỏi đáp, tóm tắt, phân loại, tạo flashcard/quiz.
7. Chuyển List/Grid, đổi kích thước cửa sổ để kiểm tra responsive, rồi tạm dừng
   Apache để cho thấy trang offline/service worker vẫn hiển thị dữ liệu đã cache.
8. Kết thúc bằng trang health, kết quả bộ test và sơ đồ ERD/database đã chuẩn hóa.

## Lệnh kiểm thử trước khi trình bày

XAMPP:

```powershell
php tests/verify_full_rubric_checklist.php
php test_system_flow.php
php -r 'require "env.php"; require "tests/test_ai_live.php";'
php tests/security_regression_check.php
```

Docker:

```powershell
docker compose -f docker-compose.yml -f docker-compose.services.yml exec -T web php tests/verify_full_rubric_checklist.php
docker compose -f docker-compose.yml -f docker-compose.services.yml exec -T web php test_system_flow.php
docker compose -f docker-compose.yml -f docker-compose.services.yml exec -T web php -r 'require "env.php"; require "tests/test_ai_live.php";'
docker compose -f docker-compose.yml -f docker-compose.services.yml exec -T web php tests/security_regression_check.php
docker compose -f docker-compose.yml -f docker-compose.services.yml ps
```

Khi chuyển lại sang XAMPP, dừng toàn bộ stack Docker trước để giải phóng cổng
`3307`:

```powershell
docker compose -f docker-compose.yml -f docker-compose.services.yml --profile tools stop
```

Không chỉnh sửa hoặc trình chiếu trực tiếp giá trị bí mật Gmail/API trong `.env`.
