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

$error = '';
$notes = [];
$user_id = (int) $_SESSION['id'];

// Kiểm tra user_id hợp lệ
if ($user_id <= 0) {
    $error = 'Phiên đăng nhập không hợp lệ. Vui lòng đăng nhập lại.';
    header('Location: index.php');
    exit();
}

notezy_ensure_user_last_seen_column($conn);
$seen_stmt = $conn->prepare("UPDATE users SET last_seen_at = NOW() WHERE id = ?");
if ($seen_stmt) {
    $seen_stmt->bind_param('i', $user_id);
    $seen_stmt->execute();
    $seen_stmt->close();
}

// Kiểm tra tài khoản đã kích hoạt và lấy preferences
$sql = "SELECT activated, theme, language FROM users WHERE id = ?";
$stm = $conn->prepare($sql);
if (!$stm) {
    die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
}
$stm->bind_param('i', $user_id);
if (!$stm->execute()) {
    die("Execute failed: (" . $stm->errno . ") " . $stm->error);
}
$result = $stm->get_result();
if (!$result) {
    die("Get result failed: (" . $conn->errno . ") " . $conn->error);
}
$user_info = $result->fetch_assoc();
if ($user_info['activated'] === 0) {
    $error = 'Bạn chưa kích hoạt tài khoản';
}

$_SESSION['theme'] = $user_info['theme'] ?? 'light';
$_SESSION['language'] = $user_info['language'] ?? 'vi';

$lang = $_SESSION['language'];
$t = [
    'vi' => [
        'note_list' => 'Danh Sách Ghi Chú',
        'my_notes' => 'Ghi chú của tôi',
        'shared_notes' => 'Được chia sẻ với tôi',
        'no_notes' => 'Bạn chưa có ghi chú nào.',
        'all_labels' => 'Tất cả',
        'manage_labels' => 'Quản lý nhãn',
    ],
    'en' => [
        'note_list' => 'Notes List',
        'my_notes' => 'My Notes',
        'shared_notes' => 'Shared with me',
        'no_notes' => 'You have no notes yet.',
        'all_labels' => 'All',
        'manage_labels' => 'Manage Labels',
    ]
][$lang] ?? [
    'note_list' => 'Danh Sách Ghi Chú',
    'my_notes' => 'Ghi chú của tôi',
    'shared_notes' => 'Được chia sẻ với tôi',
    'no_notes' => 'Bạn chưa có ghi chú nào.',
    'all_labels' => 'Tất cả',
    'manage_labels' => 'Quản lý nhãn',
];

// Lấy danh sách ghi chú
try {
    // Kiểm tra kết nối
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Tăng giới hạn GROUP_CONCAT và tắt ONLY_FULL_GROUP_BY
    $conn->query("SET SESSION group_concat_max_len = 10000");
    $conn->query("SET SESSION sql_mode = ''");

    $sql = "
        SELECT n.note_id, n.title, n.content, n.pinned, n.created_at, n.user_id, n.password_hash, n.archived, n.image_path, n.updated_at,
               n.pin_hash, n.background_color, n.text_color, n.font_family, n.reminder_at, n.status, n.deadline,
               GROUP_CONCAT(l.name SEPARATOR ',') AS labels,
               CASE WHEN n.user_id = ? THEN 'own' ELSE 'shared' END AS ownership,
               u.username AS shared_by_username,
               ns.permission,
               ns.shared_at
        FROM notes n
        LEFT JOIN note_labels nl ON n.note_id = nl.note_id
        LEFT JOIN labels l ON nl.label_id = l.label_id
        LEFT JOIN note_shares ns ON n.note_id = ns.note_id AND ns.shared_with_user_id = ?
        LEFT JOIN users u ON ns.shared_by_user_id = u.id
        WHERE n.user_id = ? OR ns.note_id IS NOT NULL
        GROUP BY n.note_id, n.title, n.content, n.pinned, n.created_at, n.user_id, n.password_hash, n.archived, n.image_path, n.updated_at,
                 n.pin_hash, n.background_color, n.text_color, n.font_family, n.reminder_at, n.status, n.deadline, u.username, ns.permission, ns.shared_at
        ORDER BY n.pinned DESC, n.created_at DESC
    ";
    $stm = $conn->prepare($sql);
    if (!$stm) {
        die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
    }
    $stm->bind_param('iii', $user_id, $user_id, $user_id);
    if (!$stm->execute()) {
        die("Execute failed: (" . $stm->errno . ") " . $stm->error);
    }
    $result = $stm->get_result();
    if (!$result) {
        die("Get result failed: (" . $conn->errno . ") " . $conn->error);
    }
    $notes = $result->fetch_all(MYSQLI_ASSOC);
    if ($notes === false) {
        die("Fetch failed: (" . $conn->errno . ") " . $conn->error);
    }

    // Danh sách các sổ đã mở khóa mã bảo mật trong phiên hiện tại
    $session_id = session_id();
    $unlocked_pin_notes = isset($_SESSION['pin_unlocked_notes']) ? $_SESSION['pin_unlocked_notes'] : [];
    $chk_stm = $conn->prepare("SELECT note_id FROM note_pin_unlocks WHERE session_id = ?");
    if ($chk_stm) {
        $chk_stm->bind_param('s', $session_id);
        $chk_stm->execute();
        $chk_res = $chk_stm->get_result();
        while ($chk_row = $chk_res->fetch_assoc()) {
            $unlocked_pin_notes[(int)$chk_row['note_id']] = true;
        }
        $chk_stm->close();
    }
    $_SESSION['pin_unlocked_notes'] = $unlocked_pin_notes;

    $accessed_notes = isset($_SESSION['accessed_notes']) ? $_SESSION['accessed_notes'] : [];

    foreach ($notes as &$row) {
        $row['labels'] = $row['labels'] ? explode(',', $row['labels']) : [];
        $nid = (int)$row['note_id'];
        $has_pin = !empty($row['pin_hash']);
        $row['has_pin'] = $has_pin;
        $row['is_pin_locked'] = $has_pin && empty($unlocked_pin_notes[$nid]);

        $has_pwd = !empty($row['password_hash']);
        $row['has_password'] = $has_pwd;
        $row['is_password_locked'] = $has_pwd && empty($accessed_notes[$nid]);

        // Bảo vệ nội dung chống rò rỉ khi chưa xác thực mật khẩu
        if ($row['is_password_locked']) {
            $row['masked_content'] = '[Ghi chú này đã được bảo vệ bằng mật khẩu. Vui lòng mở khóa để xem nội dung.]';
        } else {
            $row['masked_content'] = $row['content'];
        }
    }
    unset($row);

    $own_notes = array_filter($notes, function($note) {
        return $note['ownership'] == 'own' && $note['archived'] == 0;
    });
    $shared_notes = array_filter($notes, function($note) {
        return $note['ownership'] == 'shared' && $note['archived'] == 0;
    });
    $archived_notes = array_filter($notes, function($note) {
        return $note['archived'] == 1;
    });

    // Distinct labels across own + shared (non-archived) notes, for the filter chip bar
    $all_labels_set = [];
    foreach (array_merge($own_notes, $shared_notes) as $n) {
        foreach ($n['labels'] as $lbl) {
            if ($lbl !== '') $all_labels_set[$lbl] = true;
        }
    }
    $all_labels = array_keys($all_labels_set);
    sort($all_labels, SORT_STRING | SORT_FLAG_CASE);

    $stm->close();
} catch (Exception $e) {
    $error = "Lỗi truy vấn: " . $e->getMessage();
}


$user_id = (int) $_SESSION['id'];  // 🛠️ Thêm dòng này để gán giá trị user_id

$sql1 = "SELECT avatar FROM users WHERE id = ?";
$stm1 = $conn->prepare($sql1);

if (!$stm1) {
    die("Prepare failed: " . $conn->error);
}

$stm1->bind_param('i', $user_id);

if (!$stm1->execute()) {
    die("Execute failed: " . $stm1->error);
}

$result1 = $stm1->get_result();

if (!$result1) {
    die("Get result failed: " . $stm1->error);
}

$kq = $result1->fetch_assoc();
$avatar = $kq && isset($kq['avatar']) ? $kq['avatar'] : 'default.png'; // fallback nếu không có avatar
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Notezy - Ứng dụng ghi chú</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#0d6efd">
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
        .main-content {
            padding: 40px 0;
            padding-top: 50px;
        }
        .section-title {
            position: relative;
            font-size: 2.5rem;
            font-weight: bold;
            text-align: center;
            margin-bottom: 30px;
            color: #000000;
        }
        .section-title:after {
            content: '';
            display: block;
            width: 80px;
            height: 4px;
            background-color: #000000;
            margin: 10px auto 0;
            border-radius: 2px;
        }
        .note-form {
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            padding: 30px;
            margin-bottom: 30px;
            max-width: 800px;
            margin: 0 auto 50px;
            border: 1px solid #e0e0e0;
        }
        .form-label {
            font-weight: 600;
            color: #000000;
            margin-bottom: 8px;
        }
        .form-control {
            border: 2px solid #d0d0d0;
            border-radius: 5px;
            padding: 12px 15px;
            transition: all 0.3s;
            background-color: #fafafa;
        }
        .form-control:focus {
            box-shadow: none;
            border-color: #000000;
            background-color: #ffffff;
        }
        .form-text {
            background-color: #e6e6e6;
            border-left: 4px solid #000000;
            padding: 12px 15px;
            color: #333333;
            border-radius: 4px;
            margin-bottom: 20px;
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
        .view-controls {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            border: 1px solid #e0e0e0;
        }
        .btn-outline-primary {
            color: #000000;
            border-color: #000000;
        }
        .btn-outline-primary:hover, 
        .btn-outline-primary.active {
            background-color: #000000;
            color: #ffffff;
        }
        .btn-outline-secondary {
            color: #6c757d;
            border-color: #6c757d;
        }
        .btn-outline-secondary:hover {
            background-color: #6c757d;
            color: #ffffff;
        }
        .btn-outline-info {
            color: #0dcaf0;
            border-color: #0dcaf0;
        }
        .btn-outline-info:hover {
            background-color: #0dcaf0;
            color: #ffffff;
        }
        .btn-outline-danger {
            color: #dc3545;
            border-color: #dc3545;
        }
        .btn-outline-danger:hover {
            background-color: #dc3545;
            color: #ffffff;
        }
        .note-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        .note-item {
            background-color: #ffffff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            border-top: 4px solid #000000;
            border: 1px solid #e0e0e0;
        }
        .note-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.12);
        }
        .note-item h5 {
            font-size: 1.2rem;
            font-weight: bold;
            color: #000000;
            margin-bottom: 10px;
        }
        .note-item p {
            color: #333333;
            font-size: 0.95rem;
            margin-bottom: 15px;
        }
        .note-actions {
            display: flex;
            justify-content: flex-end;
            gap: 5px;
            flex-wrap: wrap;
        }
        .note-list {
            list-style-type: none;
            padding: 0;
            margin-top: 30px;
        }
        .note-list .note-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid #e0e0e0;
            border-top: none;
            border-radius: 6px;
            margin-bottom: 15px;
        }
        .note-list .note-item .content {
            flex: 1;
            margin-left: 15px;
        }
        .note-list .note-item h5 {
            font-size: 1.2rem;
            margin-bottom: 5px;
        }
        footer {
            background-color: #000000;
            color: #f5f5f5;
        }
        footer h5 {
            font-weight: 600;
            margin-bottom: 20px;
            color: #ffffff;
        }
        footer a {
            color: #c0c0c0;
            text-decoration: none;
            transition: color 0.3s;
        }
        footer a:hover {
            color: #ffffff;
            text-decoration: none;
        }
        footer .list-unstyled li {
            margin-bottom: 10px;
        }
        footer .fab {
            width: 35px;
            height: 35px;
            line-height: 35px;
            text-align: center;
            border-radius: 50%;
            background-color: #333333;
            transition: all 0.3s;
        }
        footer .fab:hover {
            background-color: #555555;
            transform: translateY(-2px);
        }
        .pin-icon {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 1.3rem;
            color: black;
            transform: rotate(-45deg);
            transition: transform 0.3s ease, color 0.3s ease;
            opacity: 0.7;
        }
        .lock-icon {
            position: absolute;
            top: 10px;
            right: 30px;
            font-size: 1.2rem;
            color: #ff5555;
            opacity: 0.7;
        }
        .share-icon {
            position: absolute;
            top: 10px;
            right: 50px;
            font-size: 1.2rem;
            color: #55aa55;
            opacity: 0.7;
        }
        .note-item:hover .pin-icon,
        .note-item:hover .lock-icon,
        .note-item:hover .share-icon {
            transform: rotate(0deg);
            opacity: 1;
        }
        .floating-action-btn {
            position: fixed;
            bottom: 30px;
            left: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: black;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            cursor: pointer;
        }
        .floating-action-btn i {
            font-size: 24px;
        }
        .floating-menu {
            position: fixed;
            bottom: 100px;
            left: 30px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            z-index: 999;
            display: none;
        }
        .floating-menu.show {
            display: block;
        }
        .floating-menu a {
            display: block;
            padding: 12px 15px;
            text-decoration: none;
            color: #333;
            border-bottom: 1px solid #eee;
        }
        .floating-menu a:last-child {
            border-bottom: none;
        }
        .floating-menu a:hover {
            background-color: #f8f9fa;
        }
        .note-labels {
            margin-top: 8px;
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
        .label-filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .label-chip {
            cursor: pointer;
            user-select: none;
            font-size: 0.85rem;
            padding: 5px 12px;
            border-radius: 16px;
            border: 1px solid #ccc;
            background-color: #f5f5f5;
            color: #333;
            transition: all 0.15s ease;
        }
        .label-chip:hover {
            background-color: #e9e9e9;
        }
        .label-chip.active {
            background-color: #0d6efd;
            border-color: #0d6efd;
            color: #ffffff;
        }
        .manage-labels-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 10px;
            border-bottom: 1px solid #eee;
        }
        .manage-labels-row:last-child {
            border-bottom: none;
        }
        .search-modal {
            position: fixed;
            width: 280px;
            max-width: 90%;
            z-index: 1060;
            margin: 0;
            transition: all 0.3s ease;
        }
        .search-modal .modal-content {
            border-radius: 6px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
            background-color: #ffffff;
            border: 1px solid #e0e0e0;
        }
        .search-modal .modal-header {
            border-bottom: 1px solid #e0e0e0;
            padding: 10px 12px;
        }
        .search-modal .modal-body {
            padding: 12px;
        }
        .search-modal .modal-title {
            font-size: 1.1rem;
            font-weight: 600;
        }
        .search-modal .form-label {
            font-size: 0.85rem;
            margin-bottom: 4px;
        }
        .search-modal .form-control {
            font-size: 0.8rem;
            padding: 8px 10px;
        }
        .search-modal .form-check-label {
            font-size: 0.8rem;
        }
        .search-modal .form-check {
            margin-bottom: 8px;
        }
        .modal.no-backdrop::before {
            content: none;
        }
        @media (max-width: 768px) {
            .search-modal {
                width: 90%;
                max-width: 300px;
                top: 60px !important;
                left: 50% !important;
                transform: translateX(-50%) !important;
                right: auto !important;
            }
            .search-modal .modal-content {
                padding: 8px;
            }
            .search-modal .modal-header {
                padding: 8px 10px;
            }
            .search-modal .modal-body {
                padding: 10px;
            }
            .search-modal .form-label {
                font-size: 0.8rem;
            }
            .search-modal .form-control {
                font-size: 0.75rem;
                padding: 6px 8px;
            }
            .search-modal .form-check-label {
                font-size: 0.75rem;
            }
            .note-actions {
                flex-direction: column;
                align-items: flex-end;
            }
            .note-actions .btn {
                margin-bottom: 5px;
            }
        }
        @media (max-width: 576px) {
            .search-modal {
                width: 95%;
                max-width: 280px;
            }
            .floating-action-btn {
                bottom: 20px;
                left: 20px;
                width: 50px;
                height: 50px;
            }
            .floating-action-btn i {
                font-size: 20px;
            }
        }
        @media (max-width: 480px) {
            .main-content {
                padding-left: 8px !important;
                padding-right: 8px !important;
            }
            .view-controls {
                justify-content: center !important;
            }
            .view-controls .btn {
                font-size: 0.8rem;
                padding: 6px 10px;
            }
            .label-filter-bar {
                justify-content: center;
            }
            .label-chip {
                font-size: 0.75rem;
                padding: 4px 10px;
            }
            .note-grid {
                grid-template-columns: 1fr !important;
            }
            .note-item {
                padding: 10px !important;
            }
            .note-item h5 {
                font-size: 1rem;
            }
            .note-item img {
                max-height: 140px !important;
            }
            .note-actions {
                width: 100%;
            }
            .note-actions .btn {
                width: 100%;
                font-size: 0.75rem;
            }
            .navbar-brand.logo {
                font-size: 1.1rem;
            }
            .modal-dialog {
                margin: 8px;
            }
        }

        /* ── NÚT BẤM ACTION DẠNG ICON-ONLY (CHỈ HIỆN ẢNH/ICON) ── */
        .btn-action-icon {
            width: 35px;
            height: 35px;
            padding: 0;
            border-radius: 50% !important;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(0, 0, 0, 0.08);
            background-color: #ffffff;
            color: #4b5563;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            flex-shrink: 0;
        }
        .btn-action-icon:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.12);
        }
        .btn-action-icon.btn-timetable {
            background-color: #ecfdf5;
            color: #059669;
            border-color: #a7f3d0;
        }
        .btn-action-icon.btn-timetable:hover {
            background-color: #059669;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        }
        .btn-action-icon.btn-pin-active {
            background-color: #fef3c7;
            color: #d97706;
            border-color: #fcd34d;
            box-shadow: 0 0 8px rgba(217, 119, 6, 0.3);
        }
        .btn-action-icon.btn-pin-active:hover {
            background-color: #d97706;
            color: #ffffff;
        }
        .btn-action-icon.btn-pin {
            background-color: #f3f4f6;
            color: #4b5563;
        }
        .btn-action-icon.btn-pin:hover {
            background-color: #374151;
            color: #ffffff;
        }
        .btn-action-icon.btn-unlock {
            background-color: #eff6ff;
            color: #2563eb;
            border-color: #bfdbfe;
        }
        .btn-action-icon.btn-unlock:hover {
            background-color: #2563eb;
            color: #ffffff;
        }
        .btn-action-icon.btn-view {
            background-color: #f0fdf4;
            color: #16a34a;
            border-color: #bbf7d0;
        }
        .btn-action-icon.btn-view:hover {
            background-color: #16a34a;
            color: #ffffff;
        }
        .btn-action-icon.btn-edit {
            background-color: #f8fafc;
            color: #475569;
            border-color: #cbd5e1;
        }
        .btn-action-icon.btn-edit:hover {
            background-color: #475569;
            color: #ffffff;
        }
        .btn-action-icon.btn-pin-toggle.active {
            background-color: #fef9c3;
            color: #ca8a04;
            border-color: #fde047;
        }
        .btn-action-icon.btn-share {
            background-color: #f0fdf4;
            color: #15803d;
            border-color: #bbf7d0;
        }
        .btn-action-icon.btn-share:hover {
            background-color: #15803d;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(21, 128, 61, 0.3);
        }
        .btn-action-icon.btn-archive:hover {
            background-color: #d97706;
            color: #ffffff;
        }
        .btn-action-icon.btn-delete:hover {
            background-color: #dc2626;
            color: #ffffff;
            border-color: #dc2626;
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
        }

        /* Navbar Theme Toggle Button */
        .btn-inside-theme-toggle {
            border-radius: 9999px;
            padding: 5px 14px;
            font-size: 0.75rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
            border: 1px solid rgba(16, 185, 129, 0.3);
            background-color: #ecfdf5;
            color: #047857;
        }
        .btn-inside-theme-toggle:hover {
            background-color: #d1fae5;
            color: #065f46;
        }
        .theme-pulse-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #10b981;
            box-shadow: 0 0 8px rgba(16, 185, 129, 0.8);
            display: inline-block;
            animation: pulseDot 1.5s infinite;
        }
        @keyframes pulseDot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.85); }
        }

        /* ── CHẾ ĐỘ SÁNG: TRẮNG + XANH LÁ CÂY (LIGHT MODE) ── */
        body.theme-light, body:not(.theme-dark) {
            background-color: #f8fafc;
            color: #1e293b;
        }
        body.theme-light .navbar, body:not(.theme-dark) .navbar {
            background: rgba(255, 255, 255, 0.94) !important;
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(16, 185, 129, 0.15) !important;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
        }
        body.theme-light .note-item, body:not(.theme-dark) .note-item {
            background: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            border-radius: 16px !important;
        }
        body.theme-light .note-item:hover, body:not(.theme-dark) .note-item:hover {
            border-color: #10b981 !important;
            box-shadow: 0 8px 24px rgba(16, 185, 129, 0.12) !important;
        }

        /* ── CHẾ ĐỘ TỐI: ĐEN + ĐỎ RỰC (DARK MODE) ── */
        body.theme-dark {
            background-color: #050505 !important;
            color: #f1f5f9 !important;
        }
        body.theme-dark .navbar {
            background: rgba(8, 8, 8, 0.94) !important;
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(220, 38, 38, 0.3) !important;
            box-shadow: 0 4px 20px rgba(220, 38, 38, 0.1) !important;
        }
        body.theme-dark .navbar-brand.logo, body.theme-dark .nav-link {
            color: #ffffff !important;
        }
        body.theme-dark .nav-link:hover {
            color: #ef4444 !important;
        }
        body.theme-dark .btn-inside-theme-toggle {
            border-color: rgba(239, 68, 68, 0.4);
            background-color: rgba(69, 10, 10, 0.5);
            color: #f87171;
        }
        body.theme-dark .btn-inside-theme-toggle:hover {
            background-color: rgba(127, 29, 29, 0.6);
            color: #fca5a5;
        }
        body.theme-dark .theme-pulse-dot {
            background-color: #ef4444;
            box-shadow: 0 0 8px rgba(239, 68, 68, 0.9);
        }
        body.theme-dark .note-item {
            background: #121215 !important;
            color: #f1f5f9 !important;
            border: 1px solid #27272a !important;
            border-radius: 16px !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4) !important;
        }
        body.theme-dark .note-item:hover {
            border-color: #dc2626 !important;
            box-shadow: 0 8px 24px rgba(220, 38, 38, 0.2) !important;
        }
        body.theme-dark .note-item h5, body.theme-dark .note-body-text {
            color: #ffffff !important;
        }
        body.theme-dark .modal-content, body.theme-dark .view-controls, body.theme-dark .note-form {
            background-color: #121215 !important;
            color: #ffffff !important;
            border-color: #27272a !important;
        }
        body.theme-dark .form-control {
            background-color: #18181b !important;
            border-color: #3f3f46 !important;
            color: #ffffff !important;
        }
        body.theme-dark .form-control:focus {
            border-color: #ef4444 !important;
            box-shadow: 0 0 0 0.2rem rgba(239, 68, 68, 0.25) !important;
        }
        body.theme-dark .btn-primary {
            background-color: #dc2626 !important;
            border-color: #dc2626 !important;
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3) !important;
        }
        body.theme-dark .btn-primary:hover {
            background-color: #b91c1c !important;
        }
        body.theme-dark .btn-action-icon {
            background-color: #18181b;
            border-color: #27272a;
            color: #94a3b8;
        }
        body.theme-dark .btn-action-icon.btn-timetable {
            background-color: #450a0a;
            color: #f87171;
            border-color: #7f1d1d;
        }
        body.theme-dark .btn-action-icon.btn-timetable:hover {
            background-color: #dc2626;
            color: #ffffff;
            box-shadow: 0 0 10px rgba(239, 68, 68, 0.5);
        }
        body.theme-dark .btn-action-icon.btn-pin-active {
            background-color: #78350f;
            color: #fde047;
            border-color: #b45309;
        }
        body.theme-dark .locked-note-cover {
            background-color: #18181b !important;
            border-color: rgba(239, 68, 68, 0.3) !important;
        }
        body.theme-dark .locked-note-cover .text-dark {
            color: #ffffff !important;
        }
        body.theme-dark footer {
            background-color: #000000 !important;
            border-top: 1px solid #27272a !important;
        }
    </style>
