
<?php
require_once __DIR__ . '/includes/session.php';
require_once('db.php');
notezy_session_start();

// Biến thông báo
$successMessage = "";
$errorMessage = "";

// Kiểm tra đăng nhập
if (!isset($_SESSION['id'])) {
    header('Location: index.php');
    exit();
}

$user_id = (int) $_SESSION['id'];

// Hàm xử lý nhãn
function processLabels($conn, $note_id, $labels_raw) {
    if ($labels_raw !== '') {
        $labels_array = array_filter(array_map('trim', explode(',', $labels_raw)));

        // Xóa nhãn cũ
        $sql_delete_labels = "DELETE FROM note_labels WHERE note_id = ?";
        $stm_delete_labels = $conn->prepare($sql_delete_labels);
        $stm_delete_labels->bind_param('i', $note_id);
        $stm_delete_labels->execute();
        $stm_delete_labels->close();

        foreach ($labels_array as $label_name) {
            // Kiểm tra label đã có chưa
            $stmt = $conn->prepare("SELECT label_id FROM labels WHERE name = ?");
            $stmt->bind_param("s", $label_name);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $label_id = $row['label_id'];
            } else {
                // Thêm label mới
                $stmt->close();
                $stmt = $conn->prepare("INSERT INTO labels (name) VALUES (?)");
                $stmt->bind_param("s", $label_name);
                $stmt->execute();
                $label_id = $stmt->insert_id;
            }
            $stmt->close();

            // Thêm vào note_labels
            $stmt = $conn->prepare("INSERT IGNORE INTO note_labels (note_id, label_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $note_id, $label_id);
            $stmt->execute();
            $stmt->close();
        }
    }
}

