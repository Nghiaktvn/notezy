<?php
require_once __DIR__ . '/includes/session.php';
require_once('db.php');
notezy_session_start();

if (!isset($_SESSION['id'])) {
    header('Location: index.php');
    exit();
}

$user_id = (int)$_SESSION['id'];
$msg = '';
$msg_type = '';

// SECURITY FIX (audit 2026-09-02): this legacy page duplicated the same
// global-name label bug as the old api/labels.php (see that file's header
// comment for the full explanation) — it also had zero CSRF token check.
// Both are fixed below using the same per-user scoping / claim-or-fork
// helpers now shared with api/labels.php.
require_once __DIR__ . '/includes/csrf.php';

// ── Handle POST actions (create/rename/delete) ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $msg = 'Phiên làm việc đã hết hạn, vui lòng tải lại trang và thử lại.'; $msg_type = 'danger';
    } elseif ($action === 'create') {
        $name = trim($_POST['label_name'] ?? '');
        if ($name === '') {
            $msg = 'Tên nhãn không được để trống.'; $msg_type = 'danger';
        } elseif (mb_strlen($name, 'UTF-8') > 100) {
            $msg = 'Tên nhãn quá dài (tối đa 100 ký tự).'; $msg_type = 'danger';
        } else {
            // Scoped to this user (or reuse of a legacy unowned label) —
            // no longer a global name lookup.
            $chk = $conn->prepare("SELECT label_id FROM labels WHERE name = ? AND user_id = ?");
            $chk->bind_param('si', $name, $user_id);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $msg = "Nhãn \"" . htmlspecialchars($name) . "\" đã tồn tại."; $msg_type = 'warning';
            } else {
                $ins = $conn->prepare("INSERT INTO labels (name, user_id) VALUES (?, ?)");
                $ins->bind_param('si', $name, $user_id);
                if ($ins->execute()) {
                    $msg = "Đã tạo nhãn \"" . htmlspecialchars($name) . "\" thành công."; $msg_type = 'success';
                } elseif ($conn->errno === 1062) {
                    $msg = "Nhãn \"" . htmlspecialchars($name) . "\" đã tồn tại."; $msg_type = 'warning';
                } else {
                    $msg = 'Tạo nhãn thất bại.'; $msg_type = 'danger';
                }
            }
        }
    } elseif ($action === 'rename') {
        $label_id = (int)($_POST['label_id'] ?? 0);
        $new_name = trim($_POST['label_name'] ?? '');
        if (!$label_id) {
            $msg = 'Thiếu ID nhãn.'; $msg_type = 'danger';
        } elseif ($new_name === '') {
            $msg = 'Tên nhãn không được để trống.'; $msg_type = 'danger';
        } else {
            // Claim-or-fork resolution instead of a bare note-ownership check —
            // guarantees the row being renamed can never be shared with another user.
            $owned_label_id = resolve_owned_label_for_write($conn, $label_id, $user_id);
            if ($owned_label_id === 0) {
                $msg = 'Bạn không có quyền sửa nhãn này.'; $msg_type = 'danger';
            } else {
                $upd = $conn->prepare("UPDATE labels SET name = ? WHERE label_id = ? AND (user_id = ? OR user_id IS NULL)");
                $upd->bind_param('sii', $new_name, $owned_label_id, $user_id);
                if ($upd->execute()) {
                    $msg = "Đã đổi tên nhãn thành \"" . htmlspecialchars($new_name) . "\"."; $msg_type = 'success';
                } elseif ($conn->errno === 1062) {
                    $msg = "Bạn đã có nhãn tên \"" . htmlspecialchars($new_name) . "\"."; $msg_type = 'warning';
                } else {
                    $msg = 'Đổi tên thất bại.'; $msg_type = 'danger';
                }
            }
        }
    } elseif ($action === 'delete') {
        $label_id = (int)($_POST['label_id'] ?? 0);
        if (!$label_id) {
            $msg = 'Thiếu ID nhãn.'; $msg_type = 'danger';
        } else {
            $owned_label_id = resolve_owned_label_for_write($conn, $label_id, $user_id);
            if ($owned_label_id === 0) {
                $msg = 'Bạn không có quyền xoá nhãn này.'; $msg_type = 'danger';
            } else {
                // Remove from user's notes
                $del_nl = $conn->prepare(
                    "DELETE nl FROM note_labels nl JOIN notes n ON nl.note_id = n.note_id WHERE nl.label_id = ? AND n.user_id = ?"
                );
                $del_nl->bind_param('ii', $owned_label_id, $user_id);
                $del_nl->execute();
                // Orphan cleanup — only for this user's own (or now-unreferenced legacy) label
                $orphan = $conn->prepare("SELECT COUNT(*) as cnt FROM note_labels WHERE label_id = ?");
                $orphan->bind_param('i', $owned_label_id);
                $orphan->execute();
                if ($orphan->get_result()->fetch_assoc()['cnt'] == 0) {
                    $del_l = $conn->prepare("DELETE FROM labels WHERE label_id = ? AND (user_id = ? OR user_id IS NULL)");
                    $del_l->bind_param('ii', $owned_label_id, $user_id);
                    $del_l->execute();
                }
                $msg = 'Đã xoá nhãn thành công.'; $msg_type = 'success';
            }
        }
    }
}

