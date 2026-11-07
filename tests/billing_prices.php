<?php
require_once dirname(__DIR__) . '/includes/billing.php';
$cases = [
    ['STUDENT', 1, 29000], ['PERSONAL', 3, 177000], ['TEAM', 12, 1609200],
];
foreach ($cases as [$code, $months, $expected]) {
    $quote = notezy_order_total($code, $months);
    if (!$quote || $quote['total_vnd'] !== $expected) {
        fwrite(STDERR, "Sai tổng tiền: $code / $months\n"); exit(1);
    }
}
foreach ([['FREE', 1], ['PERSONAL', 0], ['TEAM', 2], ['STUDENT', 13]] as [$code, $months]) {
    if (notezy_order_total($code, $months) !== null) {
        fwrite(STDERR, "Đã nhận gói/thời hạn không hợp lệ.\n"); exit(1);
    }
}
echo "Billing prices: OK\n";
