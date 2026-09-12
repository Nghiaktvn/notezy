<?php
require_once __DIR__ . '/includes/session.php';
require_once('db.php');
notezy_session_start();

if (!isset($_SESSION['id'])) {
    header('Location: index.php');
    exit();
}

if (!isset($_SESSION['accessed_notes'])) {
    $_SESSION['accessed_notes'] = array();
}

$user_id = (int) $_SESSION['id'];
$note_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$error = '';
$note = null;
$is_owner = false;
$permission = null;

// Lấy thông báo từ session
$success_messages = isset($_SESSION['success_messages']) ? $_SESSION['success_messages'] : [];
$error_messages = isset($_SESSION['error_messages']) ? $_SESSION['error_messages'] : [];
unset($_SESSION['success_messages'], $_SESSION['error_messages']);

// Lấy thông tin ghi chú
try {
    // Tăng giới hạn GROUP_CONCAT và tắt ONLY_FULL_GROUP_BY
    $conn->query("SET SESSION group_concat_max_len = 10000");
    $conn->query("SET SESSION sql_mode = ''");

    $sql = "
        SELECT n.note_id, n.title, n.content, n.pinned, n.created_at, n.user_id, n.password_hash, n.pin_hash,
               n.background_color, n.text_color, n.font_family, n.reminder_at, n.image_path,
               GROUP_CONCAT(l.name SEPARATOR ',') AS labels,
               ns.permission
        FROM notes n
        LEFT JOIN note_labels nl ON n.note_id = nl.note_id
        LEFT JOIN labels l ON nl.label_id = l.label_id
        LEFT JOIN note_shares ns ON n.note_id = ns.note_id AND ns.shared_with_user_id = ?
        WHERE n.note_id = ? AND (n.user_id = ? OR ns.note_id IS NOT NULL)
        GROUP BY n.note_id, n.title, n.content, n.pinned, n.created_at, n.user_id, n.password_hash, n.pin_hash,
                 n.background_color, n.text_color, n.font_family, n.reminder_at, n.image_path, ns.permission
    ";
    $stm = $conn->prepare($sql);
    if (!$stm) {
        die("Prepare failed: " . $conn->error);
    }
    $stm->bind_param('iii', $user_id, $note_id, $user_id);
    if (!$stm->execute()) {
        die("Execute failed: " . $stm->error);
    }
    $result = $stm->get_result();
    $note = $result->fetch_assoc();
    if (!$note) {
        $error = 'Ghi chú không tồn tại hoặc bạn không có quyền truy cập.';
    } else {
        $note['labels'] = $note['labels'] ? explode(',', $note['labels']) : [];
        $is_owner = $note['user_id'] == $user_id;
        $permission = $is_owner ? 'write' : $note['permission'];
    }
    $stm->close();
} catch (Exception $e) {
    $error = "Lỗi truy vấn: " . $e->getMessage();
}

// ── Kiểm tra mã bảo mật 4 số (PIN) trước khi xem sổ ──────────────────────
if (!isset($_SESSION['pin_unlocked_notes'])) {
    $_SESSION['pin_unlocked_notes'] = [];
}
$is_pin_unlocked = !empty($_SESSION['pin_unlocked_notes'][$note_id]);
if (!$is_pin_unlocked && $note && !empty($note['pin_hash'])) {
    $sess_id = session_id();
    $chk_stm = $conn->prepare("SELECT id FROM note_pin_unlocks WHERE note_id = ? AND session_id = ?");
    if ($chk_stm) {
        $chk_stm->bind_param("is", $note_id, $sess_id);
        $chk_stm->execute();
        if ($chk_stm->get_result()->num_rows > 0) {
            $is_pin_unlocked = true;
            $_SESSION['pin_unlocked_notes'][$note_id] = true;
        }
        $chk_stm->close();
    }
}

