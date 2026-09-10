<?php
require_once __DIR__ . '/includes/session.php';
require_once('db.php');

// Session cookie security
session_set_cookie_params([
    'httponly' => true,
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax',
]);
notezy_session_start();

if (!isset($_SESSION['id'])) {
    header('Location: index.php');
    exit();
}

$user_id = (int) $_SESSION['id'];

// Xử lý cập nhật avatar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
    $max_size = 5 * 1024 * 1024; // 2MB
    $file = $_FILES['avatar'];

    if ($file['error'] === UPLOAD_ERR_OK && $file['size'] <= $max_size) {
        // Validate if it's an image
        $image_info = getimagesize($file['tmp_name']);
        if ($image_info !== false) {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
            $upload_dir = 'Uploads/';
            $upload_path = $upload_dir . $filename;

            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $sql = "UPDATE users SET avatar = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $filename, $user_id);
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Cập nhật avatar thành công', 'avatar' => $upload_path]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Lỗi khi cập nhật avatar']);
                }
                $stmt->close();
            } else {
                echo json_encode(['success' => false, 'message' => 'Lỗi khi tải lên file']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'File không phải là ảnh']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'File quá lớn hoặc lỗi tải lên']);
    }
    exit;
}

// Xử lý cập nhật dữ liệu khi gửi POST ajax
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_field'])) {
    $field = $_POST['update_field'];
    $value = trim($_POST['update_value']);

    $allowed_fields = ['username', 'firstname', 'lastname', 'email', 'theme', 'language'];
    if (!in_array($field, $allowed_fields)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Trường không hợp lệ']);
        exit;
    }

    if ($value === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Giá trị không được để trống']);
        exit;
    }

    $sql = "UPDATE users SET $field = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $value, $user_id);
    if ($stmt->execute()) {
        $_SESSION[$field] = $value;
        echo json_encode(['success' => true, 'message' => 'Cập nhật thành công']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lỗi khi cập nhật dữ liệu']);
    }
    $stmt->close();
    exit;
}

// Check if avatar column exists
$avatar_column_exists = false;
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'avatar'");
if ($result->num_rows > 0) {
    $avatar_column_exists = true;
}

