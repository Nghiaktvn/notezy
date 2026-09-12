<?php
declare(strict_types=1);
require __DIR__ . '/../../shared/bootstrap.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$routes = [
    '/api/auth/' => ['name' => 'auth', 'url' => 'http://auth_service:8080'],
    '/api/users/' => ['name' => 'user', 'url' => 'http://user_service:8080'],
    '/api/notes/' => ['name' => 'note', 'url' => 'http://note_service:8080'],
    '/api/files/' => ['name' => 'file', 'url' => 'http://file_service:8080'],
    '/api/ai/' => ['name' => 'ai', 'url' => 'http://ai_service:8080'],
    '/api/premium/' => ['name' => 'premium', 'url' => 'http://premium_service:8080'],
    '/api/collaboration/' => ['name' => 'collaboration', 'url' => 'http://collaboration_service:8080'],
];
if ($path === '/health') svc_health('gateway');
if ($path === '/ready') {
    $checks = [];
    foreach ($routes as $route) {
        $body = @file_get_contents($route['url'] . '/ready', false, stream_context_create(['http' => ['timeout' => 1, 'ignore_errors' => true]]));
        $checks[$route['name']] = $body !== false;
    }
    svc_json(in_array(false, $checks, true) ? 503 : 200, ['status' => in_array(false, $checks, true) ? 'unavailable' : 'ok', 'service' => 'gateway', 'dependencies' => $checks, 'request_id' => svc_request_id()]);
}
foreach ($routes as $prefix => $route) {
    if (str_starts_with($path, $prefix)) {
        $startedAt = microtime(true);
        $suffix = substr($path, strlen('/api/' . explode('/', trim($prefix, '/'))[1]));
        $url = $route['url'] . ($suffix === false ? '/' : $suffix) . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
        $headers = ["Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'application/json'), 'X-Request-Id: ' . svc_request_id()];
        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) $headers[] = 'Authorization: ' . $_SERVER['HTTP_AUTHORIZATION'];
        $ctx = stream_context_create(['http' => ['method' => $_SERVER['REQUEST_METHOD'], 'header' => implode("\r\n", $headers), 'content' => file_get_contents('php://input'), 'ignore_errors' => true, 'timeout' => 10]]);
        $body = @file_get_contents($url, false, $ctx);
        $status = 502;
        foreach ($http_response_header ?? [] as $line) if (preg_match('#HTTP/\\S+\\s+(\\d+)#', $line, $m)) $status = (int)$m[1];
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Request-Id: ' . svc_request_id());
        header('X-Upstream-Service: ' . $route['name']);
        error_log(json_encode(['event' => 'gateway_proxy', 'request_id' => svc_request_id(), 'upstream' => $route['name'], 'status' => $status, 'duration_ms' => (int)((microtime(true) - $startedAt) * 1000)]));
        echo $body === false ? json_encode(['error' => 'UPSTREAM_UNAVAILABLE']) : $body;
        exit;
    }
}
svc_json(404, ['error' => 'ROUTE_NOT_FOUND']);