/**
 * Shared with api/labels.php — see that file for full documentation.
 * Duplicated here (rather than required) because this legacy page is a
 * plain script, not part of the api/ JSON layer; kept in exact behavioral
 * sync with api/labels.php's version.
 */
function find_or_create_label(mysqli $conn, string $name, int $user_id): int {
    $sel = $conn->prepare("SELECT label_id FROM labels WHERE name = ? AND (user_id = ? OR user_id IS NULL) LIMIT 1");
    $sel->bind_param("si", $name, $user_id);
    $sel->execute();
    $row = $sel->get_result()->fetch_assoc();
    if ($row) return (int)$row['label_id'];
    $ins = $conn->prepare("INSERT INTO labels (name, user_id) VALUES (?, ?)");
    $ins->bind_param("si", $name, $user_id);
    if ($ins->execute()) return (int)$conn->insert_id;
    if ($conn->errno === 1062) {
        $sel2 = $conn->prepare("SELECT label_id FROM labels WHERE name = ? AND user_id = ? LIMIT 1");
        $sel2->bind_param("si", $name, $user_id);
        $sel2->execute();
        $row2 = $sel2->get_result()->fetch_assoc();
        if ($row2) return (int)$row2['label_id'];
    }
    return 0;
}

function resolve_owned_label_for_write(mysqli $conn, int $label_id, int $user_id): int {
    $lbl = $conn->prepare("SELECT label_id, name, user_id FROM labels WHERE label_id = ?");
    $lbl->bind_param("i", $label_id);
    $lbl->execute();
    $label = $lbl->get_result()->fetch_assoc();
    if (!$label) return 0;
    if ($label['user_id'] !== null && (int)$label['user_id'] === $user_id) return $label_id;
    if ($label['user_id'] !== null) return 0;

    $own = $conn->prepare(
        "SELECT COUNT(*) as cnt FROM note_labels nl JOIN notes n ON nl.note_id = n.note_id WHERE nl.label_id = ? AND n.user_id = ?"
    );
    $own->bind_param("ii", $label_id, $user_id);
    $own->execute();
    if ((int)$own->get_result()->fetch_assoc()['cnt'] === 0) return 0;

    $others = $conn->prepare(
        "SELECT COUNT(DISTINCT n.user_id) as cnt FROM note_labels nl JOIN notes n ON nl.note_id = n.note_id WHERE nl.label_id = ? AND n.user_id != ?"
    );
    $others->bind_param("ii", $label_id, $user_id);
    $others->execute();
    $other_users = (int)$others->get_result()->fetch_assoc()['cnt'];

    if ($other_users === 0) {
        $claim = $conn->prepare("UPDATE labels SET user_id = ? WHERE label_id = ?");
        $claim->bind_param("ii", $user_id, $label_id);
        $claim->execute();
        return $label_id;
    }

    $new_id = find_or_create_label($conn, $label['name'], $user_id);
    if ($new_id === $label_id || $new_id === 0) return 0;
    $remap = $conn->prepare(
        "UPDATE note_labels nl JOIN notes n ON nl.note_id = n.note_id SET nl.label_id = ? WHERE nl.label_id = ? AND n.user_id = ?"
    );
    $remap->bind_param("iii", $new_id, $label_id, $user_id);
    $remap->execute();
    return $new_id;
}

