<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/config/database.php';
notezy_session_start();
if (!isset($_SESSION['id'])) { header('Location: index.php?auth=login'); exit; }
if (!$conn || $conn->connect_error) { http_response_code(503); exit('Cơ sở dữ liệu không khả dụng.'); }
$userId = (int) $_SESSION['id'];
$check = $conn->prepare('SELECT role, activated FROM users WHERE id = ?');
$check->bind_param('i', $userId);
$check->execute();
$user = $check->get_result()->fetch_assoc();
if (!$user || $user['role'] !== 'admin' || (int) $user['activated'] !== 1) { http_response_code(403); exit('Không có quyền quản trị.'); }
$orders = $conn->query("SELECT o.id, o.order_code, o.plan_name, o.total_vnd, o.status, o.created_at, u.email FROM premium_orders o JOIN users u ON u.id = o.user_id ORDER BY FIELD(o.status, 'pending', 'paid', 'cancelled'), o.id DESC LIMIT 100");
if (!$orders) { http_response_code(503); exit('Chưa khởi tạo bảng đơn hàng.'); }
function h($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Đơn hàng Premium · Quản trị</title><style>body{font:16px system-ui,sans-serif;background:#f5f7fb;color:#172033;margin:0;padding:24px}main{max-width:1100px;margin:auto}table{width:100%;border-collapse:collapse;background:white}th,td{text-align:left;padding:12px;border-bottom:1px solid #ddd}a{color:#1d4ed8}@media(max-width:700px){table{display:block;overflow-x:auto;white-space:nowrap}}</style></head><body><main><a href="admin_users.php">← Quản trị</a><h1>Đơn hàng Premium</h1><p>Chỉ xác nhận sau khi đã đối chiếu giao dịch ngân hàng thực tế.</p><table><thead><tr><th>Mã đơn</th><th>Email</th><th>Gói</th><th>Tổng</th><th>Trạng thái</th><th>Ngày đặt</th><th></th></tr></thead><tbody>
<?php while ($order = $orders->fetch_assoc()): ?><tr><td><?= h($order['order_code']) ?></td><td><?= h($order['email']) ?></td><td><?= h($order['plan_name']) ?></td><td><?= number_format((int) $order['total_vnd'], 0, ',', '.') ?> đ</td><td><?= h($order['status']) ?></td><td><?= h($order['created_at']) ?></td><td><a href="premium_order.php?id=<?= (int) $order['id'] ?>">Xem</a></td></tr><?php endwhile; ?>
</tbody></table></main></body></html>
