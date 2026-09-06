<?php
require_once dirname(__DIR__) . '/includes/session.php';
require_once __DIR__ . '/../db.php';
notezy_session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Bạn chưa đăng nhập.']);
    exit;
}

$current_user_id = (int) $_SESSION['id'];
$conn = create_connect();
$action = trim($_REQUEST['action'] ?? '');

function json_out(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function display_name(array $user): string {
    $name = trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? ''));
    return $name !== '' ? $name : ($user['username'] ?? 'User');
}

function ensure_chat_schema($conn): void {
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
}

function mark_seen($conn, int $user_id): void {
    $stmt = $conn->prepare("UPDATE users SET last_seen_at = NOW() WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->close();
    }
}

if ($conn && !$conn->connect_error) {
    ensure_chat_schema($conn);
    mark_seen($conn, $current_user_id);
}

if (!$conn || $conn->connect_error) {
    if (!isset($_SESSION['mock_chat_messages'])) {
        $_SESSION['mock_chat_messages'] = [];
    }
    if ($action === 'get_users') {
        $users = array_values(array_filter($_SESSION['mock_users'] ?? [], fn($u) => (int)$u['id'] !== $current_user_id));
        json_out(['success' => true, 'users' => array_map(function($u) {
            return [
                'id' => (int)$u['id'],
                'username' => $u['username'],
                'display_name' => display_name($u),
                'email' => $u['email'],
                'avatar' => $u['avatar'] ?? null,
                'activated' => (int)($u['activated'] ?? 0),
                'is_online' => false,
                'is_contact' => false,
            ];
        }, $users)]);
    }
    if ($action === 'get_messages') {
        json_out(['success' => true, 'messages' => $_SESSION['mock_chat_messages']]);
    }
    if ($action === 'send_message') {
        $message = trim($_POST['message'] ?? '');
        if ($message === '') json_out(['success' => false, 'message' => 'Tin nhắn không được để trống.']);
        $_SESSION['mock_chat_messages'][] = [
            'message_id' => count($_SESSION['mock_chat_messages']) + 1,
            'sender_id' => $current_user_id,
            'receiver_id' => (int)($_POST['with_user_id'] ?? 0),
            'message_type' => 'text',
            'message' => $message,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        json_out(['success' => true]);
    }
    if ($action === 'get_my_notes') {
        json_out(['success' => true, 'notes' => []]);
    }
    json_out(['success' => false, 'message' => 'Chế độ offline chưa hỗ trợ thao tác này.'], 400);
}

if ($action === 'get_users') {
    $q = '%' . trim($_GET['q'] ?? '') . '%';
    $sql = "SELECT u.id, u.username, u.firstname, u.lastname, u.email, u.avatar, u.activated, u.last_seen_at,
                   CASE WHEN EXISTS (
                       SELECT 1 FROM chat_messages cm
                       WHERE (cm.sender_id = ? AND cm.receiver_id = u.id)
                          OR (cm.sender_id = u.id AND cm.receiver_id = ?)
                   ) OR EXISTS (
                       SELECT 1 FROM note_shares ns
                       WHERE (ns.shared_by_user_id = ? AND ns.shared_with_user_id = u.id)
                          OR (ns.shared_by_user_id = u.id AND ns.shared_with_user_id = ?)
                   ) THEN 1 ELSE 0 END AS is_contact
            FROM users u
            WHERE u.id != ?
              AND (u.username LIKE ? OR u.email LIKE ? OR u.firstname LIKE ? OR u.lastname LIKE ?)
            ORDER BY is_contact DESC, u.username ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iiiiissss', $current_user_id, $current_user_id, $current_user_id, $current_user_id, $current_user_id, $q, $q, $q, $q);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $users = array_map(function($u) {
        $last = !empty($u['last_seen_at']) ? strtotime($u['last_seen_at']) : 0;
        return [
            'id' => (int)$u['id'],
            'username' => $u['username'],
            'display_name' => display_name($u),
            'email' => $u['email'],
            'avatar' => $u['avatar'],
            'activated' => (int)$u['activated'],
            'is_online' => $last > 0 && (time() - $last) <= 300,
            'is_contact' => (int)$u['is_contact'] === 1,
        ];
    }, $rows);
    json_out(['success' => true, 'users' => $users]);
}

if ($action === 'get_my_notes') {
    $stmt = $conn->prepare("SELECT note_id, title, content FROM notes WHERE user_id = ? AND COALESCE(archived, 0) = 0 ORDER BY updated_at DESC, created_at DESC");
    $stmt->bind_param('i', $current_user_id);
    $stmt->execute();
    $notes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    json_out(['success' => true, 'notes' => $notes]);
}

