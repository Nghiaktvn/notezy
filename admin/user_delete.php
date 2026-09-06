<?php
// admin/user_delete.php — Forwards to admin_users.php?action=delete
$_GET['action'] = 'delete';
require_once __DIR__ . '/../admin_users.php';