</head>
<body class="theme-light">

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
                    <li class="nav-item"><a class="nav-link" href="introduce.php"><i class="fas fa-info-circle me-1"></i>About Us</a></li>
                    <li class="nav-item"><a class="nav-link" href="khampha.php"><i class="fas fa-star me-1"></i>Features</a></li>
                    <li class="nav-item"><a class="nav-link" href="privacy.php"><i class="fas fa-shield-alt me-1"></i>Privacy</a></li>
                    <li class="nav-item"><a class="nav-link" href="contact.php"><i class="fas fa-envelope me-1"></i>Contact</a></li>
                    <li class="nav-item"><a class="nav-link" href="labels.php"><i class="fas fa-tags me-1"></i>Nhãn</a></li>
                        
                        
                    <li class="nav-item"><a class="nav-link text-primary fw-bold" href="thoikhoabieu.php"><i class="fas fa-calendar-alt me-1"></i>Thời khóa biểu</a></li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#searchModal" title="Tìm kiếm">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-search align-middle" viewBox="0 0 16 16">
                                <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001q.044.06.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1 1 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0"/>
                            </svg>
                            Tìm kiếm
                        </a>
                    </li>
                    <!-- 🔔 NOTIFICATION BELL -->
                    <li class="nav-item dropdown ms-2 d-flex align-items-center" id="notifBellItem">
                        <a class="nav-link position-relative p-2" href="#" id="notifBellBtn"
                           role="button" data-bs-toggle="dropdown" aria-expanded="false"
                           title="Nhắc nhở ghi chú">
                            <i class="fas fa-bell fs-5" style="color:#f59e0b"></i>
                            <span id="notifBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none">0</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow" id="notifDropdown"
                            style="min-width:320px;max-height:380px;overflow-y:auto;">
                            <li class="px-3 py-2 border-bottom">
                                <strong><i class="fas fa-bell text-warning me-1"></i> Nhắc nhở ghi chú</strong>
                            </li>
                            <li id="notifEmpty" class="px-3 py-3 text-center text-muted">
                                <i class="fas fa-check-circle text-success me-1"></i>Không có nhắc nhở nào
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item ms-2 d-flex align-items-center">
                        <button type="button" id="btnThemeToggleInside" class="btn-inside-theme-toggle" onclick="toggleInsideTheme()" title="Chuyển chế độ: Sáng (Trắng & Xanh) | Tối (Đen & Đỏ)">
                            <i class="fas fa-sun" id="themeToggleIcon"></i>
                            <span id="themeToggleText" class="d-none d-md-inline ms-1">Sáng (Trắng & Xanh)</span>
                            <span class="theme-pulse-dot ms-1"></span>
                        </button>
                    </li>
                    <li class="nav-item dropdown avatar-container ms-2">
                        <a class="nav-link dropdown-toggle p-0" href="#" id="avatarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="<?=$avatar?>" alt="Avatar" class="avatar">
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="avatarDropdown">
                            <li><a class="dropdown-item" href="account.php"><i class="fas fa-user me-2"></i>Xem trang cá nhân</a></li>
                            <li><a class="dropdown-item" href="admin_users.php"><i class="fas fa-users-cog me-2"></i>Quản trị Người Dùng</a></li>
                            <li><a class="dropdown-item" href="labels.php"><i class="fas fa-tags me-2"></i>Quản lý Nhãn</a></li>
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
    <h2 class="text-center mb-4"><?= htmlspecialchars($t['note_list']) ?></h2>
    
    <div class="view-controls d-flex justify-content-end flex-wrap gap-2">
        <a href="thoikhoabieu.php" class="btn btn-primary">
            <i class="fas fa-calendar-alt me-1"></i> Thời khóa biểu & Lịch
        </a>
        <button class="btn btn-outline-secondary" id="manageLabelsBtn" type="button">
            <i class="fas fa-tags me-1"></i> <?= htmlspecialchars($t['manage_labels']) ?>
        </button>
        <button class="btn btn-outline-primary me-2 active" id="gridViewBtn">
            <i class="fas fa-th me-1"></i> Grid View
        </button>
        <button class="btn btn-outline-primary" id="listViewBtn">
            <i class="fas fa-list me-1"></i> List View
        </button>
    </div>

    <!-- Label filter chips (client-side filter across all 4 note areas) -->
    <div id="labelFilterBar" class="label-filter-bar mb-3 mt-2">
        <span class="label-chip active" data-label=""><?= htmlspecialchars($t['all_labels']) ?></span>
        <?php foreach ($all_labels as $lbl): ?>
            <span class="label-chip" data-label="<?= htmlspecialchars($lbl) ?>"><?= htmlspecialchars($lbl) ?></span>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- Own Notes Grid View -->
    <div id="ownNotesGrid" class="container note-grid">
        <div class="d-flex align-items-center justify-content-between mb-3 w-100 col-12">
            <h3 class="mb-0 fw-bold"><i class="fas fa-book-reader text-primary me-2"></i><?= htmlspecialchars($t['my_notes']) ?></h3>
            <span class="badge bg-dark rounded-pill px-3 py-2"><?= count($own_notes) ?> sổ ghi chú</span>
        </div>
        <?php if (!empty($own_notes)): ?>
            <?php foreach ($own_notes as $note): ?>
                <?php
