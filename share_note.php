<?php
require_once __DIR__ . '/includes/session.php';
/**
 * share_note.php
 * Backend API xử lý chia sẻ ghi chú (ID 23 - Shared Note)
 * Hỗ trợ: Share ghi chú (email/username), phân quyền Viewer/Editor, cập nhật quyền và thu hồi chia sẻ (Unshare).
 */
require_once 'db.php';
notezy_session_start();

header('Content-Type: application/json; charset=utf-8');

// Bước 1: Kiểm tra đăng nhập
if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Bạn chưa đăng nhập. Vui lòng đăng nhập để tiếp tục.'
    ]);
    exit();
}

$current_user_id = (int) $_SESSION['id'];
$conn = create_connect();

notezy_ensure_user_last_seen_column($conn);
@$conn->query("CREATE TABLE IF NOT EXISTS chat_messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message_type ENUM('text','note') NOT NULL DEFAULT 'text',
    message TEXT NULL,
    note_id INT NULL,
    permission ENUM('read','write') NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_chat_pair (sender_id, receiver_id, created_at),
    INDEX idx_chat_note (note_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

function add_chat_note_card($conn, int $sender_id, int $receiver_id, int $note_id, string $note_title, string $permission): void {
    $message = 'Đã chia sẻ ghi chú "' . $note_title . '".';
    $stmt = $conn->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message_type, message, note_id, permission) VALUES (?, ?, 'note', ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param('iisis', $sender_id, $receiver_id, $message, $note_id, $permission);
        $stmt->execute();
        $stmt->close();
    }
}

// Xử lý GET: Lấy danh sách người đang được chia sẻ cho một ghi chú
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $note_id = isset($_GET['note_id']) ? (int) $_GET['note_id'] : 0;
    if ($note_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Mã ghi chú không hợp lệ.']);
        exit();
    }

    // Kiểm tra ghi chú thuộc về người dùng hiện tại (Owner)
    $chk = $conn->prepare("SELECT note_id, title FROM notes WHERE note_id = ? AND user_id = ?");
    $chk->bind_param('ii', $note_id, $current_user_id);
    $chk->execute();
    $note_row = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$note_row) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Bạn không có quyền quản lý chia sẻ cho ghi chú này.']);
        exit();
    }

    // Lấy danh sách shares
    $sql = "SELECT ns.share_id, ns.shared_with_user_id, ns.permission, ns.shared_at,
                   u.username, u.email, u.firstname, u.lastname
            FROM note_shares ns
            JOIN users u ON ns.shared_with_user_id = u.id
            WHERE ns.note_id = ?
            ORDER BY ns.shared_at DESC";
    $stm = $conn->prepare($sql);
    $stm->bind_param('i', $note_id);
    $stm->execute();
    $shares = $stm->get_result()->fetch_all(MYSQLI_ASSOC);
    $stm->close();

    echo json_encode([
        'success' => true,
        'note_title' => $note_row['title'],
        'shares' => $shares
    ]);
    exit();
}

