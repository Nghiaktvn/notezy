# Gửi OTP thật qua Gmail API trên Render Free

## Cấu hình riêng tư

Chọn đúng dịch vụ Notezy trên Render. Lưu trong Environment, không đưa vào GitHub:

| Biến | Giá trị |
| --- | --- |
| MAIL_MAILER | gmail_api |
| MAIL_FROM_ADDRESS | Gmail đã cấp quyền gửi thư |
| MAIL_FROM_NAME | Notezy |
| GMAIL_CLIENT_ID | OAuth client ID riêng |
| GMAIL_CLIENT_SECRET | OAuth client secret riêng |
| GMAIL_REFRESH_TOKEN | Refresh token có quyền gmail.send |
| NOTEZY_DEMO_OTP | false |
| MAIL_TIMEOUT_SECONDS | 10 |

Gmail API dùng HTTPS; SMTP thông thường trên cổng 25/465/587 bị chặn ở Render Free. Gemini key và mật khẩu đăng nhập Gmail không thay thế được OAuth credentials.

Sau khi đổi Environment cần triển khai lại để tiến trình nhận biến mới. Kiểm tra nhánh và commit đang triển khai trong Render. Không merge bản sửa vào main trước khi kiểm thử hosting thành công.

## Đăng ký và xác minh

1. Mở /register.php. Điền tên tài khoản, tên hiển thị, thư điện tử, mật khẩu và xác nhận.
2. Gửi biểu mẫu. Tài khoản chỉ được lưu ở trạng thái **chưa kích hoạt**, chưa có quyền đăng nhập.
3. Kiểm tra hộp thư và thư rác. Mã ngẫu nhiên sáu số có hiệu lực năm phút.
4. Nhập đúng mã mới nhất để kích hoạt rồi đăng nhập bằng mật khẩu đã đặt.
5. Nếu nhập sai mã thật: tài khoản không được kích hoạt, mã đó không còn dùng được; chọn **Gửi lại mã xác thực**.
6. Mỗi lần gửi cách nhau ít nhất 60 giây. Mã mới thay thế mã cũ và khác mã cũ.
7. Lỗi gửi thư phải được hiển thị; không coi việc API chấp nhận gửi thư là bằng chứng email đã tới hộp thư. Cần xác minh việc nhận thực tế.

Mã 123456 chỉ dành cho chế độ demo localhost khi NOTEZY_DEMO_OTP=true. Khi bật gửi thư thật, mã này không được chấp nhận và liên kết kích hoạt cũ không thay thế bước nhập OTP.

## Hạn mức và thời hạn OAuth

Dùng Gmail API theo hạn mức thông thường không yêu cầu bật billing. Không thêm thẻ hay nâng gói cho demo.

Nếu Google OAuth app ở trạng thái Testing, refresh token có thể hết hạn sau bảy ngày. Thời gian này khác với access token khoảng một giờ và OTP năm phút. Khi nhận lỗi refresh token, cần cấp quyền lại để lấy refresh token mới; không chép access token ngắn hạn vào GMAIL_REFRESH_TOKEN. Kiểm tra lại gửi thư trước buổi demo.

## Kiểm thử

- tests/real_otp_flow.py dùng SMTP nội bộ tại localhost; tạo tài khoản thử riêng, không gửi thư tới Gmail thật.
- tests/mail_failure_regression.php chỉ chạy CLI trong môi trường local/demo: thiếu cấu hình Gmail không báo gửi thành công; SMTP bị chặn thất bại nhanh.
- Kiểm thử hosting phải dùng Gmail đã cấu hình, thử mã sai, gửi lại, mã đúng và đăng nhập. Chỉ sau khi đạt các bước này mới merge.

Nguồn: [Render Free](https://render.com/docs/free), [Gmail API quota](https://developers.google.com/workspace/gmail/api/reference/quota), [OAuth Playground](https://developers.google.com/oauthplayground/).
