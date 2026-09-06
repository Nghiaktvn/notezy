# DEPLOYMENT.md — Notezy

## Cách chạy dự án

---

### 🚀 TRIỂN KHAI CLOUD (RAILWAY / RENDER) — [ƯU TIÊN 1: LẤY 0.5 ĐIỂM]

Dự án đã được cấu hình sẵn `Dockerfile`, `railway.json`, `render.yaml`, `entrypoint.sh` và cơ chế **Tự động khởi tạo Database** khi kết nối.

#### Cách 1: Triển khai lên Railway (Khuyên dùng — 2 phút có URL trực tiếp)
1. Truy cập [railway.app](https://railway.app) và đăng nhập bằng GitHub.
2. Nhấn **+ New Project** ➔ Chọn **Deploy from GitHub repo** ➔ Chọn repo `notezy`.
3. Thêm Database MySQL:
   - Trong cùng Project, nhấn **+ New** ➔ Chọn **Database** ➔ Chọn **Add MySQL**.
   - Notezy đã được cấu hình tự động nhận diện các biến `MYSQLHOST`, `MYSQLPORT`, `MYSQLUSER`, `MYSQLPASSWORD`, `MYSQLDATABASE` do Railway cấp mà **không cần chỉnh sửa code**!
4. Kết nối Web Service với MySQL:
   - Trong giao diện Web Service, vào tab **Variables** ➔ Nhấn **Add Reference** chọn các biến của service MySQL.
   - Nhấn **Settings** ➔ Tại mục **Networking**, nhấn **Generate Domain**.
   - Bạn sẽ nhận được URL công khai dạng:
     ```
     https://notezy-production-xxxx.up.railway.app
     ```
5. Truy cập URL và kiểm tra:
   - ✅ Mở trang web: `https://notezy-xxxx.up.railway.app`
   - ✅ Đăng ký / Đăng nhập tài khoản mới
   - ✅ Tạo ghi chú có đính kèm ảnh (Attach Image)
   - ✅ Quản lý nhãn: `/labels.php`
   - ✅ Quản trị người dùng: `/admin_users.php` (hoặc `/admin/users.php`)
   - ✅ Demo cộng tác thời gian thực: `/collab_demo.php`

#### Cách 2: Triển khai lên Render
1. Truy cập [render.com](https://render.com) và đăng nhập.
2. Nhấn **New +** ➔ Chọn **Web Service** ➔ Kết nối với repo Notezy.
3. Cấu hình:
   - **Environment**: Docker (Render sẽ tự động đọc file `Dockerfile` và `render.yaml`).
   - **Region**: Singapore.
   - **Plan**: Free.
4. Nhấn **Create Web Service**. Sau khi build xong, bạn sẽ nhận được URL dạng: `https://notezy-xxxx.onrender.com`.

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

### 🚀 Triển khai lên Production (Render, Railway, Fly.io, VPS Docker)

#### 1. Triển khai lên Railway (Khuyên dùng - Nhanh nhất có URL HTTPS công khai)
Railway hỗ trợ triển khai tự động từ GitHub repository và Dockerfile có sẵn:
1. Đăng nhập [Railway.app](https://railway.app/).
2. Tạo project mới: **New Project** -> **Deploy from GitHub repo** -> Chọn repo Notezy.
3. Thêm Database: Chọn **New** -> **Database** -> **Add MySQL**.
4. Vào service Database vừa tạo:
   - Tab **Variables**: Lưu lại các giá trị `MYSQLHOST`, `MYSQLPORT`, `MYSQLUSER`, `MYSQLPASSWORD`, `MYSQLDATABASE`.
   - Tab **Connect** hoặc dùng công cụ client kết nối vào để import schema: chạy `note.sql` rồi đến `migrations.sql`.
5. Vào service Web:
   - Trong tab **Variables**, thêm các biến môi trường sau:
     ```env
     DB_HOST=${{MySQL.MYSQLHOST}}
     DB_PORT=${{MySQL.MYSQLPORT}}
     DB_USER=${{MySQL.MYSQLUSER}}
     DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}
     DB_NAME=${{MySQL.MYSQLDATABASE}}
     APP_URL=https://${{RAILWAY_PUBLIC_DOMAIN}}
     MAIL_HOST=smtp.gmail.com
     MAIL_PORT=587
     MAIL_USERNAME=your_email@gmail.com
     MAIL_PASSWORD=your_app_password
     MAIL_FROM=your_email@gmail.com
     MAIL_FROM_NAME=Notezy
     AI_AGENT_URL=http://localhost:8765
     AI_AGENT_SHARED_SECRET=your_long_random_secret
     LLM_PROVIDER=gemini
     GEMINI_API_KEY=your_gemini_api_key
     ```
   - Trong tab **Settings**: Chọn **Generate Domain** để nhận đường dẫn HTTPS công khai (ví dụ: `https://notezy-production.up.railway.app`).

---

#### 2. Triển khai lên Render (Render.com)
1. Đăng nhập [Render.com](https://render.com/).
2. **Tạo Database**: Chọn **New** -> **PostgreSQL / MySQL** (hoặc tạo MySQL instance).
3. **Tạo Web Service**:
   - Chọn **New** -> **Web Service** -> Kết nối GitHub repository Notezy.
   - Environment: **Docker**.
   - Thêm các biến môi trường trong phần **Environment Variables** theo mẫu `.env.example`.
4. Render sẽ tự động build image từ `Dockerfile` và cấp phát URL HTTPS công khai dạng `https://notezy-xxxx.onrender.com`.

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

