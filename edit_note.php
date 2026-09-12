<?php
// The rubric requires one editor for creating and editing notes. Keep this
// legacy URL working, but always render the shared editor implementation.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    header('Location: themghichu.php?noteId=' . urlencode((string)(int)$_GET['id']));
    exit;
}
require_once __DIR__ . '/includes/session.php';
require_once('db.php');
notezy_session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['id'])) {
    header('Location: index.php');
    exit();
}

// Khởi tạo session accessed_notes
if (!isset($_SESSION['accessed_notes'])) {
    $_SESSION['accessed_notes'] = [];
}

$user_id = (int) $_SESSION['id'];
$note_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$error = '';
$note = null;

// Lấy thông tin ghi chú và kiểm tra quyền hạn (owner, write, read)
$is_owner = false;
$user_permission = null;

try {
    $sql = "
        SELECT n.*, GROUP_CONCAT(l.name SEPARATOR ',') AS labels,
               ns.permission as share_permission
        FROM notes n
        LEFT JOIN note_labels nl ON n.note_id = nl.note_id
        LEFT JOIN labels l ON nl.label_id = l.label_id
        LEFT JOIN note_shares ns ON n.note_id = ns.note_id AND ns.shared_with_user_id = ?
        WHERE n.note_id = ? AND (n.user_id = ? OR ns.note_id IS NOT NULL)
        GROUP BY n.note_id, ns.permission
    ";
    $stm = $conn->prepare($sql);
    if (!$stm) {
        throw new Exception("Lỗi chuẩn bị truy vấn: " . $conn->error);
    }
    $stm->bind_param('iii', $user_id, $note_id, $user_id);
    $stm->execute();
    $result = $stm->get_result();
    $note = $result->fetch_assoc();
    if (!$note) {
        die("Ghi chú không tồn tại hoặc bạn không có quyền truy cập.");
    }
    $note['labels'] = $note['labels'] ? explode(',', $note['labels']) : [];
    $is_owner = ((int)$note['user_id'] === $user_id);
    $user_permission = $is_owner ? 'owner' : ($note['share_permission'] ?? 'read');
    $stm->close();

    // Nếu user chỉ có quyền read (chỉ đọc), chuyển hướng an toàn sang trang xem chi tiết notepass.php
    if ($user_permission === 'read') {
        $_SESSION['error_messages'] = ["Bạn chỉ có quyền 'Chỉ đọc' đối với ghi chú này và không thể chỉnh sửa."];
        header('Location: notepass.php?id=' . urlencode($note_id));
        exit;
    }
} catch (Exception $e) {
    $error = "Lỗi truy vấn: " . htmlspecialchars($e->getMessage());
}

// Xử lý mật khẩu bảo vệ
if ($note['password_hash'] && !isset($_SESSION['accessed_notes'][$note_id])) {
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['password'])) {
        if (password_verify($_POST['password'], $note['password_hash'])) {
            $_SESSION['accessed_notes'][$note_id] = true;
            header('Location: edit_note.php?id=' . urlencode($note_id));
            exit;
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
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body {
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    background-color: #f5f5f5;
                    padding: 20px;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 100vh;
                }

                .container {
                    max-width: 500px;
                    background: #fff;
                    padding: 30px;
                    border-radius: 10px;
                    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
                }

                .form-label {
                    font-weight: 600;
                    color: #333;
                }

                .form-control {
                    border: 2px solid #d0d0d0;
                    border-radius: 5px;
                    padding: 12px;
                    transition: border-color 0.3s;
                }

                .form-control:focus {
                    border-color: #000;
                    box-shadow: 0 0 5px rgba(0, 0, 0, 0.2);
                }

                .btn-primary {
                    background-color: #000;
                    border: none;
                    padding: 12px;
                    border-radius: 5px;
                    font-weight: 600;
                    width: 100%;
                }

                .btn-primary:hover {
                    background-color: #333;
                    transform: translateY(-2px);
                }

                .alert {
                    font-size: 0.9rem;
                }
            </style>
        </head>

        <body>
            <div class="container">
                <h3 class="text-center mb-4">Nhập mật khẩu</h3>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <form method="post">
                    <div class="mb-3">
                        <label for="password" class="form-label">Mật khẩu ghi chú</label>
                        <input type="password" name="password" id="password" class="form-control" required
                            placeholder="Nhập mật khẩu">
                    </div>
                    <button type="submit" class="btn btn-primary">Xác nhận</button>
                </form>
            </div>
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        </body>

        </html>
        <?php
exit;
    }
}

