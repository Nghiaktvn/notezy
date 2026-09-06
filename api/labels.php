<?php
require_once dirname(__DIR__) . '/includes/session.php';
/**
 * api/labels.php
 *
 * SECURITY FIX (audit 2026-09-02): this file previously treated `labels.name`
 * as globally unique across ALL users (`WHERE name = ?` with no user_id
 * filter). Two users creating a label with the same name silently shared one
 * row, so renaming or deleting "your" label could rename/delete someone
 * else's. The `labels` table already gained a nullable `user_id` column and
 * a per-user UNIQUE (user_id, name) key in migrations.sql — this file now
 * actually uses it, consistent with api/ai/lib/AiServices.php::findOrCreateLabel().
 *
 * Legacy rows created before this fix have user_id = NULL and may still be
 * referenced by several users' notes. We keep those readable/usable (fallback
 * via note ownership), but any WRITE (rename/delete) to a legacy label now
 * "claims" it for the acting user if they're its only user, or "forks" it
 * (creates a private copy + remaps only this user's notes) if others still
 * use it — so one user's edit can never again silently affect another's data.
 */

require_once 'db.php';
notezy_session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}
$user_id = (int)$_SESSION['id'];
$method = $_SERVER['REQUEST_METHOD'];

/**
 * Find (or create) a label id that is safely usable by this user:
 *  - a label they already own (user_id = $user_id), OR
 *  - a legacy label (user_id IS NULL) with the same name — reused as-is
 *    for READS/attach only; callers doing a rename/delete must go through
 *    resolve_owned_label_for_write() instead.
 */
function find_or_create_label(mysqli $conn, string $name, int $user_id): int {
    $sel = $conn->prepare("SELECT label_id FROM labels WHERE name = ? AND (user_id = ? OR user_id IS NULL) LIMIT 1");
    $sel->bind_param("si", $name, $user_id);
    $sel->execute();
    $row = $sel->get_result()->fetch_assoc();
    if ($row) {
        return (int)$row['label_id'];
    }
    $ins = $conn->prepare("INSERT INTO labels (name, user_id) VALUES (?, ?)");
    $ins->bind_param("si", $name, $user_id);
    if ($ins->execute()) {
        return (int)$conn->insert_id;
    }
    // Race condition: another request created the same (user_id, name) between
    // our SELECT and INSERT — re-select scoped to this user only.
    if ($conn->errno === 1062) {
        $sel2 = $conn->prepare("SELECT label_id FROM labels WHERE name = ? AND user_id = ? LIMIT 1");
        $sel2->bind_param("si", $name, $user_id);
        $sel2->execute();
        $row2 = $sel2->get_result()->fetch_assoc();
        if ($row2) return (int)$row2['label_id'];
    }
    return 0;
}

/**
 * Resolve a label_id to one this user may safely RENAME or DELETE, "claiming"
 * or "forking" a legacy shared (user_id IS NULL) label as needed so the write
 * never touches another user's data. Returns 0 if the user has no rights to
 * this label at all.
 */
function resolve_owned_label_for_write(mysqli $conn, int $label_id, int $user_id): int {
    $lbl = $conn->prepare("SELECT label_id, name, user_id FROM labels WHERE label_id = ?");
    $lbl->bind_param("i", $label_id);
    $lbl->execute();
    $label = $lbl->get_result()->fetch_assoc();
    if (!$label) return 0;

    // Already privately owned by this user — safe to write directly.
    if ($label['user_id'] !== null && (int)$label['user_id'] === $user_id) {
        return $label_id;
    }

    // Owned outright by someone else — never writable.
    if ($label['user_id'] !== null) {
        return 0;
    }

    // Legacy shared label (user_id IS NULL) — must be used by at least one
    // of this user's notes to count as "theirs" at all.
    $own = $conn->prepare(
        "SELECT COUNT(*) as cnt FROM note_labels nl JOIN notes n ON nl.note_id = n.note_id
         WHERE nl.label_id = ? AND n.user_id = ?"
    );
    $own->bind_param("ii", $label_id, $user_id);
    $own->execute();
    if ((int)$own->get_result()->fetch_assoc()['cnt'] === 0) {
        return 0; // this user never actually used this label
    }

    // How many OTHER users still reference this legacy label?
    $others = $conn->prepare(
        "SELECT COUNT(DISTINCT n.user_id) as cnt FROM note_labels nl JOIN notes n ON nl.note_id = n.note_id
         WHERE nl.label_id = ? AND n.user_id != ?"
    );
    $others->bind_param("ii", $label_id, $user_id);
    $others->execute();
    $other_users = (int)$others->get_result()->fetch_assoc()['cnt'];

    if ($other_users === 0) {
        // Only this user actually uses it — safe to claim outright.
        $claim = $conn->prepare("UPDATE labels SET user_id = ? WHERE label_id = ?");
        $claim->bind_param("ii", $user_id, $label_id);
        $claim->execute();
        return $label_id;
    }

    // Others still use this label — fork a private copy for this user and
    // remap only their own note_labels rows onto it.
    $new_id = find_or_create_label($conn, $label['name'], $user_id);
    if ($new_id === $label_id || $new_id === 0) return 0; // safety guard
    $remap = $conn->prepare(
        "UPDATE note_labels nl JOIN notes n ON nl.note_id = n.note_id
         SET nl.label_id = ?
         WHERE nl.label_id = ? AND n.user_id = ?"
    );
    $remap->bind_param("iii", $new_id, $label_id, $user_id);
    $remap->execute();
    return $new_id;
}

