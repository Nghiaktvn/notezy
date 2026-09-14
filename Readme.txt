NOTEZY — HƯỚNG DẪN CHẤM BÀI VÀ TÁI TẠO DỰ ÁN
================================================

1. Yêu cầu
------------
- Docker Desktop 4+ (đang chạy) và Docker Compose v2.
- Cổng trống: 8080 (web), 3307 (MySQL), 8081 (phpMyAdmin), 8765 (AI), 8766 (WebSocket).
- Tạo file .env từ .env.example. Điền AI_AGENT_SHARED_SECRET bằng một chuỗi ngẫu nhiên.
  GEMINI_API_KEY là tùy chọn; nếu để trống, trợ lý AI sử dụng chế độ demo cục bộ.

2. Khởi chạy
-------------
Mở terminal tại thư mục source rồi chạy:

  docker compose --profile tools up -d --build

Lệnh trên chỉ chạy kiến trúc gọn cần cho sản phẩm: Web PHP, MySQL,
phpMyAdmin, Gemini AI Agent và WebSocket cộng tác. Không cần chạy
docker-compose.services.yml để demo hoặc chấm bài; đó là bộ microservice mở
rộng dành cho thử nghiệm kiến trúc, không phải yêu cầu vận hành thường ngày.

Đợi các container healthy, sau đó truy cập:

- Ứng dụng: http://localhost:8080
- phpMyAdmin: http://localhost:8081
- Health web: http://localhost:8080/health.php
- Health AI: http://localhost:8765/health

phpMyAdmin dùng host db, port 3306, user root và mật khẩu DB_PASSWORD trong .env.
Không công khai phpMyAdmin khi triển khai trực tuyến.

3. Tài khoản dữ liệu demo
-------------------------
- Username: notezy_demo
- Password: DemoNotezy2026!

Tài khoản phụ để kiểm tra chia sẻ/cộng tác:
- Email: user1@example.com
- Password: Password123!

4. Luồng demo đề nghị
---------------------
1) Đăng nhập notezy_demo. Chuyển Grid/List; tìm kiếm và lọc nhãn.
2) Mở “Kế hoạch ôn tập tuần này”; thử ghim, tạo/sửa note và thấy autosave.
3) Mở menu đen bên trái -> “Sơ đồ tư duy”. Chọn “Xem sơ đồ dự án Notezy”, sau đó bấm “Tạo sơ đồ tư duy”.
4) Từ dashboard gửi note vào “Thời khóa biểu”; kiểm tra ba khung sáng/chiều/tối và báo thức giờ Việt Nam (+07:00).
5) Chia sẻ “Công việc hôm nay” cho user1@example.com với quyền Editor; đăng nhập user1 ở trình duyệt thứ hai và mở collab_demo.php.
6) Mở Notezy AI; yêu cầu “Tóm tắt ghi chú Kế hoạch ôn tập tuần này bằng ba ý chính.” AI trả lời và hiển thị nguồn ghi chú.
7) Tắt mạng trong DevTools để kiểm tra trang offline/cache, sau đó bật lại để đồng bộ hàng đợi.

5. Kiểm thử
------------
docker compose exec -T web php tests/verify_full_rubric_checklist.php
docker compose exec -T web php tests/security_regression_check.php
docker compose exec -T web php tests/password_reset_security_check.php
docker compose exec -T web php tests/offline_deployment_static_check.php
docker compose exec -T web php tests/test_ai_live.php
docker compose exec -T ai_agent python -m unittest ai_agent.tests.test_ai_agent

Kết quả kiểm thử cuối: rubric 31/31 PASS; kiểm tra bảo mật, password-reset,
offline/deployment và AI unit tests đều PASS.

6. Kiến trúc
------------
Browser/PWA
    |
    +--> PHP Web + API (8080) ----> MySQL 8 (db)
    |          |
    |          +------------------> Gemini AI Agent (8765) ---> Gemini API
    |          |
    |          +------------------> WebSocket cộng tác (8766)
    |
    +--> phpMyAdmin (8081, chỉ dùng quản trị DB)

Chỉ PHP Web/API là backend nghiệp vụ chính. AI Agent và WebSocket là hai
sidecar nhỏ, tách riêng vì mỗi phần cần kết nối dài hạn/chuyên biệt. Mỗi
dịch vụ có health check; không dịch vụ nào cần được gọi trực tiếp bởi người
dùng cuối.

7. Lưu ý nộp bài
-----------------
- Nộp toàn bộ thư mục source này, gồm docker-compose.yml, Dockerfile, SQL,
  api, ai_agent, collaboration_ws.py và Readme.txt.
- Rubric.xlsx là bảng tự đánh giá đi kèm, với 32 tiêu chí.
- Video demo.mp4 phải do nhóm tự quay ở tối thiểu 1080p, tiếng rõ, có thành viên
  giới thiệu kiến trúc và lần lượt trình bày toàn bộ 32 tiêu chí.
