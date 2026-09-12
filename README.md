# Notezy

[![Open in GitHub Codespaces](https://github.com/codespaces/badge.svg)](https://github.com/codespaces/new?hide_repo_select=true&ref=main&repo=1358846417)

Ứng dụng ghi chú (notes) full-stack: PHP + MySQL cho backend/web truyền thống,
kèm REST API (`api/`) phục vụ frontend React (Vite) trong thư mục `frontend/`.

## Triển khai chỉ bằng GitHub Codespaces

GitHub Pages không chạy PHP/MySQL. Repository này cung cấp cấu hình Codespaces
để chạy nguyên cụm Docker và tạo URL HTTPS thuộc miền GitHub:

1. Mở repository trên GitHub, chọn **Code → Codespaces → Create codespace on main**.
2. Chờ các container healthy và mở port **80 — Notezy Web**.
3. Trong tab **Ports**, đặt port `8080` và `8766` thành **Public**. Giữ port
   `8081` (phpMyAdmin) ở chế độ Private để không công khai công cụ quản trị. URL ứng dụng có
   dạng `https://<codespace>-80.app.github.dev`.
4. Để gửi email thật, tạo Codespaces secrets `MAIL_USERNAME`, `MAIL_PASSWORD`,
   `MAIL_FROM`, `MAIL_HOST=smtp.gmail.com`, `MAIL_PORT=587`, rồi rebuild Codespace.
   Có thể thêm `GEMINI_API_KEY` và `LLM_PROVIDER=gemini` để bật LLM thật.

Secret nội bộ, database password và URL public được sinh tự động trong `.env`
bị Git bỏ qua. Codespace phải đang chạy khi giảng viên truy cập; port public có
thể cần bật lại sau khi Codespace được restart.

---

## ⚡ 1. Cộng tác Thời Gian Thực & Xử lý Xung đột (Realtime Collaboration & Conflict Detection)

> **Mục tiêu đánh giá: Đạt điểm tối đa 0.5/0.5**
> Hệ thống hỗ trợ 2 người dùng mở và cùng chỉnh sửa một ghi chú trên 2 cửa sổ/trình duyệt khác nhau với khả năng đồng bộ thời gian thực và cảnh báo xung đột tức thì.

### Cách 1: Sử dụng công cụ Demo trực quan 1-Click (`collab_demo.php`)
1. Mở trình duyệt và truy cập:
   ```
   http://localhost:8080/collab_demo.php
   # hoặc http://localhost/notezy/collab_demo.php (nếu dùng XAMPP)
   ```
2. Màn hình chia đôi xuất hiện:
   - **Bên trái (Cửa sổ 1):** Người dùng `collab_user_a` (Chủ sở hữu ghi chú).
   - **Bên phải (Cửa sổ 2):** Người dùng `collab_user_b` (Cộng tác viên có quyền Sửa - Editor).
3. **Thử nghiệm 1 (Live Sync tức thì):**
   - Nhấn nút **"1. Thử Live Sync"** ở thanh công cụ trên cùng (hoặc gõ trực tiếp bên cửa sổ A).
   - Quan sát khung bên phải (User B): nội dung tự động cập nhật trong vòng **1 giây** mà **không cần reload trang**, kèm hiệu ứng viền phát sáng và thông báo toast `⚡ Ghi chú vừa được cập nhật trực tiếp bởi cộng tác viên`.
4. **Thử nghiệm 2 (Cảnh báo Xung Đột - Conflict Alert):**
   - Nhấn nút **"2. Thử Conflict Alert"** (hoặc để cả 2 bên cùng gõ nội dung khác nhau cùng lúc).
   - Khi User A lưu phiên bản mới, khung User B lập tức bật **Hộp thoại Cảnh Báo Xung Đột Thời Gian Thực**:
     * Hiển thị bảng so sánh trực quan song song giữa **"Bản hiện tại của bạn"** và **"Bản mới nhất trên máy chủ"**.
     * Cung cấp 3 lựa chọn tức thì:
       1. `[Tải bản của người khác]` (Chấp nhận bản của cộng tác viên).
       2. `[Ghi đè bản của tôi]` (Giữ nguyên bản nháp của mình).
       3. `[Hợp nhất cả hai (Merge)]` (Tự động ghép cả 2 nội dung lại với nhau để không mất dữ liệu).
5. **Thử nghiệm 3 (Trạng thái Hiện Diện Trực Tuyến - Live Presence):**
   - Phía trên form chỉnh sửa hiển thị huy hiệu: `🟢 [Tên người dùng] đang cùng mở`.
   - Khi một người đang gõ chữ, bên kia hiện: `✍️ [Tên người dùng] đang gõ...`.

### Cách 2: Thử nghiệm thủ công trên 2 trình duyệt riêng biệt
1. **Trình duyệt 1 (Chrome thường):** Đăng nhập tài khoản A, tạo một ghi chú và chia sẻ cho tài khoản B với quyền **"Có thể chỉnh sửa (Editor)"** tại mục Chia sẻ ghi chú.
2. **Trình duyệt 2 (Chrome Ẩn danh / Firefox):** Đăng nhập tài khoản B, vào mở ghi chú được chia sẻ (`edit_note.php?id=...`).
3. Cùng soạn thảo và theo dõi kết quả đồng bộ hoặc cảnh báo xung đột tương tự như trên.

---

## 🛠️ 2. Hướng dẫn Môi trường XAMPP & Khắc phục lỗi `mysqli`

### Môi trường yêu cầu trên XAMPP
- **Apache** (Web server)
- **MySQL** (Database server)
- **PHP extension `mysqli`** (Bắt buộc để kết nối MySQL)
- Database: `notezy` (Bảng mã `utf8mb4_general_ci`)

### Khắc phục lỗi: `PHP Fatal error: Uncaught Error: Class "mysqli" not found`
Nếu môi trường PHP chưa bật extension `mysqli`:
1. **Trên Windows XAMPP:**
   - Mở **XAMPP Control Panel**.
   - Tại dòng **Apache**, nhấn nút **Config** → chọn **PHP (php.ini)**.
   - Tìm dòng: `;extension=mysqli`
   - Xóa dấu chấm phẩy `;` ở đầu dòng thành: `extension=mysqli`
   - Lưu file `php.ini`, sau đó nhấn **Stop** và **Start** lại dịch vụ Apache.
2. **Trên Linux / Docker:**
   ```bash
   sudo apt-get update && sudo apt-get install -y php-mysql
   sudo service apache2 restart
   ```

### Thứ tự Import Database chuẩn
Để đảm bảo toàn vẹn dữ liệu và các khóa ngoại:
1. Mở phpMyAdmin (`http://localhost/phpmyadmin`).
2. Tạo CSDL mới tên là: `notezy`.
3. Import file theo đúng thứ tự:
   - **Bước 1:** Import file `note.sql` (Cấu trúc bảng gốc).
   - **Bước 2:** Import file `migrations.sql` (Các bản nâng cấp: kích hoạt tài khoản OTP, khóa PIN, chia sẻ ghi chú, AI, thời khóa biểu).

> 💡 **Trợ lý chẩn đoán thông minh tích hợp:** Nếu chưa bật `mysqli` hoặc chưa tạo CSDL, khi truy cập Notezy sẽ hiển thị ngay màn hình **Notezy System Diagnostic & Setup Assistant** với nút bấm **Tự động khởi tạo Database 1-Click**, giúp bạn không bao giờ gặp lỗi màn hình trắng 500.

---

## 🐳 3. Chạy qua Docker Compose

Nếu môi trường của bạn hỗ trợ Docker:
```bash
docker compose up -d --build
```
Hệ thống đã cấu hình đầy đủ 4 dịch vụ:
- **Web App:** `http://localhost:8080`
- **MySQL:** `localhost:3307` (Container MySQL 8.0, tự động chạy `note.sql` và `migrations.sql`)
- **phpMyAdmin:** `http://localhost:8081` (Đăng nhập: user `root`, pass `root_password`)
- **AI Agent:** `http://localhost:8765` (Python FastAPI service)

---

## 🧪 4. Chạy Kiểm Thử Tự Động (Automated Test Suite)

Chạy kịch bản kiểm tra toàn bộ luồng hệ thống từ CLI:
```bash
php test_system_flow.php
```
Kịch bản sẽ tự động xác minh:
1. Kết nối cơ sở dữ liệu.
2. Quy trình đăng ký & kích hoạt OTP (`activated = 0`, OTP hash bcrypt, thời hạn 5 phút).
3. Đăng nhập và xác thực.
4. Chia sẻ ghi chú (phân quyền Viewer/Editor).
5. Đồng bộ cộng tác, Presence, và Conflict Detection (ID 24).
6. Ranh giới bảo mật phân quyền (Editor không được phép xóa ghi chú của Owner).
