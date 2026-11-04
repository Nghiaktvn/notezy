# Hướng dẫn chạy và trình diễn Notezy

Cập nhật ngày 05/11/2026. Dành cho bản trên nhánh `codex/fix-note-access-and-free-deploy`.

## 1. Chọn cách chạy trước khi bắt đầu

| Cách chạy | Mở ở đâu? | Máy cá nhân cần bật gì? | Dữ liệu nằm ở đâu? |
| --- | --- | --- | --- |
| Bản công khai | https://notezy-jrq0.onrender.com/ | Chỉ trình duyệt và mạng | MySQL trên Aiven |
| Bản localhost | http://localhost:8080/ | Docker Desktop và các container của dự án | Docker volume trên máy |

**Muốn demo bản công khai thì không cần mở source hoặc Docker.** Tắt laptop hay thoát Docker không làm tắt website trên Render. Đóng các tab Render/Aiven cũng không tắt dịch vụ. Dữ liệu localhost và Aiven là hai database riêng; tạo ghi chú ở một nơi không tự xuất hiện ở nơi kia.

Để máy nhẹ hơn, dừng container Docker tại máy. Không cần xóa hay tắt MySQL Aiven chỉ để giảm tải cho laptop.

## 2. Tài khoản trình diễn và quyền hạn

| Tài khoản | Mật khẩu localhost theo `.env.demo.example` | Vai trò |
| --- | --- | --- |
| `user1` | `Password123!` | Người dùng thường |
| `user2` | `Password123!` | Người dùng thường |
| `admin` | `AdminDemo123!` | Quản trị viên |

Trên Render, `user1` và `user2` dùng mật khẩu như trên. **Mật khẩu `admin` trên Render được cấp riêng trong cuộc trò chuyện khi cấu hình, không đăng công khai trong GitHub.** Mật khẩu quản trị trên đám mây được lưu ở biến `NOTEZY_DEMO_ADMIN_PASSWORD`. Giá trị localhost trong bảng không mặc nhiên là mật khẩu của bản công khai.

Các script khởi động không đặt lại mật khẩu tài khoản sẵn có. Nếu đã đổi mật khẩu qua giao diện, lần mở lại vẫn dùng mật khẩu đã đổi. Script quản trị không biến một tài khoản thường có sẵn tên `admin` thành quản trị viên.

### Người dùng thường làm được gì?

- Tạo, xem, sửa, xóa, lưu trữ/khôi phục, ghim và gắn nhãn ghi chú của mình.
- Đặt mã bảo mật sáu số hoặc mật khẩu cho ghi chú.
- Tạo và quản lý lịch cá nhân, nhắc việc; tính năng chia sẻ lịch có thể phụ thuộc gói trong ứng dụng.
- Chia sẻ ghi chú của mình cho tài khoản đã kích hoạt, chọn **Chỉ xem** hoặc **Được chỉnh sửa**; thu hồi quyền khi cần.
- Nhận ghi chú, trò chuyện, sử dụng trợ lý trong giới hạn của chế độ/gói hiện có.
- Không được mở trang quản trị, đổi vai trò hoặc quản lý tài khoản của người khác.

### Quản trị viên khác gì?

Quản trị viên có các chức năng cá nhân ở trên và truy cập `/admin_users.php` để:

- Xem danh sách tài khoản, thư điện tử, vai trò, trạng thái kích hoạt và chỉ báo trực tuyến dựa trên hoạt động gần đây.
- Thêm tài khoản người dùng hoặc quản trị viên.
- Sửa thông tin, vai trò, trạng thái kích hoạt; đặt mật khẩu mới khi có nhu cầu quản trị.
- Xóa tài khoản và dữ liệu liên quan. Chỉ trình diễn xóa trên tài khoản thử do mình vừa tạo.
- Trò chuyện hoặc chia sẻ **ghi chú thuộc sở hữu của mình** cho một người trong danh sách.