// ── Load labels for this user ─────────────────────────────────────────────
$labels = [];
// FIX: was an INNER JOIN scoped only via notes, so (a) a label the user just
// created but hasn't attached to any note yet would never appear, and
// (b) it matched legacy shared labels by note ownership only, not the new
// user_id column. LEFT JOIN + scope by l.user_id (direct) or legacy NULL.
$sql = "SELECT l.label_id, l.name, COUNT(DISTINCT CASE WHEN n.user_id = ? THEN nl.note_id END) as note_count
        FROM labels l
        LEFT JOIN note_labels nl ON l.label_id = nl.label_id
        LEFT JOIN notes n ON nl.note_id = n.note_id AND n.user_id = ?
        WHERE l.user_id = ? OR (l.user_id IS NULL AND EXISTS (
            SELECT 1 FROM note_labels nl2 JOIN notes n2 ON nl2.note_id = n2.note_id
            WHERE nl2.label_id = l.label_id AND n2.user_id = ?
        ))
        GROUP BY l.label_id, l.name
        ORDER BY l.name ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('iiii', $user_id, $user_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) $labels[] = $row;
$stmt->close();

$theme = $_SESSION['theme'] ?? 'light';
$is_dark = ($theme === 'dark');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8" />
    <title>Quản lý nhãn — Notezy</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        :root {
            --bg: <?= $is_dark ? '#121212' : '#f5f5f5' ?>;
            --card-bg: <?= $is_dark ? '#1e1e1e' : '#ffffff' ?>;
            --text: <?= $is_dark ? '#e0e0e0' : '#333333' ?>;
            --border: <?= $is_dark ? '#333' : '#e0e0e0' ?>;
            --accent: #0d6efd;
            --muted: <?= $is_dark ? '#888' : '#6c757d' ?>;
        }
        body { background: var(--bg); color: var(--text); font-family: 'Segoe UI', sans-serif; padding-top: 70px; }
        .navbar { background: var(--card-bg) !important; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .navbar-brand, .nav-link { color: var(--text) !important; }
        .card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 12px; box-shadow: 0 4px 16px rgba(0,0,0,0.07); }
        .card-header { background: transparent; border-bottom: 1px solid var(--border); padding: 20px 24px; }
        .card-header h4 { margin: 0; font-weight: 700; font-size: 1.25rem; }
        .label-row { display: flex; align-items: center; justify-content: space-between;
                     padding: 13px 20px; border-bottom: 1px solid var(--border); transition: background 0.15s; }
        .label-row:last-child { border-bottom: none; }
        .label-row:hover { background: <?= $is_dark ? '#252525' : '#f8f9fa' ?>; }
        .label-name { font-weight: 500; font-size: 1rem; }
        .label-count { font-size: 0.8rem; color: var(--muted); margin-left: 8px; }
        .badge-label { display: inline-flex; align-items: center; gap: 6px;
                       background: var(--accent); color: #fff; border-radius: 20px;
                       padding: 4px 12px; font-size: 0.85rem; font-weight: 500; }
        .btn-action { padding: 5px 12px; font-size: 0.8rem; border-radius: 6px; }
        .actions-group { display: flex; gap: 6px; }
        .create-form { display: flex; gap: 10px; align-items: center; }
        .create-form input { flex: 1; border-radius: 8px; border: 1px solid var(--border);
                             background: var(--bg); color: var(--text); padding: 10px 14px; }
        .create-form input:focus { border-color: var(--accent); outline: none; box-shadow: 0 0 0 3px rgba(13,110,253,0.15); }
        .empty-state { text-align: center; padding: 48px 24px; color: var(--muted); }
        .empty-state i { font-size: 3rem; margin-bottom: 12px; opacity: 0.4; }
        @media (max-width: 576px) { .create-form { flex-direction: column; } .create-form input { width: 100%; } }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg fixed-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index_notezy.php"><i class="fas fa-arrow-left me-2"></i>Notezy</a>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="index_notezy.php"><i class="fas fa-home me-1"></i>Trang chủ</a></li>
                <li class="nav-item"><a class="nav-link active fw-bold" href="manage_labels.php"><i class="fas fa-tags me-1"></i>Quản lý nhãn</a></li>
                <li class="nav-item"><a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt me-1"></i>Đăng xuất</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container" style="max-width: 680px; padding-bottom: 60px;">

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb" style="background:transparent; padding:0; font-size:0.875rem;">
            <li class="breadcrumb-item"><a href="index_notezy.php" class="text-decoration-none">Trang chủ</a></li>
            <li class="breadcrumb-item active">Quản lý nhãn</li>
        </ol>
    </nav>

    <h1 class="fw-bold mb-4" style="font-size: 1.8rem;"><i class="fas fa-tags me-2 text-primary"></i>Quản lý nhãn</h1>

    <!-- Alert message -->
    <?php if ($msg): ?>
        <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show" role="alert">
            <?= $msg ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Create new label -->
    <div class="card mb-4">
        <div class="card-header">
            <h4><i class="fas fa-plus-circle me-2 text-success"></i>Thêm nhãn mới</h4>
        </div>
        <div class="card-body p-4">
            <form method="POST" action="" id="createLabelForm">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <div class="create-form">
                    <input type="text" name="label_name" id="newLabelName" maxlength="100"
                           placeholder="Nhập tên nhãn..." required autocomplete="off">
                    <button type="submit" class="btn btn-primary" style="white-space:nowrap; border-radius:8px;">
                        <i class="fas fa-plus me-1"></i>Tạo nhãn
                    </button>
                </div>
                <div class="form-text mt-2" style="color: var(--muted); font-size: 0.8rem;">
                    Nhãn sẽ được tạo và có thể gắn vào ghi chú khi tạo hoặc chỉnh sửa.
                </div>
            </form>
        </div>
    </div>

    <!-- Labels list -->
    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h4><i class="fas fa-list me-2 text-primary"></i>Danh sách nhãn</h4>
            <span class="badge bg-secondary"><?= count($labels) ?> nhãn</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($labels)): ?>
                <div class="empty-state">
                    <i class="fas fa-tag d-block"></i>
                    <p class="mb-0">Bạn chưa có nhãn nào.</p>
                    <p class="mb-0" style="font-size:0.9rem;">Tạo nhãn đầu tiên ở trên và gắn vào ghi chú!</p>
                </div>
            <?php else: ?>
                <?php foreach ($labels as $label): ?>
                    <div class="label-row" id="label-row-<?= $label['label_id'] ?>">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge-label">
                                <i class="fas fa-tag" style="font-size:0.7rem;"></i>
                                <?= htmlspecialchars($label['name']) ?>
                            </span>
                            <span class="label-count"><?= (int)$label['note_count'] ?> ghi chú</span>
                        </div>
                        <div class="actions-group">
                            <button class="btn btn-outline-primary btn-action"
                                    onclick="showRenameModal(<?= $label['label_id'] ?>, '<?= addslashes(htmlspecialchars($label['name'])) ?>')">
                                <i class="fas fa-edit me-1"></i>Sửa
                            </button>
                            <button class="btn btn-outline-danger btn-action"
                                    onclick="confirmDelete(<?= $label['label_id'] ?>, '<?= addslashes(htmlspecialchars($label['name'])) ?>', <?= (int)$label['note_count'] ?>)">
                                <i class="fas fa-trash me-1"></i>Xoá
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Rename Modal -->
<div class="modal fade" id="renameModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--card-bg); color: var(--text); border-color: var(--border);">
            <div class="modal-header" style="border-color: var(--border);">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Đổi tên nhãn</h5>
                <button type="button" class="btn-close <?= $is_dark ? 'btn-close-white' : '' ?>" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="rename">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="label_id" id="renameLabelId">
                <div class="modal-body">
                    <label class="form-label fw-semibold" for="renameLabelName">Tên mới</label>
                    <input type="text" class="form-control" id="renameLabelName" name="label_name"
                           maxlength="100" required autocomplete="off"
                           style="background: var(--bg); color: var(--text); border-color: var(--border);">
                </div>
                <div class="modal-footer" style="border-color: var(--border);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Huỷ</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Lưu</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete form (hidden, submitted via JS) -->
