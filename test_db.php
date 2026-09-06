<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

// test_db.php: Diagnostic script to check MySQL and phpMyAdmin database state
require_once __DIR__ . '/env.php';

echo "=== CHECKING DATABASE CONNECTION ===\n";

$configured_port = (int)(getenv('DB_PORT') ?: 3307);
$ports = array_unique([$configured_port, 3307, 3306, 3308]);
$passwords = [getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '', 'root_password', '', 'root', '123456', 'password'];
$users = ['root'];

$success_conn = null;
$found_port = null;
$found_user = null;
$found_pass = null;

foreach ($ports as $port) {
    foreach ($users as $user) {
        foreach ($passwords as $pass) {
            $conn = @new mysqli('127.0.0.1', $user, $pass, '', $port);
            if (!$conn->connect_error) {
                echo "[SUCCESS] Connected to MySQL on port $port with user='$user', pass='$pass'\n";
                $success_conn = $conn;
                $found_port = $port;
                $found_user = $user;
                $found_pass = $pass;
                break 3;
            }
        }
    }
}

if (!$success_conn) {
    echo "[FAILED] Could not connect to any local MySQL instance on ports " . implode(',', $ports) . "\n";
    exit(1);
}

echo "\n=== DATABASES ON SERVER ===\n";
$db_res = $success_conn->query("SHOW DATABASES");
$databases = [];
while ($row = $db_res->fetch_row()) {
    $databases[] = $row[0];
    echo " - " . $row[0] . "\n";
}

$target_db = 'notezy';
if (!in_array($target_db, $databases)) {
    echo "\n[WARNING] Target database '$target_db' does NOT exist!\n";
    if (in_array('note', $databases)) {
        echo "Found database 'note' instead!\n";
        $target_db = 'note';
    }
}

if (in_array($target_db, $databases)) {
    echo "\n=== TABLES IN DATABASE '$target_db' ===\n";
    $success_conn->select_db($target_db);
    $tbl_res = $success_conn->query("SHOW TABLES");
    while ($row = $tbl_res->fetch_row()) {
        $tbl = $row[0];
        $count_res = $success_conn->query("SELECT COUNT(*) FROM `$tbl`");
        $count = $count_res ? $count_res->fetch_row()[0] : 'N/A';
        echo " - Table '$tbl': $count rows\n";
    }

    echo "\n=== USERS IN '$target_db' ===\n";
    $u_res = $success_conn->query("SELECT id, username, email, activated FROM users LIMIT 5");
    if ($u_res) {
        while ($u = $u_res->fetch_assoc()) {
            echo " - ID: {$u['id']} | Username: {$u['username']} | Email: {$u['email']} | Activated: {$u['activated']}\n";
        }
    } else {
        echo "Table 'users' query error: " . $success_conn->error . "\n";
    }
}

$success_conn->close();
echo "\n=== DONE ===\n";
