<?php
require_once __DIR__ . '/includes/session.php';
/**
 * admin_users.php
 * All-in-one User Management (CRUD) for Notezy Administrators
 * Supported routes:
 *   - admin_users.php (List all users)
 *   - admin_users.php?action=add (Add new user)
 *   - admin_users.php?action=edit&id=X (Edit user X)
 *   - admin_users.php?action=delete&id=X (Delete user X)
 */

require_once __DIR__ . '/db.php';
notezy_session_start();

// Authorization is checked against the database on every request.
if (!isset($_SESSION['id'])) {
    http_response_code(401);
    exit('Vui lòng đăng nhập tài khoản quản trị. <a href="/index.php?auth=login">Đăng nhập</a>');
}
$conn = create_connect();
if (!$conn || $conn->connect_error) { http_response_code(503); exit('Dịch vụ dữ liệu tạm thời không khả dụng.'); }
notezy_ensure_user_role_column($conn);
$auth = $conn->prepare('SELECT role, activated FROM users WHERE id = ?');
$auth->bind_param('i', $_SESSION['id']);
$auth->execute();
$admin_account = $auth->get_result()->fetch_assoc();
$auth->close();
if (!$admin_account || $admin_account['role'] !== 'admin' || (int)$admin_account['activated'] !== 1) {
    http_response_code(403);
    exit('Bạn không có quyền quản trị. <a href="/index_notezy.php">Về trang chủ</a>');
}
$is_admin = true;
$db_error = null;
require_once __DIR__ . '/includes/csrf.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_check((string)($_POST['csrf_token'] ?? ''))) {
    http_response_code(403);
    exit('Phiên biểu mẫu không hợp lệ. Vui lòng tải lại trang.');
}

$action = isset($_GET['action']) ? trim($_GET['action']) : 'list';
$flash_success = '';
$flash_error = '';

