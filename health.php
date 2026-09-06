<?php
require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$response = [
    'status' => 'ok',
    'database' => 'connected',
    'app_env' => getenv('APP_ENV') ?: 'local',
    'time' => gmdate('c'),
];

try {
    $health_conn = create_connect(false);
    if (!$health_conn) {
        throw new Exception('Database connection unavailable');
    }

    $ping = @$health_conn->query('SELECT 1 AS ok');
    if (!$ping) {
        throw new Exception('Database ping failed');
    }

    http_response_code(200);
    echo json_encode($response, JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(503);
    echo json_encode([
        'status' => 'error',
        'database' => 'unavailable',
        'message' => 'Health check failed',
        'time' => gmdate('c'),
    ], JSON_UNESCAPED_SLASHES);
}
?>
