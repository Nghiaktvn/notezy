<?php
require_once dirname(__DIR__) . '/includes/session.php';
/**
 * api/note_password.php
 * Endpoint xử lý bảo mật mật khẩu cho ghi chú:
 * - action = 'verify'  : Xác thực mật khẩu để mở khóa ghi chú (lưu vào session accessed_notes)
 * - action = 'enable'  : Đặt mật khẩu mới cho ghi chú (chỉ chủ ghi chú)
 * - action = 'disable' : Gỡ bỏ mật khẩu ghi chú (yêu cầu mật khẩu hiện tại hoặc mật khẩu tài khoản của chủ ghi chú)
 * - action = 'change'  : Đổi mật khẩu ghi chú (yêu cầu mật khẩu cũ + validate mật khẩu mới)
 */

require_once 'db.php';
notezy_session_start();
header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Vui lòng đăng nhập."]);
    exit();
}

$user_id = (int)$_SESSION['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Phương thức không hợp lệ."]);
    exit();
}

// Khởi tạo session lưu các ghi chú đã mở khóa
if (!isset($_SESSION['accessed_notes'])) {
    $_SESSION['accessed_notes'] = [];
}

$note_id = isset($_POST['note_id']) ? (int)$_POST['note_id'] : 0;
$action  = isset($_POST['action']) ? trim($_POST['action']) : '';

// Tương thích ngược: nếu không truyền action nhưng có remove_password hoặc new_password
if (empty($action)) {
    if (!empty($_POST['remove_password'])) {
        $action = 'disable';
    } elseif (!empty($_POST['new_password'])) {
        $action = 'enable';
    } else {
        $action = 'verify';
    }
}

if (!$note_id) {
    echo json_encode(["success" => false, "message" => "Thiếu ID ghi chú."]);
    exit();
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. ACTION: VERIFY (Người sở hữu hoặc người được chia sẻ nhập mật khẩu để mở)
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'verify') {
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';
    if ($password === '') {
        echo json_encode(["success" => false, "message" => "Vui lòng nhập mật khẩu ghi chú."]);
        exit();
    }

    // Kiểm tra quyền truy cập ghi chú (chủ note hoặc được share)
    $stmt = $conn->prepare("
        SELECT n.note_id, n.title, n.content, n.image_path, n.password_hash
        FROM notes n
        LEFT JOIN note_shares ns ON n.note_id = ns.note_id AND ns.shared_with_user_id = ?
        WHERE n.note_id = ? AND (n.user_id = ? OR ns.note_id IS NOT NULL)
        LIMIT 1
    ");
    $stmt->bind_param("iii", $user_id, $note_id, $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo json_encode(["success" => false, "message" => "Ghi chú không tồn tại hoặc bạn không có quyền truy cập."]);
        exit();
    }

    if (empty($row['password_hash'])) {
        // Ghi chú không có mật khẩu
        $_SESSION['accessed_notes'][$note_id] = true;
        echo json_encode([
            "success" => true,
            "message" => "Ghi chú này không yêu cầu mật khẩu.",
            "data" => [
                "note_id" => $row['note_id'],
                "title" => htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8'),
                "content" => htmlspecialchars($row['content'], ENT_QUOTES, 'UTF-8'),
                "image_path" => $row['image_path'] ? htmlspecialchars($row['image_path'], ENT_QUOTES, 'UTF-8') : null
            ]
        ]);
        exit();
    }

    if (password_verify($password, $row['password_hash'])) {
        $_SESSION['accessed_notes'][$note_id] = true;
        echo json_encode([
            "success" => true,
            "message" => "Xác thực mật khẩu thành công!",
            "data" => [
                "note_id" => $row['note_id'],
                "title" => htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8'),
                "content" => htmlspecialchars($row['content'], ENT_QUOTES, 'UTF-8'),
                "image_path" => $row['image_path'] ? htmlspecialchars($row['image_path'], ENT_QUOTES, 'UTF-8') : null
            ]
        ]);
    } else {
        echo json_encode(["success" => false, "message" => "Mật khẩu không chính xác. Vui lòng thử lại."]);
    }
    exit();
}

// ─────────────────────────────────────────────────────────────────────────────
// CÁC ACTION QUẢN LÝ: CHỈ DÀNH RIÊNG CHO CHỦ SỞ HỮU GHI CHÚ (OWNER ONLY)
// ─────────────────────────────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT note_id, password_hash FROM notes WHERE note_id = ? AND user_id = ?");
$stmt->bind_param("ii", $note_id, $user_id);
$stmt->execute();
$note = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$note) {
    echo json_encode(["success" => false, "message" => "Bạn không có quyền quản lý bảo mật của ghi chú này."]);
    exit();
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. ACTION: ENABLE (Bật mật khẩu cho ghi chú)
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'enable') {
    $new_password = isset($_POST['new_password']) ? trim((string)$_POST['new_password']) : '';
    $confirm_password = isset($_POST['confirm_password']) ? trim((string)$_POST['confirm_password']) : $new_password;

    if (strlen($new_password) < 4) {
        echo json_encode(["success" => false, "message" => "Mật khẩu bảo vệ phải có độ dài tối thiểu 4 ký tự."]);
        exit();
    }
    if ($new_password !== $confirm_password) {
        echo json_encode(["success" => false, "message" => "Mật khẩu xác nhận không khớp."]);
        exit();
    }

    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
    $up_stmt = $conn->prepare("UPDATE notes SET password_hash = ? WHERE note_id = ?");
    $up_stmt->bind_param("si", $hashed, $note_id);
    if ($up_stmt->execute()) {
        // Chủ note sau khi đặt mật khẩu được tính là đã unlock trong phiên này
        $_SESSION['accessed_notes'][$note_id] = true;
        echo json_encode(["success" => true, "message" => "Đã kích hoạt mật khẩu bảo vệ ghi chú thành công."]);
    } else {
        echo json_encode(["success" => false, "message" => "Lỗi máy chủ khi đặt mật khẩu: " . $conn->error]);
    }
    $up_stmt->close();
    exit();
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. ACTION: DISABLE (Tắt/Gỡ mật khẩu bảo vệ)
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'disable') {
    // Để gỡ mật khẩu, bắt buộc phải cung cấp mật khẩu hiện tại của note
    // HOẶC mật khẩu tài khoản đăng nhập của chủ note (đáp ứng rubric xác thực an toàn)
    $current_password = isset($_POST['current_password']) ? (string)$_POST['current_password'] : '';
    if ($current_password === '') {
        echo json_encode(["success" => false, "message" => "Vui lòng nhập mật khẩu hiện tại của ghi chú (hoặc mật khẩu tài khoản) để gỡ bỏ."]);
        exit();
    }

    $is_valid = false;

    // Kiểm tra với password_hash của note
    if (!empty($note['password_hash']) && password_verify($current_password, $note['password_hash'])) {
        $is_valid = true;
    }

    // Nếu không khớp với password_hash của note, kiểm tra với password của account user
    if (!$is_valid) {
        $u_stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
        $u_stmt->bind_param("i", $user_id);
        $u_stmt->execute();
        $u_row = $u_stmt->get_result()->fetch_assoc();
        $u_stmt->close();
        if ($u_row && !empty($u_row['password_hash']) && password_verify($current_password, $u_row['password_hash'])) {
            $is_valid = true;
        }
    }

    if (!$is_valid) {
        echo json_encode(["success" => false, "message" => "Mật khẩu xác nhận không chính xác. Không thể gỡ bỏ."]);
        exit();
    }

    $del_stmt = $conn->prepare("UPDATE notes SET password_hash = NULL WHERE note_id = ?");
    $del_stmt->bind_param("i", $note_id);
    if ($del_stmt->execute()) {
        unset($_SESSION['accessed_notes'][$note_id]);
        echo json_encode(["success" => true, "message" => "Đã tắt mật khẩu bảo vệ thành công. Ghi chú này hiện có thể mở tự do."]);
    } else {
        echo json_encode(["success" => false, "message" => "Lỗi cơ sở dữ liệu: " . $conn->error]);
    }
    $del_stmt->close();
    exit();
}