if (isset($_SESSION['flash_success'])) {
    $flash_success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (isset($_SESSION['flash_error'])) {
    $flash_error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

// Ensure 'role' column exists in users table safely if DB is active
if ($conn && !$conn->connect_error) {
    notezy_ensure_user_role_column($conn);
    notezy_ensure_user_last_seen_column($conn);
    @$conn->query("CREATE TABLE IF NOT EXISTS chat_messages (
        message_id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        message_type ENUM('text','note') NOT NULL DEFAULT 'text',
        message TEXT NULL,
        note_id INT NULL,
        permission ENUM('read','write') NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_chat_pair (sender_id, receiver_id, created_at),
        INDEX idx_chat_note (note_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    if (isset($_SESSION['id'])) {
        $current_seen_id = (int) $_SESSION['id'];
        $seen_stmt = $conn->prepare("UPDATE users SET last_seen_at = NOW() WHERE id = ?");
        if ($seen_stmt) {
            $seen_stmt->bind_param('i', $current_seen_id);
            $seen_stmt->execute();
            $seen_stmt->close();
        }
    }
} else {
    // Standalone Mock Users for Sandbox/Offline testing
    if (!isset($_SESSION['mock_users'])) {
        $_SESSION['mock_users'] = [
            1 => ['id' => 1, 'username' => 'admin', 'firstname' => 'Quản trị', 'lastname' => 'Hệ thống', 'email' => 'admin@notezy.local', 'activated' => 1, 'role' => 'admin'],
            2 => ['id' => 2, 'username' => 'user1', 'firstname' => 'Nguyễn Văn', 'lastname' => 'An', 'email' => 'user1@gmail.com', 'activated' => 1, 'role' => 'user'],
            3 => ['id' => 3, 'username' => 'user2', 'firstname' => 'Trần Thị', 'lastname' => 'Bình', 'email' => 'user2@gmail.com', 'activated' => 0, 'role' => 'user'],
        ];
    }
}

// ── 1. ACTION: DELETE USER ──────────────────────────────────────────────────
if ($action === 'delete') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Vui lòng dùng nút xóa trên trang quản trị.'); }
    $del_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($del_id <= 0) {
        $_SESSION['flash_error'] = 'ID người dùng không hợp lệ.';
    } elseif (isset($_SESSION['id']) && (int)$_SESSION['id'] === $del_id) {
        $_SESSION['flash_error'] = 'Bạn không thể tự xóa tài khoản của chính mình đang đăng nhập.';
    } else {
        if ($conn && !$conn->connect_error) {
            // Delete dependent records first (notes, shares, etc.)
            $conn->query("DELETE FROM chat_messages WHERE sender_id = $del_id OR receiver_id = $del_id");
            $conn->query("DELETE FROM note_shares WHERE shared_with_user_id = $del_id OR shared_by_user_id = $del_id");
            $conn->query("DELETE FROM notes WHERE user_id = $del_id");
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $del_id);
                if ($stmt->execute()) {
                    $_SESSION['flash_success'] = "Đã xóa thành công người dùng ID #$del_id.";
                } else {
                    $_SESSION['flash_error'] = "Lỗi khi xóa người dùng: " . $stmt->error;
                }
                $stmt->close();
            }
        } else {
            // Mock delete
            unset($_SESSION['mock_users'][$del_id]);
            $_SESSION['flash_success'] = "Đã xóa thành công người dùng ID #$del_id (chế độ ngoại tuyến).";
        }
    }
    header('Location: admin_users.php');
    exit;
}

// ── 2. ACTION: ADD USER (POST) ──────────────────────────────────────────────
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username  = trim($_POST['username'] ?? '');
    $firstname = trim($_POST['firstname'] ?? '');
    $lastname  = trim($_POST['lastname'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = trim($_POST['password'] ?? '');
    $status    = isset($_POST['status']) && (int)$_POST['status'] === 1 ? 1 : 0;
    $role      = isset($_POST['role']) && $_POST['role'] === 'admin' ? 'admin' : 'user';

    if (empty($username) || empty($email) || empty($password)) {
        $flash_error = 'Vui lòng nhập đầy đủ tên tài khoản, thư điện tử và Mật khẩu.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $flash_error = 'Định dạng thư điện tử không hợp lệ.';
    } elseif ($conn) {
        // Check unique username and email
        $chk = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
        $chk->bind_param("ss", $username, $email);
        $chk->execute();
        $exists = $chk->get_result()->fetch_assoc();
        $chk->close();

        if ($exists) {
            $flash_error = 'Tên đăng nhập hoặc thư điện tử này đã tồn tại trong hệ thống.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins = $conn->prepare("INSERT INTO users (username, firstname, lastname, email, password_hash, activated, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if ($ins) {
                $ins->bind_param("sssssis", $username, $firstname, $lastname, $email, $hash, $status, $role);
                if ($ins->execute()) {
                    $_SESSION['flash_success'] = "Đã thêm thành công người dùng '{$username}'!";
                    header('Location: admin_users.php');
                    exit;
                } else {
                    $flash_error = "Lỗi khi tạo người dùng: " . $ins->error;
                }
                $ins->close();
            }
        }
    } else {
        // Offline / Sandbox Mock Add
        $new_id = (count($_SESSION['mock_users']) ? max(array_keys($_SESSION['mock_users'])) : 0) + 1;
        $_SESSION['mock_users'][$new_id] = [
            'id' => $new_id,
            'username' => $username,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => $email,
            'activated' => $status,
            'role' => $role
        ];
        $_SESSION['flash_success'] = "Đã thêm thành công người dùng '{$username}' (chế độ ngoại tuyến)!";
        header('Location: admin_users.php');
        exit;
    }
}

// ── 3. ACTION: EDIT USER (POST) ─────────────────────────────────────────────
if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $edit_id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $username  = trim($_POST['username'] ?? '');
    $firstname = trim($_POST['firstname'] ?? '');
    $lastname  = trim($_POST['lastname'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = trim($_POST['password'] ?? '');
    $status    = isset($_POST['status']) && (int)$_POST['status'] === 1 ? 1 : 0;
    $role      = isset($_POST['role']) && $_POST['role'] === 'admin' ? 'admin' : 'user';

    if (empty($username) || empty($email) || $edit_id <= 0) {
        $flash_error = 'Vui lòng nhập đầy đủ tên tài khoản và thư điện tử.';
    } elseif ($edit_id === (int)$_SESSION['id'] && ($role !== 'admin' || $status !== 1)) {
        $flash_error = 'Bạn không thể tự gỡ quyền quản trị hoặc hủy kích hoạt tài khoản đang dùng.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $flash_error = 'Định dạng thư điện tử không hợp lệ.';
    } elseif ($conn && !$conn->connect_error) {
        // Check duplicate username/email for other users
        $chk = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ? LIMIT 1");
        $chk->bind_param("ssi", $username, $email, $edit_id);
        $chk->execute();
        $dup = $chk->get_result()->fetch_assoc();
        $chk->close();

        if ($dup) {
            $flash_error = 'Tên đăng nhập hoặc thư điện tử này đã được sử dụng bởi tài khoản khác.';
        } else {
            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $upd = $conn->prepare("UPDATE users SET username = ?, firstname = ?, lastname = ?, email = ?, password_hash = ?, activated = ?, role = ? WHERE id = ?");
                $upd->bind_param("sssssisi", $username, $firstname, $lastname, $email, $hash, $status, $role, $edit_id);
            } else {
                $upd = $conn->prepare("UPDATE users SET username = ?, firstname = ?, lastname = ?, email = ?, activated = ?, role = ? WHERE id = ?");
                $upd->bind_param("ssssisi", $username, $firstname, $lastname, $email, $status, $role, $edit_id);
            }
            if ($upd && $upd->execute()) {
                $_SESSION['flash_success'] = "Đã cập nhật thành công người dùng #{$edit_id} ({$username})!";
                header('Location: admin_users.php');
                exit;
            } else {
                $flash_error = "Lỗi khi cập nhật người dùng: " . ($upd ? $upd->error : $conn->error);
            }
        }
    } else {
        // Offline / Sandbox Mock Edit
        if (isset($_SESSION['mock_users'][$edit_id])) {
            $_SESSION['mock_users'][$edit_id]['username'] = $username;
            $_SESSION['mock_users'][$edit_id]['firstname'] = $firstname;
            $_SESSION['mock_users'][$edit_id]['lastname'] = $lastname;
            $_SESSION['mock_users'][$edit_id]['email'] = $email;
            $_SESSION['mock_users'][$edit_id]['activated'] = $status;
            $_SESSION['mock_users'][$edit_id]['role'] = $role;
            $_SESSION['flash_success'] = "Đã cập nhật thành công người dùng #{$edit_id} ({$username}) (chế độ ngoại tuyến)!";
            header('Location: admin_users.php');
            exit;
        }
    }
}

// Fetch user for edit view
$edit_user = null;
if ($action === 'edit') {
    $edit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($conn && !$conn->connect_error && $edit_id > 0) {
        $st = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $st->bind_param("i", $edit_id);
        $st->execute();
        $edit_user = $st->get_result()->fetch_assoc();
        $st->close();
    } else {
        $edit_user = $_SESSION['mock_users'][$edit_id] ?? null;
    }
    if (!$edit_user) {
        $_SESSION['flash_error'] = "Không tìm thấy người dùng ID #$edit_id.";
        header('Location: admin_users.php');
        exit;
    }
}

// Fetch list of all users
$users_list = [];
$own_notes_for_share = [];
if ($conn && !$conn->connect_error) {
    $res = $conn->query("SELECT id, username, firstname, lastname, email, activated, role, avatar, last_seen_at FROM users ORDER BY id ASC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $users_list[] = $row;
        }
    }
    if (isset($_SESSION['id'])) {
        $owner_id = (int) $_SESSION['id'];
        $notes_stmt = $conn->prepare("SELECT note_id, title FROM notes WHERE user_id = ? AND COALESCE(archived, 0) = 0 ORDER BY updated_at DESC, created_at DESC");
        if ($notes_stmt) {
            $notes_stmt->bind_param('i', $owner_id);
            $notes_stmt->execute();
            $own_notes_for_share = $notes_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $notes_stmt->close();
        }
    }
} else {
    $users_list = array_values($_SESSION['mock_users'] ?? []);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notezy - Quản trị Người Dùng</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8fafc;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #1e293b;
        }
        .admin-navbar {
            background: #0f172a;
            border-bottom: 3px solid #3b82f6;
        }
        .card-custom {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            background: #ffffff;
        }
        .table th {
            background: #f1f5f9;
            color: #475569;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-active {
            background-color: #10b981;
            color: #ffffff;
            font-weight: 500;
        }
        .badge-inactive {
            background-color: #f59e0b;
            color: #ffffff;
            font-weight: 500;
        }
        .badge-online {
            background-color: #dcfce7;
            color: #15803d;
            border: 1px solid #86efac;
            font-weight: 700;
        }
        .badge-offline {
            background-color: #f1f5f9;
            color: #64748b;
            border: 1px solid #cbd5e1;
            font-weight: 700;
        }
        .user-action-btn {
            width: 34px;
            height: 34px;
            display: inline-grid;
            place-items: center;
            border-radius: 8px;
        }
        .btn-add {
            background-color: #2563eb;
            color: #fff;
            font-weight: 600;
            border-radius: 8px;
            padding: 8px 18px;
            transition: all 0.2s;
        }
        .btn-add:hover {
            background-color: #1d4ed8;
            color: #fff;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
    </style>
</head>
<body>

    <!-- Header Navbar -->
    <nav class="navbar navbar-dark admin-navbar py-3">
        <div class="container">
            <div class="d-flex align-items-center gap-3">
                <a href="index_notezy.php" class="navbar-brand fw-bold mb-0 d-flex align-items-center gap-2">
                    <i class="fas fa-shield-alt text-primary fs-4"></i> Quản trị Notezy
                </a>
                <span class="badge bg-primary">Quản lý người dùng</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="index_notezy.php" class="btn btn-sm btn-outline-light">
                    <i class="fas fa-arrow-left me-1"></i> Về Notezy
                </a>

            </div>
        </div>
    </nav>

    <div class="container py-4">

        <!-- Flash messages -->
        <?php if (!empty($flash_success)): ?>
            <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm" role="alert">
                <i class="fas fa-check-circle fs-5"></i>
                <div><?= htmlspecialchars($flash_success) ?></div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($flash_error)): ?>
            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm" role="alert">
                <i class="fas fa-exclamation-triangle fs-5"></i>
                <div><?= htmlspecialchars($flash_error) ?></div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- ── VIEW 1: ADD USER FORM ────────────────────────────────────────── -->
        <?php if ($action === 'add'): ?>
            <div class="row justify-content-center">
                <div class="col-lg-7 col-md-9">
                    <div class="card card-custom p-4">
                        <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                            <h4 class="mb-0 fw-bold text-primary"><i class="fas fa-user-plus me-2"></i>➕ Thêm người dùng mới</h4>
                            <a href="admin_users.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-times me-1"></i> Hủy
                            </a>
                        </div>
                        <form method="post" action="admin_users.php?action=add">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Tên tài khoản <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="username" required placeholder="VD: user1" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-semibold">Họ</label>
                                    <input type="text" class="form-control" name="firstname" placeholder="VD: Nguyễn Văn" value="<?= htmlspecialchars($_POST['firstname'] ?? '') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-semibold">Tên</label>
                                    <input type="text" class="form-control" name="lastname" placeholder="VD: An" value="<?= htmlspecialchars($_POST['lastname'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Thư điện tử <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="email" required placeholder="user1@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Mật khẩu <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" name="password" required placeholder="Nhập mật khẩu cho người dùng">
                            </div>
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Trạng thái</label>
                                    <select class="form-select" name="status">
                                        <option value="1" selected>Đã kích hoạt</option>
                                        <option value="0">Chưa kích hoạt</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Vai trò</label>
                                    <select class="form-select" name="role">
                                        <option value="user" selected>User (Người dùng thường)</option>
                                        <option value="admin">Admin (Quản trị viên)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-add flex-grow-1">
                                    <i class="fas fa-check me-1"></i> Lưu người dùng
                                </button>
                                <a href="admin_users.php" class="btn btn-light border px-4">Quay lại</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        <!-- ── VIEW 2: EDIT USER FORM ───────────────────────────────────────── -->
        <?php elseif ($action === 'edit' && $edit_user): ?>
            <div class="row justify-content-center">
                <div class="col-lg-7 col-md-9">
                    <div class="card card-custom p-4">
                        <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                            <h4 class="mb-0 fw-bold text-warning text-dark"><i class="fas fa-user-edit me-2"></i>✏️ Sửa người dùng #<?= $edit_user['id'] ?></h4>
                            <a href="admin_users.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-times me-1"></i> Hủy
                            </a>
                        </div>
                        <form method="post" action="admin_users.php?action=edit&id=<?= $edit_user['id'] ?>">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Tên tài khoản <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="username" required value="<?= htmlspecialchars($edit_user['username']) ?>">
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-semibold">Họ</label>
                                    <input type="text" class="form-control" name="firstname" value="<?= htmlspecialchars($edit_user['firstname'] ?? '') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-semibold">Tên</label>
                                    <input type="text" class="form-control" name="lastname" value="<?= htmlspecialchars($edit_user['lastname'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Thư điện tử <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="email" required value="<?= htmlspecialchars($edit_user['email']) ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Đổi Mật Khẩu (Để trống nếu giữ nguyên)</label>
                                <input type="password" class="form-control" name="password" placeholder="Nhập mật khẩu mới nếu muốn đổi">
                            </div>
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Trạng thái</label>
                                    <select class="form-select" name="status">
                                        <option value="1" <?= (int)$edit_user['activated'] === 1 ? 'selected' : '' ?>>Đã kích hoạt</option>
                                        <option value="0" <?= (int)$edit_user['activated'] === 0 ? 'selected' : '' ?>>Chưa kích hoạt</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Vai trò</label>
                                    <select class="form-select" name="role">
                                        <option value="user" <?= ($edit_user['role'] ?? 'user') === 'user' ? 'selected' : '' ?>>Người dùng</option>
                                        <option value="admin" <?= ($edit_user['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Quản trị viên</option>
                                    </select>
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-warning flex-grow-1 fw-semibold">
                                    <i class="fas fa-save me-1"></i> Cập nhật thay đổi
                                </button>
                                <a href="admin_users.php" class="btn btn-light border px-4">Quay lại</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        <!-- ── VIEW 3: LIST ALL USERS TABLE ─────────────────────────────────── -->
        <?php else: ?>
            <div class="card card-custom p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                    <div>
                        <h4 class="fw-bold mb-1"><i class="fas fa-users-cog text-primary me-2"></i>Danh sách người dùng</h4>
                        <p class="text-muted small mb-0">Quản lý tài khoản, trạng thái kích hoạt và phân quyền người dùng Notezy.</p>
                    </div>
                    <a href="admin_users.php?action=add" class="btn btn-add">
                        <i class="fas fa-user-plus me-1"></i> ➕ Thêm người dùng
                    </a>
                </div>

                <div class="table-responsive mt-3">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width: 70px;">Mã</th>
                                <th>Tài khoản / Họ tên</th>
                                <th>Thư điện tử</th>
                                <th style="width: 140px;">Trạng thái</th>
                                <th style="width: 130px;">Đang trực tuyến</th>
                                <th style="width: 260px;" class="text-end">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users_list)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="fas fa-user-slash fs-2 mb-2 d-block text-secondary"></i>
                                        Chưa có người dùng nào trong cơ sở dữ liệu. Nhấn <strong>➕ Thêm người dùng</strong> để thêm mới.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users_list as $u): ?>
                                    <?php