// Xử lý POST: Chia sẻ / Cập nhật quyền / Thu hồi chia sẻ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? 'share');
    $note_id = isset($_POST['note_id']) ? (int) $_POST['note_id'] : 0;

    if ($note_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Mã ghi chú không hợp lệ.']);
        exit();
    }

    // Bước 2: Kiểm tra note thuộc về người share (Chỉ Owner mới được chia sẻ)
    $stmt_owner = $conn->prepare("SELECT note_id, title FROM notes WHERE note_id = ? AND user_id = ?");
    $stmt_owner->bind_param('ii', $note_id, $current_user_id);
    $stmt_owner->execute();
    $note = $stmt_owner->get_result()->fetch_assoc();
    $stmt_owner->close();

    if (!$note) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Bạn không phải chủ sở hữu (Owner) của ghi chú này nên không thể chia sẻ.'
        ]);
        exit();
    }

    // Thao tác hủy chia sẻ (Unshare)
    if ($action === 'unshare') {
        $share_id = isset($_POST['share_id']) ? (int) $_POST['share_id'] : 0;
        $target_user_id = isset($_POST['target_user_id']) ? (int) $_POST['target_user_id'] : 0;

        if ($share_id > 0) {
            $del = $conn->prepare("DELETE FROM note_shares WHERE share_id = ? AND note_id = ? AND shared_by_user_id = ?");
            $del->bind_param('iii', $share_id, $note_id, $current_user_id);
            $del->execute();
            $affected = $del->affected_rows;
            $del->close();
        } elseif ($target_user_id > 0) {
            $del = $conn->prepare("DELETE FROM note_shares WHERE note_id = ? AND shared_with_user_id = ? AND shared_by_user_id = ?");
            $del->bind_param('iii', $note_id, $target_user_id, $current_user_id);
            $del->execute();
            $affected = $del->affected_rows;
            $del->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Thiếu thông tin người cần hủy chia sẻ.']);
            exit();
        }

        if ($affected > 0) {
            echo json_encode(['success' => true, 'message' => 'Đã hủy quyền chia sẻ thành công.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy thông tin chia sẻ để xóa.']);
        }
        exit();
    }

    // Thao tác Share / Cập nhật quyền
    // Nhận email hoặc username từ form
    $target_input = trim($_POST['email'] ?? $_POST['sharedWithUsername'] ?? '');
    $raw_permission = trim($_POST['permission'] ?? 'read');

    if ($target_input === '') {
        echo json_encode(['success' => false, 'message' => 'Vui lòng nhập Email hoặc Tên người dùng cần chia sẻ.']);
        exit();
    }

    // Chuẩn hóa quyền: 'read' (Viewer) hoặc 'write' (Editor)
    $permission = 'read';
    if ($raw_permission === 'write' || strtolower($raw_permission) === 'editor') {
        $permission = 'write';
    }

    // Bước 4: Kiểm tra người được share tồn tại trong hệ thống
    $stmt_user = $conn->prepare("SELECT id, username, email FROM users WHERE (email = ? OR username = ?) AND activated = 1 LIMIT 1");
    $stmt_user->bind_param('ss', $target_input, $target_input);
    $stmt_user->execute();
    $target_user = $stmt_user->get_result()->fetch_assoc();
    $stmt_user->close();

    if (!$target_user) {
        echo json_encode([
            'success' => false,
            'message' => 'Không tìm thấy người dùng hoặc tài khoản này chưa được kích hoạt.'
        ]);
        exit();
    }

    $target_user_id = (int) $target_user['id'];

    // Không cho phép tự chia sẻ cho chính mình
    if ($target_user_id === $current_user_id) {
        echo json_encode([
            'success' => false,
            'message' => 'Bạn là chủ sở hữu của ghi chú này, không thể tự chia sẻ cho chính mình.'
        ]);
        exit();
    }

    // Bước 6: Kiểm tra share trùng
    $stmt_check = $conn->prepare("SELECT share_id, permission FROM note_shares WHERE note_id = ? AND shared_with_user_id = ?");
    $stmt_check->bind_param('ii', $note_id, $target_user_id);
    $stmt_check->execute();
    $existing_share = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();

    $perm_label = ($permission === 'write') ? 'Editor (Chỉnh sửa)' : 'Viewer (Chỉ đọc)';

    if ($existing_share) {
        // Đã chia sẻ trước đó -> Cập nhật quyền mới
        $share_id = (int) $existing_share['share_id'];
        $stmt_upd = $conn->prepare("UPDATE note_shares SET permission = ?, shared_at = CURRENT_TIMESTAMP WHERE share_id = ?");
        $stmt_upd->bind_param('si', $permission, $share_id);
        if ($stmt_upd->execute()) {
            $stmt_upd->close();
            add_chat_note_card($conn, $current_user_id, $target_user_id, $note_id, $note['title'], $permission);
            echo json_encode([
                'success' => true,
                'message' => "Đã cập nhật quyền thành '{$perm_label}' cho {$target_user['username']} ({$target_user['email']}).",
                'permission' => $permission
            ]);
            exit();
        } else {
            $stmt_upd->close();
            echo json_encode(['success' => false, 'message' => 'Lỗi khi cập nhật quyền: ' . $conn->error]);
            exit();
        }
    } else {
        // Chưa chia sẻ -> Thêm bản ghi mới vào note_shares
        $stmt_ins = $conn->prepare("INSERT INTO note_shares (note_id, shared_with_user_id, permission, shared_by_user_id) VALUES (?, ?, ?, ?)");
        $stmt_ins->bind_param('iisi', $note_id, $target_user_id, $permission, $current_user_id);
        if ($stmt_ins->execute()) {
            $stmt_ins->close();
            add_chat_note_card($conn, $current_user_id, $target_user_id, $note_id, $note['title'], $permission);
            echo json_encode([
                'success' => true,
                'message' => "Đã chia sẻ thành công với quyền '{$perm_label}' cho {$target_user['username']} ({$target_user['email']}).",
                'permission' => $permission
            ]);
            exit();
        } else {
            $stmt_ins->close();
            echo json_encode(['success' => false, 'message' => 'Lỗi khi tạo chia sẻ: ' . $conn->error]);
            exit();
        }
    }
}
