<?php
require_once __DIR__ . '/includes/session.php';
require_once('db.php');
notezy_session_start();

// Thiết lập header trả về JSON
header('Content-Type: application/json');

// Kiểm tra đăng nhập
if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Bạn chưa đăng nhập.']);
    exit;
}

// Kiểm tra yêu cầu POST và noteId
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['noteId'])) {
    echo json_encode(['success' => false, 'message' => 'Yêu cầu không hợp lệ.']);
    exit;
}

$user_id = (int) $_SESSION['id'];
$note_id = (int) $_POST['noteId'];
$is_shared = isset($_POST['isShared']) && $_POST['isShared'] == 1;

try {
    // Bắt đầu giao dịch
    $conn->begin_transaction();

    if ($is_shared) {
        // Xóa quyền truy cập từ note_shares
        $stmt = $conn->prepare("DELETE FROM note_shares WHERE note_id = ? AND shared_with_user_id = ?");
        if (!$stmt) {
            throw new Exception("Lỗi chuẩn bị truy vấn note_shares: " . $conn->error);
        }
        $stmt->bind_param("ii", $note_id, $user_id);
        $stmt->execute();
        $affected_rows = $stmt->affected_rows;
        $stmt->close();

        // Cam kết giao dịch
        $conn->commit();

        if ($affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Quyền truy cập đã được xóa.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền truy cập vào ghi chú này.']);
        }
    } else {
        // Xóa liên kết nhãn trong bảng note_labels
        $stmt = $conn->prepare("DELETE FROM note_labels WHERE note_id = ?");
        if (!$stmt) {
            throw new Exception("Lỗi chuẩn bị truy vấn note_labels: " . $conn->error);
        }
        $stmt->bind_param("i", $note_id);
        $stmt->execute();
        $stmt->close();

        // Xóa quyền chia sẻ trong bảng note_shares
        $stmt = $conn->prepare("DELETE FROM note_shares WHERE note_id = ?");
        if (!$stmt) {
            throw new Exception("Lỗi chuẩn bị truy vấn note_shares: " . $conn->error);
        }
        $stmt->bind_param("i", $note_id);
        $stmt->execute();
        $stmt->close();

        // Lấy thông tin image_path trước khi xóa
        $stmt_img = $conn->prepare("SELECT image_path FROM notes WHERE note_id = ? AND user_id = ?");
        $stmt_img->bind_param("ii", $note_id, $user_id);
        $stmt_img->execute();
        $res_img = $stmt_img->get_result();
        if ($row_img = $res_img->fetch_assoc()) {
            $image_path = $row_img['image_path'];
            if (!empty($image_path) && file_exists(__DIR__ . '/' . $image_path)) {
                unlink(__DIR__ . '/' . $image_path);
            }
        }
        $stmt_img->close();

        // Xóa ghi chú trong bảng notes
        $stmt = $conn->prepare("DELETE FROM notes WHERE note_id = ? AND user_id = ?");
        if (!$stmt) {
            throw new Exception("Lỗi chuẩn bị truy vấn notes: " . $conn->error);
        }
        $stmt->bind_param("ii", $note_id, $user_id);
        $stmt->execute();
        $affected_rows = $stmt->affected_rows;
        $stmt->close();

        // Cam kết giao dịch
        $conn->commit();

        if ($affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Ghi chú đã được xóa.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Ghi chú không tồn tại hoặc bạn không phải chủ sở hữu.']);
        }
    }
} catch (Exception $e) {
    // Hoàn tác giao dịch nếu có lỗi
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Lỗi khi xóa: ' . htmlspecialchars($e->getMessage())]);
}

$conn->close();
?>