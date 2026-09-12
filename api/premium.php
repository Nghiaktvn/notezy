<?php
require_once dirname(__DIR__) . '/includes/session.php';
require_once __DIR__ . '/../config/database.php';

notezy_session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Vui lòng đăng nhập.']);
    exit();
}
if (!$conn || $conn->connect_error) {
    http_response_code(503);
    echo json_encode(['status' => 'error', 'message' => 'Không thể kết nối cơ sở dữ liệu.']);
    exit();
}

$userId = (int) $_SESSION['id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $planQuery = $conn->query("SELECT code, name FROM plans WHERE active = 1 ORDER BY FIELD(code, 'FREE', 'STUDENT', 'PERSONAL', 'TEAM', 'PREMIUM'), name");
    $plans = $planQuery ? $planQuery->fetch_all(MYSQLI_ASSOC) : [];
    $active = $conn->prepare("SELECT p.code, p.name, s.starts_at, s.ends_at FROM subscriptions s JOIN plans p ON p.id = s.plan_id WHERE s.user_id = ? AND s.status = 'active' AND (s.ends_at IS NULL OR s.ends_at > NOW()) ORDER BY s.id DESC LIMIT 1");
    $active->bind_param('i', $userId);
    $active->execute();
    $subscription = $active->get_result()->fetch_assoc() ?: null;
    $trial = $conn->prepare('SELECT started_at, ends_at FROM premium_trials WHERE user_id = ?');
    $trial->bind_param('i', $userId);
    $trial->execute();
    echo json_encode(['status' => 'success', 'plans' => $plans, 'subscription' => $subscription, 'trial' => $trial->get_result()->fetch_assoc() ?: null]);
    exit();
}

$payload = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $payload['action'] ?? '';

if ($method === 'POST' && $action === 'start_trial') {
    $existing = $conn->prepare('SELECT user_id FROM premium_trials WHERE user_id = ?');
    $existing->bind_param('i', $userId);
    $existing->execute();
    if ($existing->get_result()->num_rows > 0) {
        http_response_code(409);
        echo json_encode(['status' => 'error', 'message' => 'Bạn đã sử dụng gói dùng thử.']);
        exit();
    }
    $plan = $conn->prepare("SELECT id FROM plans WHERE code = 'PERSONAL' AND active = 1 LIMIT 1");
    $plan->execute();
    $planRow = $plan->get_result()->fetch_assoc();
    if (!$planRow) {
        http_response_code(503);
        echo json_encode(['status' => 'error', 'message' => 'Gói dùng thử chưa sẵn sàng.']);
        exit();
    }
    $conn->begin_transaction();
    try {
        $endsAt = (new DateTimeImmutable('now', new DateTimeZone('Asia/Ho_Chi_Minh')))->modify('+7 days')->format('Y-m-d H:i:s');
        $trial = $conn->prepare('INSERT INTO premium_trials (user_id, ends_at) VALUES (?, ?)');
        $trial->bind_param('is', $userId, $endsAt);
        $trial->execute();
        $subscription = $conn->prepare("INSERT INTO subscriptions (user_id, plan_id, status, starts_at, ends_at) VALUES (?, ?, 'active', NOW(), ?)");
        $planId = (int) $planRow['id'];
        $subscription->bind_param('iis', $userId, $planId, $endsAt);
        $subscription->execute();
        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Bạn đã bắt đầu dùng thử Premium 7 ngày.', 'ends_at' => $endsAt]);
    } catch (Throwable $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Không thể kích hoạt dùng thử.']);
    }
    exit();
}

if ($method === 'POST' && $action === 'cancel') {
    $stmt = $conn->prepare("UPDATE subscriptions SET status = 'cancelled' WHERE user_id = ? AND status = 'active'");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    echo json_encode(['status' => 'success', 'message' => 'Đã hủy gói. Bạn vẫn có thể tiếp tục dùng gói miễn phí.']);
    exit();
}

http_response_code(405);
echo json_encode(['status' => 'error', 'message' => 'Yêu cầu không được hỗ trợ.']);
