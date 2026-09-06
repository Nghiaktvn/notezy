<?php
// admin/user_edit.php — Forwards to admin_users.php?action=edit
$_GET['action'] = 'edit';
require_once __DIR__ . '/../admin_users.php';