if ($action === 'get_messages') {
    $with_user_id = (int)($_GET['with_user_id'] ?? 0);
    if ($with_user_id <= 0) json_out(['success' => false, 'message' => 'Thiếu người nhận.'], 400);

    $sql = "SELECT cm.message_id, cm.sender_id, cm.receiver_id, cm.message_type, cm.message, cm.note_id, cm.permission, cm.created_at,
                   n.title AS note_title, n.content AS note_content,
                   CASE WHEN n.user_id = ? OR ns.permission = 'write' THEN 1 ELSE 0 END AS can_edit
            FROM chat_messages cm
            LEFT JOIN notes n ON cm.note_id = n.note_id
            LEFT JOIN note_shares ns ON ns.note_id = cm.note_id AND ns.shared_with_user_id = ? AND ns.shared_by_user_id = cm.sender_id
            WHERE (cm.sender_id = ? AND cm.receiver_id = ?)
               OR (cm.sender_id = ? AND cm.receiver_id = ?)
            ORDER BY cm.created_at ASC, cm.message_id ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iiiiii', $current_user_id, $current_user_id, $current_user_id, $with_user_id, $with_user_id, $current_user_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    json_out(['success' => true, 'messages' => $rows]);
}

if ($action === 'send_message') {
    $with_user_id = (int)($_POST['with_user_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    if ($with_user_id <= 0 || $message === '') json_out(['success' => false, 'message' => 'Thiếu nội dung hoặc người nhận.'], 400);
    $stmt = $conn->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message_type, message) VALUES (?, ?, 'text', ?)");
    $stmt->bind_param('iis', $current_user_id, $with_user_id, $message);
    $ok = $stmt->execute();
    $stmt->close();
    json_out(['success' => $ok, 'message' => $ok ? 'Đã gửi tin nhắn.' : 'Không thể gửi tin nhắn.']);
}

if ($action === 'share_note_chat') {
    $with_user_id = (int)($_POST['with_user_id'] ?? $_POST['target_user_id'] ?? 0);
    $note_id = (int)($_POST['note_id'] ?? 0);
    $raw_permission = trim($_POST['permission'] ?? 'read');
    $permission = ($raw_permission === 'write' || strtolower($raw_permission) === 'editor') ? 'write' : 'read';
    if ($with_user_id <= 0 || $note_id <= 0 || $with_user_id === $current_user_id) {
        json_out(['success' => false, 'message' => 'Thông tin chia sẻ không hợp lệ.'], 400);
    }

    $stmt = $conn->prepare("SELECT note_id, title FROM notes WHERE note_id = ? AND user_id = ? LIMIT 1");
    $stmt->bind_param('ii', $note_id, $current_user_id);
    $stmt->execute();
    $note = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$note) json_out(['success' => false, 'message' => 'Bạn không có quyền chia sẻ ghi chú này.'], 403);

    $stmt = $conn->prepare("SELECT id, username FROM users WHERE id = ? AND activated = 1 LIMIT 1");
    $stmt->bind_param('i', $with_user_id);
    $stmt->execute();
    $target = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$target) json_out(['success' => false, 'message' => 'Người nhận không tồn tại hoặc chưa kích hoạt.'], 404);

    $stmt = $conn->prepare("SELECT share_id FROM note_shares WHERE note_id = ? AND shared_with_user_id = ? LIMIT 1");
    $stmt->bind_param('ii', $note_id, $with_user_id);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        $stmt = $conn->prepare("UPDATE note_shares SET permission = ?, shared_by_user_id = ?, shared_at = CURRENT_TIMESTAMP WHERE share_id = ?");
        $share_id = (int)$existing['share_id'];
        $stmt->bind_param('sii', $permission, $current_user_id, $share_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO note_shares (note_id, shared_with_user_id, permission, shared_by_user_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('iisi', $note_id, $with_user_id, $permission, $current_user_id);
    }
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        json_out(['success' => false, 'message' => 'Không thể chia sẻ ghi chú: ' . $err], 500);
    }
    $stmt->close();

    $msg = 'Đã chia sẻ ghi chú "' . $note['title'] . '".';
    $stmt = $conn->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message_type, message, note_id, permission) VALUES (?, ?, 'note', ?, ?, ?)");
    $stmt->bind_param('iisis', $current_user_id, $with_user_id, $msg, $note_id, $permission);
    $ok = $stmt->execute();
    $stmt->close();
    json_out(['success' => $ok, 'message' => $ok ? 'Đã chia sẻ ghi chú vào cuộc trò chuyện.' : 'Đã chia sẻ ghi chú nhưng không thể gửi vào chat.']);
}

json_out(['success' => false, 'message' => 'Action không hợp lệ.'], 400);