Quản trị viên **không tự động có quyền đọc hoặc sửa mọi ghi chú riêng tư** qua API ghi chú. Quyền nội dung vẫn dựa trên chủ sở hữu và chia sẻ. Không thể tự xóa, tự hủy kích hoạt hoặc tự gỡ quyền quản trị của tài khoản đang dùng. Đổi trạng thái kích hoạt ảnh hưởng kiểm tra đăng nhập; không coi đây là chức năng chấm dứt mọi phiên đăng nhập đang mở.

## 3. Đăng ký với mã xác thực mô phỏng

Khi `NOTEZY_DEMO_OTP=true`, chỉ bước **kích hoạt tài khoản mới** sử dụng mã cố định `123456`; không gửi thư thật.

1. Chọn **Đăng ký** hoặc **Bắt đầu**.
2. Nhập tên hiển thị, thư điện tử đúng định dạng và mật khẩu. Ở trang đăng ký riêng, nhập thêm tên tài khoản.
3. Gửi biểu mẫu. Ứng dụng chuyển tới **Xác thực tài khoản**.
4. Nhập `123456`, chọn **Xác nhận mã**.
5. Đăng nhập bằng thư điện tử và mật khẩu vừa đặt, hoặc tên tài khoản đã chọn.

Modal đăng ký trên trang chủ tự tạo tên tài khoản; dùng thư điện tử để đăng nhập là thuận tiện nhất. Mã có hiệu lực năm phút; hết hạn thì chọn **Gửi lại mã xác thực**, mã demo mới vẫn là `123456`. Nhập sai bị từ chối; sau nhiều lần sai cần yêu cầu lại mã.

`123456` không phải mật khẩu tài khoản, không phải mã mở mọi ghi chú, không thay thế khôi phục mật khẩu. Khi tắt `NOTEZY_DEMO_OTP`, mã kích hoạt được tạo ngẫu nhiên và cần cấu hình dịch vụ gửi thư thật. Cấu hình demo không xác minh quyền sở hữu địa chỉ thư điện tử, nên chỉ dùng dữ liệu trình diễn.

## 4. Mở source trên máy

### Dùng bản đã có trên máy Triều

Mở Visual Studio Code → **File → Open Folder…**, chọn:

```text
/Users/zatriu/Downloads/Learning/Software Engineering/FINAL/notezy-report-review
```

Chọn **Terminal → New Terminal**. Kiểm tra thư mục có `docker-compose.yml`, `docker-compose.demo.yml`, `Dockerfile` và `.env.demo.example`.

### Tải lại từ GitHub trên máy khác

Cài Git, Docker Desktop và một trình soạn thảo. Mở Terminal ở thư mục muốn lưu rồi chạy:

```bash
git clone --branch codex/fix-note-access-and-free-deploy https://github.com/Nghiaktvn/notezy.git
cd notezy
```

Nếu đã clone, kiểm tra `git status` trước. Lưu thay đổi riêng rồi chuyển đúng nhánh và cập nhật; không ghi đè mã chưa lưu của mình. Nhánh `main` chưa mặc nhiên chứa các bản sửa trong nhánh demo.

## 5. Chạy localhost lần đầu bằng Docker

1. Mở **Docker Desktop** từ Applications/Launchpad. Đợi Docker Engine sẵn sàng.
2. Mở Terminal trong đúng thư mục source.
3. Tạo tệp cấu hình local **chỉ một lần**:

```bash
cp .env.demo.example .env.demo
```

Không chạy lại lệnh sao chép nếu đã tùy chỉnh `.env.demo`. Tệp này dùng thông tin giả cho localhost, không chứa mật khẩu Aiven. Mật khẩu database khác với mật khẩu đăng nhập website.

4. Chạy bộ container demo:

```bash
docker compose --env-file .env.demo -p notezy-demo -f docker-compose.yml -f docker-compose.demo.yml up -d --build
```