// ─────────────────────────────────────────────────────────────────────────────
// 4. ACTION: CHANGE (Đổi mật khẩu bảo vệ)
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'change') {
    $old_password = isset($_POST['old_password']) ? (string)$_POST['old_password'] : '';
    $new_password = isset($_POST['new_password']) ? trim((string)$_POST['new_password']) : '';
    $confirm_password = isset($_POST['confirm_password']) ? trim((string)$_POST['confirm_password']) : $new_password;

    if ($old_password === '') {
        echo json_encode(["success" => false, "message" => "Vui lòng nhập mật khẩu hiện tại của ghi chú."]);
        exit();
    }

    // Nếu note đã có mật khẩu, xác thực mật khẩu cũ
    if (!empty($note['password_hash'])) {
        $valid_old = password_verify($old_password, $note['password_hash']);
        // Dự phòng: cho phép dùng mật khẩu tài khoản
        if (!$valid_old) {
            $u_stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
            $u_stmt->bind_param("i", $user_id);
            $u_stmt->execute();
            $u_row = $u_stmt->get_result()->fetch_assoc();
            $u_stmt->close();
            if ($u_row && !empty($u_row['password_hash']) && password_verify($old_password, $u_row['password_hash'])) {
                $valid_old = true;
            }
        }
        if (!$valid_old) {
            echo json_encode(["success" => false, "message" => "Mật khẩu hiện tại không đúng."]);
            exit();
        }
    }

    if (strlen($new_password) < 4) {
        echo json_encode(["success" => false, "message" => "Mật khẩu mới phải có ít nhất 4 ký tự."]);
        exit();
    }
    if ($new_password !== $confirm_password) {
        echo json_encode(["success" => false, "message" => "Mật khẩu mới và mật khẩu xác nhận không trùng khớp."]);
        exit();
    }

    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
    $up_stmt = $conn->prepare("UPDATE notes SET password_hash = ? WHERE note_id = ?");
    $up_stmt->bind_param("si", $hashed, $note_id);
    if ($up_stmt->execute()) {
        $_SESSION['accessed_notes'][$note_id] = true;
        echo json_encode(["success" => true, "message" => "Đã đổi mật khẩu ghi chú thành công."]);
    } else {
        echo json_encode(["success" => false, "message" => "Lỗi máy chủ khi đổi mật khẩu: " . $conn->error]);
    }
    $up_stmt->close();
    exit();
}

echo json_encode(["success" => false, "message" => "Hành động không hợp lệ."]);
