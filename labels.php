<?php
require_once __DIR__ . '/includes/session.php';
/**
 * labels.php
 * Simple & direct Note Label Manager
 * Features:
 *   - Quick Add Presets: [ + Work ] [ + Study ] [ + Personal ]
 *   - Custom Label Input: [ Tên nhãn... ] [ Thêm ]
 *   - Table of Labels: ID, Name, Created At, Action (🗑 Delete)
 */

require_once __DIR__ . '/db.php';
notezy_session_start();

if (!isset($_SESSION['id'])) {
    $_SESSION['id'] = 1;
    $_SESSION['username'] = 'Demo User';
}

$user_id = (int)$_SESSION['id'];
$conn = null;
$db_error = null;
try {
    $conn = create_connect();
} catch (Throwable $e) {
    $db_error = $e->getMessage();
}

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

if (!$conn || $conn->connect_error) {
    if (!isset($_SESSION['mock_labels'])) {
        $_SESSION['mock_labels'] = [
            1 => ['label_id' => 1, 'name' => 'Work', 'created_at' => date('Y-m-d H:i:s'), 'note_count' => 3],
            2 => ['label_id' => 2, 'name' => 'Study', 'created_at' => date('Y-m-d H:i:s'), 'note_count' => 5],
            3 => ['label_id' => 3, 'name' => 'Personal', 'created_at' => date('Y-m-d H:i:s'), 'note_count' => 2],
        ];
    }
}

// ── 1. ACTION: DELETE LABEL ──────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $del_id = (int)$_GET['id'];
    if ($conn && !$conn->connect_error && $del_id > 0) {
        // Delete junction relations first
        $conn->query("DELETE FROM note_labels WHERE label_id = $del_id");
        $del_stmt = $conn->prepare("DELETE FROM labels WHERE label_id = ? AND (user_id = ? OR user_id IS NULL)");
        if ($del_stmt) {
            $del_stmt->bind_param("ii", $del_id, $user_id);
            if ($del_stmt->execute()) {
                $_SESSION['flash_success'] = "Đã xóa nhãn thành công!";
            } else {
                $_SESSION['flash_error'] = "Lỗi khi xóa nhãn: " . $del_stmt->error;
            }
            $del_stmt->close();
        }
    } else {
        unset($_SESSION['mock_labels'][$del_id]);
        $_SESSION['flash_success'] = "Đã xóa nhãn thành công (Offline Mode)!";
    }
    header('Location: labels.php');
    exit();
}

// ── 2. ACTION: ADD LABEL (POST OR QUICK GET) ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['label_name'])) {
    $name = trim($_POST['label_name']);
    if (empty($name)) {
        $flash_error = "Vui lòng nhập tên nhãn.";
    } elseif ($conn && !$conn->connect_error) {
        // Check if label exists for this user
        $chk = $conn->prepare("SELECT label_id FROM labels WHERE name = ? AND (user_id = ? OR user_id IS NULL) LIMIT 1");
        $chk->bind_param("si", $name, $user_id);
        $chk->execute();
        $exists = $chk->get_result()->fetch_assoc();
        $chk->close();

        if ($exists) {
            $flash_error = "Nhãn '{$name}' đã tồn tại.";
        } else {
            $ins = $conn->prepare("INSERT INTO labels (name, user_id) VALUES (?, ?)");
            if ($ins) {
                $ins->bind_param("si", $name, $user_id);
                if ($ins->execute()) {
                    $_SESSION['flash_success'] = "Đã tạo nhãn '{$name}' thành công!";
                    header('Location: labels.php');
                    exit();
                } else {
                    $flash_error = "Lỗi: " . $ins->error;
                }
                $ins->close();
            }
        }
    } else {
        $new_id = (count($_SESSION['mock_labels'] ?? []) ? max(array_keys($_SESSION['mock_labels'])) : 0) + 1;
        $_SESSION['mock_labels'][$new_id] = [
            'label_id' => $new_id,
            'name' => $name,
            'created_at' => date('Y-m-d H:i:s'),
            'note_count' => 0
        ];
        $_SESSION['flash_success'] = "Đã tạo nhãn '{$name}' thành công (Offline Mode)!";
        header('Location: labels.php');
        exit();
    }
}

// Quick Preset Add via GET (e.g. labels.php?quick=Work)
if (isset($_GET['quick'])) {
    $preset = trim($_GET['quick']);
    if (in_array($preset, ['Work', 'Study', 'Personal', 'Shopping', 'Important', 'Công việc', 'Học tập', 'Cá nhân'], true)) {
        if ($conn && !$conn->connect_error) {
            $ins = $conn->prepare("INSERT IGNORE INTO labels (name, user_id) VALUES (?, ?)");
            if ($ins) {
                $ins->bind_param("si", $preset, $user_id);
                $ins->execute();
                $ins->close();
                $_SESSION['flash_success'] = "Đã thêm nhanh nhãn '{$preset}'!";
            }
        } else {
            $new_id = (count($_SESSION['mock_labels'] ?? []) ? max(array_keys($_SESSION['mock_labels'])) : 0) + 1;
            $_SESSION['mock_labels'][$new_id] = [
                'label_id' => $new_id,
                'name' => $preset,
                'created_at' => date('Y-m-d H:i:s'),
                'note_count' => 0
            ];
            $_SESSION['flash_success'] = "Đã thêm nhanh nhãn '{$preset}' (Offline Mode)!";
        }
    }
    header('Location: labels.php');
    exit();
}

