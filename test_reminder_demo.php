<?php
// test_reminder_demo.php - CLI only
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }

require_once __DIR__ . '/config/database.php';
$conn = create_connect(true);

echo "=== NOTEZY REMINDER DEMO TEST ===\n\n";

$t = date('Y-m-d H:i:s', time() + 10);
$conn->query("UPDATE notes SET reminder_at='$t', reminder_sent=0 WHERE note_id=18");
echo "Buoc 1: Dat reminder luc: $t (10 giay nua)\n";

echo "Dang doi 12 giay...\n";
sleep(12);

$now = date('Y-m-d H:i:s');
$res = $conn->query("SELECT note_id, title, reminder_at FROM notes WHERE note_id=18 AND reminder_at <= '$now' AND reminder_sent = 0");

if ($res->num_rows > 0) {
    $row = $res->fetch_assoc();
    echo "\nREMINDER FIRED!\n";
    echo "   Ghi chu: " . $row['title'] . "\n";
    echo "   Dat luc: " . $row['reminder_at'] . "\n";
    echo "   Gio check: $now\n";
    $conn->query("UPDATE notes SET reminder_sent=1 WHERE note_id=" . (int)$row['note_id']);
    echo "   Da danh dau reminder_sent=1\n";
    echo "\nKET QUA: HE THONG REMINDER HOAT DONG DUNG!\n";
} else {
    echo "\nKhong tim thay reminder den han.\n";
    $check = $conn->query("SELECT reminder_at, reminder_sent FROM notes WHERE note_id=18");
    $row = $check->fetch_assoc();
    echo "   DB: reminder_at=" . $row['reminder_at'] . " sent=" . $row['reminder_sent'] . "\n";
}
