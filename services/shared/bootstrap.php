<?php
declare(strict_types=1);

/** Shared, dependency-free primitives for PHP service endpoints. */
function svc_json(int $status, array $body): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Request-Id: ' . svc_request_id());
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function svc_request_id(): string {
    static $id = null;
    return $id ??= ($_SERVER['HTTP_X_REQUEST_ID'] ?? bin2hex(random_bytes(12)));
}

function svc_env(string $name, ?string $default = null): ?string {
    $value = getenv($name);
    return $value === false || $value === '' ? $default : $value;
}

function svc_input(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') return [];
    $data = json_decode($raw, true);
    if (!is_array($data)) svc_json(400, ['error' => 'INVALID_JSON']);
    return $data;
}

/**
 * Tiny dependency-free Redis client for the services that need short-lived
 * state. Redis outages intentionally degrade to the database path instead of
 * taking down note editing or authentication.
 */
function svc_redis_command(array $parts): mixed {
    $host = svc_env('REDIS_HOST', 'redis');
    $port = (int)svc_env('REDIS_PORT', '6379');
    $socket = @fsockopen($host, $port, $errno, $error, 0.4);
    if (!$socket) return null;
    stream_set_timeout($socket, 1);
    $request = '*' . count($parts) . "\r\n";
    foreach ($parts as $part) { $part = (string)$part; $request .= '$' . strlen($part) . "\r\n{$part}\r\n"; }
    if (@fwrite($socket, $request) === false) { fclose($socket); return null; }
    $read = static function ($stream) use (&$read): mixed {
        $prefix = fgetc($stream); if ($prefix === false) return null;
        $line = fgets($stream); if ($line === false) return null;
        $line = rtrim($line, "\r\n");
        if ($prefix === '+') return $line;
        if ($prefix === '-') return null;
        if ($prefix === ':') return (int)$line;
        if ($prefix === '$') {
            $length = (int)$line; if ($length < 0) return null;
            $value = ''; while (strlen($value) < $length) { $chunk = fread($stream, $length - strlen($value)); if ($chunk === false || $chunk === '') return null; $value .= $chunk; }
            fread($stream, 2); return $value;
        }
        if ($prefix === '*') { $items = []; for ($i = 0, $count = (int)$line; $i < $count; $i++) $items[] = $read($stream); return $items; }
        return null;
    };
    $response = $read($socket); fclose($socket); return $response;
}

function svc_cache_get(string $key): ?string { $value = svc_redis_command(['GET', $key]); return is_string($value) ? $value : null; }
function svc_cache_set(string $key, string $value, int $seconds): bool { return svc_redis_command(['SET', $key, $value, 'EX', max(1, $seconds)]) === 'OK'; }
function svc_cache_delete(string $key): void { svc_redis_command(['DEL', $key]); }
function svc_cache_version(string $scope): int { return max(0, (int)(svc_cache_get('version:' . $scope) ?? 0)); }
function svc_cache_bump(string $scope): void { svc_redis_command(['INCR', 'version:' . $scope]); }

function svc_rate_limit_exceeded(string $scope, string $identity, int $limit): bool {
    return (int)(svc_cache_get('rate:' . $scope . ':' . hash('sha256', $identity)) ?? 0) >= $limit;
}
function svc_rate_limit_hit(string $scope, string $identity, int $seconds): int {
    $key = 'rate:' . $scope . ':' . hash('sha256', $identity);
    $count = svc_redis_command(['INCR', $key]);
    if (!is_int($count)) return 0;
    if ($count === 1) svc_redis_command(['EXPIRE', $key, max(1, $seconds)]);
    return $count;
}

function svc_db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $host = svc_env('DB_HOST', 'db');
    $port = svc_env('DB_PORT', '3306');
    $name = svc_env('DB_NAME', 'notezy');
    $user = svc_env('DB_USER', 'notezy');
    $pass = svc_env('DB_PASSWORD');
    if ($pass === null) svc_json(500, ['error' => 'DATABASE_CONFIG_MISSING']);
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec("SET time_zone = '+07:00'");
    return $pdo;
}

function svc_bearer_user(): int {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\\s+(.+)$/i', $header, $m)) svc_json(401, ['error' => 'UNAUTHENTICATED']);
    $parts = explode('.', $m[1]);
    if (count($parts) !== 2) svc_json(401, ['error' => 'INVALID_TOKEN']);
    [$payload64, $signature] = $parts;
    $secret = svc_env('JWT_SECRET');
    if ($secret === null) svc_json(500, ['error' => 'JWT_CONFIG_MISSING']);
    $expected = hash_hmac('sha256', $payload64, $secret);
    if (!hash_equals($expected, $signature)) svc_json(401, ['error' => 'INVALID_TOKEN']);
    $payload = json_decode(base64_decode(strtr($payload64, '-_', '+/'), true) ?: '', true);
    if (!is_array($payload) || (int)($payload['exp'] ?? 0) < time() || (int)($payload['sub'] ?? 0) < 1) {
        svc_json(401, ['error' => 'TOKEN_EXPIRED']);
    }
    return (int)$payload['sub'];
}

function svc_token(int $userId): string {
    $secret = svc_env('JWT_SECRET');
    if ($secret === null) svc_json(500, ['error' => 'JWT_CONFIG_MISSING']);
    $payload64 = rtrim(strtr(base64_encode(json_encode(['sub' => $userId, 'exp' => time() + 3600])), '+/', '-_'), '=');
    return $payload64 . '.' . hash_hmac('sha256', $payload64, $secret);
}

function svc_health(string $service, bool $database = false): never {
    try {
        if ($database) svc_db()->query('SELECT 1');
        svc_json(200, ['status' => 'ok', 'service' => $service, 'timezone' => 'Asia/Ho_Chi_Minh']);
    } catch (Throwable $e) {
        error_log(json_encode(['service' => $service, 'request_id' => svc_request_id(), 'error' => $e->getMessage()]));
        svc_json(503, ['status' => 'unavailable', 'service' => $service]);
    }
}
