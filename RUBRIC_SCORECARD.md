# Bảng chấm điểm Notezy theo rubric giảng viên

Ngày kiểm thử cuối: 13/09/2026 (Asia/Ho_Chi_Minh)

## Kết quả tổng quan

| Nhóm | Điểm tối đa | Tự chấm hiện tại | Trạng thái |
|---|---:|---:|---|
| Account management | 2.00 | 2.00 | Đạt |
| Simple note management | 3.50 | 3.50 | Đạt |
| Advanced note management | 2.50 | 2.50 | Đạt |
| UI/UX, responsive, offline, deployment | 2.00 | 2.00 | Đạt |
| **Tổng** | **10.00** | **10.00** | **Đạt theo tự chấm, có bằng chứng kiểm thử** |

## Chi tiết 32 tiêu chí

| ID | Tiêu chí | Điểm | Kết quả và bằng chứng |
|---:|---|---:|---|
| 1 | User registration | 0.25/0.25 | Đăng ký, xác nhận mật khẩu, bcrypt; kiểm thử động đạt. |
| 2 | Account activation | 0.25/0.25 | OTP/link email, trạng thái chưa xác minh và kích hoạt; SMTP thực tế đã chấp nhận mail. |
| 3 | User login and logout | 0.25/0.25 | Đăng nhập demo trực tiếp thành công, session bảo mật và logout có sẵn. |
| 4 | Password reset | 0.25/0.25 | OTP bcrypt, hết hạn, giới hạn lần thử, hủy session sau reset; mail reset đã gửi thử thành công. |
| 5 | View profile and avatar | 0.25/0.25 | Profile và avatar mặc định được kiểm thử động. |
| 6 | Edit profile and avatar | 0.25/0.25 | Cập nhật tên/avatar, kiểm tra MIME và kích thước. |
| 7 | Change password | 0.25/0.25 | Xác minh mật khẩu hiện tại, nhập mới hai lần, hash mới đăng nhập được. |
| 8 | User preferences | 0.25/0.25 | Theme sáng/tối, ngôn ngữ, màu nền/chữ và font note. |
| 9 | List view | 0.25/0.25 | Nút List View hoạt động, trạng thái hiển thị riêng. |
| 10 | Grid view | 0.25/0.25 | Grid là mặc định, sắp xếp và card đầy đủ trạng thái. |
| 11 | Create notes | 0.25/0.25 | Tiêu đề/nội dung là bắt buộc; cùng editor `themghichu.php` với chỉnh sửa. |
| 12 | Update notes | 0.25/0.25 | Cùng editor với tạo mới; owner/shared editor được phân quyền. |
| 13 | Delete notes | 0.25/0.25 | Có hộp xác nhận; shared editor không thể xóa. |
| 14 | Auto-save notes | 0.25/0.25 | Debounce auto-save, API riêng, xử lý conflict và hàng đợi offline. |
| 15 | Attach images/video | 0.25/0.25 | Nhiều ảnh/video, whitelist MIME theo nội dung, tên lưu ngẫu nhiên và giới hạn dung lượng. |
| 16 | Attach files | 0.25/0.25 | PDF/TXT/ZIP/DOCX, tối đa 10 tệp/lần, endpoint tải xuống kiểm tra quyền. |
| 17 | Pin notes to top | 0.25/0.25 | `pinned_at`, ghim trước và sắp theo thời điểm ghim/cập nhật. |
| 18 | Special-note indicators | 0.25/0.25 | Icon pin/share/password/PIN trong cả grid và list. |
| 19 | Search notes | 0.25/0.25 | Live search title/content, không cần nút Search. |
| 20 | Label management | 0.25/0.25 | Liệt kê/thêm/đổi tên/xóa, nhãn tách theo người dùng. |
| 21 | Attach labels | 0.25/0.25 | Một note hỗ trợ 0..n labels. |
| 22 | Filter by labels | 0.25/0.25 | Thanh chip lọc theo nhãn trên dashboard. |
| 23 | Enable/disable note password | 0.50/0.50 | Nút quản lý trên dashboard; bật cần nhập hai lần, tắt cần mật khẩu hiện tại. |
| 24 | Protect/change note password | 0.50/0.50 | Mỗi note có hash riêng; xem/sửa/tệp bị khóa; đổi cần mật khẩu cũ + mật khẩu mới hai lần. Chỉnh nội dung không còn làm mất password. |
| 25 | Share and receive notes | 0.50/0.50 | Email/username đã kích hoạt, viewer/editor, cập nhật/thu hồi quyền, danh sách người nhận và thẻ thông báo trong chat. |
| 26 | Realtime collaboration | 0.50/0.50 | WebSocket có token, room theo note, two-client broadcast, presence/typing và conflict handling. |
| 27 | AI Summary | 0.25/0.25 | Tóm tắt/regenerate, service AI tách riêng; unit và live endpoint đạt. |
| 28 | AI Q&A | 0.25/0.25 | Retrieval keyword/vector, câu trả lời tổng hợp và reference mở được note nguồn. |
| 29 | UI and UX | 0.50/0.50 | Landing/dashboard hiện đại, feedback/loading/error/empty state, modal và icon có tooltip. |
| 30 | Responsive | 0.50/0.50 | Đo trực tiếp: mobile 375px, tablet 753px, desktop 1425px đều không tràn ngang; grid lần lượt 1/2/4 cột. |
| 31 | Offline capabilities | 0.50/0.50 | Service Worker + IndexedDB notes/labels/sync queue; create/update/delete offline và conflict message. |
| 32 | Online deployment | 0.50/0.50 | GitHub Codespaces/Docker đã chạy toàn bộ service healthy. Web public trả HTTP 200 với `database: connected`; WebSocket public `/health` trả HTTP 200 `{"ok":true}`. |