// Lấy dữ liệu user — không SELECT pass (không cần plaintext)
$fields = ['username', 'firstname', 'lastname', 'email', 'theme', 'language'];
if ($avatar_column_exists) {
    $fields[] = 'avatar';
}
$field_list = implode(', ', $fields);
$sql = "SELECT $field_list FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    die("Người dùng không tồn tại.");
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8" />
    <title>Trang cá nhân - <?= htmlspecialchars($user['username']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body {
            background: #FFFFFF;
            font-family: 'Inter', sans-serif;
            color: #000000;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            overflow-x: hidden;
        }
        .container {
            max-width: 900px;
            background: #FFFFFF;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            margin: 20px auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            animation: fadeIn 0.5s ease-in-out;
        }
        h1 {
            font-weight: 700;
            text-align: center;
            margin-bottom: 20px;
            color: #000000;
            font-size: 2.2rem;
            letter-spacing: -0.02em;
        }
        /* Avatar Section */
        .avatar-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 40px;
        }
        .avatar-img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #E0E0E0;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .avatar-img:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
        .avatar-upload {
            margin-top: 15px;
            text-align: center;
        }
        .avatar-upload label {
            background: #000000;
            color: #FFFFFF;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background 0.3s ease, transform 0.2s ease;
        }
        .avatar-upload label:hover {
            background: #333333;
            transform: scale(1.05);
        }
        .avatar-upload input {
            display: none;
        }
        .avatar-message {
            margin-top: 10px;
            font-size: 0.9rem;
            font-weight: 500;
            animation: slideIn 0.3s ease;
            text-align: center;
        }
        /* Table Styles */
        table.info-table {
            width: 100%;
            max-width: 600px;
            border-collapse: separate;
            border-spacing: 0 12px;
            margin: 0 auto;
        }
        table.info-table th {
            width: 160px;
            text-align: left;
            font-weight: 600;
            color: #000000;
            padding-left: 12px;
            font-size: 1rem;
            vertical-align: middle;
        }
        table.info-table td.field-value {
            background: #F5F5F5;
            border-radius: 8px 0 0 8px;
            padding: 12px 15px;
            font-size: 1rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        table.info-table td.action {
            background: #F5F5F5;
            border-radius: 0 8px 8px 0;
            padding: 12px 15px;
            width: 80px;
            text-align: center;
        }
        table.info-table tr:hover td {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .edit-btn {
            background: #000000;
            border: none;
            color: #FFFFFF;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: background 0.3s ease, transform 0.2s ease;
        }
        .edit-btn:hover {
            background: #333333;
            transform: scale(1.05);
        }
        /* Edit Section */
        .edit-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #E0E0E0;
            width: 100%;
            max-width: 600px;
            text-align: center;
        }
        .edit-section h4 {
            font-weight: 600;
            margin-bottom: 20px;
            color: #000000;
            font-size: 1.5rem;
        }
        .form-label {
            font-weight: 500;
            color: #000000;
            font-size: 1rem;
        }
        .form-control {
            max-width: 100%;
            font-size: 1rem;
            border: 1px solid #E0E0E0;
            border-radius: 8px;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            margin: 0 auto;
        }
        .form-control:focus {
            border-color: #000000;
            box-shadow: 0 0 8px rgba(0, 0, 0, 0.2);
            outline: none;
        }
        .btn-save {
            margin-top: 15px;
            width: 100%;
            max-width: 300px;
            background: #000000;
            border: none;
            color: #FFFFFF;
            font-weight: 600;
            font-size: 1rem;
            padding: 12px;
            border-radius: 8px;
            transition: background 0.3s ease, transform 0.2s ease;
            position: relative;
            overflow: hidden;
            margin: 0 auto;
            display: block;
        }
        .btn-save:hover {
            background: #333333;
            transform: scale(1.02);
        }
        .btn-save:disabled {
            background: #666666;
            cursor: not-allowed;
        }
        .btn-save.loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            border: 2px solid #FFFFFF;
            border-top: 2px solid transparent;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
        .password-toggle {
            display: inline-block;
            margin-top: 8px;
            cursor: pointer;
            color: #000000;
            font-weight: 500;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }
        .password-toggle:hover {
            color: #333333;
        }
        .alert-message {
            margin-top: 12px;
            font-weight: 500;
            font-size: 0.95rem;
            animation: slideIn 0.3s ease;
            text-align: center;
        }
        /* Responsive */
        @media (min-width: 768px) {
            .row {
                display: flex;
                gap: 40px;
                justify-content: center;
                width: 100%;
                max-width: 900px;
            }
            .col-left {
                flex: 0 0 45%;
                display: flex;
                justify-content: center;
            }
            .col-right {
                flex: 0 0 45%;
                background: #F5F5F5;
                padding: 25px;
                border-radius: 12px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
                height: fit-content;
                display: flex;
                justify-content: center;
            }
        }
        @media (max-width: 767px) {
            .col-left, .col-right {
                width: 100%;
                padding: 15px;
            }
            .form-control {
                font-size: 0.9rem;
            }
            .btn {
                width: 100%;
                margin-bottom: 8px;
            }
        }
        @media (max-width: 480px) {
            body {
                padding: 8px;
            }
            .col-left, .col-right {
                padding: 10px;
                border-radius: 8px;
            }
            .form-label {
                font-size: 0.85rem;
            }
            .form-control {
                font-size: 0.8rem;
                padding: 8px 10px;
            }
            .avatar-preview, .avatar-img {
                width: 90px !important;
                height: 90px !important;
            }
            select.form-control {
                font-size: 0.8rem;
            }
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-10px); }
            to { opacity: 1; transform: translateX(0); }
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        /* Dark Mode Styles */
        body.dark-mode {
            background: #121212;
            color: #ffffff;
        }
        body.dark-mode .container {
            background: #1e1e1e;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
        }
        body.dark-mode h1, body.dark-mode h4, body.dark-mode .form-label, body.dark-mode table.info-table th {
            color: #ffffff;
        }
        body.dark-mode table.info-table td.field-value, body.dark-mode table.info-table td.action {
            background: #2a2a2a;
            color: #ffffff;
        }
        body.dark-mode .col-right {
            background: #1e1e1e;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
        }
        body.dark-mode .form-control {
            background: #2a2a2a;
            color: #ffffff;
            border-color: #444;
        }
        body.dark-mode .form-control:disabled {
            background: #222;
        }
        body.dark-mode .btn-save, body.dark-mode .edit-btn, body.dark-mode .avatar-upload label {
            background: #0d6efd;
            color: #ffffff;
        }
        body.dark-mode .btn-save:hover, body.dark-mode .edit-btn:hover, body.dark-mode .avatar-upload label:hover {
            background: #0b5ed7;
        }
        body.dark-mode .password-toggle {
            color: #cccccc;
        }
        body.dark-mode .password-toggle:hover {
            color: #ffffff;
        }
    </style>
