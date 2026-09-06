<?php
/**
 * Centralized session bootstrap for Notezy.
 * Keeps session files inside the project when the PHP runtime points to an
 * unavailable XAMPP temp directory.
 */
function notezy_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $session_dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sessions';
    if (!is_dir($session_dir)) {
        @mkdir($session_dir, 0775, true);
    }

    if (is_dir($session_dir) && is_writable($session_dir)) {
        session_save_path($session_dir);
    }

    session_set_cookie_params([
        'httponly' => true,
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);

    session_start();
}
