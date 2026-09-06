<?php
require_once __DIR__ . '/includes/session.php';
require_once('db.php');
notezy_session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Bạn chưa đăng nhập.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['noteId'])) {
    echo json_encode(['success' => false, 'message' => 'Yêu cầu không hợp lệ.']);
    exit;
}

$user_id = (int) $_SESSION['id'];
$note_id = (int) $_POST['noteId'];
$action = $_POST['action'] ?? 'archive'; // 'archive' or 'restore'
$archived_val = ($action === 'restore') ? 0 : 1;

try {
    $stmt = $conn->prepare("UPDATE notes SET archived = ? WHERE note_id = ? AND user_id = ?");
    if (!$stmt) {
        throw new Exception("Lỗi chuẩn bị truy vấn: " . $conn->error);
    }
    $stmt->bind_param("iii", $archived_val, $note_id, $user_id);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        $msg = ($action === 'restore') ? 'Ghi chú đã được khôi phục.' : 'Ghi chú đã được lưu trữ.';
        echo json_encode(['success' => true, 'message' => $msg]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ghi chú không tồn tại hoặc không có thay đổi.']);
    }
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . htmlspecialchars($e->getMessage())]);
}
$conn->close();
?>
