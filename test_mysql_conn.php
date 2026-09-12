<?php
if (PHP_SAPI !== 'cli' || getenv('NOTEZY_DIAGNOSTIC') !== '1') {
    http_response_code(404);
    exit();
}

require_once __DIR__ . '/config/database.php';
$configs = [[DB_USER, DB_PASS]];
foreach ($configs as [$user, $pass]) {
    $conn = @new mysqli('127.0.0.1', $user, $pass, '', 3306);
    if (!$conn->connect_error) {
        echo "Connected OK: user=$user version=" . $conn->server_info . PHP_EOL;
        $conn->close();
    } else {
        echo "FAIL: user=$user err=" . $conn->connect_error . PHP_EOL;
    }
}