$card_style = '';
                if (!empty($note['background_color']) && $note['background_color'] !== '#ffffff') {
                    $card_style .= 'background-color: ' . htmlspecialchars($note['background_color']) . ' !important; ';
                }
                if (!empty($note['text_color']) && $note['text_color'] !== '#000000') {
                    $card_style .= 'color: ' . htmlspecialchars($note['text_color']) . ' !important; ';
                }
                if (!empty($note['font_family'])) {
                    $card_style .= 'font-family: ' . htmlspecialchars($note['font_family']) . ' !important; ';
                }
                ?>
                <div class="note-item position-relative note-card-<?= $note['note_id'] ?>" data-note-id="<?= $note['note_id'] ?>" data-labels="<?= htmlspecialchars(implode(',', $note['labels'])) ?>" style="<?= $card_style ?>">
                    <?php if ($note['pinned']): ?>
                        <span class="pin-icon" title="Ghi chú đã ghim"><i class="fas fa-thumbtack text-warning"></i></span>
                    <?php endif; ?>

                    <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-1">
                        <div>
                            <?php if ($note['has_pin']): ?>
                                <?php if ($note['is_pin_locked']): ?>
                                    <span class="badge bg-danger pin-badge-<?= $note['note_id'] ?>"><i class="fas fa-lock me-1"></i>Bảo mật 4 số</span>
                                <?php else: ?>
                                    <span class="badge bg-success pin-badge-<?= $note['note_id'] ?>"><i class="fas fa-lock-open me-1"></i>Đã mở khóa</span>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if ($note['password_hash']): ?>
                                <span class="badge bg-secondary" title="Bảo vệ bằng mật khẩu"><i class="fas fa-key me-1"></i>Pass</span>
                            <?php endif; ?>
                        </div>
                        <small class="text-muted" style="font-size: 0.75rem;">
                            <i class="far fa-clock me-1"></i><?= date('d/m/Y H:i', strtotime($note['created_at'])) ?>
                        </small>
                    </div>

                    <h5 class="mb-2 fw-bold text-truncate note-title-heading" title="<?= htmlspecialchars($note['title']) ?>">
                        <?= htmlspecialchars($note['title']) ?>
                    </h5>

                    <?php if (!empty($note['reminder_at'])): ?>
                        <div class="mb-2">
                            <span class="badge bg-warning-subtle text-dark border border-warning px-2 py-1" title="Sự kiện của sổ">
                                <i class="fas fa-bell text-warning me-1"></i>Sự kiện: <?= date('H:i d/m/Y', strtotime($note['reminder_at'])) ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <?php if ($note['is_password_locked']): ?>
                        <!-- Khung che bảo vệ mật khẩu (Password Protection) -->
                        <div class="locked-note-cover text-center p-3 my-2 rounded bg-light border border-info-subtle shadow-sm" id="pwdLockedCover_grid_<?= $note['note_id'] ?>">
                            <div class="mb-2 text-primary">
                                <i class="fas fa-lock fa-2x"></i>
                            </div>
                            <div class="fw-bold text-dark small mb-1">Ghi chú đã bảo mật bằng mật khẩu</div>
                            <p class="text-muted small mb-2" style="font-size:0.75rem;">Nhập mật khẩu để xem nội dung và hình ảnh của ghi chú này.</p>
                            <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 py-1 shadow-sm" onclick="openUnlockPasswordModal(<?= $note['note_id'] ?>, '<?= htmlspecialchars(addslashes($note['title'])) ?>')">
                                <i class="fas fa-key me-1"></i> Nhập mật khẩu
                            </button>
                        </div>
                        <div class="unlocked-pwd-content" id="pwdUnlockedContent_grid_<?= $note['note_id'] ?>" style="display:none;">
                            <?php if (!empty($note['image_path'])): ?>
                                <div class="mb-2 text-center">
                                    <img src="<?= htmlspecialchars($note['image_path']) ?>" alt="Note Image" style="max-width: 100%; max-height: 180px; border-radius: 8px;">
                                </div>
                            <?php endif; ?>
                            <p class="note-body-text mb-2"><?= nl2br(htmlspecialchars($note['content'])) ?></p>
                        </div>
                    <?php elseif ($note['is_pin_locked']): ?>
                        <!-- Khung che bảo mật 4 số -->
                        <div class="locked-note-cover text-center p-3 my-2 rounded bg-light border border-warning-subtle shadow-sm" id="lockedCover_grid_<?= $note['note_id'] ?>">
                            <div class="mb-2 text-warning">
                                <i class="fas fa-shield-alt fa-2x"></i>
                            </div>
                            <div class="fw-bold text-dark small mb-1">Sổ đã khóa bảo mật 4 số</div>
                            <p class="text-muted small mb-2" style="font-size:0.75rem;">Nhập đúng mã bảo mật đã thiết lập để xem nội dung và tùy chỉnh sự kiện.</p>
                            <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 py-1 shadow-sm" onclick="openUnlockPinModal(<?= $note['note_id'] ?>, '<?= htmlspecialchars(addslashes($note['title'])) ?>')">
                                <i class="fas fa-key me-1"></i> Mở sổ
                            </button>
                        </div>
                        <!-- Nội dung ẩn, mở khi xác thực đúng mã PIN -->
                        <div class="unlocked-note-content" id="unlockedContent_grid_<?= $note['note_id'] ?>" style="display:none;">
                            <?php if (!empty($note['image_path'])): ?>
                                <div class="mb-2 text-center">
                                    <img src="<?= htmlspecialchars($note['image_path']) ?>" alt="Note Image" style="max-width: 100%; max-height: 180px; border-radius: 8px;">
                                </div>
                            <?php endif; ?>
                            <p class="note-body-text mb-2"><?= nl2br(htmlspecialchars($note['content'])) ?></p>
                        </div>
                    <?php else: ?>
                        <!-- Hiển thị nội dung đầy đủ rõ ràng -->
                        <?php if (!empty($note['image_path'])): ?>
                            <div class="mb-2 text-center">
                                <img src="<?= htmlspecialchars($note['image_path']) ?>" alt="Note Image" style="max-width: 100%; max-height: 180px; border-radius: 8px;">
                            </div>
                        <?php endif; ?>
                        <p class="note-body-text mb-2"><?= nl2br(htmlspecialchars($note['content'])) ?></p>
                    <?php endif; ?>

                    <?php if (!empty($note['labels'])): ?>
                        <div class="note-labels mb-2">
                            <?php foreach ($note['labels'] as $label): ?>
                                <span class="note-label"><?= htmlspecialchars($label) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="note-actions mt-3 pt-2 border-top d-flex align-items-center justify-content-end gap-1 flex-wrap">
                        <!-- NÚT THÊM VÀO THỜI KHÓA BIỂU (ICON-ONLY) -->
                        <button type="button" class="btn btn-action-icon btn-timetable" onclick="openAddToTimetableModal(<?= $note['note_id'] ?>, '<?= htmlspecialchars(addslashes($note['title'])) ?>', '<?= htmlspecialchars(addslashes(mb_substr(strip_tags($note['content']), 0, 120))) ?>')" title="Thêm sổ ghi chú vào Thời khóa biểu">
                            <i class="fas fa-calendar-plus"></i>
                        </button>

                        <!-- NÚT MỞ SỔ / XEM & TÙY CHỈNH (ICON-ONLY) -->
                        <?php if ($note['is_password_locked']): ?>
                            <button type="button" class="btn btn-action-icon btn-unlock" onclick="openUnlockPasswordModal(<?= $note['note_id'] ?>, '<?= htmlspecialchars(addslashes($note['title'])) ?>', 'notepass.php?id=<?= $note['note_id'] ?>')" title="Mở khóa mật khẩu ghi chú">
                                <i class="fas fa-unlock-alt text-primary"></i>
                            </button>
                        <?php elseif ($note['is_pin_locked']): ?>
                            <button type="button" class="btn btn-action-icon btn-unlock" onclick="openUnlockPinModal(<?= $note['note_id'] ?>, '<?= htmlspecialchars(addslashes($note['title'])) ?>', 'notepass.php?id=<?= $note['note_id'] ?>')" title="Mở khóa sổ (Nhập mã 4 số) & tùy chỉnh sự kiện">
                                <i class="fas fa-lock-open"></i>
                            </button>
                        <?php else: ?>
                            <a href="notepass.php?id=<?= urlencode($note['note_id']) ?>" class="btn btn-action-icon btn-view" title="Xem chi tiết & tùy chỉnh sự kiện">
                                <i class="fas fa-eye"></i>
                            </a>
                        <?php endif; ?>

                        <!-- NÚT CHỈNH SỬA (ICON-ONLY) -->
                        <a href="edit_note.php?id=<?= urlencode($note['note_id']) ?>" class="btn btn-action-icon btn-edit" title="Chỉnh sửa ghi chú">
                            <i class="fas fa-edit"></i>
                        </a>

                        <?php if ($note['ownership'] === 'own'): ?>
                            <!-- NÚT CHIA SẺ GHI CHÚ (ICON-ONLY) -->
                            <button type="button" class="btn btn-action-icon btn-share" onclick="openShareNoteModal(<?= $note['note_id'] ?>)" title="Chia sẻ ghi chú">
                                <i class="fas fa-share-alt"></i>
                            </button>

                            <!-- NÚT QUẢN LÝ MẬT KHẨU GHI CHÚ (PASSWORD) -->
                            <button type="button" class="btn btn-action-icon <?= $note['has_password'] ? 'btn-password-active text-primary' : '' ?>" onclick="openManagePasswordModal(<?= $note['note_id'] ?>, <?= $note['has_password'] ? 'true' : 'false' ?>)" title="<?= $note['has_password'] ? 'Mật khẩu bảo vệ: Đang bật (Bấm để đổi/tắt)' : 'Cài đặt mật khẩu bảo vệ' ?>">
                                <i class="fas fa-key"></i>
                            </button>
                        <?php endif; ?>

                        <!-- NÚT BẢO MẬT VỚI 4 SỐ (PIN) -->
                        <button type="button" class="btn btn-action-icon <?= $note['has_pin'] ? 'btn-pin-active' : 'btn-pin' ?>" onclick="openManagePinModal(<?= $note['note_id'] ?>, <?= $note['has_pin'] ? 'true' : 'false' ?>, '<?= htmlspecialchars(addslashes($note['title'])) ?>')" title="<?= $note['has_pin'] ? 'Bảo mật 4 số: Đang bảo vệ (Bấm để đổi/gỡ)' : 'Cài đặt mã bảo mật 4 số' ?>">
                            <i class="fas fa-shield-alt"></i>
                        </button>

                        <!-- NÚT GHIM (ICON-ONLY) -->
                        <button type="button" class="btn btn-action-icon btn-pin-toggle <?= $note['pinned'] ? 'active' : '' ?>" onclick="togglePin(<?= $note['note_id'] ?>)" title="<?= $note['pinned'] ? 'Bỏ ghim' : 'Ghim sổ lên đầu' ?>">
                            <i class="fas fa-thumbtack"></i>
                        </button>

                        <!-- NÚT LƯU TRỮ (ICON-ONLY) -->
                        <button type="button" class="btn btn-action-icon btn-archive" onclick="archiveNote(<?= $note['note_id'] ?>)" title="Lưu trữ sổ">
                            <i class="fas fa-archive"></i>
                        </button>

                        <!-- NÚT XÓA (ICON-ONLY) -->
                        <button type="button" class="btn btn-action-icon btn-delete" onclick="confirmDelete(<?= $note['note_id'] ?>)" title="Xóa sổ ghi chú">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="text-center mt-4 col-12">
                <div class="p-5 border rounded-3 bg-white shadow-sm">
                    <i class="fas fa-sticky-note fa-3x text-muted mb-3"></i>
                    <h5>Bạn chưa có ghi chú nào.</h5>
                    <p class="text-muted">Nhấn nút "+" bên dưới hoặc nút "Thêm ghi chú" để tạo ghi chú đầu tiên!</p>
                    <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#modalAddNote">
                        <i class="fas fa-plus me-1"></i> Tạo sổ ghi chú mới
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Shared Notes Grid View -->
    <div id="sharedNotesGrid" class="container note-grid">
        <h3><?= htmlspecialchars($t['shared_notes']) ?></h3>
        <?php if (!empty($shared_notes)): ?>
            <?php foreach ($shared_notes as $note): ?>
                <div class="note-item position-relative" data-labels="<?= htmlspecialchars(implode(',', $note['labels'])) ?>">
                    <?php if ($note['pinned']): ?>
                        <span class="pin-icon" title="Ghi chú đã ghim"><i class="fas fa-thumbtack text-warning"></i></span>
                    <?php endif; ?>
                    <span class="share-icon" title="Ghi chú được chia sẻ"><i class="fas fa-share"></i></span>
                    <h5 class="mb-2"><?= htmlspecialchars($note['title']) ?></h5>
                    <?php if ($note['is_pin_locked']): ?>
                        <div class="locked-note-cover text-center p-3 my-2 rounded bg-light border border-warning-subtle shadow-sm" id="lockedCover_grid_<?= $note['note_id'] ?>">
                            <div class="mb-2 text-warning">
                                <i class="fas fa-shield-alt fa-2x"></i>
                            </div>
                            <div class="fw-bold text-dark small mb-1">Sổ đã khóa bảo mật 4 số</div>
                            <p class="text-muted small mb-2" style="font-size:0.75rem;">Chủ sổ đã đặt mã bảo mật. Nhập đúng mã 4 số để xem nội dung.</p>
                            <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 py-1 shadow-sm" onclick="openUnlockPinModal(<?= $note['note_id'] ?>, '<?= htmlspecialchars(addslashes($note['title'])) ?>')">
                                <i class="fas fa-key me-1"></i> Mở sổ
                            </button>
                        </div>
                        <div class="unlocked-note-content" id="unlockedContent_grid_<?= $note['note_id'] ?>" style="display:none;">
                            <?php if (!empty($note['image_path'])): ?>
                                <div class="mb-2 text-center">
                                    <img src="<?= htmlspecialchars($note['image_path']) ?>" alt="Note Image" style="max-width: 100%; max-height: 200px; border-radius: 8px;">
                                </div>
                            <?php endif; ?>
                            <p><?= nl2br(htmlspecialchars($note['content'])) ?></p>
                        </div>
                    <?php else: ?>
                        <?php if (!empty($note['image_path'])): ?>
                            <div class="mb-2 text-center">
                                <img src="<?= htmlspecialchars($note['image_path']) ?>" alt="Note Image" style="max-width: 100%; max-height: 200px; border-radius: 8px;">
                            </div>
                        <?php endif; ?>
                        <p><?= nl2br(htmlspecialchars($note['content'])) ?></p>
                    <?php endif; ?>
                    <p class="text-muted small">
                        Chia sẻ bởi: <strong><?= htmlspecialchars($note['shared_by_username']) ?></strong> | 
                        Quyền: 
                        <?php if ($note['permission'] == 'write'): ?>
                            <span class="badge bg-success text-white"><i class="fas fa-edit me-1"></i>Editor (Chỉnh sửa)</span>
                        <?php else: ?>
                            <span class="badge bg-primary text-white"><i class="fas fa-eye me-1"></i>Viewer (Chỉ xem)</span>
                        <?php endif; ?>
                    </p>
                    <?php if (!empty($note['labels'])): ?>
                        <div class="note-labels mb-2">
                            <?php foreach ($note['labels'] as $label): ?>
                                <span class="note-label"><?= htmlspecialchars($label) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div class="note-actions mt-2 d-flex gap-2 flex-wrap">
                        <?php if ($note['permission'] == 'write'): ?>
                            <a href="edit_note.php?id=<?= urlencode($note['note_id']) ?>" class="btn btn-sm btn-outline-success">
                                <i class="fas fa-edit me-1"></i> Chỉnh sửa
                            </a>
                        <?php endif; ?>
                        <a href="notepass.php?id=<?= urlencode($note['note_id']) ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-eye me-1"></i> <?= $note['permission'] == 'write' ? 'Chi tiết' : 'Xem (Chỉ đọc)' ?>
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="leaveSharedNote(<?= (int)$note['note_id'] ?>)" title="Rời khỏi danh sách chia sẻ">
                            <i class="fas fa-sign-out-alt"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="text-center mt-4">
                <p class="text-muted">Không có ghi chú nào được chia sẻ với bạn.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Own Notes List View -->
    <ul id="ownNotesList" class="note-list" style="display:none;">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h3 class="mb-0 fw-bold"><i class="fas fa-list text-primary me-2"></i><?= htmlspecialchars($t['my_notes']) ?></h3>
            <span class="badge bg-dark rounded-pill px-3 py-2"><?= count($own_notes) ?> sổ ghi chú</span>
        </div>
        <?php if (!empty($own_notes)): ?>
            <?php foreach ($own_notes as $note): ?>
                <li class="note-item flex-column align-items-stretch note-card-<?= $note['note_id'] ?>" data-note-id="<?= $note['note_id'] ?>" data-labels="<?= htmlspecialchars(implode(',', $note['labels'])) ?>">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <h5 class="mb-0 fw-bold"><?= htmlspecialchars($note['title']) ?></h5>
                            <?php if ($note['pinned']): ?>
                                <span class="badge bg-warning text-dark"><i class="fas fa-thumbtack me-1"></i>Ghim</span>
                            <?php endif; ?>
                            <?php if ($note['has_pin']): ?>
                                <?php if ($note['is_pin_locked']): ?>
                                    <span class="badge bg-danger pin-badge-<?= $note['note_id'] ?>"><i class="fas fa-lock me-1"></i>Bảo mật 4 số</span>
                                <?php else: ?>
                                    <span class="badge bg-success pin-badge-<?= $note['note_id'] ?>"><i class="fas fa-lock-open me-1"></i>Đã mở khóa</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <small class="text-muted"><i class="far fa-clock me-1"></i><?= date('d/m/Y H:i', strtotime($note['created_at'])) ?></small>
                    </div>

                    <?php if (!empty($note['reminder_at'])): ?>
                        <div class="mb-2">
                            <span class="badge bg-warning-subtle text-dark border border-warning px-2 py-1">
                                <i class="fas fa-bell text-warning me-1"></i>Sự kiện: <?= date('H:i d/m/Y', strtotime($note['reminder_at'])) ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <?php if ($note['is_password_locked']): ?>
                        <div class="locked-note-cover p-3 rounded bg-light border border-info-subtle text-center my-2" id="pwdLockedCover_list_<?= $note['note_id'] ?>">
                            <span class="text-primary me-2"><i class="fas fa-lock"></i></span>
                            <span class="text-dark small fw-semibold">Ghi chú đã bảo mật bằng mật khẩu.</span>
                            <button type="button" class="btn btn-sm btn-primary rounded-pill ms-2 px-3" onclick="openUnlockPasswordModal(<?= $note['note_id'] ?>, '<?= htmlspecialchars(addslashes($note['title'])) ?>')">
                                <i class="fas fa-key me-1"></i> Nhập mật khẩu
                            </button>
                        </div>
                        <div class="unlocked-pwd-content" id="pwdUnlockedContent_list_<?= $note['note_id'] ?>" style="display:none;">
                            <?php if (!empty($note['image_path'])): ?>
                                <div class="mb-2"><img src="<?= htmlspecialchars($note['image_path']) ?>" alt="Note Image" style="max-width: 150px; border-radius: 6px;"></div>
                            <?php endif; ?>
                            <p class="mb-2"><?= nl2br(htmlspecialchars($note['content'])) ?></p>
                        </div>
                    <?php elseif ($note['is_pin_locked']): ?>
                        <div class="locked-note-cover p-3 rounded bg-light border border-warning-subtle text-center my-2" id="lockedCover_list_<?= $note['note_id'] ?>">
                            <span class="text-warning me-2"><i class="fas fa-lock"></i></span>
                            <span class="text-dark small fw-semibold">Sổ đã khóa bảo mật bằng 4 số.</span>
                            <button type="button" class="btn btn-sm btn-primary rounded-pill ms-2 px-3" onclick="openUnlockPinModal(<?= $note['note_id'] ?>, '<?= htmlspecialchars(addslashes($note['title'])) ?>')">
                                <i class="fas fa-key me-1"></i> Mở sổ
                            </button>
                        </div>
                        <div class="unlocked-note-content" id="unlockedContent_list_<?= $note['note_id'] ?>" style="display:none;">
                            <?php if (!empty($note['image_path'])): ?>
                                <div class="mb-2"><img src="<?= htmlspecialchars($note['image_path']) ?>" alt="Note Image" style="max-width: 150px; border-radius: 6px;"></div>
                            <?php endif; ?>
                            <p class="mb-2"><?= nl2br(htmlspecialchars($note['content'])) ?></p>
                        </div>
                    <?php else: ?>
                        <?php if (!empty($note['image_path'])): ?>
                            <div class="mb-2"><img src="<?= htmlspecialchars($note['image_path']) ?>" alt="Note Image" style="max-width: 150px; border-radius: 6px;"></div>
                        <?php endif; ?>
                        <p class="mb-2"><?= nl2br(htmlspecialchars($note['content'])) ?></p>
                    <?php endif; ?>

                    <?php if (!empty($note['labels'])): ?>
                        <div class="note-labels mb-2">
                            <?php foreach ($note['labels'] as $label): ?>
                                <span class="note-label"><?= htmlspecialchars($label) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="note-actions border-top pt-2 mt-2 d-flex align-items-center justify-content-end gap-1 flex-wrap">
                        <!-- NÚT THÊM VÀO THỜI KHÓA BIỂU (ICON-ONLY) -->
                        <button type="button" class="btn btn-action-icon btn-timetable" onclick="openAddToTimetableModal(<?= $note['note_id'] ?>, '<?= htmlspecialchars(addslashes($note['title'])) ?>', '<?= htmlspecialchars(addslashes(mb_substr(strip_tags($note['content']), 0, 120))) ?>')" title="Thêm sổ ghi chú vào Thời khóa biểu">
                            <i class="fas fa-calendar-plus"></i>
                        </button>

                        <!-- NÚT MỞ SỔ / XEM & TÙY CHỈNH (ICON-ONLY) -->
                        <?php if ($note['is_password_locked']): ?>
                            <button type="button" class="btn btn-action-icon btn-unlock" onclick="openUnlockPasswordModal(<?= $note['note_id'] ?>, '<?= htmlspecialchars(addslashes($note['title'])) ?>', 'notepass.php?id=<?= $note['note_id'] ?>')" title="Mở khóa mật khẩu ghi chú">
                                <i class="fas fa-unlock-alt text-primary"></i>
                            </button>
                        <?php elseif ($note['is_pin_locked']): ?>
                            <button type="button" class="btn btn-action-icon btn-unlock" onclick="openUnlockPinModal(<?= $note['note_id'] ?>, '<?= htmlspecialchars(addslashes($note['title'])) ?>', 'notepass.php?id=<?= $note['note_id'] ?>')" title="Mở khóa sổ (Nhập mã 4 số) & tùy chỉnh sự kiện">
                                <i class="fas fa-lock-open"></i>
                            </button>
                        <?php else: ?>
                            <a href="notepass.php?id=<?= urlencode($note['note_id']) ?>" class="btn btn-action-icon btn-view" title="Xem chi tiết & tùy chỉnh sự kiện">
                                <i class="fas fa-eye"></i>
                            </a>
                        <?php endif; ?>

                        <!-- NÚT CHỈNH SỬA (ICON-ONLY) -->
                        <a href="edit_note.php?id=<?= urlencode($note['note_id']) ?>" class="btn btn-action-icon btn-edit" title="Chỉnh sửa ghi chú">
                            <i class="fas fa-edit"></i>
                        </a>

                        <?php if ($note['ownership'] === 'own'): ?>
                            <!-- NÚT CHIA SẺ GHI CHÚ (ICON-ONLY) -->
                            <button type="button" class="btn btn-action-icon btn-share" onclick="openShareNoteModal(<?= $note['note_id'] ?>)" title="Chia sẻ ghi chú">
                                <i class="fas fa-share-alt"></i>
                            </button>

                            <!-- NÚT QUẢN LÝ MẬT KHẨU GHI CHÚ (PASSWORD) -->
                            <button type="button" class="btn btn-action-icon <?= $note['has_password'] ? 'btn-password-active text-primary' : '' ?>" onclick="openManagePasswordModal(<?= $note['note_id'] ?>, <?= $note['has_password'] ? 'true' : 'false' ?>)" title="<?= $note['has_password'] ? 'Mật khẩu bảo vệ: Đang bật (Bấm để đổi/tắt)' : 'Cài đặt mật khẩu bảo vệ' ?>">
                                <i class="fas fa-key"></i>
                            </button>
                        <?php endif; ?>

                        <!-- NÚT BẢO MẬT VỚI 4 SỐ (PIN) -->
                        <button type="button" class="btn btn-action-icon <?= $note['has_pin'] ? 'btn-pin-active' : 'btn-pin' ?>" onclick="openManagePinModal(<?= $note['note_id'] ?>, <?= $note['has_pin'] ? 'true' : 'false' ?>, '<?= htmlspecialchars(addslashes($note['title'])) ?>')" title="<?= $note['has_pin'] ? 'Bảo mật 4 số: Đang bảo vệ (Bấm để đổi/gỡ)' : 'Cài đặt mã bảo mật 4 số' ?>">
                            <i class="fas fa-shield-alt"></i>
                        </button>

                        <!-- NÚT GHIM (ICON-ONLY) -->
                        <button type="button" class="btn btn-action-icon btn-pin-toggle <?= $note['pinned'] ? 'active' : '' ?>" onclick="togglePin(<?= $note['note_id'] ?>)" title="<?= $note['pinned'] ? 'Bỏ ghim' : 'Ghim sổ lên đầu' ?>">
                            <i class="fas fa-thumbtack"></i>
                        </button>

                        <!-- NÚT LƯU TRỮ (ICON-ONLY) -->
                        <button type="button" class="btn btn-action-icon btn-archive" onclick="archiveNote(<?= $note['note_id'] ?>)" title="Lưu trữ sổ">
                            <i class="fas fa-archive"></i>
                        </button>

                        <!-- NÚT XÓA (ICON-ONLY) -->
                        <button type="button" class="btn btn-action-icon btn-delete" onclick="confirmDelete(<?= $note['note_id'] ?>)" title="Xóa sổ ghi chú">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </li>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="text-center mt-4">
                <p class="text-muted">Bạn chưa có ghi chú nào.</p>
            </div>
        <?php endif; ?>
    </ul>

    <!-- Shared Notes List View -->
    <ul id="sharedNotesList" class="note-list" style="display:none;">
        <h3>Được chia sẻ với tôi</h3>
        <?php if (!empty($shared_notes)): ?>
            <?php foreach ($shared_notes as $note): ?>
                <li class="note-item" data-labels="<?= htmlspecialchars(implode(',', $note['labels'])) ?>">
                    <div class="content">
                        <?php if ($note['pinned']): ?>
                            <span class="pin-icon" title="Ghi chú đã ghim"><i class="fas fa-thumbtack"></i></span>
                        <?php endif; ?>
                        <?php if ($note['password_hash']): ?>
                            <span class="lock-icon" title="Ghi chú được bảo vệ bằng mật khẩu"><i class="fas fa-lock"></i></span>
                        <?php endif; ?>
                        <span class="share-icon" title="Ghi chú được chia sẻ"><i class="fas fa-share"></i></span>
                        <h5><?= htmlspecialchars($note['title']) ?></h5>
                        <?php if ($note['is_pin_locked']): ?>
                            <div class="locked-note-cover text-center p-3 my-2 rounded bg-light border border-warning-subtle shadow-sm" id="lockedCover_list_<?= $note['note_id'] ?>">
                                <div class="mb-2 text-warning">
                                    <i class="fas fa-shield-alt fa-2x"></i>
                                </div>
                                <div class="fw-bold text-dark small mb-1">Sổ đã khóa bảo mật 4 số</div>
                                <p class="text-muted small mb-2" style="font-size:0.75rem;">Chủ sổ đã đặt mã bảo mật. Nhập đúng mã 4 số để xem nội dung.</p>
                                <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 py-1 shadow-sm" onclick="openUnlockPinModal(<?= $note['note_id'] ?>, '<?= htmlspecialchars(addslashes($note['title'])) ?>')">
                                    <i class="fas fa-key me-1"></i> Mở sổ
                                </button>
                            </div>
                            <div class="unlocked-note-content" id="unlockedContent_list_<?= $note['note_id'] ?>" style="display:none;">
                                <?php if (!empty($note['image_path'])): ?>
                                    <div class="mb-2">
                                        <img src="<?= htmlspecialchars($note['image_path']) ?>" alt="Note Image" style="max-width: 150px; max-height: 150px; border-radius: 8px;">
                                    </div>
                                <?php endif; ?>
                                <p><?= nl2br(htmlspecialchars($note['content'])) ?></p>
                            </div>
                        <?php else: ?>
                            <?php if (!empty($note['image_path'])): ?>
                                <div class="mb-2">
                                    <img src="<?= htmlspecialchars($note['image_path']) ?>" alt="Note Image" style="max-width: 150px; max-height: 150px; border-radius: 8px;">
                                </div>
                            <?php endif; ?>
                            <p><?= nl2br(htmlspecialchars($note['content'])) ?></p>
                        <?php endif; ?>
                        <p class="text-muted small">
                            Chia sẻ bởi: <strong><?= htmlspecialchars($note['shared_by_username']) ?></strong> | 
                            Quyền: 
                            <?php if ($note['permission'] == 'write'): ?>
                                <span class="badge bg-success text-white"><i class="fas fa-edit me-1"></i>Editor (Chỉnh sửa)</span>
                            <?php else: ?>
                                <span class="badge bg-primary text-white"><i class="fas fa-eye me-1"></i>Viewer (Chỉ xem)</span>
                            <?php endif; ?>
                            | Thời gian: <?= htmlspecialchars($note['shared_at']) ?>
                        </p>
                        <?php if (!empty($note['labels'])): ?>
                            <div class="note-labels mb-2">
                                <?php foreach ($note['labels'] as $label): ?>
                                    <span class="note-label"><?= htmlspecialchars($label) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($note['pinned']): ?>
                            <span class="badge bg-warning text-dark">Ghim</span>
                        <?php endif; ?>
                        <div class="note-actions mt-2 d-flex gap-2 flex-wrap">
                            <?php if ($note['permission'] == 'write'): ?>
                                <a href="edit_note.php?id=<?= urlencode($note['note_id']) ?>" class="btn btn-sm btn-outline-success">
                                    <i class="fas fa-edit me-1"></i> Chỉnh sửa
                                </a>
                            <?php endif; ?>
                            <a href="notepass.php?id=<?= urlencode($note['note_id']) ?>" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-eye me-1"></i> <?= $note['permission'] == 'write' ? 'Chi tiết' : 'Xem (Chỉ đọc)' ?>
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="leaveSharedNote(<?= (int)$note['note_id'] ?>)" title="Rời khỏi danh sách chia sẻ">
                                <i class="fas fa-sign-out-alt me-1"></i> Rời khỏi
                            </button>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="text-center mt-4">
                <p class="text-muted">Không có ghi chú nào được chia sẻ với bạn.</p>
            </div>
        <?php endif; ?>
    </ul>

    <!-- Archived Notes Section -->
    <div id="archivedNotesSection" class="container mt-4">
        <h3><i class="fas fa-archive me-2 text-secondary"></i>Đã lưu trữ <span class="badge bg-secondary"><?= count($archived_notes) ?></span></h3>
        <?php if (!empty($archived_notes)): ?>
            <div class="row">
                <?php foreach ($archived_notes as $note): ?>
                    <div class="col-12 col-md-6 col-lg-4 mb-3">
                        <div class="card shadow-sm h-100" style="opacity:.8">
                            <div class="card-body">
                                <h5 class="card-title text-muted"><i class="fas fa-archive me-2"></i><?= htmlspecialchars($note['title']) ?></h5>
                                <p class="card-text text-muted small"><?= htmlspecialchars(mb_strimwidth($note['content'], 0, 120, '...')) ?></p>
                                <p class="text-muted small mb-0">Cập nhật gần nhất: <?= htmlspecialchars($note['updated_at']) ?></p>
                            </div>
                            <div class="card-footer bg-white d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-success" onclick="restoreNote(<?= (int)$note['note_id'] ?>)">
                                    <i class="fas fa-undo me-1"></i> Khôi phục
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDelete(<?= (int)$note['note_id'] ?>)">
                                    <i class="fas fa-trash-alt me-1"></i> Xóa vĩnh viễn
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-muted">Không có ghi chú nào trong mục lưu trữ.</p>
        <?php endif; ?>
    </div>

    <!-- Manage Labels Modal -->
    <div class="modal fade" id="manageLabelsModal" tabindex="-1" aria-labelledby="manageLabelsModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="manageLabelsModalLabel">Quản lý nhãn</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <div class="input-group mb-3">
                        <input type="text" id="newLabelInput" class="form-control" placeholder="Nhập tên nhãn mới...">
                        <button class="btn btn-primary" type="button" id="btnAddNewLabel">
                            <i class="fas fa-plus me-1"></i>Thêm nhãn
                        </button>
                    </div>
                    <h6 class="fw-bold text-muted small mb-2 text-uppercase">Danh sách nhãn hiện có:</h6>
                    <div id="manageLabelsList">
                        <p class="text-muted text-center mb-0">Đang tải...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search Modal -->
    <div class="modal fade no-backdrop search-modal" id="searchModal" tabindex="-1" aria-labelledby="searchModalLabel" aria-hidden="true" data-bs-backdrop="false">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="searchModalLabel">Tìm kiếm ghi chú</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <form id="searchForm">
                        <div class="mb-3">
                            <label for="searchTitle" class="form-label">Tiêu đề</label>
                            <input type="text" name="searchTitle" id="searchTitle" class="form-control" placeholder="Nhập tiêu đề...">
                        </div>
                        <div class="mb-3">
                            <label for="searchContent" class="form-label">Nội dung</label>
                            <input type="text" name="searchContent" id="searchContent" class="form-control" placeholder="Nhập nội dung...">
                        </div>
                        <div class="mb-3">
                            <label for="searchLabels" class="form-label">Nhãn</label>
                            <input type="text" name="searchLabels" id="searchLabels" class="form-control" placeholder="Nhập nhãn, cách nhau bằng dấu phẩy">
                        </div>
                        <div class="form-check mb-3">
                            <input type="checkbox" name="searchPinned" id="searchPinned" class="form-check-input">
                            <label for="searchPinned" class="form-check-label">Chỉ tìm ghi chú được ghim</label>
                        </div>
                    </form>
                    <div id="searchResults"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Manage Password Modal -->
    <div class="modal fade" id="managePasswordModal" tabindex="-1" aria-labelledby="managePasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="managePasswordModalLabel">
                        <i class="fas fa-lock me-2"></i>Bảo mật ghi chú
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <!-- Status badge -->
                    <div id="pwdStatusBadge" class="mb-3"></div>
                    <form id="managePasswordForm">
                        <input type="hidden" name="note_id" id="managePasswordNoteId">
                        <!-- Current password section (required when changing or removing password) -->
                        <div id="pwdCurrentSection" style="display:none;" class="mb-3">
                            <label for="current_password" class="form-label fw-semibold">
                                Mật khẩu hiện tại <span class="text-danger">*</span>
                            </label>
                            <input type="password" name="current_password" id="current_password"
                                   class="form-control" placeholder="Nhập mật khẩu hiện tại (hoặc mật khẩu tài khoản)">
                            <div class="form-text text-muted" style="font-size:0.75rem;">Cần nhập để xác thực quyền trước khi thay đổi hoặc gỡ bỏ mật khẩu.</div>
                        </div>

                        <!-- New password section -->
                        <div id="pwdNewSection">
                            <div class="mb-3">
                                <label for="new_password" class="form-label fw-semibold">
                                    <span id="pwdNewLabel">Đặt mật khẩu mới</span>
                                </label>
                                <input type="password" name="new_password" id="new_password"
                                       class="form-control" placeholder="Ít nhất 4 ký tự" minlength="4">
                            </div>
                            <div class="mb-3" id="pwdConfirmSection">
                                <label for="confirm_password" class="form-label fw-semibold">
                                    Xác nhận mật khẩu mới
                                </label>
                                <input type="password" name="confirm_password" id="confirm_password"
                                       class="form-control" placeholder="Nhập lại mật khẩu mới" minlength="4">
                            </div>
                        </div>

                        <!-- Remove password section (only when note has password) -->
                        <div id="pwdRemoveSection" style="display:none;" class="mb-3 p-2 bg-light rounded border border-danger-subtle">
                            <div class="form-check">
                                <input type="checkbox" name="remove_password" id="remove_password" class="form-check-input">
                                <label for="remove_password" class="form-check-label text-danger fw-semibold">
                                    <i class="fas fa-lock-open me-1"></i>Tắt / Gỡ mật khẩu bảo vệ
                                </label>
                            </div>
                            <div class="small text-muted mt-1" style="font-size:0.75rem;">Khi chọn mục này, ghi chú sẽ được mở khóa tự do cho tất cả người có quyền truy cập.</div>
                        </div>

                        <div class="d-flex gap-2 justify-content-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Huỷ</button>
                            <button type="submit" class="btn btn-primary" id="pwdSubmitBtn">
                                <i class="fas fa-save me-1"></i>Lưu bảo mật
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Mở khóa Mật khẩu Ghi chú (Password Unlock Modal) -->
    <div class="modal fade" id="unlockPasswordModal" tabindex="-1" aria-labelledby="unlockPasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
                <div class="modal-header border-0 pb-0 justify-content-end">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body text-center px-4 pt-0 pb-4">
                    <div class="mb-3 text-primary">
                        <i class="fas fa-key fa-3x"></i>
                    </div>
                    <h5 class="fw-bold mb-1" id="unlockPasswordTitle">Ghi chú được bảo vệ</h5>
                    <p class="text-muted small mb-3">Vui lòng nhập mật khẩu ghi chú để xem nội dung.</p>
                    <div id="unlockPasswordError" class="alert alert-danger py-2 small d-none"></div>
                    <form id="unlockPasswordForm">
                        <input type="hidden" id="unlockPasswordNoteId">
                        <input type="hidden" id="unlockPasswordRedirectUrl">
                        <div class="mb-3">
                            <input type="password" id="unlockPasswordInput" class="form-control py-2 text-center"
                                   placeholder="Nhập mật khẩu ghi chú" required autocomplete="off">
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary py-2 fw-semibold" id="unlockPasswordSubmitBtn">
                                <i class="fas fa-unlock me-1"></i> Mở khóa ngay
                            </button>
                            <button type="button" class="btn btn-light py-2 text-muted" data-bs-dismiss="modal">
                                Hủy bỏ
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Share Note Modal -->
    <div class="modal fade" id="shareNoteModal" tabindex="-1" aria-labelledby="shareNoteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="shareNoteModalLabel"><i class="fas fa-share-alt me-2"></i>Chia sẻ ghi chú</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="shareNoteForm">
                        <input type="hidden" name="note_id" id="shareNoteId">
                        <div class="mb-3">
                            <label for="sharedWithUsername" class="form-label fw-semibold">Email hoặc Tên người dùng</label>
                            <input type="text" name="sharedWithUsername" id="sharedWithUsername" class="form-control form-control-lg" placeholder="Nhập email (abc@gmail.com) hoặc username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold d-block">Quyền truy cập</label>
                            <div class="d-flex gap-4 p-2 bg-light rounded border">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="permission" id="permViewer" value="read" checked>
                                    <label class="form-check-label fw-medium" for="permViewer">
                                        <i class="fas fa-eye text-primary me-1"></i> Viewer (Chỉ xem)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="permission" id="permEditor" value="write">
                                    <label class="form-check-label fw-medium" for="permEditor">
                                        <i class="fas fa-edit text-success me-1"></i> Editor (Chỉnh sửa)
                                    </label>
                                </div>
                            </div>
                            <div class="form-text text-muted">
                                <strong>Viewer:</strong> Xem nội dung, xem ảnh, nhãn. Không sửa, không xóa.<br>
                                <strong>Editor:</strong> Xem, sửa, auto-save. Không được xóa ghi chú của bạn.
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold" id="btnShareSubmit">
                            <i class="fas fa-paper-plane me-1"></i> Chia sẻ ngay
                        </button>
                    </form>

                    <!-- Danh sách người đang được chia sẻ -->
                    <div class="mt-4 pt-3 border-top" id="collaboratorsSection">
                        <h6 class="fw-bold mb-2 text-secondary"><i class="fas fa-users me-1"></i> Đang được chia sẻ với:</h6>
                        <div id="collaboratorsList" class="small">
                            <span class="text-muted">Chưa có người nào.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Unlock PIN Modal (Mở khóa bảo mật 4 số để xem nội dung và tùy chỉnh sự kiện) -->
    <div class="modal fade" id="unlockPinModal" tabindex="-1" aria-labelledby="unlockPinModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
                <div class="modal-header border-bottom bg-light" style="border-radius: 16px 16px 0 0;">
                    <h5 class="modal-title fw-bold text-dark" id="unlockPinModalLabel">
                        <i class="fas fa-shield-alt text-warning me-2"></i>Mở khóa sổ ghi chú
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <form id="unlockPinForm">
                    <div class="modal-body p-4 text-center">
                        <input type="hidden" id="unlockPinNoteId" name="note_id">
                        <input type="hidden" id="unlockPinRedirectUrl" value="">
                        <div class="mb-3">
                            <span class="d-inline-flex align-items-center justify-content-center p-3 rounded-circle bg-warning-subtle text-warning">
                                <i class="fas fa-lock fa-2x"></i>
                            </span>
                        </div>
                        <h6 class="fw-bold mb-1 text-dark" id="unlockPinNoteTitle">Sổ ghi chú</h6>
                        <p class="text-muted small mb-3">Nhập đúng mã bảo mật 4 số đã thiết lập trước đó để xem nội dung và tùy chỉnh sự kiện của sổ:</p>
                        <div class="mb-3 d-flex justify-content-center">
                            <input type="password" id="unlockPinInput" name="pin" class="form-control text-center fw-bold fs-3 shadow-sm"
                                   style="width: 200px; letter-spacing: 12px; border-radius: 10px;"
                                   maxlength="4" minlength="4" pattern="\d{4}" inputmode="numeric" required placeholder="••••" autofocus autocomplete="off">
                        </div>
                        <div id="unlockPinError" class="alert alert-danger small py-2 d-none"></div>
                    </div>
                    <div class="modal-footer border-top px-4 py-3 d-flex justify-content-between">
                        <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4" id="unlockPinSubmitBtn">
                            <i class="fas fa-unlock me-1"></i> Mở sổ ngay
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Manage PIN Modal (Bảo mật sổ với 4 số: Đặt mã, Đổi mã, Gỡ mã) -->
    <div class="modal fade" id="managePinModal" tabindex="-1" aria-labelledby="managePinModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
                <div class="modal-header border-bottom bg-light" style="border-radius: 16px 16px 0 0;">
                    <h5 class="modal-title fw-bold text-dark" id="managePinModalLabel">
                        <i class="fas fa-shield-alt text-primary me-2"></i>Bảo mật sổ ghi chú (4 số)
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <form id="managePinForm">
                    <div class="modal-body p-4">
                        <input type="hidden" id="managePinNoteId" name="note_id">
                        <div id="managePinStatusAlert" class="alert alert-info small py-2 mb-3"></div>
                        <h6 class="fw-bold mb-3 text-dark text-truncate" id="managePinNoteTitle">Sổ ghi chú</h6>

                        <!-- Nhập mã cũ hoặc pass khi sổ đã được đặt PIN trước đó -->
                        <div id="managePinOldSection" style="display:none;" class="mb-3">
                            <label for="manageOldPin" class="form-label small fw-semibold">Mã bảo mật 4 số hiện tại (hoặc mật khẩu đăng nhập):</label>
                            <input type="password" id="manageOldPin" name="old_pin" class="form-control fw-bold" placeholder="Nhập 4 số hiện tại hoặc mật khẩu">
                        </div>

                        <!-- Nhập mã 4 số mới -->
                        <div id="managePinNewSection">
                            <div class="mb-3">
                                <label for="manageNewPin" class="form-label small fw-semibold" id="manageNewPinLabel">Mã bảo mật 4 số mới:</label>
                                <input type="password" id="manageNewPin" name="pin" class="form-control text-center fw-bold fs-4"
                                       style="letter-spacing: 8px; max-width: 200px;" maxlength="4" minlength="4" pattern="\d{4}" inputmode="numeric" placeholder="••••" required autocomplete="off">
                            </div>
                            <div class="mb-3">
                                <label for="manageConfirmPin" class="form-label small fw-semibold">Xác nhận lại mã bảo mật (4 số):</label>
                                <input type="password" id="manageConfirmPin" name="pin_confirm" class="form-control text-center fw-bold fs-4"
                                       style="letter-spacing: 8px; max-width: 200px;" maxlength="4" minlength="4" pattern="\d{4}" inputmode="numeric" placeholder="••••" required autocomplete="off">
                            </div>
                        </div>

                        <!-- Nút gỡ bảo mật -->
                        <div id="managePinRemoveSection" style="display:none;" class="mt-3 pt-3 border-top">
                            <button type="button" class="btn btn-sm btn-outline-danger w-100 rounded-pill" id="manageRemovePinBtn">
                                <i class="fas fa-trash-alt me-1"></i> Gỡ bỏ bảo mật khỏi sổ này
                            </button>
                        </div>
                    </div>
                    <div class="modal-footer border-top px-4 py-3 d-flex justify-content-between">
                        <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Đóng</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4" id="managePinSubmitBtn">
                            <i class="fas fa-save me-1"></i> Lưu bảo mật
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Thêm sổ ghi chú vào Thời khóa biểu -->
    <div class="modal fade" id="modalAddToTimetable" tabindex="-1" aria-labelledby="modalAddToTimetableLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 480px;">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
                <div class="modal-header border-bottom bg-light" style="border-radius: 16px 16px 0 0;">
                    <h5 class="modal-title fw-bold text-dark" id="modalAddToTimetableLabel">
                        <i class="fas fa-calendar-plus text-success me-2"></i>Thêm vào Thời Khóa Biểu
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <form id="formAddToTimetable">
                    <div class="modal-body p-4">
                        <input type="hidden" id="tt_note_id" name="note_id">
                        
                        <div class="mb-3">
                            <label for="tt_title" class="form-label small fw-bold">Tên môn học / Nhiệm vụ:</label>
                            <input type="text" id="tt_title" name="title" class="form-control fw-semibold" required placeholder="Ví dụ: Lập trình Web, Họp đồ án...">
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-7">
                                <label for="tt_day_of_week" class="form-label small fw-bold">Lặp lại theo Thứ:</label>
                                <select id="tt_day_of_week" name="day_of_week" class="form-select">
                                    <option value="1">Thứ 2 (Thứ Hai)</option>
                                    <option value="2">Thứ 3 (Thứ Ba)</option>
                                    <option value="3">Thứ 4 (Thứ Tư)</option>
                                    <option value="4">Thứ 5 (Thứ Năm)</option>
                                    <option value="5">Thứ 6 (Thứ Sáu)</option>
                                    <option value="6">Thứ 7 (Thứ Bảy)</option>
                                    <option value="0">Chủ Nhật</option>
                                </select>
                            </div>
                            <div class="col-5">
                                <label for="tt_color" class="form-label small fw-bold">Màu thẻ biểu:</label>
                                <input type="color" id="tt_color" name="color" class="form-control form-control-color w-100" value="#10b981">
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label for="tt_start_time" class="form-label small fw-bold">Giờ bắt đầu:</label>
                                <input type="time" id="tt_start_time" name="start_time" class="form-control fw-semibold" required value="08:00">
                            </div>
                            <div class="col-6">
                                <label for="tt_end_time" class="form-label small fw-bold">Giờ kết thúc:</label>
                                <input type="time" id="tt_end_time" name="end_time" class="form-control fw-semibold" required value="09:30">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="tt_location" class="form-label small fw-bold">Địa điểm / Phòng học (tùy chọn):</label>
                            <input type="text" id="tt_location" name="location" class="form-control" placeholder="Ví dụ: Phòng B204 hoặc Trực tuyến">
                        </div>

                        <div class="mb-3">
                            <label for="tt_note" class="form-label small fw-bold">Ghi chú trích xuất từ sổ:</label>
                            <textarea id="tt_note" name="note" class="form-control small" rows="2" placeholder="Ghi chú thêm..."></textarea>
                        </div>

                        <div class="d-flex align-items-center justify-content-between p-2 rounded bg-light border small">
                            <span><i class="fas fa-bell text-warning me-1"></i> Báo thức nhắc trước:</span>
                            <select id="tt_reminder" name="reminder_minutes" class="form-select form-select-sm w-auto">
                                <option value="5">5 phút</option>
                                <option value="15" selected>15 phút</option>
                                <option value="30">30 phút</option>
                                <option value="60">1 tiếng</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer border-top px-4 py-3 d-flex justify-content-between">
                        <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4" id="btnSubmitTimetable">
                            <i class="fas fa-save me-1"></i> Lưu vào Thời khóa biểu
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Floating Action Button -->
    <div class="floating-action-btn" id="floatingActionBtn">
        <i class="fas fa-plus"></i>
    </div>

    <!-- Floating Menu -->
    <div class="floating-menu" id="floatingMenu">
        <a href="#" data-bs-toggle="modal" data-bs-target="#modalAddNote">
            <i class="fas fa-plus-circle me-2"></i> Thêm ghi chú
        </a>
        <a href="thoikhoabieu.php">
            <i class="fas fa-calendar-alt me-2 text-primary"></i> Thời khóa biểu
        </a>
    </div>

    <!-- Modal Add Note (Direct Native Form - Fix Blank Modal) -->
    <div class="modal fade" id="modalAddNote" tabindex="-1" aria-labelledby="modalAddNoteLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content shadow-lg border-0" style="border-radius: 16px;">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold" id="modalAddNoteLabel">
                        <i class="fas fa-edit text-primary me-2"></i>Thêm ghi chú mới
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <form id="directAddNoteForm" enctype="multipart/form-data">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label for="directNoteTitle" class="form-label fw-semibold">Tiêu đề ghi chú <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="directNoteTitle" name="noteTitle" placeholder="Nhập tiêu đề..." required>
                        </div>
                        <div class="mb-3">
                            <label for="directNoteContent" class="form-label fw-semibold">Nội dung <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="directNoteContent" name="noteContent" rows="5" placeholder="Nhập nội dung ghi chú..." required></textarea>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="directNoteLabels" class="form-label fw-semibold">Nhãn (cách nhau bởi dấu phẩy)</label>
                                <input type="text" class="form-control" id="directNoteLabels" name="noteLabels" placeholder="Công việc, Học tập, Cá nhân...">
                            </div>
                            <div class="col-md-6">
                                <label for="directReminderAt" class="form-label fw-semibold">
                                    <i class="fas fa-bell text-warning me-1"></i>Báo thời / Hẹn giờ sự kiện
                                </label>
                                <input type="datetime-local" class="form-control" id="directReminderAt" name="reminder_at">
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label for="directBgColor" class="form-label fw-semibold">Màu nền</label>
                                <input type="color" class="form-control form-control-color w-100" id="directBgColor" name="background_color" value="#ffffff">
                            </div>
                            <div class="col-md-4">
                                <label for="directTextColor" class="form-label fw-semibold">Màu chữ</label>
                                <input type="color" class="form-control form-control-color w-100" id="directTextColor" name="text_color" value="#000000">
                            </div>
                            <div class="col-md-4">
                                <label for="directFontFamily" class="form-label fw-semibold">Phông chữ</label>
                                <select class="form-select" id="directFontFamily" name="font_family">
                                    <option value="Poppins">Poppins</option>
                                    <option value="Roboto">Roboto</option>
                                    <option value="Open Sans">Open Sans</option>
                                    <option value="Playfair Display">Playfair Display</option>
                                </select>
                            </div>
                        </div>

                        <!-- Mục bảo mật với mã 4 số -->
                        <div class="p-3 mb-3 border rounded-3 bg-light-subtle">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="directEnablePin" onchange="toggleDirectPinInput(this)">
                                <label class="form-check-label fw-bold text-dark" for="directEnablePin">
                                    <i class="fas fa-shield-alt text-primary me-1"></i>Bảo mật sổ bằng mã 4 số (PIN)
                                </label>
                            </div>
                            <div id="directPinWrapper" style="display:none;" class="mt-2">
                                <label for="directNotePin" class="form-label small fw-semibold">Nhập mã bảo mật 4 số (0000 - 9999):</label>
                                <input type="password" class="form-control fw-bold" id="directNotePin" name="note_pin" maxlength="4" pattern="\d{4}" inputmode="numeric" placeholder="•••• (4 số)" style="letter-spacing: 6px; max-width: 180px; font-size: 1.1rem;" autocomplete="off">
                                <small class="text-muted d-block mt-1">Khi mở sổ trên trang chủ, bạn sẽ cần nhập đúng mã 4 số này để xem nội dung và tùy chỉnh sự kiện.</small>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="directNotePassword" class="form-label fw-semibold">Mật khẩu chữ thông thường (tùy chọn)</label>
                                <input type="password" class="form-control" id="directNotePassword" name="notePassword" placeholder="Để trống nếu không đặt mật khẩu">
                            </div>
                            <div class="col-md-6">
                                <label for="directNoteImage" class="form-label fw-semibold">Ảnh đính kèm (tối đa 5MB)</label>
                                <input type="file" class="form-control" id="directNoteImage" name="noteImage" accept="image/*">
                            </div>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="directPinNote" name="pinNote" value="1">
                            <label class="form-check-label fw-semibold" for="directPinNote">
                                <i class="fas fa-thumbtack text-warning me-1"></i>Ghim ghi chú lên đầu
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer border-top px-4 py-3">
                        <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4" id="directSaveNoteBtn">
                            <i class="fas fa-save me-1"></i> Lưu ghi chú
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="bg-dark text-white-50 pt-5 pb-3 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5 class="text-white">Notezy</h5>
                    <p>Ứng dụng ghi chú đơn giản nhưng mạnh mẽ dành cho mọi người.</p>
                    <div class="d-flex gap-2">
                        <a href="#" class="text-white-50"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-white-50"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-white-50"><i class="fab fa-youtube"></i></a>
                        <a href="#" class="text-white-50"><i class="fab fa-twitter"></i></a>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <h5 class="text-white">Liên kết</h5>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-white-50">Home</a></li>
                        <li><a href="#" class="text-white-50">About us</a></li>
                        <li><a href="#" class="text-white-50">Features</a></li>
                        <li><a href="#" class="text-white-50">Privacy</a></li>
                        <li><a href="#" class="text-white-50">Contact</a></li>
                    </ul>
                </div>
                <div class="col-md-4 mb-4">
                    <h5 class="text-white">Liên hệ</h5>
                    <p><i class="fas fa-map-marker-alt me-2"></i> Địa chỉ: 123 Đường ABC, Quận XYZ, Thành phố HCM</p>
                    <p><i class="fas fa-phone me-2"></i> Điện thoại: (028) 1234 5678</p>
                    <p><i class="fas fa-envelope me-2"></i> Email: info@notezy.com</p>
                </div>
            </div>
            <div class="text-center mt-3 border-top pt-3">
                <p>© 2025 Notezy. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Toggle between Grid and List View
        const ownNotesGrid = document.getElementById('ownNotesGrid');
        const sharedNotesGrid = document.getElementById('sharedNotesGrid');
        const ownNotesList = document.getElementById('ownNotesList');
        const sharedNotesList = document.getElementById('sharedNotesList');
        const gridViewBtn = document.getElementById('gridViewBtn');
        const listViewBtn = document.getElementById('listViewBtn');

        // View Mode: Grid / List with LocalStorage Persistence
        const savedViewMode = localStorage.getItem('notezy_view_mode') || 'grid';
        if (savedViewMode === 'list') {
            ownNotesGrid.style.display = 'none';
            sharedNotesGrid.style.display = 'none';
            ownNotesList.style.display = 'block';
            sharedNotesList.style.display = 'block';
            listViewBtn.classList.add('active');
            gridViewBtn.classList.remove('active');
        } else {
            ownNotesGrid.style.display = 'grid';
            sharedNotesGrid.style.display = 'grid';
            ownNotesList.style.display = 'none';
            sharedNotesList.style.display = 'none';
            gridViewBtn.classList.add('active');
            listViewBtn.classList.remove('active');
        }

        gridViewBtn.addEventListener('click', () => {
            ownNotesGrid.style.display = 'grid';
            sharedNotesGrid.style.display = 'grid';
            ownNotesList.style.display = 'none';
            sharedNotesList.style.display = 'none';
            gridViewBtn.classList.add('active');
            listViewBtn.classList.remove('active');
            localStorage.setItem('notezy_view_mode', 'grid');
        });

        listViewBtn.addEventListener('click', () => {
            ownNotesGrid.style.display = 'none';
            sharedNotesGrid.style.display = 'none';
            ownNotesList.style.display = 'block';
            sharedNotesList.style.display = 'block';
            listViewBtn.classList.add('active');
            gridViewBtn.classList.remove('active');
            localStorage.setItem('notezy_view_mode', 'list');
        });

        // ── Label filter chips: client-side filter across all 4 note areas ──
        (function () {
            const chipBar = document.getElementById('labelFilterBar');
            if (!chipBar) return;
            const allNoteItems = () => document.querySelectorAll(
                '#ownNotesGrid .note-item, #ownNotesList .note-item, #sharedNotesGrid .note-item, #sharedNotesList .note-item'
            );

            function applyFilter(selectedLabel) {
                allNoteItems().forEach(item => {
                    const raw = item.getAttribute('data-labels') || '';
                    const labels = raw.split(',').map(s => s.trim()).filter(Boolean);
                    const show = selectedLabel === '' || labels.includes(selectedLabel);
                    item.style.display = show ? '' : 'none';
                });
            }

            chipBar.addEventListener('click', (e) => {
                const chip = e.target.closest('.label-chip');
                if (!chip) return;
                chipBar.querySelectorAll('.label-chip').forEach(c => c.classList.remove('active'));
                chip.classList.add('active');
                applyFilter(chip.getAttribute('data-label') || '');
            });
        })();

        // ── Manage Labels modal: list, rename, delete ──
        const manageLabelsBtn = document.getElementById('manageLabelsBtn');
        const manageLabelsModalEl = document.getElementById('manageLabelsModal');
        const manageLabelsModal = new bootstrap.Modal(manageLabelsModalEl);
        const manageLabelsList = document.getElementById('manageLabelsList');

        async function loadManageLabelsList() {
            manageLabelsList.innerHTML = '<p class="text-muted text-center mb-0">Đang tải...</p>';
            try {
                const res = await fetch('api/labels.php', { credentials: 'same-origin' });
                const result = await res.json();
                if (result.status !== 'success' || !result.data.length) {
                    manageLabelsList.innerHTML = '<p class="text-muted text-center mb-0">Bạn chưa có nhãn nào.</p>';
                    return;
                }
                manageLabelsList.innerHTML = '';
                result.data.forEach(label => {
                    const row = document.createElement('div');
                    row.className = 'manage-labels-row';
                    row.innerHTML = `
                        <span class="label-name">${label.name}</span>
                        <span>
                            <button type="button" class="btn btn-sm btn-outline-primary me-1 btn-rename-label">Sửa</button>
                            <button type="button" class="btn btn-sm btn-outline-danger btn-delete-label">Xóa</button>
                        </span>
                    `;
                    row.querySelector('.btn-rename-label').addEventListener('click', () => renameLabel(label.label_id, label.name));
                    row.querySelector('.btn-delete-label').addEventListener('click', () => deleteLabel(label.label_id, label.name));
                    manageLabelsList.appendChild(row);
                });
            } catch (err) {
                manageLabelsList.innerHTML = '<p class="text-danger text-center mb-0">Không thể tải danh sách nhãn.</p>';
            }
        }

        document.getElementById('btnAddNewLabel')?.addEventListener('click', async () => {
            const input = document.getElementById('newLabelInput');
            const name = input.value.trim();
            if (!name) {
                Swal.fire({ icon: 'warning', title: 'Thông báo', text: 'Vui lòng nhập tên nhãn.' });
                return;
            }
            try {
                const res = await fetch('api/labels.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name: name })
                });
                const d = await res.json();
                if (d.status === 'success') {
                    input.value = '';
                    loadManageLabelsList();
                    Swal.fire({ icon: 'success', title: 'Thành công', text: 'Đã thêm nhãn mới!', timer: 1200, showConfirmButton: false });
                } else {
                    Swal.fire({ icon: 'error', title: 'Lỗi', text: d.message || 'Không thể tạo nhãn' });
                }
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Lỗi', text: 'Không thể kết nối máy chủ' });
            }
        });

        async function renameLabel(labelId, currentName) {
            const { value: newName } = await Swal.fire({
                title: 'Sửa tên nhãn',
                input: 'text',
                inputValue: currentName,
                showCancelButton: true,
                confirmButtonText: 'Lưu',
                cancelButtonText: 'Hủy',
                inputValidator: (value) => {
                    if (!value || !value.trim()) return 'Tên nhãn không được để trống';
                }
            });
            if (!newName) return;
            try {
                const res = await fetch('api/labels.php', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ action: 'rename', label_id: labelId, name: newName.trim() })
                });
                const result = await res.json();
                if (result.status === 'success') {
                    Swal.fire({ icon: 'success', title: 'Đã đổi tên nhãn', timer: 1200, showConfirmButton: false })
                        .then(() => window.location.reload());
                } else {
                    Swal.fire({ icon: 'error', title: 'Lỗi', text: result.message || 'Đổi tên nhãn thất bại' });
                }
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Lỗi', text: 'Không thể kết nối tới server' });
            }
        }

        async function deleteLabel(labelId, name) {
            const confirmResult = await Swal.fire({
                title: `Xóa nhãn "${name}"?`,
                text: 'Nhãn sẽ bị gỡ khỏi tất cả ghi chú.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Xóa',
                cancelButtonText: 'Hủy'
            });
            if (!confirmResult.isConfirmed) return;
            try {
                const res = await fetch('api/labels.php?label_id=' + encodeURIComponent(labelId), {
                    method: 'DELETE',
                    credentials: 'same-origin'
                });
                const result = await res.json();
                if (result.status === 'success') {
                    Swal.fire({ icon: 'success', title: 'Đã xóa nhãn', timer: 1200, showConfirmButton: false })
                        .then(() => window.location.reload());
                } else {
                    Swal.fire({ icon: 'error', title: 'Lỗi', text: result.message || 'Xóa nhãn thất bại' });
                }
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Lỗi', text: 'Không thể kết nối tới server' });
            }
        }

        if (manageLabelsBtn) {
            manageLabelsBtn.addEventListener('click', () => {
                loadManageLabelsList();
                manageLabelsModal.show();
            });
        }

        // Confirm Delete with SweetAlert2
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
                if (!navigator.onLine && window.NotezyOffline) {
                    await window.NotezyOffline.deleteNoteOffline(noteId);
                    await window.NotezyOffline.renderOfflineNotes();
                    await Swal.fire({
                        title: 'Saved locally',
                        text: 'Ghi chú đã được đánh dấu xóa trên thiết bị và sẽ đồng bộ khi có mạng.',
                        icon: 'info',
                        timer: 1600,
                        showConfirmButton: false
                    });
                    return;
                }
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
                        window.location.reload();
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

        async function togglePin(noteId) {
            const btn = document.querySelector(`.note-card-${noteId} .btn-pin-toggle, [data-note-id="${noteId}"] .btn-pin-toggle`);
            const nextPinned = !(btn && btn.classList.contains('active'));
            if (!navigator.onLine && window.NotezyOffline) {
                await window.NotezyOffline.togglePinOffline(noteId, nextPinned ? 1 : 0);
                if (btn) btn.classList.toggle('active', nextPinned);
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'info',
                    title: 'Saved locally',
                    text: 'Thao tác ghim sẽ đồng bộ khi có mạng.',
                    timer: 1800,
                    showConfirmButton: false
                });
                return;
            }

            try {
                const res = await fetch('api/notes.php', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ note_id: noteId, pinned: nextPinned ? 1 : 0 })
                });
                const data = await res.json();
                if (data.status === 'success') {
                    window.location.reload();
                } else {
                    Swal.fire('Lỗi', data.message || 'Không thể cập nhật trạng thái ghim.', 'error');
                }
            } catch (error) {
                Swal.fire('Lỗi kết nối', 'Không thể kết nối tới server.', 'error');
            }
        }

        // Open Manage Password Modal
        function openManagePasswordModal(noteId, hasPassword) {
            document.getElementById('managePasswordNoteId').value = noteId;
            document.getElementById('new_password').value = '';
            document.getElementById('confirm_password').value = '';
            document.getElementById('current_password').value = '';
            document.getElementById('remove_password').checked = false;

            const statusBadge = document.getElementById('pwdStatusBadge');
            const currentSection = document.getElementById('pwdCurrentSection');
            const removeSection = document.getElementById('pwdRemoveSection');
            const newLabel = document.getElementById('pwdNewLabel');
            const newSection = document.getElementById('pwdNewSection');

            if (hasPassword) {
                statusBadge.innerHTML = '<div class="alert alert-warning small py-2 mb-2"><i class="fas fa-lock me-1"></i> Ghi chú này đang được bảo mật bằng mật khẩu. Nhập mật khẩu hiện tại để đổi mật khẩu mới hoặc gỡ bỏ.</div>';
                currentSection.style.display = 'block';
                document.getElementById('current_password').setAttribute('required', 'required');
                removeSection.style.display = 'block';
                newLabel.textContent = 'Đổi sang mật khẩu mới (hoặc tích chọn gỡ mật khẩu bên dưới):';
                document.getElementById('new_password').removeAttribute('required');
                document.getElementById('confirm_password').removeAttribute('required');
            } else {
                statusBadge.innerHTML = '<div class="alert alert-info small py-2 mb-2"><i class="fas fa-shield-alt me-1"></i> Ghi chú chưa đặt mật khẩu. Đặt mật khẩu để bảo vệ nội dung không bị người khác nhìn thấy.</div>';
                currentSection.style.display = 'none';
                document.getElementById('current_password').removeAttribute('required');
                removeSection.style.display = 'none';
                newLabel.textContent = 'Đặt mật khẩu mới:';
                document.getElementById('new_password').setAttribute('required', 'required');
                document.getElementById('confirm_password').setAttribute('required', 'required');
            }

            document.getElementById('remove_password').onchange = function() {
                if (this.checked) {
                    newSection.style.display = 'none';
                    document.getElementById('new_password').removeAttribute('required');
                    document.getElementById('confirm_password').removeAttribute('required');
                } else {
                    newSection.style.display = 'block';
                }
            };
            newSection.style.display = 'block';

            new bootstrap.Modal(document.getElementById('managePasswordModal')).show();
        }

        // Handle Manage Password Form Submission → api/note_password.php
        document.getElementById('managePasswordForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('pwdSubmitBtn');
            const noteId = document.getElementById('managePasswordNoteId').value;
            const currentPwd = document.getElementById('current_password').value.trim();
            const newPwd = document.getElementById('new_password').value.trim();
            const confirmPwd = document.getElementById('confirm_password').value.trim();
            const isRemove = document.getElementById('remove_password').checked;

            const formData = new URLSearchParams();
            formData.append('note_id', noteId);

            if (isRemove) {
                formData.append('action', 'disable');
                formData.append('current_password', currentPwd);
            } else {
                if (newPwd.length < 4) {
                    Swal.fire({ icon: 'warning', title: 'Lỗi', text: 'Mật khẩu phải có ít nhất 4 ký tự.' });
                    return;
                }
                if (newPwd !== confirmPwd) {
                    Swal.fire({ icon: 'warning', title: 'Lỗi', text: 'Mật khẩu xác nhận không khớp.' });
                    return;
                }
                if (currentPwd) {
                    formData.append('action', 'change');
                    formData.append('old_password', currentPwd);
                    formData.append('new_password', newPwd);
                    formData.append('confirm_password', confirmPwd);
                } else {
                    formData.append('action', 'enable');
                    formData.append('new_password', newPwd);
                    formData.append('confirm_password', confirmPwd);
                }
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Đang lưu...';
            try {
                const response = await fetch('api/note_password.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: formData.toString()
                });
                const data = await response.json();
                if (data.success) {
                    await Swal.fire({
                        title: 'Thành công!',
                        text: data.message,
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    bootstrap.Modal.getInstance(document.getElementById('managePasswordModal')).hide();
                    window.location.reload();
                } else {
                    await Swal.fire({
                        title: 'Lỗi!',
                        text: data.message || 'Đã có lỗi xảy ra.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            } catch (error) {
                console.error('Lỗi:', error);
                await Swal.fire({
                    title: 'Lỗi!',
                    text: 'Không thể kết nối tới server.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save me-1"></i>Lưu bảo mật';
            }
        });

        // ── Modal & Logic Mở khóa Mật khẩu Ghi chú (Password Unlock) ────────
        function openUnlockPasswordModal(noteId, noteTitle, redirectUrl = '') {
            document.getElementById('unlockPasswordNoteId').value = noteId;
            document.getElementById('unlockPasswordTitle').textContent = noteTitle || ('Ghi chú #' + noteId);
            document.getElementById('unlockPasswordRedirectUrl').value = redirectUrl || '';
            const pwdInput = document.getElementById('unlockPasswordInput');
            pwdInput.value = '';
            const errBox = document.getElementById('unlockPasswordError');
            errBox.classList.add('d-none');
            errBox.textContent = '';

            const modalEl = document.getElementById('unlockPasswordModal');
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
            setTimeout(() => pwdInput.focus(), 400);
        }

        document.getElementById('unlockPasswordForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('unlockPasswordSubmitBtn');
            const noteId = document.getElementById('unlockPasswordNoteId').value;
            const redirectUrl = document.getElementById('unlockPasswordRedirectUrl').value;
            const password = document.getElementById('unlockPasswordInput').value;
            const errBox = document.getElementById('unlockPasswordError');

            if (!password) {
                errBox.textContent = 'Vui lòng nhập mật khẩu.';
                errBox.classList.remove('d-none');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Đang xác thực...';
            errBox.classList.add('d-none');

            try {
                const formData = new URLSearchParams();
                formData.append('action', 'verify');
                formData.append('note_id', noteId);
                formData.append('password', password);

                const res = await fetch('api/note_password.php', { method: 'POST', body: formData });
                const data = await res.json();

                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('unlockPasswordModal')).hide();
                    await Swal.fire({
                        icon: 'success',
                        title: 'Mở khóa thành công!',
                        text: data.message,
                        timer: 1000,
                        showConfirmButton: false
                    });

                    if (redirectUrl) {
                        window.location.href = redirectUrl;
                    } else {
                        // Hiển thị nội dung trực tiếp trên trang chủ
                        const gridCover = document.getElementById(`pwdLockedCover_grid_${noteId}`);
                        const gridContent = document.getElementById(`pwdUnlockedContent_grid_${noteId}`);
                        if (gridCover) gridCover.style.display = 'none';
                        if (gridContent) gridContent.style.display = 'block';

                        const listCover = document.getElementById(`pwdLockedCover_list_${noteId}`);
                        const listContent = document.getElementById(`pwdUnlockedContent_list_${noteId}`);
                        if (listCover) listCover.style.display = 'none';
                        if (listContent) listContent.style.display = 'block';
                    }
                } else {
                    errBox.textContent = data.message || 'Mật khẩu không chính xác.';
                    errBox.classList.remove('d-none');
                    document.getElementById('unlockPasswordInput').select();
                }
            } catch (err) {
                errBox.textContent = 'Không thể kết nối đến máy chủ. Vui lòng thử lại.';
                errBox.classList.remove('d-none');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-unlock me-1"></i> Mở khóa ngay';
            }
        });

        // Archive Note
        async function archiveNote(noteId) {
            const result = await Swal.fire({
                title: 'Lưu trữ ghi chú?',
                text: "Ghi chú này sẽ được chuyển vào mục lưu trữ.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ffc107',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Có, lưu trữ!',
                cancelButtonText: 'Hủy'
            });

            if (result.isConfirmed) {
                try {
                    const response = await fetch('archive_note.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'noteId=' + noteId + '&action=archive'
                    });
                    const data = await response.json();
                    if (data.success) {
                        await Swal.fire('Thành công!', data.message, 'success');
                        window.location.reload();
                    } else {
                        await Swal.fire('Lỗi!', data.message, 'error');
                    }
                } catch (error) {
                    console.error('Lỗi:', error);
                    await Swal.fire('Lỗi!', 'Đã có lỗi xảy ra, vui lòng thử lại sau.', 'error');
                }
            }
        }

        // Restore Archived Note
        async function restoreNote(noteId) {
            const result = await Swal.fire({
                title: 'Khôi phục ghi chú?',
                text: "Ghi chú sẽ quay lại danh sách chính.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#198754',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Có, khôi phục!',
                cancelButtonText: 'Hủy'
            });

            if (!result.isConfirmed) return;

            try {
                const response = await fetch('archive_note.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'noteId=' + noteId + '&action=restore'
                });
                const data = await response.json();
                if (data.success) {
                    await Swal.fire('Thành công!', data.message, 'success');
                    window.location.reload();
                } else {
                    await Swal.fire('Lỗi!', data.message, 'error');
                }
            } catch (error) {
                console.error('Lỗi:', error);
                await Swal.fire('Lỗi!', 'Đã có lỗi xảy ra, vui lòng thử lại sau.', 'error');
            }
        }

        // Helper escape HTML
        function escapeShareHtml(str) {
            if (!str) return '';
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        // Open Share Note Modal & Load Collaborators
        async function openShareNoteModal(noteId) {
            document.getElementById('shareNoteId').value = noteId;
            document.getElementById('sharedWithUsername').value = '';
            const permViewer = document.getElementById('permViewer');
            if (permViewer) permViewer.checked = true;

            const modalEl = document.getElementById('shareNoteModal');
            const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
            bsModal.show();

            await loadCollaborators(noteId);
        }

        // Load Collaborators for a note
        async function loadCollaborators(noteId) {
            const listEl = document.getElementById('collaboratorsList');
            if (!listEl) return;
            listEl.innerHTML = '<span class="text-muted"><i class="fas fa-spinner fa-spin me-1"></i> Đang tải danh sách...</span>';
            try {
                const res = await fetch(`share_note.php?note_id=${encodeURIComponent(noteId)}`);
                const data = await res.json();
                if (data.success && data.shares && data.shares.length > 0) {
                    let html = '<ul class="list-group list-group-flush">';
                    data.shares.forEach(s => {
                        const permBadge = s.permission === 'write'
                            ? '<span class="badge bg-success text-white"><i class="fas fa-edit me-1"></i>Editor (Chỉnh sửa)</span>'
                            : '<span class="badge bg-primary text-white"><i class="fas fa-eye me-1"></i>Viewer (Chỉ xem)</span>';
                        html += `
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                                <div>
                                    <span class="fw-semibold">${escapeShareHtml(s.username)}</span>
                                    <span class="text-muted small ms-1">(${escapeShareHtml(s.email)})</span>
                                    <div class="mt-1">${permBadge} <span class="text-muted small ms-2"><i class="far fa-clock me-1"></i>${escapeShareHtml(s.shared_at)}</span></div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="revokeShare(${noteId}, ${s.share_id}, '${escapeShareHtml(s.username)}')">
                                    <i class="fas fa-user-minus me-1"></i> Hủy
                                </button>
                            </li>
                        `;
                    });
                    html += '</ul>';
                    listEl.innerHTML = html;
                } else {
                    listEl.innerHTML = '<p class="text-muted mb-0">Chưa chia sẻ ghi chú này cho người nào khác.</p>';
                }
            } catch (e) {
                listEl.innerHTML = '<p class="text-danger mb-0">Không thể tải danh sách chia sẻ.</p>';
            }
        }

        // Revoke share
        async function revokeShare(noteId, shareId, username) {
            const confirm = await Swal.fire({
                title: 'Hủy chia sẻ?',
                text: `Bạn có chắc muốn hủy quyền truy cập của "${username}"?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Hủy chia sẻ',
                cancelButtonText: 'Đóng'
            });
            if (!confirm.isConfirmed) return;

            const body = new URLSearchParams({
                action: 'unshare',
                note_id: noteId,
                share_id: shareId
            });
            try {
                const res = await fetch('share_note.php', { method: 'POST', body });
                const data = await res.json();
                if (data.success) {
                    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: data.message, showConfirmButton: false, timer: 1500 });
                    await loadCollaborators(noteId);
                } else {
                    Swal.fire('Lỗi', data.message, 'error');
                }
            } catch (e) {
                Swal.fire('Lỗi', 'Không thể kết nối máy chủ', 'error');
            }
        }

        // Handle Share Note Form Submission
        document.getElementById('shareNoteForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('btnShareSubmit');
            if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Đang chia sẻ...'; }

            const formData = new FormData(this);
            try {
                const response = await fetch('share_note.php', {
                    method: 'POST',
                    body: new URLSearchParams(formData)
                });
                const data = await response.json();
                if (data.success) {
                    await Swal.fire({
                        title: 'Thành công!',
                        text: data.message,
                        icon: 'success',
                        timer: 1800,
                        showConfirmButton: false
                    });
                    document.getElementById('sharedWithUsername').value = '';
                    const nid = document.getElementById('shareNoteId').value;
                    await loadCollaborators(nid);
                } else {
                    await Swal.fire({
                        title: 'Lỗi!',
                        text: data.message,
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            } catch (error) {
                console.error('Lỗi:', error);
                await Swal.fire({
                    title: 'Lỗi!',
                    text: 'Đã có lỗi xảy ra, vui lòng thử lại sau.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            } finally {
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Chia sẻ ngay'; }
            }
        });

        // Leave a shared note (remove self from note_shares)
        async function leaveSharedNote(noteId) {
            const result = await Swal.fire({
                title: 'Rời khỏi ghi chú?',
                text: 'Ghi chú này sẽ không còn hiển thị trong danh sách "Được chia sẻ với tôi" của bạn.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Rời khỏi',
                cancelButtonText: 'Hủy'
            });
            if (result.isConfirmed) {
                try {
                    const response = await fetch('delete_note.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `noteId=${encodeURIComponent(noteId)}&isShared=1`
                    });
                    const data = await response.json();
                    if (data.success) {
                        await Swal.fire({
                            title: 'Thành công!',
                            text: 'Đã rời khỏi ghi chú chia sẻ.',
                            icon: 'success',
                            timer: 1500,
                            showConfirmButton: false
                        });
                        window.location.reload();
                    } else {
                        Swal.fire('Lỗi!', data.message || 'Không thể rời khỏi ghi chú.', 'error');
                    }
                } catch (e) {
                    Swal.fire('Lỗi!', 'Không thể kết nối máy chủ.', 'error');
                }
            }
        }

        // Live Search
        let searchTimeout;
        const searchInputs = document.querySelectorAll('#searchForm input, #searchForm input[type="checkbox"]');
        searchInputs.forEach(input => {
            input.addEventListener('input', () => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    const title = document.getElementById('searchTitle').value.trim();
                    const content = document.getElementById('searchContent').value.trim();
                    const labels = document.getElementById('searchLabels').value.trim();
                    const pinned = document.getElementById('searchPinned').checked ? 1 : 0;

                    const resultsDiv = document.getElementById('searchResults');
                    resultsDiv.innerHTML = '<p class="text-secondary">Đang tìm kiếm...</p>';

                    fetch('search.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                        body: `searchTitle=${encodeURIComponent(title)}&searchContent=${encodeURIComponent(content)}&searchLabels=${encodeURIComponent(labels)}&searchPinned=${pinned}`
                    })
                    .then(response => response.text())
                    .then(data => {
                        ownNotesGrid.innerHTML = data;
                        sharedNotesGrid.style.display = 'none';
                        ownNotesList.style.display = 'none';
                        sharedNotesList.style.display = 'none';
                        gridViewBtn.classList.add('active');
                        listViewBtn.classList.remove('active');
                        resultsDiv.innerHTML = '';
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        resultsDiv.innerHTML = '<p class="text-danger">Đã có lỗi xảy ra, vui lòng thử lại.</p>';
                    });
                }, 300);
            });
        });

        // Floating Action Button and Menu
        document.addEventListener('DOMContentLoaded', function() {
            const floatingBtn = document.getElementById('floatingActionBtn');
            const floatingMenu = document.getElementById('floatingMenu');
            
            floatingBtn.addEventListener('click', function() {
                floatingMenu.classList.toggle('show');
            });
            
            document.addEventListener('click', function(event) {
                if (!floatingBtn.contains(event.target) && !floatingMenu.contains(event.target)) {
                    floatingMenu.classList.remove('show');
                }
            });

            // Dynamic Search Modal Positioning
            const searchModal = document.getElementById('searchModal');
            searchModal.addEventListener('show.bs.modal', function () {
                const modalDialog = this.querySelector('.modal-dialog');
                const navbarHeight = document.querySelector('.navbar').offsetHeight;
                const windowWidth = window.innerWidth;
                const windowHeight = window.innerHeight;
                const modalWidth = 280;
                const modalHeight = modalDialog.offsetHeight || 300;
                const padding = 10;

                const positions = [
                    { top: navbarHeight + padding, right: padding, left: 'auto', transform: 'none' },
                    { top: navbarHeight + padding, left: padding, right: 'auto', transform: 'none' },
                    { bottom: padding, right: padding, top: 'auto', left: 'auto', transform: 'none' },
                    { bottom: padding, left: padding, top: 'auto', right: 'auto', transform: 'none' }
                ];

                let selectedPosition = positions[0];

                for (let pos of positions) {
                    let fits = true;
                    if (pos.right !== 'auto' && windowWidth - modalWidth - padding < 0) {
                        fits = false;
                    }
                    if (pos.left !== 'auto' && modalWidth + padding > windowWidth) {
                        fits = false;
                    }
                    if (pos.top !== 'auto' && modalHeight + navbarHeight + padding > windowHeight) {
                        fits = false;
                    }
                    if (pos.bottom !== 'auto' && modalHeight + padding > windowHeight - navbarHeight) {
                        fits = false;
                    }
                    if (fits) {
                        selectedPosition = pos;
                        break;
                    }
                }

                modalDialog.style.top = selectedPosition.top !== 'auto' ? `${selectedPosition.top}px` : 'auto';
                modalDialog.style.right = selectedPosition.right !== 'auto' ? `${selectedPosition.right}px` : 'auto';
                modalDialog.style.bottom = selectedPosition.bottom !== 'auto' ? `${selectedPosition.bottom}px` : 'auto';
                modalDialog.style.left = selectedPosition.left !== 'auto' ? `${selectedPosition.left}px` : 'auto';
                modalDialog.style.transform = selectedPosition.transform;
            });
        });
        window.addEventListener('message', function (event) {
    if (event.data.type === 'noteSaved') {
        // Đóng modal
        bootstrap.Modal.getInstance(document.getElementById('modalAddNote')).hide();
        // Hiển thị thông báo thành công
        Swal.fire({
            title: 'Thành công!',
            text: event.data.message,
            icon: 'success',
            timer: 1500,
            showConfirmButton: false
        }).then(() => {
            // Chuyển hướng về index.php hoặc làm mới danh sách ghi chú
            window.location.href = 'index.php';
        });
    } else if (event.data.type === 'noteError') {
        Swal.fire({
            title: 'Lỗi!',
            text: event.data.message,
            icon: 'error',
            confirmButtonText: 'OK'
        });
    }
});

// ── Quản lý và Mở khóa Sổ ghi chú bằng mã bảo mật 4 số ──────────
function toggleDirectPinInput(checkbox) {
    const wrapper = document.getElementById('directPinWrapper');
    const input = document.getElementById('directNotePin');
    if (checkbox.checked) {
        wrapper.style.display = 'block';
        input.setAttribute('required', 'required');
        input.focus();
    } else {
        wrapper.style.display = 'none';
        input.removeAttribute('required');
        input.value = '';
    }
}

// Mở modal nhập mã 4 số để mở sổ
function openUnlockPinModal(noteId, noteTitle, redirectUrl = '') {
    document.getElementById('unlockPinNoteId').value = noteId;
    document.getElementById('unlockPinNoteTitle').textContent = noteTitle || 'Sổ ghi chú #' + noteId;
    document.getElementById('unlockPinRedirectUrl').value = redirectUrl || '';
    const pinInput = document.getElementById('unlockPinInput');
    pinInput.value = '';
    const errBox = document.getElementById('unlockPinError');
    errBox.classList.add('d-none');
    errBox.textContent = '';

    const modalEl = document.getElementById('unlockPinModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
    setTimeout(() => pinInput.focus(), 400);
}

// Xử lý gửi form mở khóa mã 4 số
document.getElementById('unlockPinForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('unlockPinSubmitBtn');
    const noteId = document.getElementById('unlockPinNoteId').value;
    const redirectUrl = document.getElementById('unlockPinRedirectUrl').value;
    const pin = document.getElementById('unlockPinInput').value.trim();
    const errBox = document.getElementById('unlockPinError');

    if (!/^\d{4}$/.test(pin)) {
        errBox.textContent = 'Mã bảo mật phải gồm đúng 4 chữ số.';
        errBox.classList.remove('d-none');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Đang kiểm tra...';
    errBox.classList.add('d-none');

    try {
        const formData = new URLSearchParams();
        formData.append('action', 'verify');
        formData.append('note_id', noteId);
        formData.append('pin', pin);

        const res = await fetch('api/note_pin.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('unlockPinModal')).hide();
            await Swal.fire({
                icon: 'success',
                title: 'Mở khóa thành công!',
                text: 'Sổ ghi chú đã sẵn sàng để xem và tùy chỉnh.',
                timer: 1000,
                showConfirmButton: false
            });

            if (redirectUrl) {
                window.location.href = redirectUrl;
            } else {
                // Cập nhật giao diện trực tiếp trên trang chủ
                const gridCover = document.getElementById(`lockedCover_grid_${noteId}`);
                const gridContent = document.getElementById(`unlockedContent_grid_${noteId}`);
                if (gridCover) gridCover.style.display = 'none';
                if (gridContent) gridContent.style.display = 'block';

                const listCover = document.getElementById(`lockedCover_list_${noteId}`);
                const listContent = document.getElementById(`unlockedContent_list_${noteId}`);
                if (listCover) listCover.style.display = 'none';
                if (listContent) listContent.style.display = 'block';

                document.querySelectorAll(`.pin-badge-${noteId}`).forEach(el => {
                    el.className = `badge bg-success pin-badge-${noteId}`;
                    el.innerHTML = '<i class="fas fa-lock-open me-1"></i>Đã mở khóa';
                });
            }
        } else {
            errBox.textContent = data.message || 'Mã bảo mật 4 số không đúng.';
            errBox.classList.remove('d-none');
            document.getElementById('unlockPinInput').select();
        }
    } catch (err) {
        errBox.textContent = 'Không thể kết nối đến máy chủ. Vui lòng thử lại.';
        errBox.classList.remove('d-none');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-unlock me-1"></i> Mở sổ ngay';
    }
});

// Mở modal cài đặt / đổi / gỡ mã bảo mật 4 số
function openManagePinModal(noteId, hasPin, noteTitle) {
    document.getElementById('managePinNoteId').value = noteId;
    document.getElementById('managePinNoteTitle').textContent = noteTitle || 'Sổ ghi chú #' + noteId;
    document.getElementById('manageNewPin').value = '';
    document.getElementById('manageConfirmPin').value = '';
    document.getElementById('manageOldPin').value = '';

    const alertBox = document.getElementById('managePinStatusAlert');
    const oldSection = document.getElementById('managePinOldSection');
    const oldInput = document.getElementById('manageOldPin');
    const removeSection = document.getElementById('managePinRemoveSection');
    const labelNew = document.getElementById('manageNewPinLabel');

    if (hasPin) {
        alertBox.className = 'alert alert-warning small py-2 mb-3';
        alertBox.innerHTML = '<i class="fas fa-lock me-1"></i> Sổ này đang được bảo vệ bằng mã 4 số. Nhập mã hiện tại để đổi mã mới hoặc gỡ bỏ.';
        oldSection.style.display = 'block';
        oldInput.setAttribute('required', 'required');
        removeSection.style.display = 'block';
        labelNew.textContent = 'Mã bảo mật 4 số mới:';
    } else {
        alertBox.className = 'alert alert-info small py-2 mb-3';
        alertBox.innerHTML = '<i class="fas fa-shield-alt me-1"></i> Sổ này chưa có mã bảo vệ. Hãy thiết lập 4 số bí mật để khóa sổ an toàn.';
        oldSection.style.display = 'none';
        oldInput.removeAttribute('required');
        removeSection.style.display = 'none';
        labelNew.textContent = 'Đặt mã bảo mật 4 số:';
    }

    const modalEl = document.getElementById('managePinModal');
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
}

// Xử lý lưu thiết lập mã bảo mật 4 số
document.getElementById('managePinForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('managePinSubmitBtn');
    const noteId = document.getElementById('managePinNoteId').value;
    const pin = document.getElementById('manageNewPin').value.trim();
    const pinConfirm = document.getElementById('manageConfirmPin').value.trim();
    const oldPin = document.getElementById('manageOldPin').value.trim();

    if (!/^\d{4}$/.test(pin)) {
        Swal.fire({ icon: 'warning', title: 'Lỗi', text: 'Mã bảo mật phải gồm đúng 4 chữ số.' });
        return;
    }
    if (pin !== pinConfirm) {
        Swal.fire({ icon: 'warning', title: 'Lỗi', text: 'Hai lần nhập mã bảo mật 4 số không khớp nhau.' });
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Đang lưu...';

    try {
        const formData = new URLSearchParams();
        formData.append('action', 'set');
        formData.append('note_id', noteId);
        formData.append('pin', pin);
        formData.append('pin_confirm', pinConfirm);
        if (oldPin) formData.append('old_pin', oldPin);

        const res = await fetch('api/note_pin.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('managePinModal')).hide();
            await Swal.fire({
                icon: 'success',
                title: 'Thành công!',
                text: data.message || 'Đã cài đặt bảo mật 4 số cho sổ ghi chú thành công!',
                timer: 1500,
                showConfirmButton: false
            });
            window.location.reload();
        } else {
            Swal.fire({ icon: 'error', title: 'Lỗi', text: data.message || 'Không thể lưu mã bảo mật.' });
        }
    } catch (err) {
        Swal.fire({ icon: 'error', title: 'Lỗi', text: 'Không thể kết nối máy chủ.' });
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save me-1"></i> Lưu bảo mật';
    }
});

// Xử lý gỡ bỏ mã bảo mật khỏi sổ
document.getElementById('manageRemovePinBtn')?.addEventListener('click', async function() {
    const noteId = document.getElementById('managePinNoteId').value;
    const oldPin = document.getElementById('manageOldPin').value.trim();

    let confirmPin = oldPin;
    if (!confirmPin) {
        const { value: inputVal } = await Swal.fire({
            title: 'Xác nhận gỡ bảo mật',
            text: 'Vui lòng nhập mã bảo mật 4 số hiện tại (hoặc mật khẩu tài khoản) để gỡ khóa:',
            input: 'password',
            inputPlaceholder: 'Nhập 4 số hiện tại hoặc mật khẩu',
            showCancelButton: true,
            confirmButtonText: 'Xác nhận gỡ',
            cancelButtonText: 'Hủy'
        });
        if (!inputVal) return;
        confirmPin = inputVal.trim();
    }

    try {
        const formData = new URLSearchParams();
        formData.append('action', 'remove');
        formData.append('note_id', noteId);
        formData.append('old_pin', confirmPin);

        const res = await fetch('api/note_pin.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('managePinModal')).hide();
            await Swal.fire({
                icon: 'success',
                title: 'Đã gỡ bảo mật!',
                text: 'Sổ ghi chú này không còn khóa mã 4 số nữa.',
                timer: 1200,
                showConfirmButton: false
            });
            window.location.reload();
        } else {
            Swal.fire({ icon: 'error', title: 'Lỗi', text: data.message || 'Không thể gỡ mã bảo mật.' });
        }
    } catch (err) {
        Swal.fire({ icon: 'error', title: 'Lỗi', text: 'Không thể kết nối máy chủ.' });
    }
});

// Xử lý gửi form trực tiếp từ modal Thêm ghi chú mới (lưu rõ ràng vào trang chủ)
document.getElementById('directAddNoteForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('directSaveNoteBtn');

    // Kiểm tra nếu bật mã PIN thì phải đúng 4 số
    const enablePin = document.getElementById('directEnablePin')?.checked;
    const pinVal = document.getElementById('directNotePin')?.value.trim();
    if (enablePin && (!pinVal || !/^\d{4}$/.test(pinVal))) {
        Swal.fire({
            icon: 'warning',
            title: 'Mã bảo mật không hợp lệ',
            text: 'Vui lòng nhập đúng 4 chữ số (0000 - 9999) để bảo mật sổ ghi chú.'
        });
        document.getElementById('directNotePin').focus();
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Đang lưu sổ ghi chú...';

    const formData = new FormData(this);
    formData.append('ajax', '1');

    if (!navigator.onLine && window.NotezyOffline) {
        const localNote = await window.NotezyOffline.createNoteOffline(
            formData.get('noteTitle'),
            formData.get('noteContent'),
            formData.get('noteLabels'),
            {
                pinned: formData.get('pinNote') === '1',
                background_color: formData.get('background_color') || '#ffffff',
                text_color: formData.get('text_color') || '#000000',
                font_family: formData.get('font_family') || 'Poppins',
                reminder_at: formData.get('reminder_at') || ''
            }
        );
        const modalEl = document.getElementById('modalAddNote');
        const modalInstance = bootstrap.Modal.getInstance(modalEl);
        if (modalInstance) modalInstance.hide();
        this.reset();
        await window.NotezyOffline.renderOfflineNotes();
        await Swal.fire({
            icon: 'info',
            title: 'Saved locally',
            text: `"${localNote.title}" đã được lưu trên thiết bị và sẽ tự đồng bộ khi online.`,
            timer: 1800,
            showConfirmButton: false
        });
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save me-1"></i> Lưu ghi chú';
        return;
    }

    try {
        const response = await fetch('themghichu.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            const modalEl = document.getElementById('modalAddNote');
            const modalInstance = bootstrap.Modal.getInstance(modalEl);
            if (modalInstance) modalInstance.hide();

            await Swal.fire({
                icon: 'success',
                title: 'Thành công!',
                text: 'Sổ ghi chú đã được lưu rõ ràng vào trang chủ!',
                timer: 1200,
                showConfirmButton: false
            });

            window.location.reload();
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Lỗi khi lưu!',
                text: result.message || 'Không thể lưu ghi chú, vui lòng kiểm tra lại.'
            });
        }
    } catch (err) {
        Swal.fire({
            icon: 'error',
            title: 'Lỗi kết nối!',
            text: 'Không thể kết nối đến máy chủ. Vui lòng thử lại.'
        });
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save me-1"></i> Lưu ghi chú';
    }
});

// ── 1. ĐỒNG BỘ 2 CHẾ ĐỘ MÀU (SÁNG: TRẮNG-XANH, TỐI: ĐEN-ĐỎ) ──
function initInsideTheme() {
    const saved = localStorage.getItem('notezy_theme') || 'light';
    applyInsideTheme(saved);
}

function applyInsideTheme(theme) {
    const isDark = theme === 'dark';
    if (isDark) {
        document.body.classList.add('theme-dark');
        document.body.classList.remove('theme-light');
    } else {
        document.body.classList.remove('theme-dark');
        document.body.classList.add('theme-light');
    }
    localStorage.setItem('notezy_theme', theme);

    const icon = document.getElementById('themeToggleIcon');
    const text = document.getElementById('themeToggleText');
    if (icon) icon.className = isDark ? 'fas fa-moon text-danger' : 'fas fa-sun text-success';
    if (text) text.textContent = isDark ? 'Tối (Đen & Đỏ)' : 'Sáng (Trắng & Xanh)';
}

function toggleInsideTheme() {
    const current = localStorage.getItem('notezy_theme') || 'light';
    const next = current === 'dark' ? 'light' : 'dark';
    applyInsideTheme(next);
}

// Khởi chạy đồng bộ theme ngay khi trang tải xong
document.addEventListener('DOMContentLoaded', initInsideTheme);

// ── 2. CHỨC NĂNG THÊM SỔ GHI CHÚ VÀO THỜI KHÓA BIỂU ──
function openAddToTimetableModal(noteId, noteTitle, noteSnippet) {
    document.getElementById('tt_note_id').value = noteId || '';
    document.getElementById('tt_title').value = noteTitle || '';
    document.getElementById('tt_note').value = noteSnippet || '';

    // Mặc định chọn thứ hôm nay
    const todayDay = new Date().getDay();
    const daySelect = document.getElementById('tt_day_of_week');
    if (daySelect) {
        daySelect.value = todayDay;
    }

    const modalEl = document.getElementById('modalAddToTimetable');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}

// Xử lý gửi form thêm vào thời khóa biểu
document.getElementById('formAddToTimetable')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmitTimetable');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Đang lưu...';

    const payload = {
        title: document.getElementById('tt_title').value.trim(),
        day_of_week: parseInt(document.getElementById('tt_day_of_week').value, 10),
        start_time: document.getElementById('tt_start_time').value.trim(),
        end_time: document.getElementById('tt_end_time').value.trim(),
        location: document.getElementById('tt_location').value.trim(),
        note: document.getElementById('tt_note').value.trim(),
        color: document.getElementById('tt_color').value.trim(),
        reminder_minutes: parseInt(document.getElementById('tt_reminder').value, 10)
    };

    try {
        const res = await fetch('api/timetable.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.status === 'success') {
            bootstrap.Modal.getInstance(document.getElementById('modalAddToTimetable')).hide();
            Swal.fire({
                icon: 'success',
                title: 'Đã thêm vào Thời Khóa Biểu!',
                text: `Đã xếp lịch cho "${payload.title}" thành công.`,
                showCancelButton: true,
                confirmButtonText: 'Xem Thời Khóa Biểu ngay',
                cancelButtonText: 'Ở lại trang',
                confirmButtonColor: '#10b981'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'thoikhoabieu.php';
                }
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Lỗi',
                text: data.message || 'Không thể thêm vào thời khóa biểu.'
            });
        }
    } catch (err) {
        Swal.fire({
            icon: 'error',
            title: 'Lỗi kết nối',
            text: 'Không thể kết nối đến máy chủ thời khóa biểu.'
        });
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save me-1"></i> Lưu vào Thời khóa biểu';
    }
});
</script>
    <script src="js/timetable-alarm.js" defer></script>
    <script src="js/reminders.js" defer></script>
    <script src="js/offline-store.js"></script>
    <script>
        // ── Lưu snapshot ghi chú vào IndexedDB để xem khi ngoại tuyến (Offline) ──
        (function() {
            try {
                const currentNotes = <?= json_encode(array_values(array_map(function($n) {
                    return [
                        'note_id' => $n['note_id'],
                        'title' => $n['title'],
                        'content' => !empty($n['is_password_locked']) ? '' : $n['content'],
                        'pinned' => (int)$n['pinned'],
                        'labels' => $n['labels'],
                        'image_path' => !empty($n['is_password_locked']) ? '' : $n['image_path'],
                        'created_at' => $n['created_at'],
                        'has_pin' => !empty($n['has_pin']),
                        'has_password' => !empty($n['has_password']),
                        'is_password_locked' => !empty($n['is_password_locked']),
                        'ownership' => $n['ownership']
                    ];
                }, array_merge($own_notes, $shared_notes)))) ?>;

                if (window.NotezyOffline && Array.isArray(currentNotes)) {
                    window.NotezyOffline.saveNotesSnapshot(currentNotes);
                }
            } catch (e) {
                console.warn('[Offline Snapshot] Không thể lưu snapshot:', e);
            }
        })();

        // ── Register service worker for offline support (item 7) ──
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('sw.js')
                    .catch((err) => console.warn('SW registration failed:', err));
            });
        }
    </script>
    <?php
$notezy_ai_page = 'notes_list';
    $notezy_ai_note_id = 0;
    include __DIR__ . '/includes/ai_widget.php';
    ?>
</body>
</html>
