# Notezy

[![Open in GitHub Codespaces](https://github.com/codespaces/badge.svg)](https://github.com/codespaces/new?hide_repo_select=true&ref=main&repo=1358846417)

Ứng dụng ghi chú (notes) full-stack: PHP + MySQL cho backend/web truyền thống,
kèm REST API (`api/`) phục vụ frontend React (Vite) trong thư mục `frontend/`.

## Chạy nhanh trên Windows bằng PowerShell và Docker

Mở **Docker Desktop** và đợi trạng thái **Engine running**. Mở PowerShell tại thư mục
chứa `docker-compose.yml` (trên máy tác giả là `D:\notezy_fixed_updated`). Nếu chưa
có mã nguồn, chạy `git clone https://github.com/Nghiaktvn/notezy.git` rồi
`Set-Location .\notezy`. Sau đó chạy lần lượt:

```powershell
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
docker compose up -d --build --wait
docker compose exec -T web php scripts/seed_demo_accounts.php
Start-Process 'http://localhost:8080/'
```

Đăng nhập demo bằng `user1` hoặc `user2`, mật khẩu `Password123!`. Hai tài khoản
được kích hoạt sẵn. Muốn có ghi chú mẫu để trình bày, chạy thêm
`docker compose exec -T web php scripts/seed_demo_showcase.php` **một lần**. Lệnh
này tạo lại các ghi chú demo cùng tên của `user1` nếu chạy lặp lại. Xem mục 3
để cấu hình email OTP, kiểm tra hệ thống và xử lý lỗi khởi động.

### Nén mã nguồn để nộp E-learning (Windows PowerShell)

Từ thư mục dự án đã clone bằng Git, chạy:

```powershell
python scripts/package_submission.py
Get-Item .\submission\Notezy-Docker-Source.zip | Select-Object Name, @{Name='MB';Expression={[math]::Round($_.Length / 1MB, 2)}}
```

Nộp **một file** `submission/Notezy-Docker-Source.zip`. Script kiểm tra ZIP không
hỏng và nhỏ hơn 100 MB. ZIP chứa mã nguồn PHP, SQL MySQL, frontend, assets,
Docker Compose, dữ liệu demo và README; bỏ `.git`, `.env` chứa mật khẩu,
`node_modules`, cache, tệp tạm và Supabase/PostgreSQL không được ứng dụng Docker
sử dụng. Khi giải nén trên máy khác, chạy các lệnh ở đầu README để khởi động.

### Kiến trúc hướng dịch vụ

Chế độ Docker gọn gồm bốn container độc lập: `web` phục vụ PHP/API, `db` lưu dữ liệu,
`ai_agent` xử lý trợ lý AI và `collab_ws` xử lý kết nối cộng tác thời gian thực.
Các thành phần giao tiếp qua mạng nội bộ Compose và có health check. Mã nguồn
cho từng dịch vụ nghiệp vụ (auth, user, note, file, AI, premium, collaboration),
API gateway, Redis và outbox worker nằm trong `services/`; cấu hình mở rộng là
`docker-compose.services.yml`. Kiểm tra danh sách dịch vụ bằng PowerShell:

```powershell
docker compose -f docker-compose.yml -f docker-compose.services.yml config --services
```

Chế độ mở rộng giữ nguyên giao diện PHP hiện có và thêm API gateway ở cổng 8088.
Các trang PHP cũ vẫn được xử lý bởi `web`; các API qua gateway được chuyển tới
service tương ứng. Chỉ bật chế độ mở rộng khi cần trình bày kiến trúc và máy có
đủ tài nguyên để tải, dựng thêm các image:

```powershell
docker compose -f docker-compose.yml -f docker-compose.services.yml up -d --build --wait
docker compose -f docker-compose.yml -f docker-compose.services.yml ps
```

## Triển khai chỉ bằng GitHub Codespaces

GitHub Pages không chạy PHP/MySQL. Repository này cung cấp cấu hình Codespaces
để chạy nguyên cụm Docker và tạo URL HTTPS thuộc miền GitHub:

1. Mở repository trên GitHub, chọn **Code → Codespaces → Create codespace on main**.
2. Chờ các container healthy và mở port **8080 — Notezy Web**.
3. Trong tab **Ports**, đặt port `8080` và `8766` thành **Public**. Giữ port
   `8081` (phpMyAdmin) ở chế độ Private để không công khai công cụ quản trị. URL ứng dụng có
   dạng `https://<codespace>-8080.app.github.dev`.
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

### Kiểm tra trên 2 trình duyệt riêng biệt
1. **Trình duyệt 1 (Chrome thường):** Đăng nhập tài khoản A, tạo một ghi chú và chia sẻ cho tài khoản B với quyền **"Có thể chỉnh sửa (Editor)"** tại mục Chia sẻ ghi chú.
2. **Trình duyệt 2 (Chrome Ẩn danh / Firefox):** Đăng nhập tài khoản B, vào mở ghi chú được chia sẻ (`edit_note.php?id=...`).
3. Cùng soạn thảo và theo dõi kết quả đồng bộ hoặc cảnh báo xung đột trên cả hai phiên.

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
2. **Trên Docker:** image trong `Dockerfile` đã cài `mysqli`; nếu container lỗi, xem `docker compose logs --tail 100 web db` trong PowerShell.

### Thứ tự Import Database chuẩn
Để đảm bảo toàn vẹn dữ liệu và các khóa ngoại:
1. Mở phpMyAdmin (`http://localhost/phpmyadmin`).
2. Tạo CSDL mới tên là: `notezy`.
3. Import file theo đúng thứ tự:
   - **Bước 1:** Import file `note.sql` (Cấu trúc bảng gốc).
   - **Bước 2:** Import file `migrations.sql` (Các bản nâng cấp: kích hoạt tài khoản OTP, khóa PIN, chia sẻ ghi chú, AI, thời khóa biểu).

> 💡 **Trợ lý chẩn đoán thông minh tích hợp:** Nếu chưa bật `mysqli` hoặc chưa tạo CSDL, khi truy cập Notezy sẽ hiển thị ngay màn hình **Notezy System Diagnostic & Setup Assistant** với nút bấm **Tự động khởi tạo Database 1-Click**, giúp bạn không bao giờ gặp lỗi màn hình trắng 500.

---

## 🐳 3. Chạy và demo bằng Docker Desktop trên Windows PowerShell

### 3.1. Chuẩn bị

1. Cài và mở **Docker Desktop**, chọn Linux containers/WSL 2 và chờ trạng thái *Engine running*.
2. Mở **Windows PowerShell** tại thư mục gốc dự án, nơi có `docker-compose.yml`. Nếu vừa clone từ GitHub, dùng thư mục `notezy`; đường dẫn dưới đây chỉ là ví dụ trên máy tác giả:

   ```powershell
   Set-Location "D:\notezy_fixed_updated"
   docker compose version
   ```

3. Nếu chưa có `.env`, tạo từ mẫu. File mẫu đủ để **chạy giao diện và hai tài khoản demo trên máy mới**; muốn thử email OTP thật hoặc AI thật thì mở file và điền thông tin riêng. Không đưa mật khẩu hoặc API key lên Git. Với máy đã chạy và có dữ liệu MySQL, giữ `DB_PASSWORD` hiện tại để không làm hỏng kết nối tới volume cũ.

   ```powershell
   if (-not (Test-Path -LiteralPath '.env')) { Copy-Item '.env.example' '.env' }
   ```

   Để gửi OTP bằng Gmail, mở `notepad .env` và đặt `MAIL_USERNAME` là email gửi, `MAIL_PASSWORD` là **mật khẩu ứng dụng Google**, không phải mật khẩu đăng nhập Gmail; đặt **cả** `MAIL_FROM_ADDRESS` và `MAIL_FROM` trùng `MAIL_USERNAME`. Thêm dòng `MAIL_FROM=...` vào `.env` nếu chưa có. Nếu dùng AI thật, cấu hình `LLM_PROVIDER=gemini` và `GEMINI_API_KEY`; nếu chưa có khóa, đặt `LLM_PROVIDER=mock` để demo giao diện AI. Thay các giá trị mẫu `JWT_SECRET` và `AI_AGENT_SHARED_SECRET` bằng chuỗi bí mật riêng trước khi chia sẻ bản chạy cho người khác. Sau khi sửa `.env` trên container đang chạy, dùng `docker compose up -d --no-deps --force-recreate web ai_agent` để các dịch vụ đọc giá trị mới.

### 3.2. Khởi động và kiểm tra

```powershell
docker compose --profile tools up -d --build --wait
docker compose --profile tools ps
(Invoke-WebRequest 'http://localhost:8080/health.php' -UseBasicParsing).StatusCode
Start-Process 'http://localhost:8080/'
```

Kết quả mong đợi: các dịch vụ chính `web`, `db`, `ai_agent`, `collab_ws` ở trạng thái `healthy`; lệnh kiểm tra HTTP trả `200`. Ứng dụng ở `http://localhost:8080/`, phpMyAdmin (profile `tools`) ở `http://localhost:8081/`, AI Agent ở `http://localhost:8765/`, WebSocket ở port `8766`. MySQL mở trên máy tại port `3307`; web kết nối nội bộ tới `db:3306`. Nếu port `8080` đã bị dùng, đóng ứng dụng chiếm port hoặc thêm `WEB_PORT=8082` vào `.env`, chạy lại Compose và mở `http://localhost:8082/`; khi thử liên kết OTP, đặt `APP_URL=http://localhost:8082` cùng lúc. Nếu đã có các container dịch vụ bổ sung từ `docker-compose.services.yml`, Compose có thể báo *orphan containers*; cảnh báo này không đồng nghĩa lần khởi động thất bại. Không chạy `--remove-orphans` khi vẫn cần các dịch vụ đó.

### 3.3. Tạo hai tài khoản và ghi chú mẫu

```powershell
docker compose exec -T web php scripts/seed_demo_accounts.php
docker compose exec -T web php scripts/seed_demo_showcase.php
```

Lệnh đầu tạo/kích hoạt hoặc đặt lại mật khẩu cho `user1` và `user2`, rồi phải in `Login user1: SUCCESS` và `Login user2: SUCCESS`. Cả hai dùng mật khẩu demo `Password123!`. Lệnh thứ hai tạo bốn ghi chú mẫu cho `user1` (ghim, ảnh, khóa, cộng tác) và chia sẻ ghi chú cộng tác cho `user2` với quyền sửa. **Chạy lại lệnh thứ hai sẽ xóa và tạo lại các ghi chú mẫu khớp tiêu đề demo của `user1`**; không dùng sau khi đã sửa các ghi chú mẫu và muốn giữ thay đổi. Mật khẩu riêng của ghi chú khóa mẫu là `123456`.

Đăng nhập `user1` trên Chrome thường, `user2` trên cửa sổ ẩn danh hoặc trình duyệt khác. Mỗi cửa sổ cần phiên đăng nhập riêng. Khi thử đăng ký tài khoản mới hoặc quên mật khẩu, dùng một email bạn thật sự đọc được; tài khoản `user1`/`user2` đã kích hoạt sẵn nên không có OTP kích hoạt để nhận.

### 3.4. Kiểm tra dịch vụ từ PowerShell

```powershell
docker compose ps
(Invoke-WebRequest 'http://localhost:8080/health.php' -UseBasicParsing).StatusCode
docker compose logs --tail 100 web db ai_agent collab_ws
```

Các lệnh trên xác nhận container và endpoint đang chạy. Hãy thực hiện các thao tác giao diện ở mục 4 để kiểm tra chức năng; không suy ra điểm 32/32 chỉ từ trạng thái container. Không cần cài PHP trên Windows.

---

## 4. Thứ tự demo 32 tiêu chí trong rubric

Bảng rubric chính thức nằm trong mục IV của `503073-FinalProject-V1.pdf`. Mỗi bước dưới đây có thao tác và kết quả cần quan sát; nếu kết quả khác, ghi lại thông báo/lỗi và kiểm tra log trước khi chuyển sang bước tiếp theo. Dùng dữ liệu mẫu `user1`/`user2` cho các bước ghi chú. Với bước đăng ký và quên mật khẩu, dùng **tài khoản thử riêng** và email nhận thư thật, tránh đổi mật khẩu của hai tài khoản demo đang dùng.

### 4.1. Tài khoản (ID 1–8)

1. **Đăng ký (1):** Tại `http://localhost:8080/`, bấm **Bắt đầu**. Trang `register.php` phải mở. Nhập tên tài khoản mới (3–40 ký tự chữ/số/gạch dưới), tên hiển thị, email nhận thư, mật khẩu tối thiểu 6 ký tự và xác nhận. Bấm **Tạo tài khoản**. Kết quả: chuyển tới trang xác thực; không được vào dashboard khi chưa kích hoạt. Thử đăng ký trùng tên/email và xác nhận có lỗi rõ ràng.
2. **Kích hoạt (2):** Mở thư Notezy trong Inbox hoặc Spam, lấy OTP **6 số**; nhập tại `verify_activation.php`. Mã có hiệu lực 5 phút. Thử một mã sai để xem thông báo, rồi nhập mã đúng hoặc bấm **Gửi lại OTP** (chờ 60 giây giữa các lần gửi). Kết quả: báo kích hoạt thành công, OTP cũ không dùng lại được.
3. **Đăng nhập/đăng xuất (3):** Tại trang ngoài bấm **Đăng nhập**, nhập tài khoản vừa kích hoạt; kiểm tra vào `index_notezy.php`. Bấm avatar góc trên → **Đăng xuất**; kiểm tra trở về trang ngoài và không mở được dashboard khi chưa đăng nhập lại. Với phần demo còn lại, dùng `user1` và `user2` đã seed.
4. **Quên mật khẩu (4):** Trên form đăng nhập bấm **Quên mật khẩu?**. Nhập **đúng tên tài khoản và email** của tài khoản thử. Kiểm tra OTP nhận qua email (15 phút), nút **Gửi lại mã OTP** sau 60 giây và thông báo mã sai. Nhập mã đúng, đặt mật khẩu mới tối thiểu 6 ký tự và xác nhận trùng nhau. Kết quả: trở về trang ngoài; đăng nhập lại bằng mật khẩu mới thành công. Không nhập mật khẩu mới vào tài liệu hay ảnh chụp.
5. **Xem hồ sơ/avatar (5):** Đăng nhập `user1`, bấm avatar → **Xem trang cá nhân**. Kiểm tra tên tài khoản, họ tên, email và avatar đúng với người đang đăng nhập.
6. **Sửa hồ sơ/avatar (6):** Trong `account.php`, bấm **Sửa** một trường họ/tên, lưu và tải lại. Bấm **Upload Avatar**, chọn ảnh thử, kiểm tra avatar mới xuất hiện ở hồ sơ và menu. Dùng ảnh không nhạy cảm.
7. **Đổi mật khẩu khi đã đăng nhập (7):** Trong trang hồ sơ, bấm **Đổi mật khẩu** để mở `update_password.php`. Nhập mật khẩu hiện tại và mật khẩu mới hai lần. Thử mật khẩu hiện tại sai, sau đó đổi thành công; đăng xuất và đăng nhập lại để kiểm tra mật khẩu cũ không còn dùng được.
8. **Tùy chọn (8):** Trong `account.php`, đổi **Giao diện** Sáng/Tối và **Ngôn ngữ**, bấm **Lưu tùy chọn**; tải lại và kiểm tra lựa chọn được giữ.

### 4.2. Ghi chú cơ bản (ID 9–22)

9. **List view (9):** Ở dashboard `user1`, bấm **List View**; kiểm tra các ghi chú thành hàng danh sách, vẫn mở và thao tác được.
10. **Grid view (10):** Bấm **Grid View**; kiểm tra các ghi chú thành thẻ. Ghi chú ghim mẫu nên ở đầu.
11. **Tạo (11):** Bấm **Tạo ghi chú**, nhập tiêu đề `DEMO RUBRIC`, nội dung ngắn và bấm **Lưu ghi chú**. Quay về dashboard, kiểm tra ghi chú mới xuất hiện. Ghi lại ID nếu cần kiểm tra các bước sau.
12. **Sửa (12):** Mở **Sửa nội dung sổ** của `DEMO RUBRIC`, đổi tiêu đề/nội dung. Tải lại và kiểm tra nội dung mới còn đó.
13. **Xóa (13):** Tạo một ghi chú thử khác tên `DEMO XOA`, bấm **Xóa sổ** trên đúng ghi chú đó, đọc hộp xác nhận rồi xác nhận xóa. Kiểm tra ghi chú biến mất. Không xóa ghi chú mẫu hoặc ghi chú cá nhân.
14. **Tự lưu (14):** Mở ghi chú `DEMO RUBRIC` để sửa, gõ thêm một câu rồi chờ trạng thái lưu; tải lại trang. Kết quả: nội dung vừa gõ vẫn còn mà không cần bấm lưu thủ công.
15. **Ảnh/video (15):** Trong form sửa ghi chú, chọn một ảnh nhỏ và một video thử theo giới hạn hiển thị. Lưu, tải lại, kiểm tra ảnh/video hiển thị và truy cập được. Nếu chỉ có ô ảnh ở form nhanh, dùng trang **Sửa nội dung sổ** để kiểm tra video/tệp.
16. **Tệp khác (16):** Trong trang sửa, chọn một PDF hoặc DOCX thử ở **Tệp đính kèm**; lưu, tải lại, tải xuống và mở được tệp. Thử với `user2` không có quyền để kiểm tra tệp không bị lộ.
17. **Ghim (17):** Bấm **Ghim hoặc bỏ ghim sổ** trên `DEMO RUBRIC`; tải lại và kiểm tra nó ở đầu danh sách, bấm lần nữa để bỏ ghim.
18. **Biểu tượng (18):** So sánh thẻ ghi chú ghim, khóa và đang chia sẻ trong dữ liệu mẫu. Kiểm tra dấu ghim/khóa/chia sẻ đúng trạng thái thực của từng ghi chú.
19. **Tìm kiếm (19):** Dùng **Tìm kiếm ghi chú**, tìm `DEMO RUBRIC`, tìm một cụm trong nội dung, rồi tìm chuỗi không tồn tại. Kiểm tra đúng kết quả và trạng thái rỗng.
20. **Nhãn (20):** Bấm **Quản lý nhãn**, thêm nhãn `Demo`, đổi tên thành `Demo mới`, kiểm tra danh sách, rồi xóa nhãn thử sau khi đã gỡ nó khỏi ghi chú.
21. **Gắn nhãn (21):** Gắn `Demo mới` vào `DEMO RUBRIC`, lưu và tải lại; nhãn phải hiện trên thẻ. Thử bỏ nhãn rồi kiểm tra lại.
22. **Lọc nhãn (22):** Chọn nhãn `Demo mới` trên thanh lọc; chỉ ghi chú mang nhãn này được hiển thị. Chọn **Tất cả** để khôi phục danh sách.

### 4.3. Bảo vệ, chia sẻ, cộng tác và AI (ID 23–28)

23. **Bật/tắt mật khẩu ghi chú (23):** Trên ghi chú thử, bấm **Đặt hoặc đổi mật khẩu**, nhập mật khẩu mới và xác nhận hai lần. Kiểm tra ghi chú bị che khi mở lại; để tắt, nhập đúng mật khẩu hiện tại và chọn gỡ bảo vệ. Dữ liệu mẫu có ghi chú khóa với mật khẩu `123456` để trình bày mở khóa.
24. **Đổi mật khẩu ghi chú (24):** Thử mở khóa bằng mật khẩu sai, sau đó đúng; đổi sang mật khẩu mới bằng cách nhập mật khẩu cũ và mật khẩu mới hai lần. Kiểm tra mật khẩu cũ không còn mở được, mật khẩu mới mở được; sau demo có thể gỡ khóa trên ghi chú thử.
25. **Chia sẻ/nhận (25):** Ở `user1`, bấm **Chia sẻ sổ** của ghi chú thử, nhập email của `user2` (`user2@example.com`), chọn Viewer/đọc. Ở cửa sổ `user2`, kiểm tra **Được chia sẻ với tôi**; thử sửa và xác nhận bị chặn. Ở `user1`, đổi quyền sang Editor/ghi; ở `user2`, thử sửa lại. Kiểm tra danh sách người nhận và quyền, rồi thu hồi quyền sau demo nếu đó là ghi chú thử.
26. **Cộng tác thời gian thực (26):** Dùng ghi chú **👥 Ghi chú Cộng tác thời gian thực** đã seed, mở `themghichu.php?id=...` trong hai cửa sổ: `user1` và `user2`. Gõ ở A, quan sát B cập nhật mà không tải lại; thử gõ cùng lúc để xem presence, typing và cảnh báo xung đột.
27. **AI tóm tắt (27):** Mở một ghi chú có vài đoạn nội dung, bấm **Hỏi trợ lý AI** và yêu cầu “Tóm tắt ghi chú này”. Kiểm tra tóm tắt bám đúng nội dung, không chỉ là câu chào. Nếu muốn chứng minh LLM thật, kiểm tra `LLM_PROVIDER` và khóa API hợp lệ trong `.env` trước demo.
28. **AI hỏi đáp (28):** Hỏi AI một chi tiết chỉ có trong ghi chú đã mở, ví dụ “Ghi chú này đặt hạn vào ngày nào?”. So sánh câu trả lời với nội dung thật; thử câu hỏi ngoài dữ liệu để kiểm tra phản hồi khi không biết.

### 4.4. Chất lượng và triển khai (ID 29–32)

29. **UI/UX (29):** Trình diễn cả trang ngoài, đăng ký, dashboard, trạng thái chưa có ghi chú, thông báo thành công/lỗi, thao tác bàn phím và nhãn form. Tiêu chí này do giảng viên đánh giá trực tiếp.
30. **Responsive (30):** Mở DevTools của Chrome (`F12`), bật biểu tượng thiết bị (`Ctrl+Shift+M`), thử kích thước điện thoại, tablet và desktop tại trang ngoài, dashboard và form sửa. Kiểm tra không tràn ngang, nút đủ dễ chạm và menu vẫn dùng được.
31. **Offline (31):** Khi đã mở dashboard và ghi chú, vào DevTools → **Network** → **Offline**; thử xem ghi chú đã lưu và sửa/tạo ghi chú thử. Chuyển lại **Online**, chờ trạng thái đồng bộ rồi tải lại để đối chiếu dữ liệu. Không dùng `docker compose stop` để giả lập mất mạng vì nó tắt cả server và làm sai kịch bản PWA.
32. **Triển khai online (32):** Bản ZIP này chạy được bằng Docker Compose trên máy cục bộ. Để đạt mức điểm tối đa theo rubric, cần triển khai ứng dụng thực sự lên một URL công khai có HTTPS, kiểm tra hiệu năng, khả năng mở rộng và thời gian hoạt động. Chỉ chạy `localhost` hoặc có cấu hình Docker chưa đủ chứng minh tiêu chí này.

Sau demo, nếu ghi chú `DEMO RUBRIC` hoặc nhãn thử còn tồn tại, dọn chúng bằng UI sau khi đối chiếu đúng tiêu đề. Để dừng container mà giữ dữ liệu, dùng:

```powershell
docker compose --profile tools stop
```

Không dùng `docker compose down -v` trừ khi chủ động muốn xóa toàn bộ dữ liệu trong Docker volumes. Các mục chưa kiểm tra trực tiếp trên môi trường demo không được xem là đã đạt điểm tối đa.