Lần đầu cần mạng để tải image và build, có thể mất vài phút. Các lần sau dùng lại dữ liệu trong volume. Bộ demo dùng web PHP, MySQL, trợ lý Python mô phỏng và dịch vụ cộng tác; không cần chạy thêm `npm` để mở giao diện PHP đang demo.

5. Kiểm tra trạng thái:

```bash
docker compose --env-file .env.demo -p notezy-demo -f docker-compose.yml -f docker-compose.demo.yml ps
```

6. Mở http://localhost:8080/ . Kiểm tra http://localhost:8080/health.php trả `status: ok`, `database: connected` rồi đăng nhập `user1`.

Web khởi tạo schema, seed hai tài khoản thường khi bảng tài khoản còn trống và tạo quản trị viên khi tên đó chưa tồn tại. Nếu dùng lại database đã có tài khoản, không tự đặt lại mật khẩu hoặc seed thêm hai tài khoản thường. Không chạy script `seed_demo_accounts.php` tùy tiện trên dữ liệu đang dùng vì script cũ có thể đặt lại mật khẩu demo.

### Nếu cổng đang bị dùng

Dừng bản Notezy cũ trong **Docker Desktop → Containers → Stop**. Hoặc sửa các cổng trong `.env.demo`, ví dụ:

```dotenv
WEB_PORT=8090
APP_URL=http://localhost:8090
DB_HOST_PORT=13307
AI_AGENT_HOST_PORT=18765
COLLAB_WS_HOST_PORT=18766
COLLAB_WS_PUBLIC_URL=ws://localhost:18766
```

Chọn các cổng chưa bị dùng, chạy lại lệnh `up -d --build`, rồi mở đúng `APP_URL`. Không cần cài XAMPP/MySQL riêng cho cách chạy Docker này.

## 6. Tắt máy hôm nay, mở lại hôm sau

### Dừng bản localhost để nhẹ máy

Trong thư mục source:

```bash
docker compose --env-file .env.demo -p notezy-demo -f docker-compose.yml -f docker-compose.demo.yml stop
```

Có thể chọn **Stop** cho nhóm `notezy-demo` trong Docker Desktop. Nếu còn các nhóm thử khác như `notezy-report-check` hoặc bản cũ `notezy-main`, chúng không tự dừng theo nhóm này; dừng đúng nhóm mình không cần dùng. Sau đó có thể thoát Docker Desktop.

Không chọn xóa volume nếu muốn giữ database và tệp đính kèm. Chỉ đóng Terminal không bảo đảm container đã dừng.

### Mở lại localhost

1. Mở Docker Desktop, đợi Engine sẵn sàng.
2. Mở source và Terminal tại thư mục dự án.
3. Chạy:

```bash
docker compose --env-file .env.demo -p notezy-demo -f docker-compose.yml -f docker-compose.demo.yml up -d
```

4. Mở http://localhost:8080/ hoặc cổng mình đã đổi. Đăng nhập lại nếu phiên hết hạn. Không cần tạo lại `.env.demo`, database hay tài khoản.

Nếu thay đổi mã, Dockerfile hoặc cấu hình khởi động thì dùng lại `up -d --build`.

### Mở lại bản công khai

Chỉ mở https://notezy-jrq0.onrender.com/ . Không cần mở Docker. Render Free ngủ sau khoảng 15 phút không có truy cập; mở trước buổi demo khoảng một đến hai phút để chờ đánh thức. Đây là hành vi [được Render mô tả](https://render.com/docs/free).

## 7. Render và Aiven: xem trạng thái, cập nhật và khắc phục

### Dịch vụ hiện có

- Render: **Notezy**, địa chỉ `notezy-jrq0.onrender.com`, gói **Free**.
- Aiven: **mysql-158fddf6**, project **trangiatrieu2001-7d00**, gói **Free-1-1gb**.
- Source triển khai: nhánh `codex/fix-note-access-and-free-deploy`.
- Dockerfile trên Render: `Dockerfile.render`; health check: `/health.php`.
- Tự triển khai đang tắt; thay đổi trên GitHub không tự xuất hiện trên website cho tới khi triển khai thủ công.

