<?php
require_once dirname(__DIR__) . '/includes/session.php';
require_once __DIR__ . '/../config/database.php';

notezy_session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Vui lòng đăng nhập để chia sẻ lịch.']);
    exit();
}
if (!$conn || $conn->connect_error) {
    http_response_code(503);
    echo json_encode(['status' => 'error', 'message' => 'Không thể kết nối cơ sở dữ liệu.']);
    exit();
}

$userId = (int) $_SESSION['id'];
$method = $_SERVER['REQUEST_METHOD'];
$payload = json_decode(file_get_contents('php://input'), true) ?: [];

function timetable_owner(mysqli $conn, int $timetableId, int $userId): bool {
    $stmt = $conn->prepare('SELECT id FROM timetable WHERE id = ? AND user_id = ?');
    if (!$stmt) return false;
    $stmt->bind_param('ii', $timetableId, $userId);
    $stmt->execute();
    $owned = $stmt->get_result()->num_rows === 1;
    $stmt->close();
    return $owned;
}

if ($method === 'GET') {
    $timetableId = (int) ($_GET['timetable_id'] ?? 0);
    if ($timetableId < 1 || !timetable_owner($conn, $timetableId, $userId)) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy lịch học.']);
        exit();
    }
    $stmt = $conn->prepare('SELECT s.id, s.permission, s.created_at, u.username, u.email, u.firstname, u.lastname FROM timetable_shares s JOIN users u ON u.id = s.shared_with_user_id WHERE s.timetable_id = ? ORDER BY s.created_at DESC');
    $stmt->bind_param('i', $timetableId);
    $stmt->execute();
    echo json_encode(['status' => 'success', 'data' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
    exit();
}

if ($method === 'POST') {
    $timetableId = (int) ($payload['timetable_id'] ?? 0);
    $recipient = trim((string) ($payload['recipient'] ?? ''));
    $permission = ($payload['permission'] ?? 'read') === 'write' ? 'write' : 'read';
    if ($timetableId < 1 || $recipient === '' || !timetable_owner($conn, $timetableId, $userId)) {
        http_response_code(422);
        echo json_encode(['status' => 'error', 'message' => 'Thông tin chia sẻ chưa hợp lệ.']);
        exit();
    }
    $find = $conn->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
    $find->bind_param('ss', $recipient, $recipient);
    $find->execute();
    $recipientRow = $find->get_result()->fetch_assoc();
    if (!$recipientRow || (int) $recipientRow['id'] === $userId) {
        http_response_code(422);
        echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy người dùng để chia sẻ.']);
        exit();
    }
    $recipientId = (int) $recipientRow['id'];
    $share = $conn->prepare('INSERT INTO timetable_shares (timetable_id, owner_user_id, shared_with_user_id, permission) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE permission = VALUES(permission)');
    $share->bind_param('iiis', $timetableId, $userId, $recipientId, $permission);
    if (!$share->execute()) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Không thể chia sẻ lịch lúc này.']);
        exit();
    }
    echo json_encode(['status' => 'success', 'message' => 'Đã chia sẻ lịch học.', 'permission' => $permission]);
    exit();
}

if ($method === 'DELETE') {
    $shareId = (int) ($payload['share_id'] ?? $_GET['share_id'] ?? 0);
    $stmt = $conn->prepare('DELETE s FROM timetable_shares s JOIN timetable t ON t.id = s.timetable_id WHERE s.id = ? AND t.user_id = ?');
    $stmt->bind_param('ii', $shareId, $userId);
    $stmt->execute();
    if ($stmt->affected_rows !== 1) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy quyền chia sẻ.']);
        exit();
    }
    echo json_encode(['status' => 'success', 'message' => 'Đã thu hồi quyền truy cập.']);
    exit();
}

http_response_code(405);
echo json_encode(['status' => 'error', 'message' => 'Phương thức không được hỗ trợ.']);
