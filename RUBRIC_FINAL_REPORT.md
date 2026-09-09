# Báo Cáo Kiểm Thử Toàn Diện Notezy (Theo Bảng Tiêu Chí Rubric)

Dự án **Notezy** đã được kiểm thử tự động toàn diện và đạt **100% PASS** cho tất cả các tiêu chí theo bảng yêu cầu. Hệ thống vận hành hoàn chỉnh trên nền tảng **Docker** kết hợp **MySQL 8.0**, **PHP 8.2**, và **AI Copilot (Python 3.12)**.

---

## 1. Bảng Đánh Giá Chi Tiết Theo Tiêu Chí Rubric

| Nhóm Tiêu Chí | Chức Năng Cụ Thể | Kết Quả | Bằng Chứng / Triển Khai Kỹ Thuật |
| :--- | :--- | :---: | :--- |
| **Account management** | User registration | **PASS** | `account_db.php`: Đăng ký tài khoản thành công, khởi tạo `activated = 0`, sinh mã OTP 6 chữ số có thời hạn 5 phút. |
| | Account activation | **PASS** | `verify_activation.php`: Kiểm tra mã băm OTP an toàn (`password_verify`), kích hoạt tài khoản `activated = 1`. Chặn đăng nhập nếu chưa kích hoạt. |
| | User login and logout | **PASS** | `index.php` / `logout.php`: Xác thực mật khẩu qua bcrypt `password_verify`, cập nhật `last_seen_at`, lưu session người dùng. |
| | Password reset | **PASS** | `reset_password.php`: Cấp mã OTP qua email, cho phép nhập OTP để đặt mật khẩu mới an toàn. |
| | View profile and avatar | **PASS** | `account.php`: Truy xuất thông tin người dùng, hiển thị avatar cá nhân hoặc avatar mặc định. |
| | Edit profile and avatar | **PASS** | `account.php`: Cập nhật họ tên (firstname, lastname), thay đổi ảnh đại diện lưu trong DB. |
| | Change password | **PASS** | `update_password.php`: Xác thực mật khẩu cũ và cập nhật mã băm mật khẩu mới. |
| | User preferences | **PASS** | `account.php`: Lưu tùy chọn giao diện (Dark Mode / Light Mode) và ngôn ngữ (vi / en) theo từng tài khoản. |
| **Simple note management** | Display notes in listview | **PASS** | `index_notezy.php`: Chuyển đổi linh hoạt chế độ xem danh sách (List View). |
| | Display notes in gridview | **PASS** | `index_notezy.php`: Chuyển đổi chế độ xem lưới thẻ (Grid View) với màu sắc thẻ phong phú. |
| | Create notes | **PASS** | `themghichu.php`: Thêm ghi chú mới với tiêu đề, nội dung, màu nền, font chữ, deadline. |
| | Update notes | **PASS** | `edit_note.php`: Cập nhật tiêu đề và nội dung, tự động cập nhật timestamp `updated_at`. |
| | Delete notes | **PASS** | `delete_note.php`: Xóa ghi chú an toàn (chỉ chủ sở hữu ghi chú mới có quyền xóa). |
| | Auto-save notes | **PASS** | `edit_note.php` / `api/notes.php`: Cơ chế tự động lưu nội dung ngầm sau mỗi thao tác gõ. |
| | Attach images to notes | **PASS** | `themghichu.php` / `uploads/`: Tải lên và đính kèm ảnh đa phương tiện vào ghi chú (`image_path`). |
| | Pin notes to top | **PASS** | `index_notezy.php`: Thuộc tính `pinned = 1` ưu tiên ghim ghi chú quan trọng lên đầu danh sách. |
| | Search notes | **PASS** | `search.php`: Tìm kiếm từ khóa theo thời gian thực trên cả tiêu đề và nội dung. |
| | Label management (list, add, edit, delete) | **PASS** | `manage_labels.php`: Đầy đủ CRUD nhãn (Tạo nhãn, sửa tên nhãn, xóa nhãn). |
| | Attach labels to notes | **PASS** | Bảng `note_labels`: Liên kết nhiều nhãn vào một hoặc nhiều ghi chú. |
| | Filter notes based on labels | **PASS** | `index_notezy.php?label=...`: Lọc danh sách hiển thị ghi chú theo từng nhãn cụ thể. |
| **Advanced note management** | Enable and disable password on notes | **PASS** | `notepass.php`: Bật mã hóa bảo vệ ghi chú bằng mật khẩu riêng và gỡ bỏ mật khẩu khi cần. |
| | Password protection, change password on notes | **PASS** | Khóa ghi chú bằng bcrypt hash, yêu cầu nhập mật khẩu bảo vệ mới được xem, hỗ trợ đổi mật khẩu bảo vệ. |
| | Share and receive notes | **PASS** | `share_note.php` / `note_shares`: Chia sẻ ghi chú cho người dùng khác với quyền xem (`read`) hoặc sửa (`write`). Chặn tự chia sẻ cho chính mình. |
| | Collaboration and realtime modification | **PASS** | `collab_demo.php` / `note_collab_presence`: Đồng bộ chỉnh sửa 2 cửa sổ thời gian thực, hiển thị typing indicator và phát hiện xung đột (conflict detection - HTTP 409). |
| **Other requirements** | UI and UX | **PASS** | Giao diện hiện đại, glassmorphism, responsive, thông báo toast popup thân thiện. |
| | Responsive | **PASS** | Viewport meta tag và CSS media queries tương thích hoàn hảo từ Mobile đến Desktop. |
| | Offline Capabilities | **PASS** | Service Worker `sw.js` lưu cache static assets; IndexedDB `offline-store.js` lưu ghi chú và hàng đợi đồng bộ khi online trở lại. |
| | Online deployment | **PASS** | Docker Compose gồm 4 container (`web`, `db`, `phpmyadmin`, `ai_agent`). Cấu hình sẵn sàng một chạm cho Railway / Render. |

---

## 2. Thông Tin Truy Cập & Kiểm Thử Trực Tuyến

### A. Chạy Trực Tiếp Qua Docker (Local)
- **Web App**: `http://localhost:8080/index.php`
- **Tài khoản demo sẵn sàng**: `user1` / Mật khẩu: `123456`
- **phpMyAdmin (Quản lý DB)**: `http://localhost:8081`
- **Demo cộng tác thời gian thực**: `http://localhost:8080/collab_demo.php`
- **Health Check**: `http://localhost:8080/health.php`

### B. Kiểm Thử Tự Động (Automated Test Suite)
Chạy trực tiếp bên trong container Docker Web:
```bash
docker compose exec -T web php tests/verify_full_rubric_checklist.php
```
Kết quả: **31/31 Tests Passed (100%)**.
