<?php
require_once dirname(__DIR__) . '/includes/session.php';
require_once 'db.php';
header('Content-Type: application/json');

notezy_session_start();
session_regenerate_id(true); // Prevent session fixation
$_SESSION['id']       = $user['id'];
$_SESSION['theme']    = $user['theme'];
$_SESSION['language'] = $user['language'];

echo json_encode([
    "status"  => "success",
    "message" => "Đăng nhập thành công",
    "user"    => [
        "id"       => $user['id'],
        "avatar"   => $user['avatar'],
        "theme"    => $user['theme'],
        "language" => $user['language']
    ]
]);
?>
