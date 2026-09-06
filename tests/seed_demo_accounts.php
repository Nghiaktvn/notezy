<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../account_db.php';
$conn = create_connect(true);

$pass = 'Password123!';

// Function to ensure user exists, is activated, and has known password
function ensure_account($conn, $username, $fname, $lname, $email, $pass) {
    $res = $conn->query("SELECT id FROM users WHERE username = '$username'");
    $hash = password_hash($pass, PASSWORD_BCRYPT);
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $id = $row['id'];
        $conn->query("UPDATE users SET activated = 1, password_hash = '$hash', pass = '$hash' WHERE id = $id");
        echo "Updated account {$username} (ID: $id, Password: $pass)\n";
        return $id;
    } else {
        $stmt = $conn->prepare("INSERT INTO users (username, firstname, lastname, email, password_hash, pass, activated) VALUES (?, ?, ?, ?, ?, ?, 1)");
        $stmt->bind_param("ssssss", $username, $fname, $lname, $email, $hash, $hash);
        $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();
        echo "Created account {$username} (ID: $id, Password: $pass)\n";
        return $id;
    }
}

$u1_id = ensure_account($conn, 'user1', 'Nguyen', 'Van A', 'user1@example.com', $pass);
$u2_id = ensure_account($conn, 'user2', 'Tran', 'Thi B', 'user2@example.com', $pass);

echo "\n--- VERIFYING LOGIN --- \n";
$l1 = login('user1', $pass);
echo "Login user1: " . (!empty($l1['success']) ? "SUCCESS" : json_encode($l1)) . "\n";
$l2 = login('user2', $pass);
echo "Login user2: " . (!empty($l2['success']) ? "SUCCESS" : json_encode($l2)) . "\n";
