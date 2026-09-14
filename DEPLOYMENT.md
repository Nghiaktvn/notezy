# DEPLOYMENT.md — Notezy

## Cách chạy dự án

---

### 🚀 DEMO TRỰC TIẾP BẰNG GITHUB CODESPACES

Repository đã có `.devcontainer/devcontainer.json`; Codespaces tự tạo môi
trường Docker và forward URL HTTPS cho các port của ứng dụng.

1. Truy cập repository trên GitHub → **Code** → **Codespaces** → **Create
   codespace on main**.
2. Chờ Codespaces hoàn tất. Nó tự sinh các secret local trong `.env` (không
   commit) và khởi động stack gọn bằng `docker compose --profile tools up -d`.
3. Mở tab **Ports**, đặt port `8080` thành Public nếu cần người chấm truy cập,
   rồi mở URL HTTPS do GitHub cấp. Đây là URL demo trực tiếp của Notezy.
4. Dùng port `8081` cho phpMyAdmin (giữ Private), `8765` để kiểm tra AI và
   `8766` cho WebSocket cộng tác.
5. Trước khi demo, xác nhận `http://localhost:8080/health.php` trả về HTTP 200
   trong Codespace.

---

### 🖥️ Chạy bằng XAMPP (local development)

**Yêu cầu:** XAMPP với PHP 8.2+, MySQL/MariaDB

1. **Copy project** vào thư mục `htdocs`:
   ```
   C:\xampp\htdocs\notezy\
   ```

2. **Tạo file `.env`** từ mẫu `.env.example`:
   ```
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=notezy
   DB_USER=root
   DB_PASSWORD=
   APP_URL=http://localhost/notezy
   MAIL_HOST=smtp.gmail.com
   MAIL_PORT=587
   MAIL_USERNAME=your_email@gmail.com
   MAIL_PASSWORD=your_app_password
   MAIL_FROM=your_email@gmail.com
   MAIL_FROM_NAME="Admin Notezy"
   ```

3. **Cấu hình PHP extension mysqli (Bắt buộc):**
   - Mở XAMPP Control Panel → Tại Apache nhấn **Config** → **PHP (php.ini)**.
   - Bỏ dấu `;` trước dòng `extension=mysqli` (thành `extension=mysqli`) rồi lưu lại.
   - Nhấn **Stop** và **Start** lại Apache.

4. **Import database:**
   - Mở phpMyAdmin (`http://localhost/phpmyadmin`) → Tạo database `notezy`.
   - Chọn `notezy` → Import `note.sql` trước, sau đó import tiếp `migrations.sql`.

5. **Truy cập ứng dụng:**
   - Trang chủ: `http://localhost/notezy/index.php`
   - **Demo cộng tác thời gian thực 2 cửa sổ (Realtime Collaboration):** `http://localhost/notezy/collab_demo.php`

---

### 🐳 Chạy bằng Docker

**Yêu cầu:** Docker Desktop

1. **Tạo file `.env`** (copy từ `.env.example` và điền thông tin thật):
   ```bash
   cp .env.example .env
   ```

2. **Build và Start:**
   ```bash
   docker compose up -d --build
   ```

