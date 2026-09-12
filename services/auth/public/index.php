<?php
declare(strict_types=1);
require __DIR__ . '/../../shared/bootstrap.php';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($path === '/health' || $path === '/ready') svc_health('auth', true);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') svc_json(405, ['error' => 'METHOD_NOT_ALLOWED']);
$data = svc_input();
if ($path === '/login') {
    $login = trim((string)($data['login'] ?? $data['username'] ?? ''));
    $password = (string)($data['password'] ?? '');
    $rateIdentity = strtolower($login) . '|' . (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    if (svc_rate_limit_exceeded('auth-login', $rateIdentity, 8)) svc_json(429, ['error' => 'TOO_MANY_LOGIN_ATTEMPTS', 'retry_after' => 300]);
    $stmt = svc_db()->prepare('SELECT id, password_hash, activated FROM users WHERE username = ? OR email = ? LIMIT 1');
    $stmt->execute([$login, $login]); $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password_hash'])) {
        svc_rate_limit_hit('auth-login', $rateIdentity, 300);
        svc_json(401, ['error' => 'INVALID_CREDENTIALS']);
    }
    svc_cache_delete('rate:auth-login:' . hash('sha256', $rateIdentity));
    // Keep microservice semantics aligned with the web application: an
    // unverified account may work, but the client must show a reminder.
    svc_json(200, ['token' => svc_token((int)$user['id']), 'token_type' => 'Bearer', 'expires_in' => 3600, 'unverified' => !(bool)$user['activated']]);
}
if ($path === '/register') {
    $username = trim((string)($data['username'] ?? '')); $email = trim((string)($data['email'] ?? ''));
    $password = (string)($data['password'] ?? '');
    if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) svc_json(422, ['error' => 'INVALID_REGISTRATION']);
    try { $stmt = svc_db()->prepare('INSERT INTO users (username, firstname, lastname, email, password_hash, activated) VALUES (?, ?, ?, ?, ?, 0)'); $stmt->execute([$username, trim((string)($data['firstname'] ?? '')), trim((string)($data['lastname'] ?? '')), $email, password_hash($password, PASSWORD_DEFAULT)]); svc_json(201, ['status' => 'activation_required']); }
    catch (PDOException $e) { svc_json(409, ['error' => 'USER_EXISTS']); }
}
svc_json(404, ['error' => 'ROUTE_NOT_FOUND']);
