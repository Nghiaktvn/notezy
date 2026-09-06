<?php
require_once dirname(__DIR__) . '/includes/session.php';
/**
 * api/note_pin.php — 4-digit PIN / Security Code lock for notebooks (sổ ghi chú).
 *
 * Sổ ghi chú được bảo mật bằng mã 4 số:
 *   - Mã bảo mật gồm đúng 4 chữ số (numeric only: 0000-9999).
 *   - Khi ĐẶT mã lần đầu: Nhập mã 4 số và xác nhận.
 *   - Khi ĐỔI hoặc GỠ mã: Có thể xác nhận bằng mã 4 số hiện tại hoặc mật khẩu tài khoản.
 *   - Khi MỞ SỔ: Nhập đúng mã 4 số đã thiết lập trước đó để xem nội dung và tùy chỉnh sự kiện của sổ.
 *   - Mở khóa hợp lệ sẽ được lưu vào phiên đăng nhập (note_pin_unlocks và session).
 *
 * Actions:
 *   action=set     note_id, pin, pin_confirm, (optional: old_pin hoặc account_password)
 *   action=verify  note_id, pin
 *   action=remove  note_id, (old_pin hoặc account_password)
 *   action=status  note_id
 */

require_once 'db.php';
notezy_session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id'])) {
    echo json_encode(["success" => false, "message" => "Vui lòng đăng nhập."]);
    exit();
}

if (!isset($_SESSION['pin_unlocked_notes'])) {
    $_SESSION['pin_unlocked_notes'] = [];
}

$user_id = (int) $_SESSION['id'];
$session_id = session_id();

