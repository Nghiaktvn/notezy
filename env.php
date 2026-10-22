<?php
function loadEnv($path, $override = false) {
    if (!file_exists($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        $current = getenv($name);
        // Docker Compose often injects empty MAIL_* keys; fill those from .env
        if ($override || $current === false || $current === '') {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

loadEnv(__DIR__ . '/.env');

// Docker Compose injects its own DB_* variables and exposes /.dockerenv. On a
// Windows/XAMPP host, load an ignored database-only override so local Apache and
// CLI checks do not try to resolve Docker's internal hostname `db`. Mail and
// other credentials remain sourced from the root .env unchanged.
$notezyRuntime = getenv('NOTEZY_RUNTIME') ?: '';
$notezyInDocker = file_exists('/.dockerenv') || $notezyRuntime === 'docker';
if (!$notezyInDocker) {
    loadEnv(__DIR__ . '/.env.xampp', true);
}
?>
