# Đối chiếu 32 tiêu chí rubric Notezy

Trạng thái dưới đây được đối chiếu bằng bộ test tự động, kiểm tra giao diện thật
trên XAMPP và Docker, cùng kiểm tra database/phpMyAdmin ngày 21/09/2026.

| ID | Tiêu chí | Trạng thái | Bằng chứng demo ngắn |
|---:|---|---|---|
| 1 | User registration | PASS | Tạo user, kiểm tra trùng dữ liệu và bcrypt hash |
| 2 | Account activation | PASS | OTP hash, hạn 5 phút, sai OTP bị từ chối |
| 3 | User login and logout | PASS | Đăng nhập `user1`; session/logout có trên menu |
| 4 | Password reset | PASS | Reset và đăng nhập lại bằng mật khẩu mới |
| 5 | View profile and avatar | PASS | Trang hồ sơ đọc đúng dữ liệu/avatar |
| 6 | Edit profile and avatar | PASS | Cập nhật tên và avatar được lưu vào DB |
| 7 | Change password | PASS | Hash đổi và `password_verify` thành công |
| 8 | User preferences | PASS | Theme và language được lưu |
| 9 | Display notes in listview | PASS | Nút List View và dữ liệu ghi chú đã kiểm tra UI |
| 10 | Display notes in gridview | PASS | Nút Grid View và thẻ ghi chú đã kiểm tra UI |
| 11 | Create notes | PASS | Tạo note và nhận `note_id` thật |
| 12 | Update notes | PASS | Tiêu đề/nội dung cập nhật đúng |
| 13 | Delete notes | PASS | Xóa có ràng buộc chủ sở hữu |
| 14 | Auto-save notes | PASS | Nội dung auto-save được ghi vào DB |
| 15 | Attach images/video | PASS | API attachment chấp nhận ảnh và video MIME an toàn |
| 16 | Attach files | PASS | Bảng `note_attachments`, upload/download có kiểm quyền |
| 17 | Pin notes to top | PASS | `pinned=1`, ghi chú demo xuất hiện đầu trang |
| 18 | Special-note indicators | PASS | UI hiện ghim, khóa, chia sẻ, sự kiện và trạng thái |
| 19 | Search notes | PASS | Tìm theo tiêu đề/nội dung trả đúng note |
| 20 | Label management | PASS | Liệt kê, thêm, đổi tên, xóa nhãn có phân quyền |
| 21 | Attach labels to notes | PASS | Quan hệ `note_labels` được tạo |
| 22 | Filter notes by labels | PASS | Truy vấn lọc nhãn trả đúng note |
| 23 | Enable/disable note password | PASS | Bật và gỡ bcrypt password cho note |
| 24 | Protect/change note password | PASS | Bìa khóa che nội dung; API xác minh/đổi password |
| 25 | Share and receive notes | PASS | Viewer/Editor, nhận ghi chú và chống tự chia sẻ |
| 26 | Realtime collaboration | PASS | WebSocket HMAC, presence, typing, conflict detection |
| 27 | AI Summary | PASS | AI agent có intent/tool `summarize_note` và endpoint hoạt động |
| 28 | AI Question & Answer | PASS | AI chat HTTP 200, retrieval và phản hồi tiếng Việt |
| 29 | UI and UX | PASS | Landing/dashboard hoàn chỉnh, phản hồi và trạng thái rỗng/lỗi |
| 30 | Responsive | PASS | Viewport, CSS responsive, touch target và kiểm tra mobile |
| 31 | Offline capabilities | PASS | Service worker, IndexedDB, fallback offline và đồng bộ lại |
| 32 | Online deployment | PASS | Docker stack healthy; có cấu hình Codespaces/HTTPS |

## Kết quả môi trường

- XAMPP: checklist 31/31, system flow PASS, AI HTTP 200, WebSocket PASS.
- Docker: checklist 31/31, system flow PASS, AI HTTP 200, WebSocket PASS.
- PHP: toàn bộ file PHP được Git theo dõi đều qua `php -l`.
- Security regression: toàn bộ 22 kiểm tra PASS.
- Docker logs: không có Fatal/Uncaught/ERROR/Traceback trong lần kiểm tra cuối.
- MySQL/phpMyAdmin: view `demo_account_overview` chỉ hiện `user1`, `user2`,
  trạng thái Activated và bcrypt hash; không lưu mật khẩu chữ thường.