if ($note && !empty($note['pin_hash']) && !$is_pin_unlocked) {
    $pin_error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['note_pin'])) {
        $entered_pin = trim($_POST['note_pin']);
        if (preg_match('/^\d{6}$/', $entered_pin) && password_verify($entered_pin, $note['pin_hash'])) {
            $_SESSION['pin_unlocked_notes'][$note_id] = true;
            $ins_pin = $conn->prepare("INSERT INTO note_pin_unlocks (note_id, user_id, session_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE unlocked_at = NOW()");
            if ($ins_pin) {
                $sess_id = session_id();
                $ins_pin->bind_param("iis", $note_id, $user_id, $sess_id);
                $ins_pin->execute();
                $ins_pin->close();
            }
            header("Location: notepass.php?id=" . urlencode($note_id));
            exit;
        } else {
            $pin_error = 'Mã bảo mật 6 số không chính xác. Vui lòng thử lại.';
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="vi">
    <head>
        <meta charset="UTF-8">
        <title>Notezy - Mở khóa sổ ghi chú</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
        <style>
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background-color: #f5f5f5;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                padding: 20px;
            }
            .pin-card {
                background: #ffffff;
                border-radius: 16px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.1);
                max-width: 420px;
                width: 100%;
                padding: 35px 30px;
                text-align: center;
            }
            .pin-input {
                letter-spacing: 12px;
                font-size: 1.8rem;
                text-align: center;
                font-weight: 700;
                border-radius: 10px;
            }
        </style>
    </head>
    <body>
        <div class="pin-card">
            <div class="mb-3 text-warning">
                <i class="fas fa-shield-alt fa-3x"></i>
            </div>
            <h4 class="fw-bold mb-1">Sổ ghi chú đã khóa bảo mật</h4>
            <p class="text-muted small mb-4">Vui lòng nhập đúng mã 4 số đã thiết lập trước đó để xem nội dung và tùy chỉnh sự kiện của sổ.</p>
            <?php if ($pin_error): ?>
                <div class="alert alert-danger py-2 small"><?= htmlspecialchars($pin_error) ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="mb-3">
                    <input type="password" name="note_pin" class="form-control pin-input shadow-sm" required
                           inputmode="numeric" pattern="\d{6}" maxlength="6" minlength="6"
                           placeholder="••••" autofocus autocomplete="off">
                </div>
                <button type="submit" class="btn btn-dark w-100 py-2 rounded-pill fw-semibold mb-2">
                    <i class="fas fa-unlock me-1"></i> Mở khóa sổ
                </button>
                <a href="index_notezy.php" class="btn btn-link text-decoration-none text-muted small">
                    <i class="fas fa-arrow-left me-1"></i> Quay lại trang chủ
                </a>
            </form>
        </div>
    </body>
    </html>
    <?php
exit;
}

// Kiểm tra mật khẩu chữ thông thường nếu cần
if (false && $note && $note['password_hash'] && !isset($_SESSION['accessed_notes'][$note_id])) {
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['password'])) {
        if (password_verify($_POST['password'], $note['password_hash'])) {
            $_SESSION['accessed_notes'][$note_id] = true;
        } else {
            $error = 'Mật khẩu không đúng.';
        }
    } else {
        // Hiển thị form nhập mật khẩu
        ?>
        <!DOCTYPE html>
        <html lang="vi">
        <head>
            <meta charset="UTF-8">
            <title>Notezy - Nhập mật khẩu</title>
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body {
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    background-color: #f5f5f5;
                    padding-top: 70px;
                }
                .note-form {
                    background-color: #ffffff;
                    border-radius: 10px;
                    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
                    padding: 30px;
                    max-width: 500px;
                    margin: 50px auto;
                    border: 1px solid #e0e0e0;
                }
                .btn-primary {
                    background-color: #000000;
                    border: none;
                    padding: 10px 25px;
                    border-radius: 5px;
                    font-weight: 600;
                }
                .btn-primary:hover {
                    background-color: #333333;
                }
                .form-label {
                    font-weight: 600;
                    color: #000000;
                }
                .form-control {
                    border: 2px solid #d0d0d0;
                    border-radius: 5px;
                    padding: 12px 15px;
                }
                @media (max-width: 576px) {
                    .note-form {
                        margin: 20px;
                        padding: 20px;
                    }
                    .btn-primary {
                        padding: 8px 20px;
                    }
                }
            </style>
        </head>
        <body>
            <div class="note-form">
                <h3 class="text-center mb-4">Nhập mật khẩu</h3>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <form method="post">
                    <div class="mb-3">
                        <label for="password" class="form-label">Mật khẩu ghi chú</label>
                        <input type="password" name="password" id="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Xác nhận</button>
                </form>
            </div>
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
        </body>
        </html>
        <?php
exit;
    }
}