// ── 3. FETCH ALL LABELS ──────────────────────────────────────────────────────
$labels = [];
if ($conn && !$conn->connect_error) {
    $st = $conn->prepare("
        SELECT l.label_id, l.name, l.created_at, COUNT(nl.note_id) AS note_count
        FROM labels l
        LEFT JOIN note_labels nl ON l.label_id = nl.label_id
        WHERE l.user_id = ? OR l.user_id IS NULL
        GROUP BY l.label_id, l.name, l.created_at
        ORDER BY l.label_id DESC
    ");
    if ($st) {
        $st->bind_param("i", $user_id);
        $st->execute();
        $res = $st->get_result();
        while ($row = $res->fetch_assoc()) {
            $labels[] = $row;
        }
        $st->close();
    }
} else {
    $labels = array_values($_SESSION['mock_labels'] ?? []);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Nhãn (Note Labels) - Notezy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8fafc;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #1e293b;
        }
        .header-bar {
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
        }
        .card-custom {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.04);
            background: #ffffff;
        }
        .btn-preset {
            border-radius: 20px;
            padding: 6px 14px;
            font-size: 0.85rem;
            font-weight: 500;
            border: 1px dashed #cbd5e1;
            background: #f8fafc;
            color: #334155;
            transition: all 0.2s;
        }
        .btn-preset:hover {
            background: #3b82f6;
            color: #ffffff;
            border-color: #3b82f6;
            transform: translateY(-1px);
        }
    </style>
</head>
<body>

    <header class="header-bar py-3 mb-4">
        <div class="container d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <a href="index_notezy.php" class="text-decoration-none text-dark fw-bold fs-5">
                    <i class="fas fa-tags text-primary me-2"></i>Notezy Labels
                </a>
                <span class="badge bg-primary">Quản lý Nhãn Ghi Chú</span>
            </div>
            <a href="index_notezy.php" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Quay lại trang chủ
            </a>
        </div>
    </header>

    <div class="container pb-5" style="max-width: 800px;">

        <?php if (!empty($flash_success)): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <i class="fas fa-check-circle me-1"></i> <?= htmlspecialchars($flash_success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($flash_error)): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <i class="fas fa-exclamation-circle me-1"></i> <?= htmlspecialchars($flash_error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Form Add Label & Presets -->
        <div class="card card-custom p-4 mb-4">
            <h5 class="fw-bold mb-3"><i class="fas fa-plus-circle text-primary me-2"></i>Thêm Nhãn Mới (Add Label)</h5>
            
            <form method="post" action="labels.php" class="d-flex gap-2 mb-3">
                <input type="text" name="label_name" class="form-control" placeholder="Nhập tên nhãn (VD: Work, Study, Personal...)" required autofocus>
                <button type="submit" class="btn btn-primary px-4 fw-semibold text-nowrap">
                    <i class="fas fa-plus me-1"></i> Add Label
                </button>
            </form>

            <div class="d-flex align-items-center gap-2 flex-wrap pt-2 border-top">
                <span class="text-muted small fw-semibold"><i class="fas fa-magic me-1"></i>Thêm nhanh gợi ý:</span>
                <a href="labels.php?quick=Work" class="btn btn-preset"><i class="fas fa-briefcase me-1 text-primary"></i>+ Work</a>
                <a href="labels.php?quick=Study" class="btn btn-preset"><i class="fas fa-graduation-cap me-1 text-success"></i>+ Study</a>
                <a href="labels.php?quick=Personal" class="btn btn-preset"><i class="fas fa-user me-1 text-warning"></i>+ Personal</a>
                <a href="labels.php?quick=Important" class="btn btn-preset"><i class="fas fa-star me-1 text-danger"></i>+ Important</a>
            </div>
        </div>

        <!-- Table of Labels -->
        <div class="card card-custom p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0"><i class="fas fa-list text-secondary me-2"></i>Danh Sách Nhãn Hiện Có</h5>
                <span class="badge bg-secondary"><?= count($labels) ?> nhãn</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 70px;">ID</th>
                            <th>Tên Nhãn (Label Name)</th>
                            <th style="width: 140px;" class="text-center">Số Ghi Chú</th>
                            <th style="width: 120px;" class="text-end">Hành Động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($labels)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">
                                    Chưa có nhãn nào. Hãy bấm vào các nhãn gợi ý phía trên (<strong>Work</strong>, <strong>Study</strong>, <strong>Personal</strong>) hoặc nhập tên để tạo mới!
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($labels as $lbl): ?>
                                <tr>
                                    <td class="text-secondary fw-semibold">#<?= $lbl['label_id'] ?></td>
                                    <td>
                                        <span class="badge bg-light text-dark border px-3 py-2 fs-6">
                                            <i class="fas fa-tag text-primary me-1"></i><?= htmlspecialchars($lbl['name']) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-info-subtle text-info-emphasis px-2 py-1">
                                            <?= (int)$lbl['note_count'] ?> ghi chú
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="labels.php?action=delete&id=<?= $lbl['label_id'] ?>" 
                                           class="btn btn-sm btn-outline-danger" 
                                           onclick="return confirm('Bạn có chắc muốn xóa nhãn \'<?= htmlspecialchars(addslashes($lbl['name'])) ?>\'?')"
                                           title="Xóa nhãn">
                                            <i class="fas fa-trash-alt me-1"></i>Delete
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
