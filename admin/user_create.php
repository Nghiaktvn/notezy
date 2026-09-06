<?php
// admin/user_create.php — Forwards to admin_users.php?action=add
$_GET['action'] = 'add';
require_once __DIR__ . '/../admin_users.php';
