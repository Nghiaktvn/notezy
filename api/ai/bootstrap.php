<?php
require_once dirname(__DIR__, 2) . '/includes/session.php';
/**
 * Shared bootstrap for AI API endpoints.
 */
require_once __DIR__ . '/../db.php';

session_set_cookie_params([
    'httponly' => true,
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    notezy_session_start();
}

header('Content-Type: application/json; charset=utf-8');

function ai_json(int $code, array $payload): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
}

function ai_require_post(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        ai_json(405, ['status' => 'error', 'message' => 'Method not allowed']);
    }
}

function ai_require_auth(): int {
    if (!isset($_SESSION['id'])) {
        ai_json(401, ['status' => 'error', 'message' => 'Unauthorized — please login']);
    }
    $user_id = (int) $_SESSION['id'];
    if ($user_id <= 0) {
        ai_json(401, ['status' => 'error', 'message' => 'Unauthorized — please login']);
    }
    return $user_id;
}

function ai_check_origin(): void {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === '') {
        return;
    }
    $allowed = [
        'http://localhost:5173',
        'http://localhost:8080',
        'http://127.0.0.1:5173',
        'http://127.0.0.1:8080',
        'http://localhost',
        'http://127.0.0.1',
    ];
    $ok = in_array($origin, $allowed, true);
    if (!$ok) {
        $app = rtrim((string) getenv('APP_URL'), '/');
        if ($app !== '' && strpos($origin, $app) === 0) {
            $ok = true;
        }
    }
    if (!$ok) {
        ai_json(403, ['status' => 'error', 'message' => 'Forbidden origin']);
    }
}

function ai_read_json(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    if (strlen($raw) > 200000) {
        ai_json(413, ['status' => 'error', 'message' => 'Request too large']);
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        ai_json(400, ['status' => 'error', 'message' => 'Invalid JSON']);
    }
    return $data;
}

function ai_env_int(string $name, int $default): int {
    $v = getenv($name);
    if ($v === false || $v === '') {
        return $default;
    }
    return (int) $v;
}
