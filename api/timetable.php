<?php
require_once dirname(__DIR__) . '/includes/session.php';
/**
 * api/timetable.php — API quản lý Thời Khóa Biểu, Lịch Calendar & Báo Giờ (Notezy)
 */
require_once __DIR__ . '/../config/database.php';

// Cấu hình cookie bảo mật
if (session_status() === PHP_SESSION_NONE) {
    notezy_session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Vui lòng đăng nhập để sử dụng tính năng']);
    exit();
}

$user_id = (int) $_SESSION['id'];

// Đảm bảo bảng timetable đã được tạo
function ensureTableExists(mysqli $conn) {
    static $checked = false;
    if ($checked) return;

    $sql = "CREATE TABLE IF NOT EXISTS `timetable` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `title` varchar(255) NOT NULL,
        `day_of_week` tinyint(4) NOT NULL DEFAULT 1,
        `start_time` time NOT NULL,
        `end_time` time NOT NULL,
        `location` varchar(255) DEFAULT NULL,
        `teacher` varchar(255) DEFAULT NULL,
        `color` varchar(20) DEFAULT '#4f46e5',
        `note` text DEFAULT NULL,
        `reminder_minutes` int(11) DEFAULT 15,
        `specific_date` date DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `idx_timetable_user` (`user_id`),
        CONSTRAINT `fk_timetable_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $conn->query($sql);
    $checked = true;
}

ensureTableExists($conn);

$method = $_SERVER['REQUEST_METHOD'];

// ── GET: Lấy danh sách lịch trình ──────────────────────────────────────
if ($method === 'GET') {
    $mode = $_GET['mode'] ?? 'all'; // 'weekly', 'calendar', 'all'
    
    $sql = "SELECT id, title, day_of_week, 
                   TIME_FORMAT(start_time, '%H:%i') as start_time, 
                   TIME_FORMAT(end_time, '%H:%i') as end_time, 
                   location, teacher, color, note, reminder_minutes, specific_date 
            FROM timetable 
            WHERE user_id = ? 
            ORDER BY day_of_week ASC, start_time ASC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Lỗi truy vấn: ' . $conn->error]);
        exit();
    }

    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = [
            'id' => (int) $row['id'],
            'title' => htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8'),
            'day_of_week' => (int) $row['day_of_week'],
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time'],
            'location' => htmlspecialchars($row['location'] ?? '', ENT_QUOTES, 'UTF-8'),
            'teacher' => htmlspecialchars($row['teacher'] ?? '', ENT_QUOTES, 'UTF-8'),
            'color' => $row['color'] ?: '#4f46e5',
            'note' => htmlspecialchars($row['note'] ?? '', ENT_QUOTES, 'UTF-8'),
            'reminder_minutes' => (int) $row['reminder_minutes'],
            'specific_date' => $row['specific_date']
        ];
    }
    $stmt->close();

    echo json_encode(['status' => 'success', 'data' => $items]);
    exit();
}

// ── POST: Thêm mới lịch trình ───────────────────────────────────────────
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    $title = trim($input['title'] ?? '');
    $day_of_week = isset($input['day_of_week']) ? (int) $input['day_of_week'] : 1;
    $start_time = trim($input['start_time'] ?? '');
    $end_time = trim($input['end_time'] ?? '');
    $location = trim($input['location'] ?? '');
    $teacher = trim($input['teacher'] ?? '');
    $color = trim($input['color'] ?? '#4f46e5');
    $note = trim($input['note'] ?? '');
    $reminder_minutes = isset($input['reminder_minutes']) ? (int) $input['reminder_minutes'] : 15;
    $specific_date = !empty($input['specific_date']) ? trim($input['specific_date']) : null;

    if (empty($title) || empty($start_time) || empty($end_time)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Vui lòng nhập tên môn học, giờ bắt đầu và giờ kết thúc']);
        exit();
    }

    $sql = "INSERT INTO timetable (user_id, title, day_of_week, start_time, end_time, location, teacher, color, note, reminder_minutes, specific_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Lỗi chuẩn bị truy vấn: ' . $conn->error]);
        exit();
    }

    $stmt->bind_param('isissssssis', $user_id, $title, $day_of_week, $start_time, $end_time, $location, $teacher, $color, $note, $reminder_minutes, $specific_date);
    if ($stmt->execute()) {
        $new_id = $stmt->insert_id;
        $stmt->close();
        echo json_encode([
            'status' => 'success', 
            'message' => 'Đã thêm vào thời khóa biểu thành công',
            'id' => $new_id
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Không thể thêm lịch trình: ' . $stmt->error]);
    }
    exit();
}

// ── PUT: Cập nhật lịch trình ────────────────────────────────────────────
if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Dữ liệu không hợp lệ']);
        exit();
    }

    $id = (int) ($input['id'] ?? 0);
    $title = trim($input['title'] ?? '');
    $day_of_week = isset($input['day_of_week']) ? (int) $input['day_of_week'] : 1;
    $start_time = trim($input['start_time'] ?? '');
    $end_time = trim($input['end_time'] ?? '');
    $location = trim($input['location'] ?? '');
    $teacher = trim($input['teacher'] ?? '');
    $color = trim($input['color'] ?? '#4f46e5');
    $note = trim($input['note'] ?? '');
    $reminder_minutes = isset($input['reminder_minutes']) ? (int) $input['reminder_minutes'] : 15;
    $specific_date = !empty($input['specific_date']) ? trim($input['specific_date']) : null;

    if ($id <= 0 || empty($title) || empty($start_time) || empty($end_time)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Vui lòng điền đủ thông tin']);
        exit();
    }

    $sql = "UPDATE timetable 
            SET title = ?, day_of_week = ?, start_time = ?, end_time = ?, location = ?, teacher = ?, color = ?, note = ?, reminder_minutes = ?, specific_date = ?
            WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Lỗi chuẩn bị truy vấn: ' . $conn->error]);
        exit();
    }

    $stmt->bind_param('sissssssisii', $title, $day_of_week, $start_time, $end_time, $location, $teacher, $color, $note, $reminder_minutes, $specific_date, $id, $user_id);
    if ($stmt->execute()) {
        $stmt->close();
        echo json_encode(['status' => 'success', 'message' => 'Cập nhật thành công']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Cập nhật thất bại: ' . $stmt->error]);
    }
    exit();
}

// ── DELETE: Xóa lịch trình ──────────────────────────────────────────────
if ($method === 'DELETE') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int) ($input['id'] ?? 0);
    }

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'ID không hợp lệ']);
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM timetable WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $id, $user_id);
    if ($stmt->execute()) {
        $stmt->close();
        echo json_encode(['status' => 'success', 'message' => 'Đã xóa môn học khỏi thời khóa biểu']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Xóa thất bại: ' . $stmt->error]);
    }
    exit();
}

http_response_code(405);
echo json_encode(['status' => 'error', 'message' => 'Phương thức không được hỗ trợ']);
