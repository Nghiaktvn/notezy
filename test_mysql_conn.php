<?php
// Test MySQL connectivity with various credentials
$configs = [
    ['root', ''],
    ['root', 'root'],
    ['root', 'root_password'],
    ['root', '123456'],
    ['root', '12345678'],
    ['root', 'admin'],
    ['root', 'notezy'],
    ['root', '1234'],
    ['root', '123456789'],
    ['root', 'root123'],
    ['root', 'Nghia123'],
    ['root', 'Nghia@123'],
    ['root', 'Nghia282006'],
    ['root', 'trungnghia282006'],
];
foreach ($configs as [$user, $pass]) {
    $conn = @new mysqli('127.0.0.1', $user, $pass, '', 3306);
    if (!$conn->connect_error) {
        echo "Connected OK: user=$user pass=" . ($pass ?: '(empty)') . " version=" . $conn->server_info . PHP_EOL;
        $conn->close();
    } else {
        echo "FAIL: user=$user pass=" . ($pass ?: '(empty)') . " err=" . $conn->connect_error . PHP_EOL;
    }
}
