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

if (!$conn || $conn->connect_error) {
    http_response_code(503);
    echo json_encode(['status' => 'error', 'message' => 'Không thể kết nối cơ sở dữ liệu']);
    exit();
}

function timetable_note_owned(mysqli $conn, int $noteId, int $userId): bool {
    $stmt = $conn->prepare('SELECT note_id FROM notes WHERE note_id = ? AND user_id = ? AND archived = 0');
    if (!$stmt) return false;
    $stmt->bind_param('ii', $noteId, $userId);
    $stmt->execute();
    $owned = $stmt->get_result()->num_rows === 1;
    $stmt->close();
    return $owned;
}

function timetable_input(array $input, int $userId, mysqli $conn): array {
    $title = trim((string) ($input['title'] ?? ''));
    $day = (int) ($input['day_of_week'] ?? 1);
    $start = trim((string) ($input['start_time'] ?? ''));
    $end = trim((string) ($input['end_time'] ?? ''));
    $date = !empty($input['specific_date']) ? trim((string) $input['specific_date']) : null;
    $color = trim((string) ($input['color'] ?? '#4f46e5'));
    $reminder = (int) ($input['reminder_minutes'] ?? 15);
    $noteId = !empty($input['note_id']) ? (int) $input['note_id'] : null;

    if ($title === '' || mb_strlen($title) > 255 || $day < 1 || $day > 7
        || !preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end)
        || $start >= $end || !preg_match('/^#[0-9a-fA-F]{6}$/', $color)
        || !in_array($reminder, [-1, 0, 5, 10, 15, 30, 60], true)) {
        throw new InvalidArgumentException('Thông tin lịch học chưa hợp lệ.');
    }
    if ($date !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new InvalidArgumentException('Ngày cụ thể chưa hợp lệ.');
    }
    if ($noteId !== null && !timetable_note_owned($conn, $noteId, $userId)) {
        throw new InvalidArgumentException('Ghi chú được chọn không tồn tại hoặc không thuộc về bạn.');
    }

    return [
        'title' => $title, 'day' => $day, 'start' => $start, 'end' => $end,
        'location' => trim((string) ($input['location'] ?? '')),
        'teacher' => trim((string) ($input['teacher'] ?? '')),
        'color' => $color, 'note' => trim((string) ($input['note'] ?? '')),
        'reminder' => $reminder, 'date' => $date, 'note_id' => $noteId,
    ];
}

$method = $_SERVER['REQUEST_METHOD'];

// ── GET: Lấy danh sách lịch trình ──────────────────────────────────────
if ($method === 'GET') {
    $mode = $_GET['mode'] ?? 'all'; // 'weekly', 'calendar', 'all'
    
    $sql = "SELECT * FROM (
            SELECT t.id, t.title, t.day_of_week,
                   TIME_FORMAT(start_time, '%H:%i') as start_time, 
                   TIME_FORMAT(end_time, '%H:%i') as end_time, 
                   t.location, t.teacher, t.color, t.note, t.reminder_minutes, t.specific_date,
                   t.note_id, n.title AS linked_note_title, t.user_id AS owner_user_id,
                   0 AS is_shared, 'write' AS permission
            FROM timetable t
            LEFT JOIN notes n ON n.note_id = t.note_id AND n.user_id = t.user_id
            WHERE t.user_id = ?
            UNION ALL
            SELECT t.id, t.title, t.day_of_week,
                   TIME_FORMAT(t.start_time, '%H:%i') as start_time,
                   TIME_FORMAT(t.end_time, '%H:%i') as end_time,
                   t.location, t.teacher, t.color, t.note, t.reminder_minutes, t.specific_date,
                   t.note_id, n.title AS linked_note_title, t.user_id AS owner_user_id,
                   1 AS is_shared, s.permission
            FROM timetable_shares s
            JOIN timetable t ON t.id = s.timetable_id
            LEFT JOIN notes n ON n.note_id = t.note_id AND n.user_id = t.user_id
            WHERE s.shared_with_user_id = ?
            ) AS visible_timetable
            ORDER BY day_of_week ASC, start_time ASC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Lỗi truy vấn: ' . $conn->error]);
        exit();
    }

    $stmt->bind_param('ii', $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = [
            'id' => (int) $row['id'],
            'title' => $row['title'],
            'day_of_week' => (int) $row['day_of_week'],
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time'],
            'location' => $row['location'] ?? '',
            'teacher' => $row['teacher'] ?? '',
            'color' => $row['color'] ?: '#4f46e5',
            'note' => $row['note'] ?? '',
            'reminder_minutes' => (int) $row['reminder_minutes'],
            'specific_date' => $row['specific_date'],
            'note_id' => $row['note_id'] === null ? null : (int) $row['note_id'],
            'linked_note_title' => $row['linked_note_title']
            , 'owner_user_id' => (int) $row['owner_user_id']
            , 'is_shared' => (bool) $row['is_shared']
            , 'can_edit' => $row['permission'] === 'write'
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

    try { $data = timetable_input($input, $user_id, $conn); }
    catch (InvalidArgumentException $e) { http_response_code(422); echo json_encode(['status' => 'error', 'message' => $e->getMessage()]); exit(); }

    $sql = "INSERT INTO timetable (user_id, title, day_of_week, start_time, end_time, location, teacher, color, note, reminder_minutes, specific_date, note_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Lỗi chuẩn bị truy vấn: ' . $conn->error]);
        exit();
    }

    $stmt->bind_param('isissssssisi', $user_id, $data['title'], $data['day'], $data['start'], $data['end'], $data['location'], $data['teacher'], $data['color'], $data['note'], $data['reminder'], $data['date'], $data['note_id']);
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
    if ($id <= 0) { http_response_code(422); echo json_encode(['status' => 'error', 'message' => 'Lịch học không hợp lệ.']); exit(); }
    try { $data = timetable_input($input, $user_id, $conn); }
    catch (InvalidArgumentException $e) { http_response_code(422); echo json_encode(['status' => 'error', 'message' => $e->getMessage()]); exit(); }

    $sql = "UPDATE timetable 
            SET title = ?, day_of_week = ?, start_time = ?, end_time = ?, location = ?, teacher = ?, color = ?, note = ?, reminder_minutes = ?, specific_date = ?, note_id = ?
            WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Lỗi chuẩn bị truy vấn: ' . $conn->error]);
        exit();
    }

    $stmt->bind_param('sissssssisiii', $data['title'], $data['day'], $data['start'], $data['end'], $data['location'], $data['teacher'], $data['color'], $data['note'], $data['reminder'], $data['date'], $data['note_id'], $id, $user_id);
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
