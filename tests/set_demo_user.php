<?php
require_once __DIR__ . '/../db.php';
$conn = create_connect();
$hash = password_hash('123456', PASSWORD_DEFAULT);
$stmt = $conn->prepare("UPDATE users SET password_hash = ?, activated = 1 WHERE username = 'user1'");
$stmt->bind_param('s', $hash);
$stmt->execute();
echo "UPDATED ROWS: " . $stmt->affected_rows . PHP_EOL;

require_once __DIR__ . '/../account_db.php';
$res = login('user1', '123456');
echo "LOGIN RESULT: " . (is_array($res) && !empty($res['success']) ? 'SUCCESS (User ID: ' . $res['user']['id'] . ')' : json_encode($res)) . PHP_EOL;