</head>
<body class="<?= (isset($_SESSION['theme']) && $_SESSION['theme'] === 'dark') ? 'dark-mode' : '' ?>">

<div class="container">
    <h1>Thông tin cá nhân</h1>
    <div class="chat-button" style="margin-top:20px;">
        <a href="chat.php?user_id=<?php echo $user_id; ?>" class="btn btn-primary">Chat with Others</a>
    </div>
    <div class="avatar-section">
        <img src="<?php echo htmlspecialchars($user['avatar'] ?? 'default.png'); ?>" alt="Avatar" class="avatar-img" id="avatarImage">
        <div class="avatar-upload">
            <form id="avatarForm" method="post" action="account.php" enctype="multipart/form-data">
                <input type="file" name="avatar" id="avatarInput" accept="image/*" style="display:none;" />
                <label for="avatarInput">Upload Avatar</label>
            </form>
        </div>
    </div>
    <div class="row">
        <div class="col-left">
            <table class="info-table">
                <tbody>
                    <?php
$fields = [
                        'username' => 'Username',
                        'lastname' => 'Họ',
                        'firstname' => 'Tên',
                        'email' => 'Email',
                    ];
                    foreach ($fields as $key => $label): 
                    ?>
                    <tr>
                        <th><?= $label ?></th>
                        <td class="field-value">
                            <span id="field-<?= $key ?>">
                                <?= $key === 'pass' ? '********' : htmlspecialchars($user[$key]) ?>
                            </span>
                        </td>
                        <td class="action">
                            <button class="edit-btn" data-field="<?= $key ?>">Sửa</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="col-right">
            <div class="edit-section">
                <h4>Chỉnh sửa thông tin</h4>
                <form id="editForm" autocomplete="off">
                    <div class="mb-3">
                        <label for="editInput" class="form-label" id="editLabel">Chọn một trường để sửa</label>
                        <input type="text" class="form-control" id="editInput" name="edit_value" disabled required>
                    </div>
                    <input type="hidden" id="editField" name="edit_field" value="">
                    <button type="submit" class="btn-save" disabled>Lưu thay đổi</button>
                    <li><a href="index_notezy.php" class="text-white-50">Home</a></li>
                    <div id="editMessage" class="alert-message"></div>
                    <div id="passwordToggleContainer" style="display:none;">
                        <span class="password-toggle" id="togglePassword">Hiện mật khẩu</span>
                    </div>
                     <a href="index_notezy.php">Trở về trang chủ</a>
                </form>
            </div>
            <div class="edit-section mt-4">
                <h4>Tùy chọn hiển thị</h4>
                <form id="preferencesForm" autocomplete="off">
                    <div class="mb-3 text-start">
                        <label for="themeSelect" class="form-label">Giao diện</label>
                        <select class="form-control" id="themeSelect">
                            <option value="light" <?= $user['theme'] == 'light' ? 'selected' : '' ?>>Sáng</option>
                            <option value="dark" <?= $user['theme'] == 'dark' ? 'selected' : '' ?>>Tối</option>
                        </select>
                    </div>
                    <div class="mb-3 text-start">
                        <label for="languageSelect" class="form-label">Ngôn ngữ</label>
                        <select class="form-control" id="languageSelect">
                            <option value="vi" <?= $user['language'] == 'vi' ? 'selected' : '' ?>>Tiếng Việt</option>
                            <option value="en" <?= $user['language'] == 'en' ? 'selected' : '' ?>>English</option>
                        </select>
                    </div>
                    <button type="button" id="savePreferencesBtn" class="btn-save">Lưu tùy chọn</button>
                    <div id="prefMessage" class="alert-message mt-2"></div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    let currentField = null;
    const editInput = document.getElementById('editInput');
    const editField = document.getElementById('editField');
    const editLabel = document.getElementById('editLabel');
    const editMessage = document.getElementById('editMessage');
    const saveBtn = document.querySelector('.btn-save');
    const passwordToggleContainer = document.getElementById('passwordToggleContainer');
    const togglePasswordBtn = document.getElementById('togglePassword');
    const avatarInput = document.getElementById('avatarInput');
    const avatarImg = document.querySelector('.avatar-img');
    const avatarMessage = document.getElementById('avatarMessage');

    // Xử lý upload avatar
    avatarInput.addEventListener('change', () => {
        const file = avatarInput.files[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('avatar', file);

        avatarMessage.textContent = 'Đang tải lên...';
        avatarMessage.style.color = '#000000';

        fetch('', {
            method: 'POST',
            body: formData
        }).then(res => res.json())
        .then(data => {
            if (data.success) {
                avatarImg.src = data.avatar;
                avatarMessage.style.color = 'green';
                avatarMessage.textContent = data.message;
            } else {
                avatarMessage.style.color = 'red';
                avatarMessage.textContent = data.message;
            }
        })
        .catch(() => {
            avatarMessage.style.color = 'red';
            avatarMessage.textContent = 'Lỗi khi tải lên avatar';
        });
    });

    // Khi bấm nút Sửa
    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            console.log('Edit button clicked for field:', btn.dataset.field); // Debug log
            currentField = btn.dataset.field;
            editField.value = currentField;
            editMessage.textContent = '';
            saveBtn.disabled = false;
            editInput.disabled = false;

            const thElement = btn.closest('tr').querySelector('th');
            if (!thElement) {
                console.error('TH element not found for button:', btn);
                return;
            }
            editLabel.textContent = 'Sửa ' + thElement.textContent;
            const valSpan = document.getElementById('field-' + currentField);
            if (!valSpan) {
                console.error('Value span not found for field:', currentField);
                return;
            }
            if (currentField === 'pass') {
                editInput.type = 'password';
                editInput.value = '';
                passwordToggleContainer.style.display = 'block';
                togglePasswordBtn.textContent = 'Hiện mật khẩu';
            } else {
                editInput.type = 'text';
                editInput.value = valSpan.textContent.trim();
                passwordToggleContainer.style.display = 'none';
            }
        });
    });

    // Toggle hiện/ẩn mật khẩu
    let passVisible = false;
    togglePasswordBtn.addEventListener('click', () => {
        passVisible = !passVisible;
        editInput.type = passVisible ? 'text' : 'password';
        togglePasswordBtn.textContent = passVisible ? 'Ẩn mật khẩu' : 'Hiện mật khẩu';
    });

    // Submit form qua ajax
    document.getElementById('editForm').addEventListener('submit', function(e) {
        e.preventDefault();
        if (!currentField) {
            editMessage.style.color = 'red';
            editMessage.textContent = 'Vui lòng chọn trường cần sửa.';
            return;
        }
        const val = editInput.value.trim();
        if (val === '') {
            editMessage.style.color = 'red';
            editMessage.textContent = 'Giá trị không được để trống.';
            return;
        }

        saveBtn.classList.add('loading');
        saveBtn.disabled = true;

        fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
                update_field: currentField,
                update_value: val
            })
        }).then(res => res.json())
        .then(data => {
            saveBtn.classList.remove('loading');
            saveBtn.disabled = false;
            if (data.success) {
                editMessage.style.color = 'green';
                editMessage.textContent = data.message;
                document.getElementById('field-' + currentField).textContent = currentField === 'pass' ? '********' : val;
                editInput.value = '';
                editInput.disabled = true;
                saveBtn.disabled = true;
                passwordToggleContainer.style.display = 'none';
            } else {
                editMessage.style.color = 'red';
                editMessage.textContent = data.message;
            }
        })
        .catch(() => {
            saveBtn.classList.remove('loading');
            saveBtn.disabled = false;
            editMessage.style.color = 'red';
            editMessage.textContent = 'Lỗi khi gửi dữ liệu';
        });
    });
    // Lưu tùy chọn hiển thị
    document.getElementById('savePreferencesBtn').addEventListener('click', function() {
        const theme = document.getElementById('themeSelect').value;
        const language = document.getElementById('languageSelect').value;
        const btn = this;
        const msg = document.getElementById('prefMessage');
        
        btn.classList.add('loading');
        btn.disabled = true;

        Promise.all([
            fetch('', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({ update_field: 'theme', update_value: theme })
            }),
            fetch('', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({ update_field: 'language', update_value: language })
            })
        ]).then(() => {
            btn.classList.remove('loading');
            btn.disabled = false;
            msg.style.color = 'green';
            msg.textContent = 'Đã lưu tùy chọn thành công!';
            
            // Apply theme on current page if needed, but not strictly required
            if (theme === 'dark') {
                document.body.classList.add('dark-mode');
            } else {
                document.body.classList.remove('dark-mode');
            }
        }).catch(() => {
            btn.classList.remove('loading');
            btn.disabled = false;
            msg.style.color = 'red';
            msg.textContent = 'Lỗi khi lưu tùy chọn';
        });
    });
});
</script>
</body>
</html>
</html>