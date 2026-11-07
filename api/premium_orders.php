<?php
require_once dirname(__DIR__) . '/includes/session.php';
require_once dirname(__DIR__) . '/includes/billing.php';
require_once dirname(__DIR__) . '/config/database.php';
notezy_session_start();
require_once dirname(__DIR__) . '/includes/csrf.php';
header('Content-Type: application/json; charset=utf-8');

function premium_order_reply(int $code, array $body): void {
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['id'])) premium_order_reply(401, ['status' => 'error', 'message' => 'Vui lòng đăng nhập.']);
if (!$conn || $conn->connect_error) premium_order_reply(503, ['status' => 'error', 'message' => 'Cơ sở dữ liệu tạm thời không khả dụng.']);
$userId = (int) $_SESSION['id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $conn->prepare('SELECT id, order_code, plan_name, months, total_vnd, status, created_at FROM premium_orders WHERE user_id = ? ORDER BY id DESC LIMIT 30');
    if (!$stmt) premium_order_reply(503, ['status' => 'error', 'message' => 'Chưa khởi tạo bảng đơn hàng.']);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    premium_order_reply(200, ['status' => 'success', 'orders' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'csrf_token' => csrf_token()]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') premium_order_reply(405, ['status' => 'error', 'message' => 'Phương thức không hợp lệ.']);
$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload) || !csrf_check((string) ($payload['csrf_token'] ?? ''))) premium_order_reply(403, ['status' => 'error', 'message' => 'Phiên không hợp lệ. Vui lòng tải lại trang.']);
$action = (string) ($payload['action'] ?? '');
if ($action === 'cancel') {
    $orderId = filter_var($payload['order_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$orderId) premium_order_reply(422, ['status' => 'error', 'message' => 'Đơn hàng không hợp lệ.']);
    $stmt = $conn->prepare("UPDATE premium_orders SET status = 'cancelled' WHERE id = ? AND user_id = ? AND status = 'pending'");
    $stmt->bind_param('ii', $orderId, $userId);
    $stmt->execute();
    premium_order_reply($stmt->affected_rows ? 200 : 409, ['status' => $stmt->affected_rows ? 'success' : 'error', 'message' => $stmt->affected_rows ? 'Đã hủy đơn hàng.' : 'Chỉ có thể hủy đơn đang chờ thanh toán.']);
}
if ($action !== 'create') premium_order_reply(422, ['status' => 'error', 'message' => 'Yêu cầu không hợp lệ.']);

$planCode = (string) ($payload['plan_code'] ?? '');
$months = filter_var($payload['months'] ?? null, FILTER_VALIDATE_INT);
$quote = $months ? notezy_order_total($planCode, $months) : null;
if (!$quote) premium_order_reply(422, ['status' => 'error', 'message' => 'Gói hoặc thời hạn không hợp lệ.']);
$plan = $conn->prepare('SELECT id, name FROM plans WHERE code = ? AND active = 1 LIMIT 1');
$plan->bind_param('s', $planCode);
$plan->execute();
$planRow = $plan->get_result()->fetch_assoc();
if (!$planRow) premium_order_reply(503, ['status' => 'error', 'message' => 'Gói hiện không khả dụng.']);

$orderCode = 'NZ-' . strtoupper(bin2hex(random_bytes(8)));
$stmt = $conn->prepare("INSERT INTO premium_orders (order_code, user_id, plan_id, plan_name, unit_price_vnd, months, subtotal_vnd, discount_vnd, total_vnd) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
if (!$stmt) premium_order_reply(503, ['status' => 'error', 'message' => 'Chưa khởi tạo bảng đơn hàng.']);
$planId = (int) $planRow['id'];
$planName = (string) $planRow['name'];
$unitPrice = $quote['unit_price_vnd'];
$subtotal = $quote['subtotal_vnd'];
$discount = $quote['discount_vnd'];
$total = $quote['total_vnd'];
$stmt->bind_param('siisiiiii', $orderCode, $userId, $planId, $planName, $unitPrice, $months, $subtotal, $discount, $total);
if (!$stmt->execute()) premium_order_reply(500, ['status' => 'error', 'message' => 'Không thể tạo đơn hàng.']);
premium_order_reply(201, ['status' => 'success', 'order_id' => $stmt->insert_id, 'order_code' => $orderCode, 'total_vnd' => $quote['total_vnd'], 'message' => 'Đã tạo đơn hàng chờ thanh toán. Gói chưa được kích hoạt.']);
