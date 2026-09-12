<?php
declare(strict_types=1);
require __DIR__ . '/../../shared/bootstrap.php';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($path === '/health' || $path === '/ready') svc_health('premium', true);
$userId = svc_bearer_user(); $db = svc_db();
if ($path === '/entitlements' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $feature = strtoupper(trim((string)($_GET['feature'] ?? 'AI_QA')));
    $q = $db->prepare("SELECT p.code, e.feature_code, e.limit_value FROM subscriptions s JOIN plans p ON p.id=s.plan_id JOIN entitlements e ON e.plan_id=p.id WHERE s.user_id=? AND s.status='active' AND (s.ends_at IS NULL OR s.ends_at > CONVERT_TZ(UTC_TIMESTAMP(),'+00:00','+07:00')) AND e.feature_code=? ORDER BY s.id DESC LIMIT 1");
    $q->execute([$userId,$feature]); $row = $q->fetch(); if (!$row) { $q=$db->prepare('SELECT p.code,e.feature_code,e.limit_value FROM plans p JOIN entitlements e ON e.plan_id=p.id WHERE p.code="FREE" AND e.feature_code=?'); $q->execute([$feature]); $row=$q->fetch(); }
    if (!$row) svc_json(403,['allowed'=>false,'reason'=>'PREMIUM_REQUIRED']);
    $day=(new DateTimeImmutable('now',new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d'); $used=$db->prepare('SELECT used_count FROM usage_records WHERE user_id=? AND feature_code=? AND period_date=?'); $used->execute([$userId,$feature,$day]); $count=(int)($used->fetchColumn() ?: 0);
    svc_json(200,['allowed'=>$row['limit_value'] < 0 || $count < (int)$row['limit_value'],'plan'=>$row['code'],'remaining'=>$row['limit_value'] < 0 ? null : max(0,(int)$row['limit_value']-$count),'limit'=>(int)$row['limit_value']]);
}
svc_json(404,['error'=>'ROUTE_NOT_FOUND']);