3. **Chờ healthcheck DB sẵn sàng** (khoảng 30 giây), sau đó truy cập:
   - Web app: `http://localhost:8080`
   - Database (client dòng lệnh / MySQL Workbench): `localhost:3307` (dùng port 3307 để tránh xung đột với XAMPP)
   - **phpMyAdmin (giao diện web quản lý DB): `http://localhost:8081`** — đăng nhập bằng
     `DB_USER` / `DB_PASSWORD` trong `.env` (mặc định `root` / `root_password`).
   - Email OTP được gửi qua **Gmail thật** (không còn dùng Mailpit). Cấu hình trong `.env`:
   > ```
   > MAIL_HOST=smtp.gmail.com
   > MAIL_PORT=587
   > MAIL_USERNAME=your_email@gmail.com
   > MAIL_PASSWORD=your_16_char_app_password   # Mật khẩu ứng dụng Gmail, KHÔNG phải mật khẩu đăng nhập thường
   > MAIL_FROM=your_email@gmail.com
   > MAIL_FROM_NAME="Notezy"
   > ```
   > Mật khẩu ứng dụng lấy tại: Google Account → Security → 2-Step Verification → App passwords.
   > Sau khi sửa `.env`, chạy lại `docker compose up -d --build` để áp dụng.

   > ⚠️ **Nếu bạn đã từng chạy `docker compose up` trước đây và volume `db_data`
   > đã tồn tại**, các file `note.sql` / `migrations.sql` trong
   > `docker-entrypoint-initdb.d/` **sẽ KHÔNG chạy lại** — MySQL chỉ import
   > chúng khi tạo volume rỗng lần đầu. Đây là lý do phổ biến nhất khiến đăng ký
   > báo lỗi dù code không sai: bảng `users` trong volume cũ thiếu các cột OTP
   > (`activation_otp_hash`, …). Có 2 cách xử lý:
   > 1. **Xoá sạch để làm lại từ đầu (mất dữ liệu cũ):**
   >    `docker compose down -v && docker compose up -d --build`
   > 2. **Giữ dữ liệu cũ, chỉ vá schema:** mở phpMyAdmin (`http://localhost:8081`)
   >    → chọn database `notezy` → tab **SQL** → dán toàn bộ nội dung
   >    `migrations.sql` (đã được sửa lại idempotent, chạy lại bao nhiêu lần
   >    cũng an toàn) → Go.

4. **Xem log:**
   ```bash
   docker compose logs -f web
   docker compose logs -f db
   ```

5. **Dừng:**
   ```bash
   docker compose down
   ```

6. **Dừng và xóa data:**
   ```bash
   docker compose down -v
   ```

---

### ⚙️ Cấu trúc thư mục quan trọng

```
notezy/
├── config/
│   └── database.php    ← Single source of truth cho DB connection
├── api/                ← REST API cho frontend React (Vite)
│   ├── db.php          ← Shim → config/database.php + CORS
│   ├── login.php
│   ├── register.php
│   ├── notes.php
│   ├── labels.php
│   ├── upload.php
│   └── password.php
├── uploads/            ← Ảnh upload (được bảo vệ, không chạy PHP)
├── .env                ← Credentials (KHÔNG commit)
├── .env.example        ← Mẫu cấu hình (an toàn để commit)
├── .htaccess           ← Bảo vệ .env, ngăn PHP trong uploads/
├── migrations.sql      ← Schema updates (idempotent)
├── note.sql            ← Schema khởi tạo
└── ...
```

---

### 🔒 Bảo mật

| Hạng mục | Trạng thái |
|----------|-----------|
| Password lưu dạng `password_hash()` | ✅ |
| Không lưu plaintext password | ✅ |
| SMTP credentials từ `.env` | ✅ |
| DB credentials từ `.env` | ✅ |
| SQL Injection → Prepared Statements | ✅ |
| XSS → `htmlspecialchars()` | ✅ |
| Session Fixation → `session_regenerate_id(true)` | ✅ |
| `.env` không accessible qua web | ✅ (`.htaccess`) |
| PHP không chạy trong `uploads/` | ✅ (`.htaccess`) |
| CORS restricted (không dùng `*`) | ✅ |
| Image upload: MIME + extension + size validation | ✅ |

---

### 📝 Ghi chú SMTP

Dự án dùng Gmail SMTP với App Password (không phải mật khẩu thường). Để tạo App Password:
1. Vào Google Account → Security → 2-Step Verification (phải bật)
2. Tìm "App passwords" → Tạo password cho "Mail"
3. Điền vào `.env`:  `MAIL_PASSWORD=xxxx xxxx xxxx xxxx`