### Kiểm tra trước buổi trình diễn

1. Đăng nhập [Aiven Console](https://console.aiven.io/) bằng tài khoản đã tạo. Chọn đúng project → dịch vụ MySQL → **Overview**.
2. Xác nhận **Running** và **Free tier**. Không cần thêm thẻ hay chuyển gói.
3. Đăng nhập [Render Dashboard](https://dashboard.render.com/), mở **Notezy** → **Deploys**. Xác nhận bản triển khai **Live** và nhãn **Free**.
4. Mở website và `/health.php`.

Nếu Aiven bị tắt, vào **Actions → Power on service**, chờ Running rồi kiểm tra lại. Không chọn Delete service. Aiven mô tả thao tác bật lại trong [tài liệu chính thức](https://aiven.io/docs/products/mysql/howto/power-cycle-service). Nhà cung cấp có thể tắt dịch vụ Free ít hoạt động; xem [giới hạn Free](https://aiven.io/docs/products/mysql/concepts/mysql-free-tier). Không cần chủ động tắt Aiven để giảm tải cho máy cá nhân.

### Sau khi cập nhật source

1. Lưu và kiểm thử thay đổi tại localhost.
2. Đưa commit lên đúng nhánh GitHub đang triển khai; không cần dán mật khẩu vào mã nguồn.
3. Render → Notezy → **Manual Deploy → Deploy latest commit**. Nếu muốn chọn đúng phiên bản, dùng **Deploy a specific commit**.
4. Chờ **Live**. Xem **Logs** nếu build/health thất bại; kiểm tra lại `/health.php` và đăng nhập.

Đây là quy trình [triển khai thủ công của Render](https://render.com/docs/deploys). Không tạo dịch vụ Render/MySQL mới mỗi lần chạy demo.

### Các cấu hình quan trọng cần giữ

| Nơi | Cấu hình |
| --- | --- |
| Render Settings | Branch đúng; Dockerfile `Dockerfile.render`; gói Free; health `/health.php` |
| Render Environment | `APP_URL=https://notezy-jrq0.onrender.com`; thông tin `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` của Aiven |
| TLS database | `DB_SSL_REQUIRED=true`; `DB_SSL_CA=/etc/secrets/aiven-ca.pem`; giữ Secret File `aiven-ca.pem` |
| Khởi tạo | `AUTO_INIT_DATABASE=true`; `NOTEZY_SEED_EMPTY_DEMO=true` |
| Demo mới | `NOTEZY_DEMO_OTP=true`; `NOTEZY_SEED_DEMO_ADMIN=true`; mật khẩu quản trị riêng trong `NOTEZY_DEMO_ADMIN_PASSWORD` |
| Trợ lý và cộng tác | `LLM_PROVIDER=mock`; `AI_AGENT_URL=http://127.0.0.1:8765`; khóa nội bộ giữ nguyên; `COLLAB_WS_PUBLIC_URL=/ws` |

Không đưa mật khẩu database, khóa nội bộ hay mật khẩu quản trị đám mây vào GitHub. Không tắt xác minh TLS để chữa lỗi kết nối. Có thể xem cấu trúc triển khai ở `deploy/FREE_RENDER.md`.

Nếu web báo database không phản hồi: kiểm tra Aiven Running → đối chiếu host/cổng/database/thông tin đăng nhập trên Render → kiểm tra CA → xem Logs. Nếu bảng `users` đã có dữ liệu, không nhập lại `note.sql` chồng lên database đang sử dụng.

File upload và phiên đăng nhập trên Render Free có thể mất khi restart/redeploy; nội dung ghi chú nằm trong Aiven. Docker local dùng volume cho database và upload. Không đồng nhất hai cách lưu trữ này khi báo cáo.

## 8. Kịch bản demo chức năng theo thứ tự

### A. Chuẩn bị hai người dùng

Mở cửa sổ thường đăng nhập `user1`, cửa sổ riêng tư đăng nhập `user2`. Hai tab trong cùng một phiên trình duyệt thường dùng chung đăng nhập; đăng nhập user2 ở tab khác có thể làm tab user1 đổi phiên. Nếu dùng hai trình duyệt khác nhau thì cũng tách được phiên.

### B. Tạo, sửa, tìm và xóa ghi chú

1. User1 → **Tạo ghi chú**, nhập tiêu đề và nội dung tiếng Việt, chọn **Lưu ghi chú**.
2. Ở trang báo lưu thành công, nhấn logo **Notezy** trên cùng để về trang chủ.
3. Mở ghi chú vừa tạo; chọn sửa, thay đổi nội dung và đợi báo lưu.
4. Thử ghim, nhãn và bộ lọc; đổi **Dạng lưới / Dạng danh sách**.
5. Tạo thêm một ghi chú tên “Ghi chú thử xóa”, xóa và xác nhận. Không xóa ghi chú muốn dùng cho phần sau.

### C. Chia sẻ và phân quyền

1. User1 chọn nút **Chia sẻ sổ** trên ghi chú **do user1 tạo**. Hoặc menu chức năng → **Chia sẻ với người khác** → chọn sổ → tiếp tục.
2. Nhập **`user2`** hoặc **`user2@example.com`**. Đây là tài khoản trên cùng bản demo, không phải gửi thư ra ngoài.
3. Chọn **Chỉ xem** → **Chia sẻ ngay**. Danh sách người nhận phải xuất hiện user2.
4. Trong cửa sổ user2, tải lại trang chủ, xem **Được chia sẻ với tôi** hoặc mở **Trò chuyện**. Mở ghi chú; quyền chỉ xem không cho lưu nội dung mới.
5. User1 chia sẻ lại cùng người nhận với **Được chỉnh sửa**. User2 mở lại và sửa nội dung; user1 tải lại để đối chiếu.
6. User1 chọn **Hủy** trong danh sách chia sẻ. User2 không được tiếp tục đọc nội dung qua API/thẻ chat sau khi quyền bị thu hồi.

**Nếu không chia sẻ được:** người nhận phải tồn tại và đã kích hoạt trên cùng database; không tự chia sẻ cho chính mình; không chia sẻ lại ghi chú do người khác sở hữu. Không dùng email Render/Aiven để thay cho tài khoản Notezy. Trước đây tài khoản mới chưa hoàn tất OTP sẽ không được tìm thấy để chia sẻ; hãy kích hoạt bằng `123456` ở chế độ demo rồi thử lại.

Khóa ghi chú vẫn áp dụng với người nhận. Nếu muốn thử chia sẻ trước, dùng ghi chú chưa khóa; sau đó mới demo bảo vệ.

### D. Bảo vệ ghi chú

1. Chọn nút mã bảo mật, đặt một mã sáu số do mình nhớ, ví dụ `246810`.
2. Mở lại trong phiên mới để thấy nội dung bị che.
3. Nhập mã sai để kiểm tra từ chối, nhập đúng để mở.
4. Nếu ghi chú có cả mật khẩu và mã bảo mật, cần mở đủ cả hai. Mã kích hoạt `123456` không tự mở ghi chú này.

### E. Lịch học và nhắc việc

Mở **Thời khóa biểu**, tạo lịch với ngày/giờ hợp lệ và tên môn học; sửa hoặc xóa một lịch thử. Có thể thêm ghi chú vào lịch bằng nút lịch trên thẻ ghi chú. Nhắc bằng trình duyệt cần trang đang mở và quyền thông báo/âm thanh phù hợp. Đây không phải cam kết gửi thông báo khi toàn bộ trình duyệt đã đóng.

### F. Trợ lý tạo ghi chú rồi gửi người khác

1. User1 → **Hỏi trợ lý thông minh**.
2. Trong chế độ mô phỏng hiện tại, dùng câu đã kiểm thử: **“Tạo ghi chú Docker cơ bản để chia sẻ cho user2.”**
3. Trợ lý trả thẻ **Đã tạo ghi chú** và liên kết mở ghi chú. Đây là dữ liệu thật được lưu vào database, nhưng nội dung được tạo bởi bộ mô phỏng, không phải mô hình ngôn ngữ bên ngoài.
4. Về trang chủ, tải lại nếu cần, tìm ghi chú vừa tạo → chia sẻ cho user2 theo phần C. Hoặc vào trò chuyện với user2 → chọn đính kèm/chia sẻ ghi chú → chọn ghi chú → gửi.
5. Mở cửa sổ user2 để thấy thẻ ghi chú và thử mở nội dung.

Bộ mô phỏng có tập mẫu lệnh giới hạn; không phải mọi câu hỏi tự do đều cho câu trả lời phù hợp. Chưa cấu hình khóa mô hình thật nên không trình bày phần này như một mô hình sinh nội dung hoàn chỉnh. Gói trong ứng dụng có thể giới hạn lượt dùng; thông báo hết lượt không đồng nghĩa database hỏng.

### G. Trình diễn quyền quản trị

1. Mở cửa sổ riêng, đăng nhập `admin` bằng đúng mật khẩu của môi trường đang demo.
2. Chọn menu tài khoản → **Quản trị người dùng**, hoặc mở `/admin_users.php`.
3. Thêm tài khoản thử, ví dụ `demo_quantri_01`, với thư điện tử giả hợp lệ và vai trò người dùng. Nếu tên đã tồn tại, dùng tên khác.
4. Sửa tên hiển thị hoặc trạng thái của tài khoản thử; không đổi quyền/mật khẩu các tài khoản đang trình diễn.
5. Xóa chính tài khoản thử vừa tạo để minh họa quản lý tài khoản.
6. Dùng cửa sổ user1 mở `/admin_users.php`: phải bị từ chối. Người chưa đăng nhập cũng không truy cập được, kể cả liên kết đăng nhập quản trị tắt kiểm tra cũ.

## 9. Checklist trước khi nộp/demo

- Đang mở đúng localhost hoặc URL công khai; không nhầm dữ liệu hai bên.
- Web health kết nối được database; Render/Aiven vẫn Free nếu demo online.
- Đã đăng nhập thử user1 và user2 ở hai phiên riêng.
- Có một ghi chú chưa khóa để chia sẻ, một ghi chú thử khóa và một ghi chú thử xóa.
- Nhớ mật khẩu quản trị đúng môi trường và chỉ thao tác trên tài khoản thử.
- Nêu rõ OTP và trợ lý là mô phỏng; không trình bày như đã gửi email thật hoặc gọi mô hình thật.
- Sau buổi demo local, dừng container bằng lệnh `stop`; giữ nguyên volume.

## 10. Nguồn cấu hình trong repository

- `docker-compose.yml` + `docker-compose.demo.yml`: chạy localhost.
- `.env.demo.example`: cấu hình local có thể sao chép, không chứa bí mật đám mây.
- `deploy/start-local-demo.sh`: chuẩn bị database/tài khoản local rồi khởi động web.
- `Dockerfile.render`, `deploy/start-free.sh`: chạy trên Render.
- `config/demo.php`: công tắc OTP kích hoạt mô phỏng.
- `scripts/seed_empty_demo.php`, `scripts/seed_demo_admin.php`: tạo tài khoản khi phù hợp, giữ tài khoản đã có.
- `tests/demo_flow_regression.py`, `tests/note_access_regression.py`: kiểm tra luồng demo và bảo vệ ghi chú.
