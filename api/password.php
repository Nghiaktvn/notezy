<?php
require_once dirname(__DIR__) . '/includes/session.php';
require_once 'db.php';
notezy_session_start();
header('Content-Type: application/json');

// Retired: note passwords are intentionally unavailable; use api/note_pin.php.
http_response_code(410);
echo json_encode(["status" => "error", "message" => "Khóa bằng mật khẩu đã được thay bằng PIN 6 số."]);
exit();

if (!isset($_SESSION['id'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}
$user_id = (int)$_SESSION['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);
if (!isset($data['note_id'])) {
    echo json_encode(["status" => "error", "message" => "Missing note_id"]);
    exit();
}

$note_id = (int)$data['note_id'];
$action  = $data['action'] ?? '';

// FIX: verify ownership with prepared statement (was raw query interpolation)
$chk = $conn->prepare("SELECT note_id, password_hash FROM notes WHERE note_id = ? AND user_id = ?");
$chk->bind_param("ii", $note_id, $user_id);
$chk->execute();
$res = $chk->get_result();
if ($res->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}
$note = $res->fetch_assoc();

if ($action === 'set') {
    if (empty($data['password'])) {
        echo json_encode(["status" => "error", "message" => "Password cannot be empty"]);
        exit();
    }
    $hashed = password_hash($data['password'], PASSWORD_DEFAULT);
    // FIX: prepared statement (was string interpolation)
    $stmt = $conn->prepare("UPDATE notes SET password_hash = ? WHERE note_id = ? AND user_id = ?");
    $stmt->bind_param("sii", $hashed, $note_id, $user_id);
    $stmt->execute();
    echo json_encode(["status" => "success", "message" => "Password set successfully"]);

} elseif ($action === 'remove') {
    if (empty($note['password_hash'])) {
        echo json_encode(["status" => "error", "message" => "Note has no password"]);
        exit();
    }
    if (!password_verify($data['password'] ?? '', $note['password_hash'])) {
        echo json_encode(["status" => "error", "message" => "Incorrect password"]);
        exit();
    }
    $stmt = $conn->prepare("UPDATE notes SET password_hash = NULL WHERE note_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $note_id, $user_id);
    $stmt->execute();
    echo json_encode(["status" => "success", "message" => "Password removed"]);

} elseif ($action === 'unlock') {
    if (empty($note['password_hash'])) {
        echo json_encode(["status" => "success", "message" => "Note has no password"]);
        exit();
    }
    if (!password_verify($data['password'] ?? '', $note['password_hash'])) {
        echo json_encode(["status" => "error", "message" => "Incorrect password"]);
        exit();
    }
    if (!isset($_SESSION['unlocked_notes']) || !is_array($_SESSION['unlocked_notes'])) {
        $_SESSION['unlocked_notes'] = [];
    }
    if (!in_array($note_id, $_SESSION['unlocked_notes'])) {
        $_SESSION['unlocked_notes'][] = $note_id;
    }
    echo json_encode(["status" => "success", "message" => "Note unlocked"]);

} else {
    echo json_encode(["status" => "error", "message" => "Unknown action"]);
}
?>