## Các lỗi/rủi ro đã phát hiện và xử lý

1. **Mobile bị tràn ngang:** card rộng 552px trên vùng hiển thị 375px và nút icon bị kéo giãn. Đã sửa CSS; đo lại không còn overflow.
2. **Mật khẩu note có thể bị mất khi chỉnh sửa:** truy vấn cũ gán `password_hash = NULL`. Đã loại bỏ hành vi này và thêm regression guard.
3. **Chức năng mật khẩu khó tìm:** modal/API tồn tại nhưng không có nút truy cập trên card. Đã thêm nút đặt/đổi/gỡ mật khẩu trong grid và list.
4. **Tạo và sửa dùng đường dẫn khác nhau:** mọi đường dẫn chỉnh sửa chính đã chuyển về editor hợp nhất `themghichu.php?id=...`.
5. **AI live bị treo do container chạy mã cũ/provider ngoài:** đã sửa live test dùng biến môi trường, khởi động lại riêng service; endpoint hiện trả HTTP 200.
6. **Docker Desktop không khởi động do socket runtime hỏng và ổ C gần đầy:** dọn 2,53 GB npm cache; chuyển thư mục socket lỗi sang bản sao lưu `run.stale-20260912-1230`; engine và toàn bộ container đã healthy trở lại. Không xóa volume/database.
7. **Codespaces cũ không có workspace/port:** thay compose-devcontainer bằng workspace chuẩn + Docker-in-Docker; forward đúng 8080, 8081, 8088, 8765, 8766.
8. **Bootstrap collaboration thiếu bảng presence:** thêm migration `note_collab_presence` trước bước GRANT, để provisioner kết thúc thành công trên volume mới và cũ.
9. **Codespaces Docker-in-Docker timeout tới MySQL:** thêm fallback nội bộ `host.docker.internal:host-gateway` chỉ do bootstrap Codespaces bật; Docker thường vẫn dùng bridge `db` mặc định. Toàn bộ microservice và web đã chuyển `healthy` sau rebuild.
10. **Bind mount Windows làm entrypoint mất executable bit:** Compose chạy entrypoint qua Bash, ngăn web container lặp `Restarting (126)`.

## Bằng chứng kiểm thử cuối

- `test_system_flow.php`: đạt.
- `tests/verify_full_rubric_checklist.php`: 31/31 đạt.
- `tests/security_regression_check.php`: toàn bộ guard đạt.
- `tests/password_reset_security_check.php`: toàn bộ guard đạt.
- `tests/offline_deployment_static_check.php`: toàn bộ guard đạt.
- AI unit: 9/9 đạt; AI live: HTTP 200.
- Frontend Vite/PWA build: đạt; `npm audit`: 0 vulnerabilities.
- Docker: web, database, AI, WebSocket và mọi microservice có health; phpMyAdmin HTTP 200.
- XAMPP PHP 8.0.30: parse toàn bộ PHP và chạy web/health với MySQL Docker thành công.
- Codespaces public web: `https://bookish-space-pancake-694qjqpxg5xpc7gr-8080.app.github.dev/health.php` — HTTP 200, `{"status":"ok","database":"connected"}`.
- Codespaces public realtime: `https://bookish-space-pancake-694qjqpxg5xpc7gr-8766.app.github.dev/health` — HTTP 200, `{"ok":true}`.

> 10/10 là kết quả tự chấm theo rubric và bằng chứng kỹ thuật; điểm chính thức do giảng viên quyết định. GitHub Codespace phải còn chạy khi mở URL demo.