$display_name = trim(($u['firstname'] ?? '') . ' ' . ($u['lastname'] ?? ''));
                                        $is_active = (int)$u['activated'] === 1;
                                        $last_seen_ts = !empty($u['last_seen_at']) ? strtotime($u['last_seen_at']) : 0;
                                        $is_online = $last_seen_ts > 0 && (time() - $last_seen_ts) <= 300;
                                        $is_self = isset($_SESSION['id']) && (int)$_SESSION['id'] === (int)$u['id'];
                                    ?>
                                    <tr>
                                        <td class="fw-bold text-secondary">#<?= $u['id'] ?></td>
                                        <td>
                                            <div class="fw-semibold text-dark"><?= htmlspecialchars($u['username']) ?></div>
                                            <?php if (!empty($display_name)): ?>
                                                <small class="text-muted"><?= htmlspecialchars($display_name) ?></small>
                                            <?php endif; ?>
                                            <?php if (($u['role'] ?? '') === 'admin'): ?>
                                                <span class="badge bg-dark ms-1" style="font-size: 0.65rem;">QUẢN TRỊ</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="mailto:<?= htmlspecialchars($u['email']) ?>" class="text-decoration-none text-secondary">
                                                <?= htmlspecialchars($u['email']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?php if ($is_active): ?>
                                                <span class="badge badge-active px-2 py-1"><i class="fas fa-check-circle me-1"></i>Đã kích hoạt</span>
                                            <?php else: ?>
                                                <span class="badge badge-inactive px-2 py-1"><i class="fas fa-clock me-1"></i>Chưa kích hoạt</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($is_online): ?>
                                                <span class="badge badge-online px-2 py-1"><i class="fas fa-circle me-1"></i>Đang trực tuyến</span>
                                            <?php else: ?>
                                                <span class="badge badge-offline px-2 py-1"><i class="far fa-circle me-1"></i>Ngoại tuyến</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-success user-action-btn me-1"
                                                    onclick="openAdminShareModal(<?= (int)$u['id'] ?>, <?= json_encode($u['username'], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>)"
                                                    title="Chia sẻ ghi chú"
                                                    <?= $is_self ? 'disabled' : '' ?>>
                                                <i class="fas fa-share-alt"></i>
                                            </button>
                                            <a href="chat.php?user_id=<?= (int)$u['id'] ?>" class="btn btn-sm btn-outline-info user-action-btn me-1" title="Trò chuyện">
                                                <i class="fas fa-comment-dots"></i>
                                            </a>
                                            <a href="admin_users.php?action=edit&id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary me-1" title="Chỉnh sửa">
                                                <i class="fas fa-edit me-1"></i>Sửa
                                            </a>
                                            <form method="post" action="admin_users.php?action=delete&id=<?= (int)$u['id'] ?>" class="d-inline" onsubmit="return confirm('Xóa tài khoản này và dữ liệu liên quan? Thao tác không thể hoàn tác.')">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Xóa người dùng" <?= $is_self ? 'disabled' : '' ?>><i class="fas fa-trash-alt me-1"></i>Xóa</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <div class="modal fade" id="adminShareNoteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-light">
                    <h5 class="modal-title"><i class="fas fa-share-alt text-success me-2"></i>Chia sẻ ghi chú</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="adminShareNoteForm">
                    <div class="modal-body">
                        <input type="hidden" id="adminShareTargetId" name="with_user_id">
                        <div class="alert alert-info py-2 small">
                            Chia sẻ ghi chú cho <strong id="adminShareTargetName"></strong> và gửi thẻ ghi chú vào cuộc trò chuyện.
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Ghi chú của tôi</label>
                            <select id="adminShareNoteId" name="note_id" class="form-select" required>
                                <?php if (empty($own_notes_for_share)): ?>
                                    <option value="">Bạn chưa có ghi chú để chia sẻ</option>
                                <?php else: ?>
                                    <?php foreach ($own_notes_for_share as $note): ?>
                                        <option value="<?= (int)$note['note_id'] ?>"><?= htmlspecialchars($note['title']) ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="mb-0">
                            <label class="form-label fw-semibold">Quyền truy cập</label>
                            <select id="adminSharePermission" name="permission" class="form-select">
                                <option value="read">Chỉ xem</option>
                                <option value="write">Được chỉnh sửa</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Đóng</button>
                        <button type="submit" id="adminShareSubmit" class="btn btn-success" <?= empty($own_notes_for_share) ? 'disabled' : '' ?>>
                            <i class="fas fa-paper-plane me-1"></i> Chia sẻ ngay
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const adminShareModal = new bootstrap.Modal(document.getElementById('adminShareNoteModal'));

        function openAdminShareModal(userId, username) {
            document.getElementById('adminShareTargetId').value = userId;
            document.getElementById('adminShareTargetName').textContent = username;
            document.getElementById('adminSharePermission').value = 'read';
            adminShareModal.show();
        }

        document.getElementById('adminShareNoteForm').addEventListener('submit', async function(event) {
            event.preventDefault();
            const submit = document.getElementById('adminShareSubmit');
            const body = new URLSearchParams(new FormData(this));
            body.set('action', 'share_note_chat');
            submit.disabled = true;
            submit.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Đang chia sẻ...';
            try {
                const response = await fetch('api/chat_api.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body
                });
                const data = await response.json();
                if (data.success) {
                    adminShareModal.hide();
                    alert(data.message || 'Đã chia sẻ ghi chú.');
                } else {
                    alert(data.message || 'Không thể chia sẻ ghi chú.');
                }
            } catch (error) {
                alert('Không thể kết nối máy chủ.');
            } finally {
                submit.disabled = <?= empty($own_notes_for_share) ? 'true' : 'false' ?>;
                submit.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Chia sẻ ngay';
            }
        });
    </script>
</body>
</html>