// Xử lý chia sẻ ghi chú
if ($is_owner && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['share'])) {
    $usernames = explode(',', $_POST['shareUsernames']);
    $share_permission = in_array($_POST['sharePermission'], ['read', 'write']) ? $_POST['sharePermission'] : 'read';

    foreach ($usernames as $username) {
        $username = trim($username);
        if (empty($username)) continue;

        // Kiểm tra người dùng
        $sql = "SELECT id FROM users WHERE username = ? AND id != ?";
        $stm = $conn->prepare($sql);
        $stm->bind_param('si', $username, $user_id);
        $stm->execute();
        $result = $stm->get_result();
        if ($user = $result->fetch_assoc()) {
            $shared_with_user_id = $user['id'];
            // Kiểm tra xem đã chia sẻ chưa
            $sql_check = "SELECT share_id FROM note_shares WHERE note_id = ? AND shared_with_user_id = ?";
            $stm_check = $conn->prepare($sql_check);
            $stm_check->bind_param('ii', $note_id, $shared_with_user_id);
            $stm_check->execute();
            if ($stm_check->get_result()->num_rows > 0) {
                $error_messages[] = "Đã chia sẻ với: $username";
                continue;
            }
            // Thêm chia sẻ
            $sql_share = "INSERT INTO note_shares (note_id, shared_with_user_id, permission, shared_by_user_id) VALUES (?, ?, ?, ?)";
            $stm_share = $conn->prepare($sql_share);
            $stm_share->bind_param('iisi', $note_id, $shared_with_user_id, $share_permission, $user_id);
            if ($stm_share->execute()) {
                $success_messages[] = "Đã chia sẻ với: $username";
            } else {
                $error_messages[] = "Lỗi khi chia sẻ với: $username";
            }
        } else {
            $error_messages[] = "Tên người dùng không tồn tại: $username";
        }
    }
    // Lưu thông báo vào session
    if (!empty($success_messages)) {
        $_SESSION['success_messages'] = $success_messages;
    }
    if (!empty($error_messages)) {
        $_SESSION['error_messages'] = $error_messages;
    }
    // Chuyển hướng để làm mới trang
    header("Location: notepass.php?id=$note_id");
    exit;
}