switch ($method) {
    case 'GET':
        // Labels this user owns directly, plus legacy (NULL-owner) labels
        // actually attached to one of their notes.
        $sql = "SELECT DISTINCT l.label_id, l.name, l.created_at FROM labels l
                LEFT JOIN note_labels nl ON l.label_id = nl.label_id
                LEFT JOIN notes n ON nl.note_id = n.note_id
                WHERE l.user_id = ? OR (l.user_id IS NULL AND n.user_id = ?)
                ORDER BY l.name ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $user_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $labels = [];
        while ($row = $result->fetch_assoc()) {
            $row['name'] = htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');
            $labels[] = $row;
        }
        echo json_encode(["status" => "success", "data" => $labels]);
        break;

    case 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        $name    = isset($data['name']) ? trim($data['name']) : '';
        $note_id = isset($data['note_id']) ? (int)$data['note_id'] : 0;

        if (empty($name)) {
            echo json_encode(["status" => "error", "message" => "Tên nhãn không được để trống"]);
            exit();
        }
        if (mb_strlen($name, 'UTF-8') > 100) {
            echo json_encode(["status" => "error", "message" => "Tên nhãn quá dài (tối đa 100 ký tự)"]);
            exit();
        }

        // If note_id provided, verify note belongs to user
        if ($note_id > 0) {
            $chk = $conn->prepare("SELECT note_id FROM notes WHERE note_id = ? AND user_id = ?");
            $chk->bind_param("ii", $note_id, $user_id);
            $chk->execute();
            if ($chk->get_result()->num_rows === 0) {
                echo json_encode(["status" => "error", "message" => "Unauthorized"]);
                exit();
            }
        }

        // FIX: find-or-create is now scoped per user (own label, or reuse a
        // legacy unowned one) instead of a global name lookup.
        $label_id = find_or_create_label($conn, $name, $user_id);
        if ($label_id === 0) {
            echo json_encode(["status" => "error", "message" => "Không thể tạo nhãn"]);
            exit();
        }

        // If note_id given, attach label to note
        if ($note_id > 0) {
            $nl_stmt = $conn->prepare("INSERT IGNORE INTO note_labels (note_id, label_id) VALUES (?, ?)");
            $nl_stmt->bind_param("ii", $note_id, $label_id);
            $nl_stmt->execute();
        }

        echo json_encode(["status" => "success", "message" => "Nhãn đã được tạo/gắn thành công", "label_id" => $label_id]);
        break;

    case 'DELETE':
        $label_id = isset($_GET['label_id']) ? (int)$_GET['label_id'] : 0;
        $note_id = isset($_GET['note_id']) ? (int)$_GET['note_id'] : 0;
        $label_name = isset($_GET['name']) ? trim((string)$_GET['name']) : '';

        if ($note_id > 0 && $label_name !== '') {
            $chk = $conn->prepare("SELECT note_id FROM notes WHERE note_id = ? AND user_id = ?");
            $chk->bind_param("ii", $note_id, $user_id);
            $chk->execute();
            if ($chk->get_result()->num_rows === 0) {
                echo json_encode(["status" => "error", "message" => "Unauthorized"]);
                exit();
            }
            $chk->close();

            $del = $conn->prepare(
                "DELETE nl FROM note_labels nl
                 JOIN labels l ON nl.label_id = l.label_id
                 WHERE nl.note_id = ? AND l.name = ? AND (l.user_id = ? OR l.user_id IS NULL)"
            );
            $del->bind_param("isi", $note_id, $label_name, $user_id);
            $del->execute();
            echo json_encode(["status" => "success", "message" => "Nhãn đã được gỡ khỏi ghi chú"]);
            exit();
        }

        if (!$label_id) {
            echo json_encode(["status" => "error", "message" => "Label ID required"]);
            exit();
        }

        // FIX: resolve to a label_id this user actually owns/can safely
        // mutate (claims or forks legacy shared labels as needed) instead of
        // deleting from the shared row other users may still reference.
        $owned_label_id = resolve_owned_label_for_write($conn, $label_id, $user_id);
        if ($owned_label_id === 0) {
            echo json_encode(["status" => "error", "message" => "Bạn không có quyền xoá nhãn này"]);
            exit();
        }

        // Remove label from this user's notes
        $del_nl = $conn->prepare(
            "DELETE nl FROM note_labels nl
             JOIN notes n ON nl.note_id = n.note_id
             WHERE nl.label_id = ? AND n.user_id = ?"
        );
        $del_nl->bind_param("ii", $owned_label_id, $user_id);
        $del_nl->execute();

        // If the (now-private) label has no notes left referencing it at
        // all, remove the orphaned row too.
        $orphan = $conn->prepare("SELECT COUNT(*) as cnt FROM note_labels WHERE label_id = ?");
        $orphan->bind_param("i", $owned_label_id);
        $orphan->execute();
        $cnt = $orphan->get_result()->fetch_assoc()['cnt'];
        if ($cnt == 0) {
            $del_label = $conn->prepare("DELETE FROM labels WHERE label_id = ? AND (user_id = ? OR user_id IS NULL)");
            $del_label->bind_param("ii", $owned_label_id, $user_id);
            $del_label->execute();
        }

        echo json_encode(["status" => "success", "message" => "Nhãn đã được xoá"]);
        break;

    case 'PUT':
        // Rename a label — only the owner may rename
        $data = json_decode(file_get_contents("php://input"), true);
        if (!isset($data['action']) || $data['action'] !== 'rename') {
            echo json_encode(["status" => "error", "message" => "Hành động không hợp lệ"]);
            exit();
        }
        $label_id = isset($data['label_id']) ? (int)$data['label_id'] : 0;
        $new_name = isset($data['name']) ? trim($data['name']) : '';

        if (!$label_id) {
            echo json_encode(["status" => "error", "message" => "Thiếu label_id"]);
            exit();
        }
        if ($new_name === '') {
            echo json_encode(["status" => "error", "message" => "Tên nhãn không được để trống"]);
            exit();
        }
        if (mb_strlen($new_name, 'UTF-8') > 100) {
            echo json_encode(["status" => "error", "message" => "Tên nhãn quá dài (tối đa 100 ký tự)"]);
            exit();
        }

        // FIX: same claim-or-fork resolution as DELETE — renaming a legacy
        // shared label can no longer rename it out from under other users.
        $owned_label_id = resolve_owned_label_for_write($conn, $label_id, $user_id);
        if ($owned_label_id === 0) {
            echo json_encode(["status" => "error", "message" => "Bạn không có quyền sửa nhãn này"]);
            exit();
        }

        $upd = $conn->prepare("UPDATE labels SET name = ? WHERE label_id = ? AND (user_id = ? OR user_id IS NULL)");
        $upd->bind_param("sii", $new_name, $owned_label_id, $user_id);

        if ($upd->execute()) {
            echo json_encode(["status" => "success", "message" => "Đổi tên nhãn thành công", "name" => $new_name, "label_id" => $owned_label_id]);
        } else {
            if ($conn->errno === 1062) {
                echo json_encode(["status" => "error", "message" => "Bạn đã có nhãn tên '" . htmlspecialchars($new_name, ENT_QUOTES, 'UTF-8') . "'. Vui lòng chọn tên khác."]);
            } else {
                error_log('labels.php rename error: ' . $conn->error);
                echo json_encode(["status" => "error", "message" => "Đổi tên nhãn thất bại. Vui lòng thử lại."]);
            }
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(["status" => "error", "message" => "Method not allowed"]);
}
?>
