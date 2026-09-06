<?php
/**
 * api/db.php
 * - Reads DB credentials from environment (never hardcoded).
 * - CORS restricted to frontend origin (not wildcard *).
 * - All DB connections go through config/database.php.
 */

// ── CORS ────────────────────────────────────────────────────────────────────
$allowed_origins = [
    'http://localhost:5173',
    'http://localhost:8080',
    'http://127.0.0.1:5173',
    'http://127.0.0.1:8080',
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
// Only set CORS headers when an Origin header is present (cross-origin requests).
// Same-origin requests (XAMPP, direct PHP access) have no Origin header — skip headers
// to avoid conflicts with session cookies.
if ($origin !== '' && in_array($origin, $allowed_origins, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}


// ── DB (single source of truth) ─────────────────────────────────────────────
require_once __DIR__ . '/../config/database.php';
?>