<form method="POST" action="" id="deleteForm" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="label_id" id="deleteLabelId">
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function showRenameModal(labelId, labelName) {
        document.getElementById('renameLabelId').value = labelId;
        document.getElementById('renameLabelName').value = labelName;
        const modal = new bootstrap.Modal(document.getElementById('renameModal'));
        modal.show();
        setTimeout(() => document.getElementById('renameLabelName').select(), 300);
    }

    function confirmDelete(labelId, labelName, noteCount) {
        const text = noteCount > 0
            ? `Nhãn này đang được gắn với ${noteCount} ghi chú. Nhãn sẽ bị gỡ khỏi tất cả ghi chú.`
            : 'Nhãn sẽ bị xoá vĩnh viễn.';
        Swal.fire({
            title: `Xoá nhãn "${labelName}"?`,
            text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: '<i class="fas fa-trash me-1"></i>Xoá',
            cancelButtonText: 'Huỷ'
        }).then(result => {
            if (result.isConfirmed) {
                document.getElementById('deleteLabelId').value = labelId;
                document.getElementById('deleteForm').submit();
            }
        });
    }

    // Auto-focus on create input
    document.getElementById('newLabelName').focus();

    // Submit create form on Enter
    document.getElementById('newLabelName').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); document.getElementById('createLabelForm').submit(); }
    });
</script>
</body>
</html>
