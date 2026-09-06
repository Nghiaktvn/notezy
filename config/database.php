<?php
/**
 * config/database.php
 * Single source of truth for DB connection.
 * Reads credentials from environment variables (.env via env.php).
 * Includes graceful diagnostics and fallback handling for missing mysqli or MySQL.
 */

require_once __DIR__ . '/../env.php';
require_once __DIR__ . '/diagnostic.php';

// PHP >= 8.1 makes mysqli throw mysqli_sql_exception on connect/query failure by
// default. This codebase checks $conn->connect_error instead, so keep reporting off
// — otherwise an unreachable DB becomes an uncaught fatal instead of a 503.
if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: getenv('MYSQLHOST') ?: '127.0.0.1');
if (!defined('DB_PORT')) define('DB_PORT', getenv('DB_PORT') ?: getenv('MYSQLPORT') ?: '3306');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: getenv('MYSQLUSER') ?: 'root');
if (!defined('DB_PASS')) {
    $env_pwd = getenv('DB_PASSWORD');
    if ($env_pwd === false) $env_pwd = getenv('MYSQLPASSWORD');
    define('DB_PASS', $env_pwd !== false ? $env_pwd : '');
}
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: getenv('MYSQLDATABASE') ?: 'notezy');

// Aliases để tương thích với code cũ dùng HOST, USER, PASS
if (!defined('HOST')) define('HOST', DB_HOST);
if (!defined('USER')) define('USER', DB_USER);
if (!defined('PASS')) define('PASS', DB_PASS);

/**
 * Returns active database connection.
 * Gracefully checks for mysqli extension and handles connection failures
 * without throwing unhandled fatal 500 crashes.
 */
function create_connect($fatal = false) {
    static $conn = null;

    if ($conn instanceof mysqli) {
        try {
            if (@$conn->ping()) {
                return $conn;
            }
        } catch (Throwable $e) {
            // Handle went stale (MySQL restarted mid-process): drop it and reconnect.
        }
        $conn = null;
    }

    // 1. Kiểm tra PHP extension mysqli
    if (!extension_loaded('mysqli')) {
        error_log('Notezy DB Error: PHP extension "mysqli" is not loaded.');
        if ($fatal || (defined('NOTEZY_REQUIRE_DB') && NOTEZY_REQUIRE_DB)) {
            notezy_render_diagnostic(
                'MYSQLI_EXTENSION_MISSING',
                'PHP extension "mysqli" chưa được bật. Vui lòng bật extension=mysqli trong file php.ini hoặc cài gói php-mysql.'
            );
            exit();
        }
        return null;
    }

    // 2. Thử kết nối cơ sở dữ liệu
    $primary_port = (int)DB_PORT;
    $attempt = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, $primary_port);

    // Tự động dò cổng thay thế (3307, 3306, 3308) và mật khẩu ('root_password', '') để tương thích cả Docker lẫn XAMPP
    if ($attempt->connect_error && (DB_HOST === '127.0.0.1' || DB_HOST === 'localhost')) {
        $candidate_ports = array_unique([$primary_port, 3307, 3306, 3308]);
        $candidate_passes = array_unique([DB_PASS, 'root_password', '', 'root']);
        foreach ($candidate_ports as $alt_port) {
            foreach ($candidate_passes as $alt_pass) {
                if ($alt_port === $primary_port && $alt_pass === DB_PASS) continue;
                $alt_conn = @new mysqli(DB_HOST, DB_USER, $alt_pass, DB_NAME, $alt_port);
                if (!$alt_conn->connect_error) {
                    $attempt = $alt_conn;
                    break 2;
                }
            }
        }
    }

    if ($attempt->connect_error) {
        $error_msg = $attempt->connect_error;
        error_log('Notezy DB Connect Error: ' . $error_msg);

        if ($fatal || (defined('NOTEZY_REQUIRE_DB') && NOTEZY_REQUIRE_DB)) {
            // Kiểm tra xem máy chủ MySQL có chạy không (chỉ thiếu DB hay sập cả MySQL)
            $server_check = @new mysqli(DB_HOST, DB_USER, DB_PASS, '', (int)DB_PORT);
            if (!$server_check->connect_error) {
                $server_check->close();
                notezy_render_diagnostic(
                    'DB_NOT_FOUND',
                    "Máy chủ MySQL đang hoạt động nhưng cơ sở dữ liệu '" . DB_NAME . "' chưa được tạo hoặc chưa import dữ liệu (note.sql, migrations.sql)."
                );
            } else {
                notezy_render_diagnostic(
                    'DB_CONNECT_FAILED',
                    "Không thể kết nối máy chủ MySQL tại " . DB_HOST . ":" . DB_PORT . " (Lỗi: " . $error_msg . "). Vui lòng đảm bảo dịch vụ MySQL đang chạy trong XAMPP hoặc Docker."
                );
            }
            exit();
        }
        return null;
    }

    $conn = $attempt;
    $conn->set_charset('utf8mb4');

    // Auto-init schema if deploying to a fresh cloud DB
    static $tables_checked = false;
    if (!$tables_checked) {
        $tables_checked = true;
        $chk = @$conn->query("SHOW TABLES LIKE 'users'");
        if ($chk && $chk->num_rows === 0) {
            $sql_file = __DIR__ . '/../note.sql';
            if (file_exists($sql_file)) {
                $sql_content = file_get_contents($sql_file);
                @$conn->multi_query($sql_content);
                while (@$conn->next_result()) {;}
            }
            $mig_file = __DIR__ . '/../migrations.sql';
            if (file_exists($mig_file)) {
                $mig_content = file_get_contents($mig_file);
                @$conn->multi_query($mig_content);
                while (@$conn->next_result()) {;}
            }
        }
    }

    return $conn;
}

function notezy_ensure_column($conn, string $table, string $column, string $definition): bool {
    if (!$conn || $conn->connect_error) {
        return false;
    }

    $safeTable = str_replace('`', '``', $table);
    $safeColumn = $conn->real_escape_string($column);
    $existing = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    if (!$existing) {
        return false;
    }

    $needsCreate = $existing->num_rows === 0;
    $existing->free();
    if (!$needsCreate) {
        return true;
    }

    return (bool) $conn->query("ALTER TABLE `{$safeTable}` ADD COLUMN {$definition}");
}

function notezy_ensure_user_last_seen_column($conn): bool {
    return notezy_ensure_column($conn, 'users', 'last_seen_at', 'last_seen_at DATETIME NULL DEFAULT NULL');
}

function notezy_ensure_user_role_column($conn): bool {
    return notezy_ensure_column($conn, 'users', 'role', "role ENUM('user','admin') NOT NULL DEFAULT 'user'");
}

$conn = create_connect(false);
?>