// ─── Khóa PIN 6 số (độc lập với mật khẩu ghi chú ở trên) ───────────────────
// Khác với mật khẩu ghi chú (đặt tự do, không cần xác thực gì thêm), mã PIN:
//   - luôn đúng 6 chữ số
//   - chỉ được ĐẶT hoặc GỠ khi người dùng nhập lại mật khẩu TÀI KHOẢN
//     (xem api/note_pin.php, action=set / action=remove)
//   - khi MỞ một ghi chú đã khóa PIN thì chỉ cần nhập lại đúng 6 chữ số đó,
//     không cần nhập mật khẩu tài khoản
if (!isset($_SESSION['pin_unlocked_notes'])) {
    $_SESSION['pin_unlocked_notes'] = [];
}
if ($note['pin_hash'] && empty($_SESSION['pin_unlocked_notes'][$note_id])) {
    $pin_error = '';
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['note_pin'])) {
        $entered_pin = trim($_POST['note_pin']);
        if (preg_match('/^\d{6}$/', $entered_pin) && password_verify($entered_pin, $note['pin_hash'])) {
            $_SESSION['pin_unlocked_notes'][$note_id] = true;
            $ins_pin = $conn->prepare("INSERT INTO note_pin_unlocks (note_id, user_id, session_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE unlocked_at = NOW()");
            if ($ins_pin) {
                $curr_sess = session_id();
                $ins_pin->bind_param("iis", $note_id, $user_id, $curr_sess);
                $ins_pin->execute();
                $ins_pin->close();
            }
            header('Location: edit_note.php?id=' . urlencode($note_id));
            exit;
        } else {
            $pin_error = 'Mã bảo mật 6 số không đúng. Vui lòng nhập lại.';
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="vi">

    <head>
        <meta charset="UTF-8">
        <title>Notezy - Nhập mã PIN</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background-color: #f5f5f5;
                padding: 20px;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
            }

            .container {
                max-width: 420px;
                background: #fff;
                padding: 30px;
                border-radius: 10px;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
                text-align: center;
            }

            .pin-input {
                letter-spacing: 12px;
                font-size: 1.6rem;
                text-align: center;
                font-weight: 700;
            }

            .btn-primary {
                background-color: #000;
                border: none;
                padding: 12px;
                border-radius: 5px;
                font-weight: 600;
                width: 100%;
                margin-top: 12px;
            }

            .btn-primary:hover {
                background-color: #333;
            }
        </style>
    </head>

    <body>
        <div class="container">
            <div style="font-size:2rem;">🔒</div>
            <h3 class="mb-2">Sổ ghi chú đã khóa bảo mật</h3>
            <p class="text-muted small">Nhập đúng mã 4 số đã thiết lập trước đó để xem nội dung và tùy chỉnh sự kiện của sổ.</p>
            <?php if ($pin_error): ?>
                <div class="alert alert-danger py-2 small"><?= htmlspecialchars($pin_error) ?></div>
            <?php endif; ?>
            <form method="post">
                <input type="password" name="note_pin" class="form-control pin-input" required
                    inputmode="numeric" pattern="\d{6}" maxlength="6" minlength="6"
                    placeholder="••••" autofocus autocomplete="off">
                <button type="submit" class="btn btn-primary">Mở khóa sổ</button>
                <a href="index_notezy.php" class="btn btn-link text-decoration-none text-muted small mt-2 d-block">
                    Quay lại trang chủ
                </a>
            </form>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>

    </html>
    <?php
exit;
}

// Xử lý khi form chỉnh sửa được gửi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title'])) {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $labels_raw = trim($_POST['labels'] ?? '');
    $pinned = isset($_POST['pinned']) ? 1 : 0;
    $password = '';
    $background_color = trim($_POST['background_color'] ?? '#ffffff');
    $text_color = trim($_POST['text_color'] ?? '#000000');
    $font_family = trim($_POST['font_family'] ?? 'Poppins');

    if ($title === '' || $content === '') {
        $error = "Vui lòng nhập đầy đủ tiêu đề và nội dung.";
    } else {
        $conn->begin_transaction();
        try {
            // PIN 6 số là cơ chế khóa duy nhất. Retire old per-note passwords.
            $password_hash = null;
            $sql_update = "UPDATE notes SET title = ?, content = ?, pinned = ?, password_hash = ?, background_color = ?, text_color = ?, font_family = ?, updated_at = NOW() WHERE note_id = ? AND (user_id = ? OR note_id IN (SELECT note_id FROM note_shares WHERE shared_with_user_id = ? AND permission = 'write'))";
            $stm_update = $conn->prepare($sql_update);
            if (!$stm_update) {
                throw new Exception("Lỗi chuẩn bị truy vấn cập nhật: " . $conn->error);
            }
            $stm_update->bind_param('ssissssiii', $title, $content, $pinned, $password_hash, $background_color, $text_color, $font_family, $note_id, $user_id, $user_id);
            if (!$stm_update->execute()) {
                throw new Exception("Lỗi thực thi cập nhật: " . $stm_update->error);
            }
            $stm_update->close();

            // Xóa nhãn cũ
            $sql_delete_labels = "DELETE FROM note_labels WHERE note_id = ?";
            $stm_delete_labels = $conn->prepare($sql_delete_labels);
            if (!$stm_delete_labels) {
                throw new Exception("Lỗi chuẩn bị truy vấn xóa nhãn: " . $conn->error);
            }
            $stm_delete_labels->bind_param('i', $note_id);
            $stm_delete_labels->execute();
            $stm_delete_labels->close();

            // Thêm nhãn mới
            if ($labels_raw !== '') {
                $labels_array = array_unique(array_filter(array_map('trim', explode(',', $labels_raw))));
                foreach ($labels_array as $label_name) {
                    // Kiểm tra nhãn tồn tại
                    $sql_label = "SELECT label_id FROM labels WHERE name = ?";
                    $stm_label = $conn->prepare($sql_label);
                    if (!$stm_label) {
                        throw new Exception("Lỗi chuẩn bị truy vấn nhãn: " . $conn->error);
                    }
                    $stm_label->bind_param('s', $label_name);
                    $stm_label->execute();
                    $result = $stm_label->get_result();
                    if ($row = $result->fetch_assoc()) {
                        $label_id = $row['label_id'];
                    } else {
                        // Thêm nhãn mới
                        $sql_insert_label = "INSERT INTO labels (name) VALUES (?)";
                        $stm_insert_label = $conn->prepare($sql_insert_label);
                        if (!$stm_insert_label) {
                            throw new Exception("Lỗi chuẩn bị truy vấn thêm nhãn: " . $conn->error);
                        }
                        $stm_insert_label->bind_param('s', $label_name);
                        $stm_insert_label->execute();
                        $label_id = $stm_insert_label->insert_id;
                        $stm_insert_label->close();
                    }
                    $stm_label->close();

                    // Liên kết nhãn với ghi chú
                    $sql_insert_note_label = "INSERT IGNORE INTO note_labels (note_id, label_id) VALUES (?, ?)";
                    $stm_insert_note_label = $conn->prepare($sql_insert_note_label);
                    if (!$stm_insert_note_label) {
                        throw new Exception("Lỗi chuẩn bị truy vấn liên kết nhãn: " . $conn->error);
                    }
                    $stm_insert_note_label->bind_param('ii', $note_id, $label_id);
                    $stm_insert_note_label->execute();
                    $stm_insert_note_label->close();
                }
            }

            // Xử lý gỡ ảnh hoặc upload ảnh mới
            if (!empty($_POST['remove_image'])) {
                if (!empty($note['image_path']) && file_exists(__DIR__ . '/' . $note['image_path'])) {
                    @unlink(__DIR__ . '/' . $note['image_path']);
                }
                $img_null = $conn->prepare("UPDATE notes SET image_path = NULL WHERE note_id = ?");
                if ($img_null) {
                    $img_null->bind_param('i', $note_id);
                    $img_null->execute();
                    $img_null->close();
                }
                $note['image_path'] = null;
            } elseif (isset($_FILES['noteImage']) && $_FILES['noteImage']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['noteImage'];
                $max_bytes = 5 * 1024 * 1024;
                if ($file['size'] > $max_bytes) {
                    throw new Exception('Ảnh quá lớn. Tối đa 5MB.');
                }
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed_extensions, true)) {
                    throw new Exception('Định dạng ảnh không hợp lệ. Chỉ cho phép: ' . implode(', ', $allowed_extensions));
                }
                $upload_dir = __DIR__ . '/uploads/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0775, true);
                $safe_name = bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
                $target = $upload_dir . $safe_name;
                if (move_uploaded_file($file['tmp_name'], $target)) {
                    $new_img_path = 'uploads/' . $safe_name;
                    $img_upd = $conn->prepare("UPDATE notes SET image_path = ? WHERE note_id = ?");
                    $img_upd->bind_param('si', $new_img_path, $note_id);
                    $img_upd->execute();
                    $img_upd->close();
                }
            }

            $conn->commit();
            $_SESSION['success'] = 'Ghi chú đã được cập nhật thành công.';
            ?>
            <!DOCTYPE html>
            <html lang="vi">

            <head>
                <meta charset="UTF-8">
                <title>Chỉnh sửa ghi chú - Notezy</title>
                <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
            </head>

            <body>
                <script>
                    Swal.fire({
                        title: 'Thành công!',
                        text: 'Ghi chú đã được cập nhật thành công.',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = 'index_notezy.php';
                    });
                </script>
            </body>

            </html>
            <?php
exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Lỗi khi lưu ghi chú: " . htmlspecialchars($e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Chỉnh sửa ghi chú - Notezy</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
        }

        .container {
            max-width: 700px;
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        h2 {
            font-size: 1.9rem;
            font-weight: 600;
            text-align: center;
            margin-bottom: 25px;
            color: #333;
        }

        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 6px;
        }

        .form-control,
        .form-select {
            border: 2px solid #d0d0d0;
            border-radius: 5px;
            padding: 12px;
            transition: border-color 0.3s;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #000;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.2);
        }

        .form-check {
            margin: 15px 0;
            display: flex;
            align-items: center;
        }

        .form-check input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .form-check label {
            margin-left: 10px;
            font-weight: 500;
            color: #333;
            cursor: pointer;
        }

        .btn-primary {
            background-color: #000;
            border: none;
            padding: 12px;
            border-radius: 5px;
            font-weight: 600;
            width: 100%;
        }

        .btn-primary:hover {
            background-color: #333;
            transform: translateY(-2px);
        }

        .color-picker-group {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }

        .color-picker-group div {
            flex: 1;
        }

        .alert {
            font-size: 0.9rem;
        }

        @media (max-width: 576px) {
            body {
                padding: 10px;
            }

            .container {
                padding: 20px;
            }

            .color-picker-group {
                flex-direction: column;
                gap: 10px;
            }

            .form-control,
            .form-select {
                font-size: 0.9rem;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 6px;
            }

            .container {
                padding: 12px;
                border-radius: 8px;
            }

            h2 {
                font-size: 1.2rem;
            }

            .form-control,
            .form-select,
            textarea {
                font-size: 0.8rem;
            }

            .btn {
                width: 100%;
                font-size: 0.85rem;
                margin-bottom: 6px;
            }

            #imagePreviewContainer img,
            #imagePreview {
                max-height: 130px !important;
            }
        }

        .rt-pulse-update {
            animation: pulseSync 1.4s ease-in-out;
        }
        @keyframes pulseSync {
            0% { box-shadow: 0 0 0 0 rgba(13, 202, 240, 0.8); border-color: #0dcaf0; }
            50% { box-shadow: 0 0 15px 4px rgba(13, 202, 240, 0.4); border-color: #0dcaf0; }
            100% { box-shadow: 0 0 0 0 rgba(13, 202, 240, 0); }
        }
        .user-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.78rem;
            font-weight: 600;
            background: #e2e8f0;
            color: #1e293b;
        }
        .user-chip.active-chip {
            background: #dbeafe;
            color: #1d4ed8;
            border: 1px solid #93c5fd;
        }
    </style>
</head>

<body>
    <div class="container">
        <h2>Chỉnh sửa ghi chú</h2>

        <!-- Live Realtime Collaborators Bar -->
        <div id="collabBar" class="d-flex align-items-center justify-content-between p-2 px-3 mb-3 rounded" style="background:#f8fafc;border:1px solid #e2e8f0;font-size:.85rem;">
            <div class="d-flex align-items-center gap-2 flex-wrap" id="collabUsersList">
                <span class="badge bg-success d-inline-flex align-items-center gap-1">
                    <span class="spinner-grow spinner-grow-sm text-light" style="width:7px;height:7px;" role="status"></span>
                    Đồng bộ thời gian thực
                </span>
                <span class="text-muted" id="collabPresenceText">Đang theo dõi thay đổi trực tiếp...</span>
            </div>
            <div id="collabTypingIndicator" class="text-primary fw-semibold small" style="display:none;">
                <i class="fas fa-pencil-alt fa-beat-fade me-1"></i> <span id="collabTypingName"></span> đang gõ...
            </div>
        </div>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="title" class="form-label">Tiêu đề <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="title" name="title"
                    value="<?= htmlspecialchars($note['title']) ?>" required placeholder="Nhập tiêu đề ghi chú">
            </div>
            <div class="mb-3">
                <label for="content" class="form-label">Nội dung <span class="text-danger">*</span></label>
                <textarea class="form-control" id="content" name="content" rows="5" required
                    placeholder="Nhập nội dung ghi chú"><?= htmlspecialchars($note['content']) ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label d-flex justify-content-between">
                    <span>Nhãn (Labels)</span>
                    <a href="labels.php" target="_blank" class="small text-decoration-none"><i class="fas fa-cog me-1"></i>Quản lý nhãn</a>
                </label>
                <div class="mb-2">
                    <select id="quickSelectLabel" class="form-select" onchange="if(this.value){ const l=document.getElementById('labels'); l.value = l.value ? (l.value + ', ' + this.value) : this.value; this.value=''; }">
                        <option value="">Select Label [Chọn nhãn: Work, Study, Personal...] ▼</option>
                        <option value="Work">💼 Work</option>
                        <option value="Study">📚 Study</option>
                        <option value="Personal">👤 Personal</option>
                        <option value="Important">⭐ Important</option>
                    </select>
                </div>
                <input type="text" class="form-control" id="labels" name="labels"
                    value="<?= htmlspecialchars(implode(', ', $note['labels'])) ?>"
                    placeholder="VD: Work, Study, Personal hoặc nhập nhãn tự do">
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="pinned" name="pinned" <?= $note['pinned'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="pinned">Ghim ghi chú lên đầu</label>
            </div>
            <div class="mb-3 p-3" style="border:1px solid #e2e2e2;border-radius:8px;">
                <label class="form-label d-block">🔒 Bảo mật sổ với mã 6 số (PIN)</label>
                <p class="text-muted mb-2" style="font-size:.85rem;">
                    Bảo vệ sổ ghi chú bằng mã 6 số bí mật. Người được chia sẻ cũng phải nhập PIN do bạn cung cấp.
                </p>
                <div id="pinStatusText" class="mb-2" style="font-size:.9rem;">Đang kiểm tra trạng thái bảo mật...</div>
                <button type="button" id="pinSetBtn" class="btn btn-outline-dark btn-sm" style="display:none;">Đặt mã bảo mật (6 số)</button>
                <button type="button" id="pinRemoveBtn" class="btn btn-outline-danger btn-sm" style="display:none;">Gỡ mã bảo mật</button>
            </div>
            <div class="mb-3 p-3" style="border:1px solid #e2e2e2;border-radius:8px;">
                <label class="form-label d-block">⏰ Báo thức / Nhắc nhở sự kiện</label>
                <p class="text-muted mb-2" style="font-size:.85rem;">
                    Chọn thời điểm bạn muốn được nhắc về ghi chú này. Lưu ngay lập tức, không cần bấm "Lưu thay đổi".
                </p>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <input type="datetime-local" id="reminderInput" class="form-control" style="max-width:260px;"
                        value="<?= !empty($note['reminder_at']) ? htmlspecialchars(str_replace(' ', 'T', substr($note['reminder_at'], 0, 16))) : '' ?>">
                    <button type="button" id="reminderSaveBtn" class="btn btn-outline-dark btn-sm">Đặt báo thức</button>
                    <button type="button" id="reminderClearBtn" class="btn btn-outline-danger btn-sm" <?= empty($note['reminder_at']) ? 'style="display:none;"' : '' ?>>Huỷ báo thức</button>
                </div>
                <div id="reminderStatusText" class="mt-2" style="font-size:.9rem;">
                    <?= !empty($note['reminder_at'])
                        ? '✅ Đang đặt báo thức lúc ' . htmlspecialchars(date('H:i d/m/Y', strtotime($note['reminder_at'])))
                        : 'Chưa đặt báo thức cho ghi chú này.' ?>
                </div>
            </div>
            <div class="color-picker-group">
                <div>
                    <label for="background_color" class="form-label">Màu nền</label>
                    <input type="color" class="form-control" id="background_color" name="background_color"
                        value="<?= htmlspecialchars($note['background_color'] ?? '#ffffff') ?>">
                </div>
                <div>
                    <label for="text_color" class="form-label">Màu chữ</label>
                    <input type="color" class="form-control" id="text_color" name="text_color"
                        value="<?= htmlspecialchars($note['text_color'] ?? '#000000') ?>">
                </div>
            </div>
            <div class="mb-3">
                <label for="font_family" class="form-label">Phông chữ</label>
                <select class="form-select" id="font_family" name="font_family">
                    <option value="Poppins" <?= ($note['font_family'] ?? '') == 'Poppins' ? 'selected' : '' ?>>Poppins</option>
                    <option value="Roboto" <?= ($note['font_family'] ?? '') == 'Roboto' ? 'selected' : '' ?>>Roboto</option>
                    <option value="Open Sans" <?= ($note['font_family'] ?? '') == 'Open Sans' ? 'selected' : '' ?>>Open Sans</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="noteImage" class="form-label">Ảnh đính kèm (tối đa 5MB)</label>
                <input type="file" class="form-control" id="noteImage" name="noteImage" accept="image/*">
                <div id="imagePreviewContainer"
                    style="<?= !empty($note['image_path']) ? 'display: block;' : 'display: none;' ?> margin-top: 10px;">
                    <img id="imagePreview"
                        src="<?= !empty($note['image_path']) ? htmlspecialchars($note['image_path']) : '' ?>"
                        alt="Preview" style="max-width: 100%; max-height: 200px; border-radius: 8px;">
                </div>
            </div>
            <button type="submit" class="btn btn-primary" id="saveBtn">Lưu thay đổi</button>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/offline-store.js"></script>
    <script src="js/collaboration-ws.js"></script>
    <script>
        const noteId = <?= $note_id ?>;
        let lastKnownUpdatedAt = <?= json_encode($note['updated_at'] ?? '') ?>;

        // ── Bảo mật sổ với mã 4 số ─────────────────────────────────────────
        const pinStatusText = document.getElementById('pinStatusText');
        const pinSetBtn = document.getElementById('pinSetBtn');
        const pinRemoveBtn = document.getElementById('pinRemoveBtn');

        function pinApi(action, fields = {}) {
            const body = new URLSearchParams({ action, note_id: noteId, ...fields });
            return fetch('api/note_pin.php', { method: 'POST', body })
                .then(r => r.json());
        }

        function refreshPinStatus() {
            pinApi('status').then(res => {
                if (res.pin_locked) {
                    pinStatusText.textContent = '✅ Sổ ghi chú này đang được khóa bằng mã bảo mật 4 số.';
                    pinSetBtn.style.display = 'none';
                    pinRemoveBtn.style.display = 'inline-block';
                } else {
                    pinStatusText.textContent = 'Chưa đặt mã bảo mật 4 số cho sổ này.';
                    pinSetBtn.style.display = 'inline-block';
                    pinRemoveBtn.style.display = 'none';
                }
            });
        }
        refreshPinStatus();

        pinSetBtn.addEventListener('click', async () => {
            const { value: pinPair } = await Swal.fire({
                title: 'Đặt mã bảo mật 4 số',
                html:
                    '<input id="swalPin1" type="password" inputmode="numeric" maxlength="6" pattern="\\d{6}" ' +
                    'class="swal2-input" placeholder="Nhập 4 số bí mật (0000-9999)" style="letter-spacing:6px;text-align:center;">' +
                    '<input id="swalPin2" type="password" inputmode="numeric" maxlength="6" pattern="\\d{6}" ' +
                    'class="swal2-input" placeholder="Nhập lại mã 4 số" style="letter-spacing:6px;text-align:center;">',
                focusConfirm: false,
                showCancelButton: true,
                confirmButtonText: 'Đặt mã bảo mật',
                cancelButtonText: 'Hủy',
                preConfirm: () => {
                    const pin = document.getElementById('swalPin1').value.trim();
                    const pinConfirm = document.getElementById('swalPin2').value.trim();
                    if (!/^\d{6}$/.test(pin)) {
                        Swal.showValidationMessage('Mã bảo mật phải gồm đúng 6 chữ số.');
                        return false;
                    }
                    if (pin !== pinConfirm) {
                        Swal.showValidationMessage('Hai lần nhập mã không khớp nhau.');
                        return false;
                    }
                    return { pin, pinConfirm };
                }
            });
            if (!pinPair) return;

            const res = await pinApi('set', {
                pin: pinPair.pin,
                pin_confirm: pinPair.pinConfirm,
            });
            if (res.success) {
                Swal.fire('Đã bảo mật', res.message, 'success');
                refreshPinStatus();
            } else {
                Swal.fire('Không thể đặt mã', res.message, 'error');
            }
        });

        pinRemoveBtn.addEventListener('click', async () => {
            const { value: oldPin } = await Swal.fire({
                title: 'Xác nhận gỡ mã bảo mật',
                input: 'password',
                inputLabel: 'Nhập mã 4 số hiện tại (hoặc mật khẩu đăng nhập) để gỡ:',
                inputPlaceholder: 'Mã 4 số hoặc mật khẩu',
                showCancelButton: true,
                confirmButtonText: 'Gỡ bảo mật',
                cancelButtonText: 'Hủy',
            });
            if (!oldPin) return;

            const res = await pinApi('remove', { old_pin: oldPin });
            if (res.success) {
                Swal.fire('Đã gỡ', res.message, 'success');
                refreshPinStatus();
            } else {
                Swal.fire('Không thể gỡ mã bảo mật', res.message, 'error');
            }
        });

        // ── Báo thức / Nhắc nhở (reminder_at) ──────────────────────────────────
        const reminderInput = document.getElementById('reminderInput');
        const reminderSaveBtn = document.getElementById('reminderSaveBtn');
        const reminderClearBtn = document.getElementById('reminderClearBtn');
        const reminderStatusText = document.getElementById('reminderStatusText');

        function reminderApi(reminderAt) {
            return fetch('api/reminders.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ note_id: noteId, reminder_at: reminderAt }),
            }).then(r => r.json());
        }

        function formatReminder(dt) {
            const d = new Date(dt.replace(' ', 'T'));
            if (isNaN(d)) return dt;
            return d.toLocaleString('vi-VN', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit', year: 'numeric' });
        }

        reminderSaveBtn.addEventListener('click', async () => {
            const val = reminderInput.value; // "YYYY-MM-DDTHH:mm"
            if (!val) {
                Swal.fire('Thiếu thời gian', 'Vui lòng chọn ngày giờ báo thức.', 'warning');
                return;
            }
            const reminderAt = val.replace('T', ' ') + ':00';
            const res = await reminderApi(reminderAt);
            if (res.status === 'success') {
                reminderStatusText.textContent = '✅ Đang đặt báo thức lúc ' + formatReminder(res.reminder_at);
                reminderClearBtn.style.display = 'inline-block';
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Đã đặt báo thức', showConfirmButton: false, timer: 1800 });
            } else {
                Swal.fire('Lỗi', res.message || 'Không thể đặt báo thức', 'error');
            }
        });

        reminderClearBtn.addEventListener('click', async () => {
            const res = await reminderApi('');
            if (res.status === 'success') {
                reminderInput.value = '';
                reminderStatusText.textContent = 'Chưa đặt báo thức cho ghi chú này.';
                reminderClearBtn.style.display = 'none';
                Swal.fire({ toast: true, position: 'top-end', icon: 'info', title: 'Đã huỷ báo thức', showConfirmButton: false, timer: 1800 });
            } else {
                Swal.fire('Lỗi', res.message || 'Không thể huỷ báo thức', 'error');
            }
        });

        // ── Realtime Collaboration & Autosave Engine ─────────────────────────
        let autoSaveTimer = null;
        let isSaving = false;
        let myLastSave = lastKnownUpdatedAt; // timestamp from our last save
        let lastKnownTitle = document.getElementById('title').value;
        let lastKnownContent = document.getElementById('content').value;
        let isTyping = false;
        let typingTimeout = null;

        // WebSocket is the primary low-latency channel. The polling endpoint
        // below remains a compatibility fallback for older deployments.
        function connectEditorWebSocket() {
            if (!window.NotezyCollaboration || !noteId) return;
            window.NotezyCollaboration.connect(noteId, (change) => {
                if (change.type !== 'draft') return;
                const titleEl = document.getElementById('title');
                const contentEl = document.getElementById('content');
                const hasLocalEdits = titleEl.value !== lastKnownTitle || contentEl.value !== lastKnownContent;
                if (hasLocalEdits || document.activeElement === titleEl || document.activeElement === contentEl) {
                    showConflictModal({ title: change.title || '', content: change.content || '', updated_at: change.updated_at || '' }, 'Cộng tác viên');
                    return;
                }
                if (typeof change.title === 'string') titleEl.value = change.title;
                if (typeof change.content === 'string') contentEl.value = change.content;
                lastKnownTitle = titleEl.value;
                lastKnownContent = contentEl.value;
                highlightFields();
                showStatus('Đã nhận thay đổi tức thời từ cộng tác viên', '#0dcaf0', 'fas fa-bolt');
            });
        }
        connectEditorWebSocket();

        // Floating Save Status Indicator
        const statusEl = document.createElement('div');
        statusEl.id = 'rtSaveStatus';
        statusEl.style.cssText =
            'position:fixed;top:14px;right:16px;z-index:9999;font-size:.85rem;font-weight:600;' +
            'padding:6px 16px;border-radius:20px;background:#198754;color:#fff;' +
            'display:none;transition:all .3s ease;box-shadow:0 3px 12px rgba(0,0,0,.25);';
        document.body.appendChild(statusEl);

        function showStatus(msg, color = '#198754', icon = 'fas fa-check') {
            statusEl.innerHTML = `<i class="${icon} me-1"></i> ${msg}`;
            statusEl.style.background = color;
            statusEl.style.display = 'block';
            statusEl.style.opacity = '1';
            clearTimeout(statusEl._timer);
            statusEl._timer = setTimeout(() => {
                statusEl.style.opacity = '0';
                setTimeout(() => { statusEl.style.display = 'none'; }, 300);
            }, 2200);
        }

        // ── Realtime Conflict Resolution Modal (Side-by-Side) ────────────────
        let pendingConflict = null;
        function showConflictModal(fresh, savedBy = 'Cộng tác viên') {
            pendingConflict = fresh;
            const currentTitle = document.getElementById('title').value;
            const currentContent = document.getElementById('content').value;

            Swal.fire({
                title: '<i class="fas fa-exclamation-triangle text-danger me-2"></i>Phát hiện xung đột thời gian thực!',
                html: `
                    <p class="text-muted small mb-3">
                        <strong>${escapeHtml(savedBy)}</strong> vừa lưu phiên bản mới hơn trên máy chủ trong khi bạn cũng đang chỉnh sửa ghi chú này.
                    </p>
                    <div class="row g-2 text-start mb-3" style="font-size:0.85rem;">
                        <div class="col-6">
                            <div class="p-2 border rounded bg-light h-100">
                                <span class="badge bg-primary mb-1">Bản hiện tại của bạn:</span>
                                <div class="fw-bold text-truncate">${escapeHtml(currentTitle)}</div>
                                <div class="text-muted small mt-1" style="max-height:100px;overflow-y:auto;white-space:pre-wrap;">${escapeHtml(currentContent)}</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 border rounded bg-light h-100" style="border-color:#0dcaf0 !important;">
                                <span class="badge bg-info text-dark mb-1">Bản mới của ${escapeHtml(savedBy)}:</span>
                                <div class="fw-bold text-truncate">${escapeHtml(fresh.title)}</div>
                                <div class="text-muted small mt-1" style="max-height:100px;overflow-y:auto;white-space:pre-wrap;">${escapeHtml(fresh.content)}</div>
                            </div>
                        </div>
                    </div>
                    <div class="text-secondary small">Vui lòng chọn cách bạn muốn xử lý xung đột này:</div>
                `,
                showCancelButton: true,
                showDenyButton: true,
                confirmButtonText: '<i class="fas fa-sync-alt me-1"></i> Tải bản của người khác',
                confirmButtonColor: '#0d6efd',
                denyButtonText: '<i class="fas fa-save me-1"></i> Ghi đè bản của tôi',
                denyButtonColor: '#dc3545',
                cancelButtonText: '<i class="fas fa-code-branch me-1"></i> Hợp nhất cả hai (Merge)',
                cancelButtonColor: '#198754',
                allowOutsideClick: false
            }).then((result) => {
                if (result.isConfirmed) {
                    // Option 1: Load collaborator's version
                    document.getElementById('title').value = fresh.title;
                    document.getElementById('content').value = fresh.content;
                    lastKnownUpdatedAt = fresh.updated_at;
                    myLastSave = fresh.updated_at;
                    lastKnownTitle = fresh.title;
                    lastKnownContent = fresh.content;
                    highlightFields();
                    showStatus('Đã tải bản mới của ' + savedBy, '#0dcaf0');
                } else if (result.isDenied) {
                    // Option 2: Overwrite server with local version
                    doAutoSave(true);
                } else if (result.dismiss === Swal.DismissReason.cancel) {
                    // Option 3: Merge both versions
                    const mergedTitle = currentTitle !== fresh.title ? (currentTitle + ' / ' + fresh.title) : currentTitle;
                    const mergedContent = currentContent + "\n\n--- [Nội dung từ " + savedBy + "] ---\n" + fresh.content;
                    document.getElementById('title').value = mergedTitle;
                    document.getElementById('content').value = mergedContent;
                    lastKnownUpdatedAt = fresh.updated_at;
                    highlightFields();
                    doAutoSave(true);
                    showStatus('Đã hợp nhất và lưu ghi chú', '#198754');
                }
            });
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }

        function highlightFields() {
            const titleEl = document.getElementById('title');
            const contentEl = document.getElementById('content');
            titleEl.classList.remove('rt-pulse-update');
            contentEl.classList.remove('rt-pulse-update');
            void titleEl.offsetWidth; // trigger reflow
            titleEl.classList.add('rt-pulse-update');
            contentEl.classList.add('rt-pulse-update');
        }

        // ── Autosave Function ────────────────────────────────────────────────
        async function doAutoSave(forceSave = false) {
            if (isSaving) return;
            const title = document.getElementById('title').value.trim();
            const content = document.getElementById('content').value.trim();
            if (!title && !content) return;

            isSaving = true;
            showStatus('Đang lưu...', '#6c757d', 'fas fa-spinner fa-spin');

            const body = new URLSearchParams({
                note_id: noteId,
                title,
                content,
                client_updated_at: lastKnownUpdatedAt,
                force_save: forceSave ? '1' : '0'
            });

            if (!navigator.onLine && window.NotezyOffline) {
                await window.NotezyOffline.updateNoteOffline(noteId, {
                    title,
                    content,
                    client_updated_at: lastKnownUpdatedAt
                });
                lastKnownTitle = title;
                lastKnownContent = content;
                showStatus('Saved locally', '#b45309', 'fas fa-wifi-slash');
                isSaving = false;
                return;
            }

            try {
                const res = await fetch('api/note_autosave.php', { method: 'POST', body });
                const data = await res.json();
                if (data.conflict && data.server_note) {
                    showConflictModal(data.server_note, data.server_note.saved_by || 'Cộng tác viên');
                } else if (data.success) {
                    lastKnownUpdatedAt = data.updated_at;
                    myLastSave = data.updated_at;
                    lastKnownTitle = title;
                    lastKnownContent = content;
                    showStatus('Đã lưu trực tiếp', '#198754', 'fas fa-check-circle');
                } else {
                    showStatus('Lưu thất bại: ' + (data.message || ''), '#dc3545', 'fas fa-times-circle');
                }
            } catch {
                if (window.NotezyOffline) {
                    await window.NotezyOffline.updateNoteOffline(noteId, {
                        title,
                        content,
                        client_updated_at: lastKnownUpdatedAt
                    });
                    lastKnownTitle = title;
                    lastKnownContent = content;
                    showStatus('Saved locally', '#b45309', 'fas fa-wifi-slash');
                } else {
                    showStatus('Mất kết nối server', '#dc3545', 'fas fa-wifi-slash');
                }
            } finally {
                isSaving = false;
            }
        }

        function scheduleAutoSave() {
            isTyping = true;
            window.NotezyCollaboration?.send({
                type: 'draft', note_id: noteId,
                title: document.getElementById('title').value,
                content: document.getElementById('content').value
            });
            clearTimeout(typingTimeout);
            typingTimeout = setTimeout(() => { isTyping = false; }, 1200);

            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(() => doAutoSave(false), 700); // Snappy 700ms debounce
        }

        ['title', 'content'].forEach(id => {
            document.getElementById(id).addEventListener('input', scheduleAutoSave);
        });

        // ── Fast Realtime Sync Polling (Every 1000ms) ─────────────────────────
        async function pollRealtimeSync() {
            try {
                const url = `api/collab_sync.php?note_id=${encodeURIComponent(noteId)}&since=${encodeURIComponent(lastKnownUpdatedAt)}&typing=${isTyping ? 1 : 0}`;
                const res = await fetch(url);
                if (!res.ok) return;
                const data = await res.json();
                if (!data.success) return;

                // 1. Update active collaborators list
                const presEl = document.getElementById('collabPresenceText');
                if (data.collaborators && data.collaborators.length > 0) {
                    const others = data.collaborators.filter(u => !u.is_me);
                    if (others.length > 0) {
                        let chips = others.map(u => `
                            <span class="user-chip active-chip">
                                <i class="fas fa-user-circle"></i> ${escapeHtml(u.name)}
                                ${u.is_typing ? '<span class="badge bg-warning text-dark ms-1" style="font-size:0.65rem;">đang gõ...</span>' : ''}
                            </span>
                        `).join('');
                        presEl.innerHTML = `<span class="text-success fw-semibold"><i class="fas fa-circle text-success me-1" style="font-size:8px;"></i>${others.length} người đang cùng mở:</span> ${chips}`;
                    } else {
                        presEl.textContent = 'Chỉ có bạn đang mở ghi chú này';
                    }
                }

                // 2. Typing indicator
                const typingEl = document.getElementById('collabTypingIndicator');
                const typingNameEl = document.getElementById('collabTypingName');
                if (data.typing_users && data.typing_users.length > 0) {
                    typingNameEl.textContent = data.typing_users.join(', ');
                    typingEl.style.display = 'block';
                } else {
                    typingEl.style.display = 'none';
                }

                // 3. Detect changes from collaborator
                if (data.has_newer && data.updated_at !== myLastSave) {
                    const currentTitle = document.getElementById('title').value;
                    const currentContent = document.getElementById('content').value;
                    const hasLocalEdits = (currentTitle !== lastKnownTitle || currentContent !== lastKnownContent);
                    const isFocusing = (document.activeElement === document.getElementById('title') ||
                                        document.activeElement === document.getElementById('content'));

                    if (hasLocalEdits || isFocusing) {
                        // User is actively editing or has unsaved local edits -> CONFLICT WARNING
                        showConflictModal(data, 'Cộng tác viên');
                    } else {
                        // User has no local unsaved edits -> INSTANT SMOOTH SYNC
                        document.getElementById('title').value = data.title;
                        document.getElementById('content').value = data.content;
                        lastKnownUpdatedAt = data.updated_at;
                        myLastSave = data.updated_at;
                        lastKnownTitle = data.title;
                        lastKnownContent = data.content;
                        highlightFields();
                        showStatus('Cộng tác viên vừa cập nhật', '#0dcaf0', 'fas fa-sync-alt');
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'info',
                            title: '⚡ Ghi chú vừa được cập nhật trực tiếp bởi cộng tác viên',
                            showConfirmButton: false,
                            timer: 2000,
                            timerProgressBar: true
                        });
                    }
                }
            } catch (e) {
                // silent network error
            }
        }

        setInterval(pollRealtimeSync, 1000);
        pollRealtimeSync();

        // ── Image Preview ────────────────────────────────────────────────────
        document.getElementById('noteImage').addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    document.getElementById('imagePreview').src = e.target.result;
                    document.getElementById('imagePreviewContainer').style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                document.getElementById('imagePreview').src = '';
                document.getElementById('imagePreviewContainer').style.display = 'none';
            }
        });

        // ── Form Submit (Manual Save) ────────────────────────────────────────
        const form = document.querySelector('form');
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            clearTimeout(autoSaveTimer);

            const btn = document.getElementById('saveBtn');
            btn.disabled = true;
            btn.textContent = 'Đang lưu...';

            if (!navigator.onLine && window.NotezyOffline) {
                await window.NotezyOffline.updateNoteOffline(noteId, {
                    title: document.getElementById('title').value.trim(),
                    content: document.getElementById('content').value.trim(),
                    client_updated_at: lastKnownUpdatedAt
                });
                Swal.fire('Saved locally', 'Thay đổi sẽ được đồng bộ khi có mạng.', 'info');
                btn.disabled = false;
                btn.textContent = 'Lưu thay đổi';
                return;
            }

            const imageFile = document.getElementById('noteImage').files[0];
            if (imageFile) {
                const imgData = new FormData();
                imgData.append('note_id', noteId);
                imgData.append('image', imageFile);
                try {
                    const uploadRes = await fetch('api/upload.php', { method: 'POST', body: imgData });
                    const uploadResult = await uploadRes.json();
                    if (uploadResult.status === 'error') {
                        Swal.fire({ title: 'Lỗi tải ảnh!', text: uploadResult.message, icon: 'error' });
                        btn.disabled = false;
                        btn.textContent = 'Lưu thay đổi';
                        return;
                    }
                } catch (error) {
                    Swal.fire('Lỗi', 'Không thể kết nối đến server để tải ảnh', 'error');
                    btn.disabled = false;
                    btn.textContent = 'Lưu thay đổi';
                    return;
                }
            }

            form.submit();
        });
    </script>
    <?php
$notezy_ai_page = 'edit_note';
    $notezy_ai_note_id = (int) $note_id;
    include __DIR__ . '/includes/ai_widget.php';
    ?>
</body>

</html>
<?php
$conn->close();
?>
