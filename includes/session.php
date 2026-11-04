<?php
/**
 * Centralized session bootstrap for Notezy.
 * Store session files outside the document root, including on XAMPP.
 */
function notezy_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $session_dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR . 'notezy-sessions-' . substr(hash('sha256', dirname(__DIR__)), 0, 16);
    if (!is_dir($session_dir)) {
        @mkdir($session_dir, 0700, true);
    }

    if (is_dir($session_dir) && is_writable($session_dir)) {
        session_save_path($session_dir);
    } else {
        throw new RuntimeException('Session storage is unavailable.');
    }
    ini_set('session.use_strict_mode', '1');

    // Notezy is designed for Vietnamese users. Keep all PHP date formatting
    // and server-side reminder calculations in the same, explicit timezone.
    date_default_timezone_set('Asia/Ho_Chi_Minh');

    session_set_cookie_params([
        'httponly' => true,
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || str_starts_with(strtolower((string) getenv('APP_URL')), 'https://'),
        'samesite' => 'Lax',
    ]);

    session_start();
}
