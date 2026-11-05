<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}
require_once dirname(__DIR__) . '/config/database.php';
$db = create_connect(false);
if (!$db) exit(1);
$result = $db->query('SELECT COUNT(*) AS total FROM users');
if (!$result) exit(1);
if ((int) $result->fetch_assoc()['total'] !== 0) {
    echo "Existing accounts preserved; demo seeding skipped.\n";
    exit(0);
}
require __DIR__ . '/seed_demo_accounts.php';