// Xử lý thu hồi chia sẻ
if ($is_owner && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['revoke'])) {
    $share_id = (int) $_POST['share_id'];
    $sql = "DELETE FROM note_shares WHERE share_id = ? AND shared_by_user_id = ?";
    $stm = $conn->prepare($sql);
    $stm->bind_param('ii', $share_id, $user_id);
    if ($stm->execute()) {
        $_SESSION['success_messages'] = ["Đã thu hồi quyền chia sẻ."];
    } else {
        $_SESSION['error_messages'] = ["Lỗi khi thu hồi quyền chia sẻ."];
    }
    header("Location: notepass.php?id=$note_id");
    exit;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Notezy - Xem ghi chú</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            padding-top: 70px;
            color: #333333;
        }
        .navbar {
            background-color: #ffffff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 10px 0;
        }
        .navbar-brand.logo {
            font-size: 24px;
            font-weight: bold;
            color: #000000;
        }
        .nav-link {
            color: #333333;
            font-weight: 500;
            padding: 8px 15px;
            transition: all 0.3s;
        }
        .nav-link:hover {
            color: #000000;
        }
        .avatar-container {
            display: flex;
            align-items: center;
        }
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e0e0e0;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        .note-form {
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            padding: 30px;
            margin: 50px auto;
            max-width: 800px;
            border: 1px solid #e0e0e0;
        }
        .form-label {
            font-weight: 600;
            color: #000000;
            margin-bottom: 8px;
        }
        .form-control, .form-select {
            border: 2px solid #d0d0d0;
            border-radius: 5px;
            padding: 12px 15px;
            transition: all 0.3s;
            background-color: #fafafa;
        }
        .form-control:focus, .form-select:focus {
            box-shadow: none;
            border-color: #000000;
            background-color: #ffffff;
        }
        .btn-primary {
            background-color: #000000;
            border: none;
            padding: 10px 25px;
            border-radius: 5px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            background-color: #333333;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        .btn-outline-danger {
            color: #dc3545;
            border-color: #dc3545;
        }
        .btn-outline-danger:hover {
            background-color: #dc3545;
            color: #ffffff;
        }
        .note-label {
            font-size: 0.75rem;
            background-color: #e0e0e0;
            color: #000000;
            padding: 2px 6px;
            border-radius: 4px;
            margin-right: 5px;
            display: inline-block;
        }
        .main-content {
            padding: 40px 0;
        }
        .table-responsive {
            margin-top: 20px;
        }
        @media (max-width: 768px) {
            .note-form {
                margin: 20px;
                padding: 20px;
            }
            .btn-primary, .btn-outline-danger {
                padding: 8px 15px;
                font-size: 0.9rem;
            }
            .form-control, .form-select {
                padding: 10px;
            }
            .table {
                font-size: 0.9rem;
            }
            .table th, .table td {
                padding: 8px;
            }
        }
        @media (max-width: 576px) {
            .note-form {
                margin: 15px;
                padding: 15px;
            }
            .btn-primary, .btn-outline-danger {
                width: 100%;
                margin-bottom: 10px;
            }
            .d-flex.gap-2 {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>

<!-- HEADER/NAVBAR -->
<header>
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <img src="logo.png" alt="Notezy" width="50" height="50" class="me-2" />
            <a class="navbar-brand logo" href="index_notezy.php">Notezy</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="index_notezy.php"><i class="fas fa-home me-1"></i>Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="fas fa-info-circle me-1"></i>About Us</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="fas fa-star me-1"></i>Features</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="fas fa-shield-alt me-1"></i>Privacy</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="fas fa-envelope me-1"></i>Contact</a></li>
                    <li class="nav-item dropdown avatar-container ms-2">
                        <a class="nav-link dropdown-toggle p-0" href="#" id="avatarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="/api/placeholder/40/40" alt="Avatar" class="avatar">
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="avatarDropdown">
                            <li><a class="dropdown-item" href="account.php"><i class="fas fa-user me-2"></i>Xem trang cá nhân</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Đăng xuất</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
</header>

<!-- MAIN CONTENT -->
<main class="main-content">
    <div class="container">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php elseif ($note): ?>
            <div class="note-form" style="<?= !empty($note['background_color']) && $note['background_color'] !== '#ffffff' ? 'background-color:' . htmlspecialchars($note['background_color']) . ';' : '' ?><?= !empty($note['text_color']) && $note['text_color'] !== '#000000' ? 'color:' . htmlspecialchars($note['text_color']) . ';' : '' ?><?= !empty($note['font_family']) ? 'font-family:' . htmlspecialchars($note['font_family']) . ';' : '' ?>">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <h2 class="fw-bold mb-0"><?= htmlspecialchars($note['title']) ?></h2>
                    <div>
                        <?php if ($note['pin_hash']): ?>
                            <span class="badge bg-success"><i class="fas fa-shield-alt me-1"></i>Đã bảo mật 4 số</span>
                        <?php endif; ?>
                        <?php if ($note['pinned']): ?>
                            <span class="badge bg-warning text-dark"><i class="fas fa-thumbtack me-1"></i>Đã ghim</span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($note['reminder_at'])): ?>
                    <div class="mb-3 p-2 rounded bg-warning-subtle text-dark border border-warning">
                        <i class="fas fa-bell text-warning me-2"></i>
                        <strong>Sự kiện / Giờ nhắc nhở của sổ:</strong> <?= date('H:i, d/m/Y', strtotime($note['reminder_at'])) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($note['image_path'])): ?>
                    <div class="mb-3 text-center">
                        <img src="<?= htmlspecialchars($note['image_path']) ?>" alt="Ảnh đính kèm" class="img-fluid rounded shadow-sm" style="max-height: 350px;">
                    </div>
                <?php endif; ?>

                <div class="p-3 bg-white rounded border mb-3 text-dark" style="min-height: 120px;">
                    <?= nl2br(htmlspecialchars($note['content'])) ?>
                </div>

                <?php if (!empty($note['labels'])): ?>
                    <div class="note-labels mb-3">
                        <?php foreach ($note['labels'] as $label): ?>
                            <span class="note-label"><?= htmlspecialchars($label) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <p class="text-muted small">
                    <i class="far fa-clock me-1"></i>Ngày tạo: <?= htmlspecialchars($note['created_at']) ?>
                </p>

                <div class="d-flex justify-content-between gap-2 mb-3 pt-3 border-top">
                    <a href="index_notezy.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Trang chủ
                    </a>
                    <div class="d-flex gap-2">
                        <?php if ($is_owner || $permission == 'write'): ?>
                            <a href="themghichu.php?id=<?= urlencode($note['note_id']) ?>" class="btn btn-primary">
                                <i class="fas fa-edit me-1"></i> Chỉnh sửa & Tùy chỉnh sự kiện
                            </a>
                        <?php endif; ?>
                        <?php if ($is_owner): ?>
                            <button class="btn btn-outline-danger" onclick="confirmDelete(<?= $note['note_id'] ?>)">
                                <i class="fas fa-trash-alt me-1"></i> Xóa
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($is_owner): ?>
                    <!-- Form chia sẻ ghi chú -->
                    <h4>Chia sẻ ghi chú</h4>
                    <?php if (!empty($success_messages)): ?>
                        <div class="alert alert-success">
                            <?php foreach ($success_messages as $msg): ?>
                                <p><?= htmlspecialchars($msg) ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($error_messages)): ?>
                        <div class="alert alert-danger">
                            <?php foreach ($error_messages as $msg): ?>
                                <p><?= htmlspecialchars($msg) ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <form method="post" class="mb-4">
                        <div class="mb-3">
                            <label for="shareUsernames" class="form-label">Tên người dùng (cách nhau bằng dấu phẩy)</label>
                            <input type="text" name="shareUsernames" id="shareUsernames" class="form-control" placeholder="user1,user2" pattern="[a-zA-Z0-9_,]+" title="Chỉ chấp nhận tên người dùng, cách nhau bằng dấu phẩy">
                        </div>
                        <div class="mb-3">
                            <label for="sharePermission" class="form-label">Quyền truy cập</label>
                            <select name="sharePermission" id="sharePermission" class="form-select">
                                <option value="read">Chỉ đọc</option>
                                <option value="write">Chỉnh sửa</option>
                            </select>
                        </div>
                        <button type="submit" name="share" class="btn btn-primary">Chia sẻ</button>
                    </form>

                    <!-- Quản lý chia sẻ -->
                    <h4>Quản lý chia sẻ</h4>
                    <?php