---

### 🚀 Demo trực tiếp từ GitHub Codespaces

1. Mở repository Notezy trên GitHub và tạo Codespace từ nhánh `main`.
2. Chờ `.devcontainer` khởi động. Docker Compose tự chạy stack gọn gồm Web,
   MySQL, phpMyAdmin, AI Agent và WebSocket.
3. Trong tab **Ports**, mở port `8080`. GitHub cung cấp URL HTTPS để demo
   trực tiếp. Đặt visibility thành Public chỉ trong thời gian chấm bài, sau đó
   trả về Private.
4. Kiểm tra URL `/health.php` trước khi đưa cho giảng viên. Không commit file
   `.env` hoặc bất kỳ API key nào vào repository.

---

#### 3. Triển khai lên VPS (Ubuntu/Debian) bằng Docker Compose
1. Cài đặt Docker và Docker Compose trên VPS:
   ```bash
   sudo apt update && sudo apt install -y docker.io docker-compose-plugin
   ```
2. Clone repo về VPS:
   ```bash
   git clone <repo-url> /opt/notezy
   cd /opt/notezy
   ```
3. Tạo file `.env` từ `.env.example` và thiết lập mật khẩu mạnh:
   ```bash
   cp .env.example .env
   nano .env
   ```
4. Khởi chạy toàn bộ hệ thống bằng Docker Compose:
   ```bash
   docker compose up -d --build
   ```
5. Cấu hình Nginx Reverse Proxy với Let's Encrypt SSL trỏ domain về `http://127.0.0.1:8080`:
   ```nginx
   server {
       server_name notezy.yourdomain.com;
       location / {
           proxy_pass http://127.0.0.1:8080;
           proxy_set_header Host $host;
           proxy_set_header X-Real-IP $remote_addr;
           proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
           proxy_set_header X-Forwarded-Proto $scheme;
       }
   }
   ```

---

### ✅ Bảng Checklist Kiểm Thử Toàn Diện Sau Khi Deploy (Rubric 11/11)

| STT | Tính năng kiểm tra | Hành động thực hiện | Kết quả mong đợi |
|-----|--------------------|---------------------|------------------|
| 1 | **Landing page** | Mở URL public của Notezy | Giao diện hiện đúng logo, responsive, không lỗi CSS/JS |
| 2 | **Register** | Đăng ký tài khoản mới kèm email thật | Nhận mã OTP kích hoạt tài khoản qua Gmail thành công |
| 3 | **Login / Logout** | Đăng nhập và đăng xuất | Tạo phiên bảo mật, session_regenerate_id, điều hướng chuẩn |
| 4 | **Create note** | Bấm Tạo ghi chú mới | Ghi chú xuất hiện ngay trên trang chủ |
| 5 | **Edit note** | Chỉnh sửa tiêu đề/nội dung/màu sắc | Dữ liệu cập nhật chính xác, không mất dữ liệu cũ |
| 6 | **Delete note** | Xóa ghi chú | Hộp thoại xác nhận SweetAlert2, ghi chú biến mất |
| 7 | **Search** | Tìm kiếm theo tiêu đề/nội dung/nhãn | Kết quả lọc trực tiếp (Live search) chính xác |
| 8 | **Sharing** | Chia sẻ ghi chú với User khác | Phân quyền rõ ràng (Read-only vs Write) |
| 9 | **Password-protected note** | Bật mật khẩu cho ghi chú | Nội dung bị che; mở khóa đúng pass mới xem được |
| 10 | **AI Assistant** | Nhắn tin yêu cầu AI tóm tắt hoặc tạo ghi chú | AI phản hồi thông minh, thực thi công việc chuẩn xác |
| 11 | **Offline & Collaboration** | Ngắt mạng (DevTools Offline) / Polling đồng thời | IndexedDB lưu dữ liệu offline, auto-sync khi online trở lại; Realtime báo Saved / Conflict |