function respond($ok, $message, $extra = []) {
    echo json_encode(array_merge(["success" => $ok, "message" => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit();
}

function get_owned_note($conn, $note_id, $user_id) {
    $stmt = $conn->prepare("SELECT note_id, title, content, pin_hash, background_color, text_color, font_family, reminder_at FROM notes WHERE note_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $note_id, $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row;
}

function get_shared_note($conn, $note_id, $user_id) {
    $stmt = $conn->prepare(
        "SELECT n.note_id, n.title, n.content, n.pin_hash, n.background_color, n.text_color, n.font_family, n.reminder_at
         FROM notes n
         INNER JOIN note_shares ns ON ns.note_id = n.note_id
         WHERE n.note_id = ? AND ns.shared_with_user_id = ?"
    );
    $stmt->bind_param("ii", $note_id, $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row;
}

function is_unlocked_this_session($conn, $note_id, $session_id) {
    if (!empty($_SESSION['pin_unlocked_notes'][$note_id])) {
        return true;
    }
    $stmt = $conn->prepare("SELECT id FROM note_pin_unlocks WHERE note_id = ? AND session_id = ?");
    $stmt->bind_param("is", $note_id, $session_id);
    $stmt->execute();
    $found = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    if ($found) {
        $_SESSION['pin_unlocked_notes'][$note_id] = true;
    }
    return $found;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$note_id = (int) ($_POST['note_id'] ?? $_GET['note_id'] ?? 0);

if (!$note_id) {
    respond(false, "Thiếu ID ghi chú.");
}

$note = get_owned_note($conn, $note_id, $user_id);
if (!$note && in_array($action, ['verify', 'status'], true)) {
    $note = get_shared_note($conn, $note_id, $user_id);
}
if (!$note) {
    respond(false, "Bạn không có quyền với ghi chú này.");
}

switch ($action) {

    case 'status':
        $locked = !empty($note['pin_hash']);
        $unlocked = $locked ? is_unlocked_this_session($conn, $note_id, $session_id) : true;
        respond(true, "OK", ["pin_locked" => $locked, "unlocked" => $unlocked]);
        break;

    case 'set':
        if ($method !== 'POST') respond(false, "Phương thức yêu cầu không hợp lệ.");

        $pin = trim((string) ($_POST['pin'] ?? ''));
        $pin_confirm = trim((string) ($_POST['pin_confirm'] ?? ''));
        $old_pin = trim((string) ($_POST['old_pin'] ?? ''));
        $account_password = (string) ($_POST['account_password'] ?? '');

        if (!preg_match('/^\d{4}$/', $pin)) {
            respond(false, "Mã bảo mật phải gồm đúng 4 chữ số (0000 - 9999).");
        }
        if ($pin !== $pin_confirm) {
            respond(false, "Hai lần nhập mã bảo mật 4 số không khớp nhau.");
        }

        // Nếu sổ đã có mã PIN từ trước, yêu cầu xác nhận mã cũ hoặc mật khẩu tài khoản
        if (!empty($note['pin_hash'])) {
            $verified = false;
            if ($old_pin !== '' && password_verify($old_pin, $note['pin_hash'])) {
                $verified = true;
            } elseif ($account_password !== '') {
                $u = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
                $u->bind_param("i", $user_id);
                $u->execute();
                $urow = $u->get_result()->fetch_assoc();
                $u->close();
                if ($urow && !empty($urow['password_hash']) && password_verify($account_password, $urow['password_hash'])) {
                    $verified = true;
                }
            }

            if (!$verified) {
                respond(false, "Vui lòng nhập đúng mã bảo mật 4 số hiện tại hoặc mật khẩu đăng nhập để đổi mã.");
            }
        }

        $pin_hash = password_hash($pin, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE notes SET pin_hash = ?, pin_set_at = NOW() WHERE note_id = ? AND user_id = ?");
        $stmt->bind_param("sii", $pin_hash, $note_id, $user_id);
        if (!$stmt->execute()) {
            respond(false, "Lỗi khi lưu mã bảo mật: " . $conn->error);
        }
        $stmt->close();

        // Đánh dấu đã mở khóa cho phiên làm việc hiện tại
        $_SESSION['pin_unlocked_notes'][$note_id] = true;
        $ins = $conn->prepare(
            "INSERT INTO note_pin_unlocks (note_id, user_id, session_id) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE unlocked_at = NOW()"
        );
        $ins->bind_param("iis", $note_id, $user_id, $session_id);
        $ins->execute();
        $ins->close();

        respond(true, "Đã bật bảo mật 4 số cho sổ ghi chú thành công!");
        break;

    case 'verify':
        if ($method !== 'POST') respond(false, "Phương thức yêu cầu không hợp lệ.");

        if (empty($note['pin_hash'])) {
            $_SESSION['pin_unlocked_notes'][$note_id] = true;
            respond(true, "Ghi chú này không có khóa bảo mật.", [
                "pin_locked" => false,
                "unlocked" => true,
                "note" => [
                    "note_id" => $note['note_id'],
                    "title" => $note['title'],
                    "content" => $note['content']
                ]
            ]);
        }

        $pin = trim((string) ($_POST['pin'] ?? ''));
        if (!preg_match('/^\d{4}$/', $pin)) {
            respond(false, "Mã bảo mật phải gồm đúng 4 chữ số.");
        }

        if (!password_verify($pin, $note['pin_hash'])) {
            respond(false, "Mã bảo mật 4 số không chính xác. Vui lòng thử lại.");
        }

        $_SESSION['pin_unlocked_notes'][$note_id] = true;

        $ins = $conn->prepare(
            "INSERT INTO note_pin_unlocks (note_id, user_id, session_id) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE unlocked_at = NOW()"
        );
        $ins->bind_param("iis", $note_id, $user_id, $session_id);
        $ins->execute();
        $ins->close();

        respond(true, "Mở khóa sổ ghi chú thành công!", [
            "pin_locked" => true,
            "unlocked" => true,
            "note" => [
                "note_id" => $note['note_id'],
                "title" => $note['title'],
                "content" => $note['content']
            ]
        ]);
        break;

    case 'remove':
        if ($method !== 'POST') respond(false, "Phương thức yêu cầu không hợp lệ.");

        if (empty($note['pin_hash'])) {
            respond(true, "Sổ ghi chú này chưa được cài mã bảo mật.");
        }

        $old_pin = trim((string) ($_POST['old_pin'] ?? ''));
        $account_password = (string) ($_POST['account_password'] ?? '');

        $verified = false;
        if ($old_pin !== '' && password_verify($old_pin, $note['pin_hash'])) {
            $verified = true;
        } elseif ($account_password !== '') {
            $u = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
            $u->bind_param("i", $user_id);
            $u->execute();
            $urow = $u->get_result()->fetch_assoc();
            $u->close();
            if ($urow && !empty($urow['password_hash']) && password_verify($account_password, $urow['password_hash'])) {
                $verified = true;
            }
        }

        if (!$verified) {
            respond(false, "Vui lòng nhập đúng mã bảo mật 4 số hiện tại hoặc mật khẩu đăng nhập để gỡ khóa.");
        }

        $stmt = $conn->prepare("UPDATE notes SET pin_hash = NULL, pin_set_at = NULL WHERE note_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $note_id, $user_id);
        $stmt->execute();
        $stmt->close();

        unset($_SESSION['pin_unlocked_notes'][$note_id]);

        $del = $conn->prepare("DELETE FROM note_pin_unlocks WHERE note_id = ?");
        $del->bind_param("i", $note_id);
        $del->execute();
        $del->close();

        respond(true, "Đã gỡ bảo mật 4 số cho sổ ghi chú thành công!");
        break;

    default:
        respond(false, "Hành động không hợp lệ.");
}