$sql = "
                        SELECT ns.share_id, ns.permission, ns.shared_at, u.username
                        FROM note_shares ns
                        JOIN users u ON ns.shared_with_user_id = u.id
                        WHERE ns.note_id = ? AND ns.shared_by_user_id = ?
                    ";
                    $stm = $conn->prepare($sql);
                    $stm->bind_param('ii', $note_id, $user_id);
                    $stm->execute();
                    $shares = $stm->get_result()->fetch_all(MYSQLI_ASSOC);
                    ?>
                    <?php if (!empty($shares)): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Tên người dùng</th>
                                        <th>Quyền</th>
                                        <th>Thời gian</th>
                                        <th>Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($shares as $share): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($share['username']) ?></td>
                                            <td><?= htmlspecialchars($share['permission'] == 'read' ? 'Chỉ đọc' : 'Chỉnh sửa') ?></td>
                                            <td><?= htmlspecialchars($share['shared_at']) ?></td>
                                            <td>
                                                <form method="post" style="display:inline;">
                                                    <input type="hidden" name="share_id" value="<?= $share['share_id'] ?>">
                                                    <button type="submit" name="revoke" class="btn btn-sm btn-danger">Thu hồi</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">Chưa chia sẻ với ai.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    async function confirmDelete(noteId) {
        const result = await Swal.fire({
            title: 'Xóa ghi chú?',
            text: 'Bạn có chắc chắn muốn xóa ghi chú này? Hành động này không thể hoàn tác!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Xóa',
            cancelButtonText: 'Hủy',
            reverseButtons: true,
            customClass: {
                popup: 'animated bounceIn'
            }
        });

        if (result.isConfirmed) {
            try {
                const response = await fetch('delete_note.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `noteId=${noteId}`
                });

                const data = await response.json();

                if (data.success) {
                    await Swal.fire({
                        title: 'Thành công!',
                        text: 'Ghi chú đã được xóa.',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false,
                        customClass: {
                            popup: 'animated fadeIn'
                        }
                    });
                    window.location.href = 'index_notezy.php';
                } else {
                    await Swal.fire({
                        title: 'Lỗi!',
                        text: `Không thể xóa ghi chú: ${data.message}`,
                        icon: 'error',
                        confirmButtonText: 'OK',
                        customClass: {
                            popup: 'animated shake'
                        }
                    });
                }
            } catch (error) {
                console.error('Lỗi khi xóa:', error);
                await Swal.fire({
                    title: 'Lỗi!',
                    text: 'Đã có lỗi xảy ra, vui lòng thử lại sau.',
                    icon: 'error',
                    confirmButtonText: 'OK',
                    customClass: {
                        popup: 'animated shake'
                    }
                });
            }
        }
    }
</script>
</body>
</html>
