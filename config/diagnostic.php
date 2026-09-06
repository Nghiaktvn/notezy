<?php
/**
 * config/diagnostic.php
 * Notezy System Environment Diagnostic & Setup Assistant
 * Displays a clean, professional status page when PHP mysqli extension or
 * MySQL database is not configured, with 1-click auto-setup when possible.
 */

function notezy_render_diagnostic(string $errorType, string $errorMessage = '', array $extra = []): void {
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "\n=======================================================\n");
        fwrite(STDERR, "⚠️  NOTEZY SYSTEM ENVIRONMENT DIAGNOSTIC\n");
        fwrite(STDERR, "=======================================================\n");
        fwrite(STDERR, "• Thông báo: " . $errorMessage . "\n");
        fwrite(STDERR, "• PHP Version: " . PHP_VERSION . "\n");
        fwrite(STDERR, "• mysqli extension: " . (extension_loaded('mysqli') ? "✅ Đã bật" : "❌ CHƯA BẬT (Bật extension=mysqli trong php.ini hoặc sudo apt install php-mysql)") . "\n");
        fwrite(STDERR, "• Hướng dẫn XAMPP: Khởi động Apache & MySQL, tạo DB notezy, import note.sql rồi migrations.sql\n");
        fwrite(STDERR, "• Hướng dẫn Docker: docker compose up -d --build (web:8080, db:3307, phpmyadmin:8081)\n");
        fwrite(STDERR, "=======================================================\n\n");
        exit(1);
    }

    $is_api = (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/') !== false)
        || (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
        || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

    if ($is_api) {
        if (!headers_sent()) {
            http_response_code(503);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'status' => 'error',
            'code' => $errorType,
            'message' => $errorMessage,
            'diagnostic' => [
                'php_version' => PHP_VERSION,
                'mysqli_loaded' => extension_loaded('mysqli'),
                'pdo_loaded' => extension_loaded('pdo'),
                'pdo_mysql_loaded' => extension_loaded('pdo_mysql'),
            ],
            'help' => [
                'xampp' => 'Mở XAMPP Control Panel -> Bật Apache & MySQL -> Config php.ini -> Bỏ dấu ; trước extension=mysqli -> Restart Apache.',
                'docker' => 'Chạy: docker compose up -d --build (web:8080, db:3307, phpmyadmin:8081, ai_agent:8765)',
                'database' => 'Tạo database notezy và import note.sql rồi migrations.sql'
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit();
    }

    if (!headers_sent()) {
        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
    }

    $php_version = PHP_VERSION;
    $has_mysqli = extension_loaded('mysqli');
    $has_pdo = extension_loaded('pdo');
    $has_pdo_mysql = extension_loaded('pdo_mysql');
    $db_host = defined('DB_HOST') ? DB_HOST : (getenv('DB_HOST') ?: '127.0.0.1');
    $db_port = defined('DB_PORT') ? DB_PORT : (getenv('DB_PORT') ?: '3306');
    $db_user = defined('DB_USER') ? DB_USER : (getenv('DB_USER') ?: 'root');
    $db_name = defined('DB_NAME') ? DB_NAME : (getenv('DB_NAME') ?: 'notezy');

    // Handle 1-click database auto-setup if MySQL is available but DB is missing
    $setup_message = '';
    $setup_success = false;
    if ($has_mysqli && isset($_POST['action']) && $_POST['action'] === 'auto_setup_db') {
        try {
            $raw_conn = @new mysqli($db_host, $db_user, (defined('DB_PASS') ? DB_PASS : (getenv('DB_PASSWORD') ?: '')), '', (int)$db_port);
            if ($raw_conn->connect_error) {
                $setup_message = "Không thể kết nối máy chủ MySQL: " . $raw_conn->connect_error;
            } else {
                $raw_conn->query("CREATE DATABASE IF NOT EXISTS `{$db_name}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
                $raw_conn->select_db($db_name);

                // Run note.sql
                $note_sql_path = __DIR__ . '/../note.sql';
                if (file_exists($note_sql_path)) {
                    $note_sql = file_get_contents($note_sql_path);
                    $raw_conn->multi_query($note_sql);
                    while ($raw_conn->more_results() && $raw_conn->next_result()) {;}
                }

                // Run migrations.sql
                $mig_sql_path = __DIR__ . '/../migrations.sql';
                if (file_exists($mig_sql_path)) {
                    $mig_conn = new mysqli($db_host, $db_user, (defined('DB_PASS') ? DB_PASS : (getenv('DB_PASSWORD') ?: '')), $db_name, (int)$db_port);
                    $mig_sql = file_get_contents($mig_sql_path);
                    $mig_conn->multi_query($mig_sql);
                    while ($mig_conn->more_results() && $mig_conn->next_result()) {;}
                    $mig_conn->close();
                }

                $raw_conn->close();
                $setup_success = true;
                $setup_message = "Khởi tạo database notezy và nạp schema thành công! Đang tải lại ứng dụng...";
                echo "<script>setTimeout(function(){ window.location.reload(); }, 1500);</script>";
            }
        } catch (Throwable $t) {
            $setup_message = "Lỗi khi tự động thiết lập: " . $t->getMessage();
        }
    }

    ?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notezy - Trợ lý cấu hình môi trường</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #f8fafc;
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 15px;
        }
        .diag-card {
            background: rgba(30, 41, 59, 0.85);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
            max-width: 850px;
            width: 100%;
            overflow: hidden;
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 4px 10px;
            border-radius: 12px;
            font-weight: 600;
        }
        .badge-ok { background: #059669; color: #fff; }
        .badge-fail { background: #dc2626; color: #fff; }
        .badge-warn { background: #d97706; color: #fff; }
        .code-box {
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 8px;
            padding: 12px 16px;
            color: #38bdf8;
            font-family: monospace;
            font-size: 0.9rem;
            overflow-x: auto;
        }
    </style>
</head>
<body>
<div class="diag-card p-4 p-md-5">
    <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom border-secondary">
        <div style="font-size: 2.2rem; color: #38bdf8;">
            <i class="fas fa-stethoscope"></i>
        </div>
        <div>
            <h3 class="fw-bold mb-0">Notezy — Trợ lý kiểm tra môi trường & Cơ sở dữ liệu</h3>
            <p class="text-secondary mb-0 small">Báo cáo chẩn đoán trạng thái hệ thống PHP & MySQL</p>
        </div>
    </div>

    <?php if ($setup_message): ?>
        <div class="alert <?= $setup_success ? 'alert-success' : 'alert-danger' ?> d-flex align-items-center gap-2">
            <i class="fas <?= $setup_success ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <span><?= htmlspecialchars($setup_message) ?></span>
        </div>
    <?php endif; ?>

    <div class="alert alert-warning d-flex align-items-start gap-3 mb-4" style="background: rgba(217, 119, 6, 0.15); border-color: rgba(217, 119, 6, 0.4); color: #fef3c7;">
        <i class="fas fa-info-circle fa-lg mt-1 text-warning"></i>
        <div>
            <strong>Thông tin chẩn đoán:</strong> <?= htmlspecialchars($errorMessage ?: 'Cần cấu hình dịch vụ MySQL / PHP extension để khởi chạy.') ?>
        </div>
    </div>

    <h5 class="fw-bold mb-3"><i class="fas fa-tasks me-2 text-info"></i>Trạng thái thành phần hệ thống</h5>
    <div class="list-group mb-4">
        <!-- PHP Version -->
        <div class="list-group-item bg-dark text-light border-secondary d-flex justify-content-between align-items-center">
            <div>
                <i class="fab fa-php me-2 text-primary"></i> <strong>PHP Engine</strong>
                <div class="text-secondary small">Phiên bản đang chạy: <?= htmlspecialchars($php_version) ?></div>
            </div>
            <span class="status-badge badge-ok"><i class="fas fa-check me-1"></i>Hoạt động (<?= htmlspecialchars(PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION) ?>)</span>
        </div>

        <!-- mysqli extension -->
        <div class="list-group-item bg-dark text-light border-secondary d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-plug me-2 text-warning"></i> <strong>PHP Extension `mysqli`</strong>
                <div class="text-secondary small">Cần thiết để kết nối MySQL trong ứng dụng XAMPP & PHP thuần</div>
            </div>
            <?php if ($has_mysqli): ?>
                <span class="status-badge badge-ok"><i class="fas fa-check me-1"></i>Đã bật</span>
            <?php else: ?>
                <span class="status-badge badge-fail"><i class="fas fa-times me-1"></i>Chưa bật / Thiếu</span>
            <?php endif; ?>
        </div>

        <!-- MySQL Connection -->
        <div class="list-group-item bg-dark text-light border-secondary d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-database me-2 text-info"></i> <strong>Kết nối MySQL (<?= htmlspecialchars($db_host . ':' . $db_port) ?>)</strong>
                <div class="text-secondary small">Người dùng: <?= htmlspecialchars($db_user) ?>, CSDL mục tiêu: `<?= htmlspecialchars($db_name) ?>`</div>
            </div>
            <?php if ($errorType === 'MYSQLI_EXTENSION_MISSING'): ?>
                <span class="status-badge badge-warn">Chưa kiểm tra (Cần bật mysqli trước)</span>
            <?php elseif ($errorType === 'DB_NOT_FOUND'): ?>
                <span class="status-badge badge-warn"><i class="fas fa-exclamation me-1"></i>MySQL chạy, chưa có DB `<?= htmlspecialchars($db_name) ?>`</span>
            <?php else: ?>
                <span class="status-badge badge-fail"><i class="fas fa-times me-1"></i>Chưa kết nối được</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Instructions Tabs / Guides -->
    <h5 class="fw-bold mb-3"><i class="fas fa-wrench me-2 text-success"></i>Hướng dẫn khắc phục nhanh trong 1 phút</h5>

    <?php if (!$has_mysqli): ?>
        <div class="card bg-dark border-secondary mb-3">
            <div class="card-header border-secondary bg-secondary bg-opacity-25 fw-semibold text-warning">
                <i class="fas fa-lightbulb me-1"></i> Cách bật extension `mysqli` cho PHP
            </div>
            <div class="card-body">
                <p class="mb-2"><strong>Cách 1: Trên XAMPP (Windows)</strong></p>
                <ol class="small text-secondary mb-3 ps-3">
                    <li>Mở XAMPP Control Panel, tại dòng <strong>Apache</strong> nhấn nút <strong>Config</strong> -> chọn <strong>PHP (php.ini)</strong>.</li>
                    <li>Tìm dòng: <code>;extension=mysqli</code> và xóa dấu chấm phẩy ở đầu thành: <code>extension=mysqli</code></li>
                    <li>Lưu file <code>php.ini</code> và nhấn <strong>Stop</strong> rồi <strong>Start</strong> lại Apache.</li>
                </ol>

                <p class="mb-2"><strong>Cách 2: Trên Ubuntu / Debian / Docker Linux</strong></p>
                <div class="code-box mb-3">sudo apt-get update && sudo apt-get install -y php-mysql<br>sudo service apache2 restart # hoặc php -S ...</div>

                <p class="mb-2"><strong>Cách 3: Chạy trực tiếp qua Docker Compose (đã tích hợp đầy đủ)</strong></p>
                <div class="code-box">docker compose up -d --build</div>
            </div>
        </div>
    <?php else: ?>
        <div class="card bg-dark border-secondary mb-3">
            <div class="card-header border-secondary bg-secondary bg-opacity-25 fw-semibold text-info">
                <i class="fas fa-database me-1"></i> Hướng dẫn khởi tạo Database `notezy`
            </div>
            <div class="card-body">
                <?php if ($errorType === 'DB_NOT_FOUND'): ?>
                    <p class="text-light">Máy chủ MySQL đang hoạt động! Bạn có thể nhấn nút dưới đây để hệ thống tự động tạo Database và import dữ liệu ngay lập tức:</p>
                    <form method="POST" class="mb-3">
                        <input type="hidden" name="action" value="auto_setup_db">
                        <button type="submit" class="btn btn-success fw-semibold">
                            <i class="fas fa-magic me-1"></i> Tự động tạo CSDL `notezy` & nạp schema (1-Click)
                        </button>
                    </form>
                    <hr class="border-secondary">
                <?php endif; ?>

                <p class="mb-1"><strong>Các bước thủ công trong phpMyAdmin / XAMPP:</strong></p>
                <ol class="small text-secondary mb-0 ps-3">
                    <li>Khởi động dịch vụ <strong>MySQL</strong> trong XAMPP Control Panel.</li>
                    <li>Truy cập <code>http://localhost/phpmyadmin</code> trên trình duyệt.</li>
                    <li>Tạo database tên là <code>notezy</code> (bảng mã <code>utf8mb4_general_ci</code>).</li>
                    <li>Import file <code>note.sql</code> trước, sau đó import tiếp file <code>migrations.sql</code>.</li>
                </ol>
            </div>
        </div>
    <?php endif; ?>

    <div class="d-flex gap-2 justify-content-end mt-4 pt-3 border-top border-secondary">
        <a href="test_system_flow.php" class="btn btn-outline-info btn-sm">
            <i class="fas fa-vial me-1"></i> Chạy kiểm thử tự động
        </a>
        <button onclick="window.location.reload();" class="btn btn-primary btn-sm px-3">
            <i class="fas fa-sync-alt me-1"></i> Thử kết nối lại
        </button>
    </div>
</div>
</body>
</html>
    <?php
    exit();
}
