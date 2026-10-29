# Kịch bản demo 32 tiêu chí Notezy

Theo bảng IV trong `503073-FinalProject-V1.pdf` (trang 8–12). Dùng hai cửa sổ độc lập: cửa sổ thường đăng nhập `user1`, cửa sổ ẩn danh đăng nhập `user2`. Dữ liệu mẫu được tạo bằng `docker compose exec -T web php tests/seed_demo_showcase.php` nếu cần; lệnh này tạo lại các ghi chú mẫu của `user1`.

Ký hiệu: **Đã thử** = có kiểm thử backend hoặc trình duyệt trong phiên 28/10/2026; **Chưa đủ** = cần chạy thao tác cuối trên giao diện hoặc dịch vụ thật. Không coi “Đã thử” là điểm số giảng viên.

| ID | Thao tác demo | Kết quả cần thấy | Tình trạng |
|---:|---|---|---|
| 1 | Bấm Bắt đầu, tạo tài khoản mới | Trang đăng ký riêng, lưu user | Đã thử đường dẫn và backend; email chưa đủ |
| 2 | Mở email, nhập OTP; thử sai/gửi lại | Chỉ mã đúng còn hạn kích hoạt | Đã gửi lại OTP qua Gmail, nhập đúng mã, DB activated=1 và OTP bị xóa |
| 3 | Đăng nhập rồi đăng xuất | Vào dashboard; logout về trang ngoài | Đã thử UI với `user1` và tài khoản mới kích hoạt |
| 4 | Quên mật khẩu với đúng username và email | OTP; đổi mật khẩu; đăng nhập lại | OTP email thật đã gửi và xác minh; đổi mật khẩu và đăng nhập lại chưa xác nhận |
| 5 | Mở hồ sơ `user1` | Tên/email/avatar đúng | DB đã thử; UI chưa đủ |
| 6 | Sửa tên và avatar | Lưu và hiện sau tải lại | DB đã thử; upload chưa đủ |
| 7 | Đổi mật khẩu từ hồ sơ | Mật khẩu cũ bị thay thế | Hash đã thử; UI chưa đủ |
| 8 | Đổi theme/ngôn ngữ | Tùy chọn lưu sau tải lại | DB đã thử; UI chưa đủ |
| 9 | Chọn List view | Note hiển thị dạng danh sách | Đã thử UI với `user1` |
| 10 | Chọn Grid view | Note hiển thị dạng thẻ | Đã thử UI với `user1` |
| 11 | Tạo note mới | Note xuất hiện và có ID | Đã thử UI với note tạm; đã dọn |
| 12 | Sửa tiêu đề/nội dung note | Tải lại vẫn đúng | Đã thử sửa nội dung UI; đã dọn |
| 13 | Xóa note thử | Note biến mất, người khác không xóa được | Backend đã thử; UI chưa đủ |
| 14 | Gõ nội dung và chờ tự lưu | Tải lại vẫn có nội dung | Đã thử UI, tải lại vẫn lưu; đã dọn |
| 15 | Đính kèm ảnh và video | Tải/xem được file thật | Chưa đủ |
| 16 | Đính kèm tệp khác | Tải xuống được, đúng quyền | Chưa đủ |
| 17 | Ghim note | Note lên đầu sau tải lại | DB đã thử; UI chưa đủ |
| 18 | Mở note ghim/khóa/chia sẻ | Huy hiệu trạng thái đúng | Chưa đủ |
| 19 | Tìm tiêu đề và nội dung | Đúng kết quả, có trạng thái rỗng | DB đã thử; UI chưa đủ |
| 20 | Thêm, đổi tên, xóa nhãn | Danh sách nhãn cập nhật | DB thêm/sửa; UI/xóa chưa đủ |
| 21 | Gắn và bỏ nhãn khỏi note | Nhãn hiển thị đúng | DB gắn; UI/bỏ chưa đủ |
| 22 | Lọc note theo nhãn | Chỉ note phù hợp xuất hiện | DB đã thử; UI chưa đủ |
| 23 | Bật/tắt khóa note | Yêu cầu xác nhận mật khẩu phù hợp | DB đã thử; UI chưa đủ |
| 24 | Đổi mật khẩu note, thử sai/đúng | Không lộ nội dung khi chưa mở khóa | Chưa đủ |
| 25 | `user1` chia sẻ cho `user2` Viewer rồi Editor | `user2` nhận note; quyền đúng | Backend đã thử; 2 UI chưa đủ |
| 26 | Cùng mở note bằng hai cửa sổ | Đồng bộ, presence, cảnh báo xung đột | Backend/WS đã thử; 2 UI chưa đủ |
| 27 | Yêu cầu AI tóm tắt note | Tóm tắt đúng nội dung note | Agent test; Gemini thật chưa đủ |
| 28 | Hỏi AI về nội dung note | Trả lời dựa trên note | HTTP 200; chất lượng chưa đủ |
| 29 | Duyệt giao diện, lỗi, trạng thái rỗng | Dễ dùng và có phản hồi rõ | Giảng viên đánh giá |
| 30 | Thử mobile/tablet/desktop | Không tràn, thao tác cảm ứng tốt | Chưa đủ |
| 31 | Ngắt mạng, sửa note, nối lại | Xem offline và đồng bộ trở lại | Chưa đủ |
| 32 | Chạy Docker trên máy khác hoặc Codespaces | Trang hoạt động ổn định, HTTPS nếu online | Docker local đã thử; môi trường khác chưa đủ |

## Chặn hiện tại

Ngày 28/10/2026, Gmail SMTP từ container đã xác thực thành công; ứng dụng đã gửi lại OTP và tài khoản mới đã kích hoạt bằng mã nhận được. Sau khi đăng xuất phiên demo cũ, tài khoản mới đăng nhập thành công vào trang ghi chú và mở được biểu mẫu tạo sổ. Ở mục 4, OTP khôi phục đã gửi và xác minh thành công; bước đặt mật khẩu mới do người dùng tự thực hiện và chưa có bằng chứng đăng nhập lại. Không nhập mật khẩu hoặc OTP vào báo cáo này.