// Xử lý khi form được gửi hoặc lưu tự động
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $is_ajax = isset($_POST['autoSave'])
        || isset($_POST['ajax'])
        || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

    $title = trim($_POST['noteTitle'] ?? '');
    $content = trim($_POST['noteContent'] ?? '');
    $isPinned = isset($_POST['pinNote']) ? 1 : 0;
    // Labels từ checkbox (array) hoặc text (autoSave)
    if (isset($_POST['noteLabels']) && is_array($_POST['noteLabels'])) {
        $labels_raw = implode(',', array_map('trim', $_POST['noteLabels']));
    } else {
        $labels_raw = trim($_POST['noteLabels'] ?? '');
    }
    $password = trim($_POST['notePassword'] ?? '');
    $note_pin = trim($_POST['note_pin'] ?? $_POST['pin'] ?? '');
    $background_color = trim($_POST['background_color'] ?? '#ffffff');
    $text_color = trim($_POST['text_color'] ?? '#000000');
    $font_family = trim($_POST['font_family'] ?? 'Poppins');
    $note_id = isset($_POST['noteId']) && !empty($_POST['noteId']) ? (int) $_POST['noteId'] : 0;

    if ($title === '' || $content === '') {
        $errorMessage = "❌ Vui lòng nhập đầy đủ tiêu đề và nội dung.";
        if ($is_ajax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => $errorMessage], JSON_UNESCAPED_UNICODE);
            exit;
        }
    } else {
        $conn->begin_transaction();
        try {
            // Tắt ONLY_FULL_GROUP_BY
            $conn->query("SET SESSION sql_mode = ''");

            // Xử lý mật khẩu
            $password_hash = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null;

            // Xử lý mã PIN 4 số bảo mật
            $pin_hash = null;
            if ($note_pin !== '') {
                if (!preg_match('/^\d{4}$/', $note_pin)) {
                    throw new Exception("Mã bảo mật phải gồm đúng 4 chữ số (0000 - 9999).");
                }
                $pin_hash = password_hash($note_pin, PASSWORD_DEFAULT);
            }

            if ($note_id > 0) {
                // Cập nhật ghi chú
                if ($pin_hash !== null) {
                    $sql_update = "UPDATE notes SET title = ?, content = ?, pinned = ?, password_hash = ?, background_color = ?, text_color = ?, font_family = ?, pin_hash = ?, pin_set_at = NOW() WHERE note_id = ? AND user_id = ?";
                    $stm_update = $conn->prepare($sql_update);
                    if (!$stm_update) throw new Exception("Lỗi truy vấn: " . $conn->error);
                    $stm_update->bind_param('ssisssssii', $title, $content, $isPinned, $password_hash, $background_color, $text_color, $font_family, $pin_hash, $note_id, $user_id);
                } else {
                    $sql_update = "UPDATE notes SET title = ?, content = ?, pinned = ?, password_hash = ?, background_color = ?, text_color = ?, font_family = ? WHERE note_id = ? AND user_id = ?";
                    $stm_update = $conn->prepare($sql_update);
                    if (!$stm_update) throw new Exception("Lỗi truy vấn: " . $conn->error);
                    $stm_update->bind_param('ssissssii', $title, $content, $isPinned, $password_hash, $background_color, $text_color, $font_family, $note_id, $user_id);
                }
            } else {
                // Thêm ghi chú mới
                if ($pin_hash !== null) {
                    $sql_insert = "INSERT INTO notes (user_id, title, content, pinned, password_hash, background_color, text_color, font_family, pin_hash, pin_set_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                    $stm_insert = $conn->prepare($sql_insert);
                    if (!$stm_insert) throw new Exception("Lỗi truy vấn: " . $conn->error);
                    $stm_insert->bind_param('ississsss', $user_id, $title, $content, $isPinned, $password_hash, $background_color, $text_color, $font_family, $pin_hash);
                } else {
                    $sql_insert = "INSERT INTO notes (user_id, title, content, pinned, password_hash, background_color, text_color, font_family) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $stm_insert = $conn->prepare($sql_insert);
                    if (!$stm_insert) throw new Exception("Lỗi truy vấn: " . $conn->error);
                    $stm_insert->bind_param('ississss', $user_id, $title, $content, $isPinned, $password_hash, $background_color, $text_color, $font_family);
                }
            }

            if ($note_id > 0) {
                $stm_update->execute();
            } else {
                $stm_insert->execute();
                $note_id = $stm_insert->insert_id;
            }

            // Nếu có mã PIN, tự động mở khóa cho phiên này
            if ($pin_hash !== null) {
                if (!isset($_SESSION['pin_unlocked_notes'])) {
                    $_SESSION['pin_unlocked_notes'] = [];
                }
                $_SESSION['pin_unlocked_notes'][$note_id] = true;
                $ins_pin = $conn->prepare("INSERT INTO note_pin_unlocks (note_id, user_id, session_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE unlocked_at = NOW()");
                $curr_sess = session_id();
                $ins_pin->bind_param("iis", $note_id, $user_id, $curr_sess);
                $ins_pin->execute();
                $ins_pin->close();
            }

            // Xử lý báo thời / hẹn giờ nhắc nhở (nếu có)
            $reminder_at = !empty($_POST['reminder_at']) ? trim($_POST['reminder_at']) : null;
            if ($reminder_at) {
                $upd_rem = $conn->prepare("UPDATE notes SET reminder_at = ?, reminder_sent = 0 WHERE note_id = ? AND user_id = ?");
                if ($upd_rem) {
                    $upd_rem->bind_param("sii", $reminder_at, $note_id, $user_id);
                    $upd_rem->execute();
                    $upd_rem->close();
                }
            }

            // Xử lý nhãn
            processLabels($conn, $note_id, $labels_raw);

            // Xử lý upload ảnh (nếu có file đính kèm)
            $image_path = null;
            if (isset($_FILES['noteImage']) && $_FILES['noteImage']['error'] === UPLOAD_ERR_OK) {
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
                $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime  = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                if (!in_array($mime, $allowed_mimes, true)) {
                    throw new Exception('File không phải ảnh hợp lệ.');
                }
                $upload_dir = __DIR__ . '/uploads/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0775, true);
                $safe_name  = bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
                $target     = $upload_dir . $safe_name;
                if (move_uploaded_file($file['tmp_name'], $target)) {
                    $image_path = 'uploads/' . $safe_name;
                    $img_upd = $conn->prepare("UPDATE notes SET image_path = ? WHERE note_id = ?");
                    $img_upd->bind_param('si', $image_path, $note_id);
                    $img_upd->execute();
                } else {
                    throw new Exception('Không thể lưu file ảnh.');
                }
            }

            $conn->commit();
            $successMessage = "✅ Ghi chú đã được lưu thành công!";
            if ($is_ajax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => true,
                    'note_id' => $note_id,
                    'message' => 'Ghi chú đã được lưu thành công!',
                    'image_path' => $image_path
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        } catch (Exception $e) {
            $conn->rollback();
            $errorMessage = "❌ Lỗi khi lưu ghi chú: " . $e->getMessage();
            if ($is_ajax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => $errorMessage], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    }
}

// Lấy danh sách nhãn của user để hiển thị trong form
$user_labels = [];
$lsql = "SELECT DISTINCT l.label_id, l.name FROM labels l
         WHERE l.user_id = ? OR l.user_id IS NULL
         ORDER BY l.name ASC";
$lstmt = $conn->prepare($lsql);
if ($lstmt) {
    $lstmt->bind_param('i', $user_id);
    $lstmt->execute();
    $lresult = $lstmt->get_result();
    while ($lrow = $lresult->fetch_assoc()) {
        $user_labels[] = $lrow;
    }
    $lstmt->close();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Notezy - Thêm ghi chú</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            padding: 20px;
            color: #333333;
        }
        .container {
            max-width: 700px;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        h2 {
            font-size: 1.8rem;
            font-weight: bold;
            text-align: center;
            margin-bottom: 20px;
            color: #000000;
        }
        .form-label {
            font-weight: 600;
            color: #000000;
            margin-bottom: 6px;
        }
        .form-control, .form-select {
            border: 1.5px solid #ddd;
            border-radius: 6px;
            padding: 10px;
            background-color: #fefefe;
            color: #222222;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: #000000;
            box-shadow: 0 0 6px rgba(0, 0, 0, 0.1);
            background-color: #fff;
        }
        .form-check {
            margin: 15px 0;
        }
        .form-check input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        .form-check label {
            margin-left: 10px;
            font-weight: 500;
            color: #000000;
            cursor: pointer;
        }
        .btn-primary {
            background-color: #000000;
            border: none;
            padding: 10px 20px;
            font-size: 14px;
            border-radius: 6px;
            color: white;
            font-weight: 600;
            transition: background-color 0.3s ease;
        }
        .btn-primary:hover {
            background-color: #333333;
        }
        .color-picker-group {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }
        .color-picker-group div {
            flex: 1;
        }
        #autoSaveStatus {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 10px;
            display: none;
        }
        @media (max-width: 576px) {
            .container {
                padding: 15px;
            }
            .color-picker-group {
                flex-direction: column;
                gap: 10px;
            }
        }
        @media (max-width: 480px) {
            body {
                padding: 6px;
            }
            .container {
                padding: 10px;
                border-radius: 8px;
            }
            h2 {
                font-size: 1.2rem;
            }
            .form-control, textarea {
                font-size: 0.85rem;
            }
            .btn {
                width: 100%;
                font-size: 0.85rem;
            }
            #imagePreviewContainer img, #imagePreview {
                max-height: 130px !important;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Thêm ghi chú mới</h2>
        <?php if ($successMessage): ?>
            <div class="alert alert-success"><?= $successMessage ?></div>
        <?php endif; ?>
        <?php if ($errorMessage): ?>
            <div class="alert alert-danger"><?= $errorMessage ?></div>
        <?php endif; ?>
        <form id="noteForm" method="post" enctype="multipart/form-data">
            <input type="hidden" name="noteId" id="noteId" value="0">
            <div class="mb-3">
                <label for="noteTitle" class="form-label">Tiêu đề</label>
                <input type="text" class="form-control" id="noteTitle" name="noteTitle" required>
            </div>
            <div class="mb-3">
                <label for="noteContent" class="form-label">Nội dung</label>
                <textarea class="form-control" id="noteContent" name="noteContent" rows="5" required></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label d-flex justify-content-between align-items-center">
                    <span>Nhãn (Select Label)</span>
                    <a href="labels.php" target="_blank" class="small text-decoration-none"><i class="fas fa-tags me-1"></i>Quản lý nhãn</a>
                </label>
                <div class="mb-2">
                    <select id="quickSelectLabel" class="form-select" onchange="if(this.value){ const cb = document.querySelector(`input[name='noteLabels[]'][value='${this.value}']`); if(cb) { cb.checked = true; updateLabelText(); } else { const input = document.getElementById('newLabelInput'); input.value = this.value; document.getElementById('addNewLabelBtn').click(); } this.value = ''; }">
                        <option value="">Select Label [Chọn nhãn: Work, Study, Personal...] ▼</option>
                        <option value="Work">💼 Work</option>
                        <option value="Study">📚 Study</option>
                        <option value="Personal">👤 Personal</option>
                        <option value="Important">⭐ Important</option>
                        <?php foreach ($user_labels as $ul): ?>
                            <?php if (!in_array($ul['name'], ['Work', 'Study', 'Personal', 'Important'], true)): ?>
                                <option value="<?= htmlspecialchars($ul['name']) ?>"><?= htmlspecialchars($ul['name']) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="label-dropdown-wrapper" style="position:relative;">
                    <div id="labelDropdownToggle" class="form-control d-flex align-items-center justify-content-between" style="cursor:pointer; min-height:42px;">
                        <span id="labelDropdownText" class="text-muted">-- Chọn nhãn --</span>
                        <i class="fas fa-chevron-down ms-2" style="font-size:0.75rem;"></i>
                    </div>
                    <div id="labelDropdownPanel" class="border rounded bg-white shadow-sm" style="display:none; position:absolute; top:100%; left:0; right:0; z-index:999; max-height:220px; overflow-y:auto; padding:8px;">
                        <?php if (!empty($user_labels)): ?>
                            <?php foreach ($user_labels as $lbl): ?>
                                <div class="form-check">
                                    <input class="form-check-input label-checkbox" type="checkbox"
                                           name="noteLabels[]" value="<?= htmlspecialchars($lbl['name']) ?>"
                                           id="lbl_<?= $lbl['label_id'] ?>">
                                    <label class="form-check-label" for="lbl_<?= $lbl['label_id'] ?>">
                                        <?= htmlspecialchars($lbl['name']) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted mb-0" style="font-size:0.85rem;">Bạn chưa có nhãn nào. <a href="manage_labels.php" target="_blank">Tạo nhãn</a></p>
                        <?php endif; ?>
                        <hr class="my-2">
                        <div class="input-group input-group-sm">
                            <input type="text" id="newLabelInput" class="form-control" placeholder="Thêm nhãn mới..." style="font-size:0.85rem;">
                            <button type="button" id="addNewLabelBtn" class="btn btn-outline-secondary" style="font-size:0.8rem;">+ Thêm</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="pinNote" name="pinNote">
                <label class="form-check-label" for="pinNote">Ghim ghi chú</label>
            </div>
            <div class="mb-3 p-3 border rounded bg-light">
                <label for="note_pin" class="form-label fw-bold text-dark mb-1">
                    <i class="fas fa-shield-alt text-primary me-1"></i>Bảo mật sổ bằng mã 4 số (PIN)
                </label>
                <input type="password" class="form-control fw-bold" id="note_pin" name="note_pin" maxlength="4" pattern="\d{4}" inputmode="numeric" placeholder="•••• (Nhập 4 số, VD: 1234)" style="letter-spacing: 4px; max-width: 320px;">
                <small class="text-muted d-block mt-1">Để trống nếu không muốn khóa sổ. Nếu đặt mã, khi mở sổ trên trang chủ cần nhập đúng 4 số này để xem và tùy chỉnh.</small>
            </div>
            <div class="mb-3">
                <label for="notePassword" class="form-label">Mật khẩu chữ thông thường (tùy chọn)</label>
                <input type="password" class="form-control" id="notePassword" name="notePassword" placeholder="Nhập mật khẩu chữ nếu muốn">
            </div>
            <div class="color-picker-group">
                <div>
                    <label for="background_color" class="form-label">Màu nền</label>
                    <input type="color" class="form-control" id="background_color" name="background_color" value="#ffffff">
                </div>
                <div>
                    <label for="text_color" class="form-label">Màu chữ</label>
                    <input type="color" class="form-control" id="text_color" name="text_color" value="#000000">
                </div>
            </div>
            <div class="mb-3">
                <label for="font_family" class="form-label">Phông chữ</label>
                <select class="form-select" id="font_family" name="font_family">
                    <option value="Poppins">Poppins</option>
                    <option value="Roboto">Roboto</option>
                    <option value="Open Sans">Open Sans</option>
                    <option value="Playfair Display">Playfair Display</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="noteImage" class="form-label">Ảnh đính kèm (tối đa 5MB)</label>
                <input type="file" class="form-control" id="noteImage" name="noteImage" accept="image/*">
                <div id="imagePreviewContainer" style="display: none; margin-top: 10px;">
                    <img id="imagePreview" src="" alt="Preview" style="max-width: 100%; max-height: 200px; border-radius: 8px;">
                </div>
            </div>
            <button type="submit" class="btn btn-primary" id="saveBtn">Lưu ghi chú</button>
        </form>
        <div id="autoSaveStatus">Đang lưu...</div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        let lastSavedData = {};
        let isSaving = false;

        // ── Label dropdown toggle ──────────────────────────────────────────────
        const labelToggle  = document.getElementById('labelDropdownToggle');
        const labelPanel   = document.getElementById('labelDropdownPanel');
        const labelText    = document.getElementById('labelDropdownText');

        if (labelToggle && labelPanel) {
            labelToggle.addEventListener('click', (e) => {
                const isOpen = labelPanel.style.display !== 'none';
                labelPanel.style.display = isOpen ? 'none' : 'block';
            });
            // Close panel when clicking outside
            document.addEventListener('click', (e) => {
                if (!labelToggle.closest('.label-dropdown-wrapper').contains(e.target)) {
                    labelPanel.style.display = 'none';
                }
            });
        }

        // Update toggle text when checkboxes change
        function updateLabelText() {
            const checked = [...document.querySelectorAll('.label-checkbox:checked')].map(c => c.value);
            labelText.textContent = checked.length > 0 ? checked.join(', ') : '-- Chọn nhãn --';
            labelText.classList.toggle('text-muted', checked.length === 0);
        }
        document.querySelectorAll('.label-checkbox').forEach(cb => {
            cb.addEventListener('change', updateLabelText);
        });

        // Add new label dynamically
        const addNewLabelBtn = document.getElementById('addNewLabelBtn');
        const newLabelInput  = document.getElementById('newLabelInput');
        if (addNewLabelBtn && newLabelInput) {
            addNewLabelBtn.addEventListener('click', () => {
                const name = newLabelInput.value.trim();
                if (!name) return;
                // Check if already exists
                const existing = [...document.querySelectorAll('.label-checkbox')].find(cb => cb.value.toLowerCase() === name.toLowerCase());
                if (existing) {
                    existing.checked = true;
                    updateLabelText();
                    newLabelInput.value = '';
                    return;
                }
                // Add new checkbox
                const uid = 'new_' + Date.now();
                const div = document.createElement('div');
                div.className = 'form-check';
                div.innerHTML = `<input class="form-check-input label-checkbox" type="checkbox" name="noteLabels[]" value="${name}" id="${uid}" checked>
                                 <label class="form-check-label" for="${uid}">${name}</label>`;
                div.querySelector('.label-checkbox').addEventListener('change', updateLabelText);
                // Insert before the <hr>
                const hr = labelPanel.querySelector('hr');
                labelPanel.insertBefore(div, hr);
                updateLabelText();
                newLabelInput.value = '';
            });
            newLabelInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') { e.preventDefault(); addNewLabelBtn.click(); }
            });
        }

        // ── Auto-save (text fields only, not image) ───────────────────────────
        function getAutoSaveData() {
            const checked = [...document.querySelectorAll('.label-checkbox:checked')].map(c => c.value);
            return {
                noteId:           document.getElementById('noteId').value,
                noteTitle:        document.getElementById('noteTitle').value,
                noteContent:      document.getElementById('noteContent').value,
                noteLabels:       checked.join(','),
                pinNote:          document.getElementById('pinNote').checked,
                notePassword:     document.getElementById('notePassword').value,
                background_color: document.getElementById('background_color').value,
                text_color:       document.getElementById('text_color').value,
                font_family:      document.getElementById('font_family').value
            };
        }

        function hasChanges(currentData) {
            return JSON.stringify(currentData) !== JSON.stringify(lastSavedData);
        }

        async function autoSave() {
            if (isSaving) return;
            const formData = getAutoSaveData();
            if (!hasChanges(formData)) return;

            isSaving = true;
            const statusEl = document.getElementById('autoSaveStatus');
            statusEl.style.display = 'block';
            statusEl.textContent = 'Đang lưu...';

            const data = new FormData();
            for (const key in formData) {
                // For array-style labels in autoSave, send as flat string
                if (key === 'noteLabels') {
                    data.append('noteLabels', formData[key]);
                } else {
                    data.append(key, formData[key]);
                }
            }
            data.append('autoSave', 'true');

            try {
                const response = await fetch('themghichu.php', { method: 'POST', body: data });
                const result = await response.json();
                if (result.success) {
                    lastSavedData = formData;
                    document.getElementById('noteId').value = result.note_id;
                    statusEl.textContent = 'Đã lưu tự động!';
                    setTimeout(() => { statusEl.style.display = 'none'; }, 1500);
                } else {
                    statusEl.textContent = 'Lỗi lưu tự động!';
                }
            } catch (err) {
                statusEl.textContent = 'Lỗi lưu tự động!';
            } finally {
                isSaving = false;
            }
        }

        // Trigger auto-save on text changes
        document.querySelectorAll('#noteForm input:not([type="file"]):not([type="checkbox"]), #noteForm textarea, #noteForm select').forEach(el => {
            el.addEventListener('input', () => {
                clearTimeout(window.autoSaveTimeout);
                window.autoSaveTimeout = setTimeout(autoSave, 3000);
            });
        });

        // ── Image preview ─────────────────────────────────────────────────────
        document.getElementById('noteImage').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                if (file.size > 5 * 1024 * 1024) {
                    Swal.fire({ icon: 'error', title: 'Ảnh quá lớn', text: 'Vui lòng chọn ảnh nhỏ hơn 5MB.' });
                    this.value = '';
                    document.getElementById('imagePreviewContainer').style.display = 'none';
                    return;
                }
                const reader = new FileReader();
                reader.onload = (ev) => {
                    document.getElementById('imagePreview').src = ev.target.result;
                    document.getElementById('imagePreviewContainer').style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                document.getElementById('imagePreviewContainer').style.display = 'none';
            }
        });

        // ── Form submit (single-step: image now handled server-side) ──────────
        document.getElementById('noteForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            formData.append('autoSave', 'true'); // Response as JSON

            const btn = document.getElementById('saveBtn');
            btn.disabled = true;
            btn.textContent = 'Đang lưu...';

            try {
                const response = await fetch('themghichu.php', { method: 'POST', body: formData });
                const result = await response.json();

                if (!result.success) {
                    throw new Error(result.message || 'Lỗi khi lưu ghi chú');
                }

                window.parent.location.reload();
            } catch (error) {
                Swal.fire({ title: 'Lỗi!', text: error.message, icon: 'error', timer: 3000 });
                btn.disabled = false;
                btn.textContent = 'Lưu ghi chú';
            }
        });
    </script>
</body>
</html>
