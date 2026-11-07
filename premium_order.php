<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/config/database.php';
notezy_session_start();
require_once __DIR__ . '/includes/csrf.php';
if (!isset($_SESSION['id'])) { header('Location: index.php?auth=login'); exit; }
if (!$conn || $conn->connect_error) { http_response_code(503); exit('Cơ sở dữ liệu tạm thời không khả dụng.'); }
$userId = (int) $_SESSION['id'];
$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$orderId) { http_response_code(404); exit('Không tìm thấy đơn hàng.'); }
$adminCheck = $conn->prepare('SELECT role, activated FROM users WHERE id = ?');
$adminCheck->bind_param('i', $userId);
$adminCheck->execute();
$viewer = $adminCheck->get_result()->fetch_assoc();
$isAdmin = $viewer && $viewer['role'] === 'admin' && (int) $viewer['activated'] === 1;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isAdmin || !csrf_check((string) ($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        exit('Không có quyền xác nhận thanh toán.');
    }
    if (($_POST['action'] ?? '') !== 'confirm_paid') { http_response_code(422); exit('Yêu cầu không hợp lệ.'); }
    $paymentReference = trim((string) ($_POST['payment_reference'] ?? ''));
    if (strlen($paymentReference) < 3 || strlen($paymentReference) > 100) {
        http_response_code(422);
        exit('Nhập mã giao dịch ngân hàng từ 3 đến 100 ký tự trước khi xác nhận.');
    }
    $conn->begin_transaction();
    try {
        $lock = $conn->prepare('SELECT user_id, plan_id, months, status FROM premium_orders WHERE id = ? FOR UPDATE');
        $lock->bind_param('i', $orderId);
        if (!$lock->execute()) throw new RuntimeException('Không thể kiểm tra đơn hàng.');
        $pending = $lock->get_result()->fetch_assoc();
        if (!$pending || $pending['status'] !== 'pending') throw new RuntimeException('Đơn hàng không còn ở trạng thái chờ.');
        $subscriberId = (int) $pending['user_id'];
        $existing = $conn->prepare("SELECT MAX(ends_at) AS latest_end FROM subscriptions WHERE user_id = ? AND status = 'active' AND ends_at > NOW()");
        $existing->bind_param('i', $subscriberId);
        if (!$existing->execute()) throw new RuntimeException('Không thể kiểm tra thời hạn gói hiện tại.');
        $latestEnd = $existing->get_result()->fetch_assoc()['latest_end'] ?? null;
        $baseDate = $latestEnd ? new DateTimeImmutable($latestEnd) : new DateTimeImmutable();
        $endsAt = $baseDate->modify('+' . (int) $pending['months'] . ' months')->format('Y-m-d H:i:s');
        $retire = $conn->prepare("UPDATE subscriptions SET status = 'expired' WHERE user_id = ? AND status = 'active'");
        $retire->bind_param('i', $subscriberId);
        if (!$retire->execute()) throw new RuntimeException('Không thể cập nhật gói hiện tại.');
        $subscription = $conn->prepare("INSERT INTO subscriptions (user_id, plan_id, status, starts_at, ends_at) VALUES (?, ?, 'active', NOW(), ?)");
        $planId = (int) $pending['plan_id'];
        $subscription->bind_param('iis', $subscriberId, $planId, $endsAt);
        if (!$subscription->execute()) throw new RuntimeException('Không thể kích hoạt gói.');
        $update = $conn->prepare("UPDATE premium_orders SET status = 'paid', paid_at = NOW(), payment_reference = ?, confirmed_by = ? WHERE id = ? AND status = 'pending'");
        $update->bind_param('sii', $paymentReference, $userId, $orderId);
        if (!$update->execute() || $update->affected_rows !== 1) throw new RuntimeException('Không thể cập nhật đơn hàng.');
        $conn->commit();
        $success = 'Đã ghi nhận thanh toán và kích hoạt gói.';
    } catch (Throwable $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}

$sql = 'SELECT o.*, u.email FROM premium_orders o JOIN users u ON u.id = o.user_id WHERE o.id = ?';
if (!$isAdmin) $sql .= ' AND o.user_id = ?';
$stmt = $conn->prepare($sql);
if (!$stmt) { http_response_code(503); exit('Chưa khởi tạo bảng đơn hàng.'); }
if ($isAdmin) $stmt->bind_param('i', $orderId); else $stmt->bind_param('ii', $orderId, $userId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
if (!$order) { http_response_code(404); exit('Không tìm thấy đơn hàng.'); }
function h($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function money($value): string { return number_format((int) $value, 0, ',', '.') . ' đ'; }
$bankName = trim((string) (getenv('PAYMENT_BANK_NAME') ?: ''));
$bankAccount = trim((string) (getenv('PAYMENT_BANK_ACCOUNT') ?: ''));
$bankOwner = trim((string) (getenv('PAYMENT_BANK_OWNER') ?: ''));
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Đơn hàng <?= h($order['order_code']) ?> · Notezy</title>
<style>body{font:16px/1.55 system-ui,sans-serif;color:#172033;background:#f5f7fb;margin:0}main{max-width:760px;margin:32px auto;padding:32px;background:#fff;border:1px solid #dbe4ee;border-radius:12px}header,.row{display:flex;justify-content:space-between;gap:16px}h1{margin:0}h2{font-size:1.15rem;margin-top:30px}.muted{color:#64748b}.row{padding:12px 0;border-bottom:1px solid #e5eaf0}.total{font-weight:700;font-size:1.3rem}.badge{padding:6px 12px;border-radius:16px;background:#eef2ff}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:30px}a,button{color:#1d4ed8}button{background:#1d4ed8;color:white;border:0;border-radius:6px;padding:9px 15px;cursor:pointer}.notice{background:#fff7df;padding:15px;border-radius:8px}.error{color:#b42318}.success{color:#067647}@media(max-width:650px){main{margin:0;padding:20px;border:0;border-radius:0}header{display:block}}@media print{body{background:white}main{border:0;margin:0;max-width:none}.actions,form{display:none}}</style></head><body><main>
<header><div><h1>Notezy</h1><div class="muted">Chi tiết đơn hàng Premium</div></div><div><strong><?= h($order['order_code']) ?></strong><br><span class="badge"><?= $order['status'] === 'paid' ? 'Đã thanh toán' : ($order['status'] === 'cancelled' ? 'Đã hủy' : 'Chờ thanh toán') ?></span></div></header>
<?php if ($error): ?><p class="error"><?= h($error) ?></p><?php endif; ?>
<?php if ($success): ?><p class="success"><?= h($success) ?></p><?php endif; ?>
<p>Ngày đặt: <?= h($order['created_at']) ?><br>Người mua: <?= h($order['email']) ?></p>
<h2>Chi tiết tính tiền</h2>
<div class="row"><span>Gói <?= h($order['plan_name']) ?> · <?= (int) $order['months'] ?> tháng × <?= money($order['unit_price_vnd']) ?></span><span><?= money($order['subtotal_vnd']) ?></span></div>
<div class="row"><span>Giảm giá thời hạn</span><span>−<?= money($order['discount_vnd']) ?></span></div>
<div class="row total"><span>Tổng thanh toán</span><span><?= money($order['total_vnd']) ?></span></div>
<?php if ($order['status'] === 'pending'): ?>
<h2>Thanh toán chuyển khoản</h2>
<?php if ($bankName && $bankAccount && $bankOwner): ?>
<div class="notice">Ngân hàng: <strong><?= h($bankName) ?></strong><br>Số tài khoản: <strong><?= h($bankAccount) ?></strong><br>Chủ tài khoản: <strong><?= h($bankOwner) ?></strong><br>Nội dung chuyển khoản: <strong><?= h($order['order_code']) ?></strong><br>Số tiền: <strong><?= money($order['total_vnd']) ?></strong></div>
<p class="muted">Gói sẽ được kích hoạt sau khi người quản trị đối chiếu giao dịch và xác nhận thanh toán.</p>
<?php else: ?><div class="notice">Thông tin nhận thanh toán chưa được cấu hình. Vui lòng liên hệ quản trị viên trước khi chuyển tiền. Gói chưa được kích hoạt.</div><?php endif; ?>
<?php elseif ($order['status'] === 'paid'): ?><p>Đã ghi nhận thanh toán lúc <?= h($order['paid_at']) ?>. Mã giao dịch: <?= h($order['payment_reference']) ?>. Đây là biên nhận điện tử của đơn hàng; chứng từ thuế được cung cấp riêng khi có yêu cầu và đủ thông tin pháp lý.</p><?php endif; ?>
<?php if ($isAdmin && $order['status'] === 'pending'): ?><form method="post" onsubmit="return confirm('Bạn đã đối chiếu giao dịch ngân hàng và xác nhận đã nhận đúng số tiền?')"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="confirm_paid"><label for="payment_reference">Mã giao dịch ngân hàng</label> <input id="payment_reference" name="payment_reference" required minlength="3" maxlength="100" autocomplete="off"> <button type="submit">Xác nhận đã nhận tiền và kích hoạt gói</button></form><?php endif; ?>
<div class="actions"><a href="premium.php">← Về trang gói</a><button type="button" onclick="window.print()">In / lưu PDF</button></div>
</main></body></html>
