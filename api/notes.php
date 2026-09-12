<?php
require_once dirname(__DIR__) . '/includes/session.php';
require_once 'db.php';
notezy_session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized — please login"]);
    exit();
}
$user_id = (int)$_SESSION['id'];

$method = $_SERVER['REQUEST_METHOD'];

// ==========================================
// SIMULATED AI ALGORITHM FOR AUTO-TAGGING
// ==========================================
function simulatedAITagging(string $content, string $title): array {
    $text = mb_strtolower($content . " " . $title, 'UTF-8');
    $tags = [];
    $type = 'note';

    if (mb_strpos($text, 'hạn chót') !== false || mb_strpos($text, 'deadline') !== false
        || mb_strpos($text, 'cần làm') !== false || mb_strpos($text, 'nhiệm vụ') !== false) {
        $type = 'task';
    }
    if (mb_strpos($text, 'họp') !== false || mb_strpos($text, 'meeting') !== false
        || mb_strpos($text, 'báo cáo') !== false) $tags[] = 'Công việc';
    if (mb_strpos($text, 'khách hàng') !== false || mb_strpos($text, 'hợp đồng') !== false) $tags[] = 'Khách hàng';
    if (mb_strpos($text, 'học') !== false || mb_strpos($text, 'bài tập') !== false
        || mb_strpos($text, 'thi') !== false) $tags[] = 'Học tập';
    if (mb_strpos($text, 'mua') !== false || mb_strpos($text, 'tiền') !== false
        || mb_strpos($text, 'chi tiêu') !== false) $tags[] = 'Tài chính';

    return ['type' => $type, 'tags' => $tags];
}
// ==========================================

switch ($method) {
    case 'GET':
        // FIX: single-note lookup for realtime polling (edit_note.php).
        // Additive branch — does not touch the existing list logic below.
        // Allows note owner OR a user the note has been shared with (any permission) to poll updated_at.
        if (isset($_GET['note_id'])) {
            $poll_note_id = (int)$_GET['note_id'];
            $sql = "SELECT n.note_id, n.title, n.content, n.updated_at, n.updated_at AS version,
                           n.pinned, n.status, n.deadline, n.password_hash, n.pin_hash
                    FROM notes n
                    WHERE n.note_id = ?
                      AND (n.user_id = ? OR n.note_id IN (
                            SELECT note_id FROM note_shares WHERE shared_with_user_id = ?
                          ))
                    LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iii", $poll_note_id, $user_id, $user_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if (!$row) {
                echo json_encode(["status" => "error", "message" => "Note not found or unauthorized"]);
                exit();
            }

            $unlocked_notes = isset($_SESSION['accessed_notes']) ? $_SESSION['accessed_notes'] : [];
            $unlocked_pins = isset($_SESSION['pin_unlocked_notes']) ? $_SESSION['pin_unlocked_notes'] : [];
            $has_pwd = !empty($row['password_hash']);
            $is_pwd_locked = $has_pwd && empty($unlocked_notes[$poll_note_id]);
            $has_pin = !empty($row['pin_hash']);
            $is_pin_locked = $has_pin && empty($unlocked_pins[$poll_note_id]);

            if ($is_pwd_locked || $is_pin_locked) {
                $row['content'] = '';
                $row['is_password_locked'] = true;
            } else {
                $row['is_password_locked'] = false;
                $row['content'] = htmlspecialchars($row['content'], ENT_QUOTES, 'UTF-8');
            }
            $row['is_pin_locked'] = $is_pin_locked;
            unset($row['password_hash'], $row['pin_hash']);

            $row['title'] = htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8');
            echo json_encode(["status" => "success", "data" => $row]);
            exit();
        }

        // FIX: type_filter via prepared statement to prevent SQL Injection
        $type_filter = isset($_GET['type']) ? trim($_GET['type']) : '';
        $archived_filter = isset($_GET['archived']) ? (int)$_GET['archived'] : 0;

        if ($type_filter !== '') {
            $sql = "SELECT n.*, n.updated_at AS version, GROUP_CONCAT(l.name) as labels
                    FROM notes n
                    LEFT JOIN note_labels nl ON n.note_id = nl.note_id
                    LEFT JOIN labels l ON nl.label_id = l.label_id
                    WHERE n.user_id = ? AND n.note_type = ? AND n.archived = ?
                    GROUP BY n.note_id ORDER BY n.pinned DESC, n.pinned_at DESC, n.updated_at DESC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isi", $user_id, $type_filter, $archived_filter);
        } else {
            $sql = "SELECT n.*, n.updated_at AS version, GROUP_CONCAT(l.name) as labels
                    FROM notes n
                    LEFT JOIN note_labels nl ON n.note_id = nl.note_id
                    LEFT JOIN labels l ON nl.label_id = l.label_id
                    WHERE n.user_id = ? AND n.archived = ?
                    GROUP BY n.note_id ORDER BY n.pinned DESC, n.pinned_at DESC, n.updated_at DESC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $user_id, $archived_filter);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $notes = [];
        $unlocked_notes = isset($_SESSION['accessed_notes']) ? $_SESSION['accessed_notes'] : [];
        $unlocked_pins = isset($_SESSION['pin_unlocked_notes']) ? $_SESSION['pin_unlocked_notes'] : [];

        while ($row = $result->fetch_assoc()) {
            $row['labels'] = $row['labels'] ? explode(',', $row['labels']) : [];
            $row_id = (int)$row['note_id'];
            $has_pwd = !empty($row['password_hash']);
            $is_pwd_locked = $has_pwd && empty($unlocked_notes[$row_id]);
            $has_pin = !empty($row['pin_hash']);
            $is_pin_locked = $has_pin && empty($unlocked_pins[$row_id]);
            $row['has_password'] = $has_pwd;
            $row['is_password_locked'] = $is_pwd_locked;
            $row['has_pin'] = $has_pin;
            $row['is_pin_locked'] = $is_pin_locked;

            if ($is_pwd_locked || $is_pin_locked) {
                $row['content'] = '';
            } else {
                $row['content'] = htmlspecialchars($row['content'], ENT_QUOTES, 'UTF-8');
            }
            unset($row['password_hash'], $row['pin_hash']);

            $row['title'] = htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8');
            $notes[] = $row;
        }
        echo json_encode(["status" => "success", "data" => $notes]);
        break;

    case 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        if (!$data && !empty($_POST)) $data = $_POST;

        $title    = trim($data['title']   ?? 'Untitled');
        $content  = trim($data['content'] ?? '');
        $pinned   = isset($data['pinned']) ? (int)$data['pinned'] : 0;
        $status   = trim($data['status']  ?? 'todo');
        $deadline = isset($data['deadline']) && $data['deadline'] !== '' ? $data['deadline'] : null;
        $note_type = trim($data['note_type'] ?? '');
        $bg_raw = trim($data['background_color'] ?? '');
        $background_color = preg_match('/^#[0-9a-fA-F]{6}$/', $bg_raw) ? $bg_raw : '#ffffff';
        $tc_raw = trim($data['text_color'] ?? '');
        $text_color = preg_match('/^#[0-9a-fA-F]{6}$/', $tc_raw) ? $tc_raw : '#000000';
        $reminder_at = isset($data['reminder_at']) && $data['reminder_at'] !== '' ? $data['reminder_at'] : null;

        // AI fallback
        $ai_result = simulatedAITagging($content, $title);
        if ($note_type === '') $note_type = $ai_result['type'];

        // FIX: Use prepared statement — no string interpolation
        $sql = "INSERT INTO notes (user_id, title, content, pinned, pinned_at, status, deadline, note_type, background_color, text_color, reminder_at)
                VALUES (?, ?, ?, ?, IF(? = 1, NOW(), NULL), ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issiissssss", $user_id, $title, $content, $pinned, $pinned, $status, $deadline, $note_type, $background_color, $text_color, $reminder_at);

        if ($stmt->execute()) {
            $note_id = $conn->insert_id;

            // Apply AI tags using prepared statements.
            // SECURITY FIX (audit 2026-09-02): this used to look up/create
            // labels by name alone (globally unique), so two users' notes
            // triggering the same auto-tag (e.g. "họp"/"deadline") ended up
            // sharing one labels row — see api/labels.php header comment for
            // the full explanation. Now scoped per user_id, same as
            // find_or_create_label() in api/labels.php and
            // AiServices::findOrCreateLabel().
            foreach ($ai_result['tags'] as $tagName) {
                $l_stmt = $conn->prepare("SELECT label_id FROM labels WHERE name = ? AND (user_id = ? OR user_id IS NULL) LIMIT 1");
                $l_stmt->bind_param("si", $tagName, $user_id);
                $l_stmt->execute();
                $l_res = $l_stmt->get_result();
                if ($l_res->num_rows > 0) {
                    $label_id = $l_res->fetch_assoc()['label_id'];
                } else {
                    $ins = $conn->prepare("INSERT INTO labels (name, user_id) VALUES (?, ?)");
                    $ins->bind_param("si", $tagName, $user_id);
                    if ($ins->execute()) {
                        $label_id = $conn->insert_id;
                    } elseif ($conn->errno === 1062) {
                        // Race: another request created it first — re-select scoped to this user.
                        $l_stmt2 = $conn->prepare("SELECT label_id FROM labels WHERE name = ? AND user_id = ? LIMIT 1");
                        $l_stmt2->bind_param("si", $tagName, $user_id);
                        $l_stmt2->execute();
                        $row2 = $l_stmt2->get_result()->fetch_assoc();
                        $label_id = $row2 ? $row2['label_id'] : null;
                    } else {
                        $label_id = null;
                    }
                }
                if ($label_id) {
                    $nl_stmt = $conn->prepare("INSERT IGNORE INTO note_labels (note_id, label_id) VALUES (?, ?)");
                    $nl_stmt->bind_param("ii", $note_id, $label_id);
                    $nl_stmt->execute();
                }
            }

            $fresh_updated_at = null;
            $fresh_stmt = $conn->prepare("SELECT updated_at FROM notes WHERE note_id = ?");
            $fresh_stmt->bind_param("i", $note_id);
            $fresh_stmt->execute();
            $fresh_row = $fresh_stmt->get_result()->fetch_assoc();
            if ($fresh_row) $fresh_updated_at = $fresh_row['updated_at'];
            $fresh_stmt->close();

            echo json_encode([
                "status"   => "success",
                "message"  => "Note created successfully",
                "note_id"  => $note_id,
                "updated_at" => $fresh_updated_at,
                "version" => $fresh_updated_at,
                "ai_type"  => $note_type,
                "ai_tags"  => $ai_result['tags']
            ]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to create note"]);
        }
        break;

    case 'PUT':
        $data = json_decode(file_get_contents("php://input"), true);
        if (!isset($data['note_id'])) {
            echo json_encode(["status" => "error", "message" => "Note ID required"]);
            exit();
        }
        $note_id = (int)$data['note_id'];

        $client_updated_at = trim((string)($data['client_updated_at'] ?? $data['version'] ?? ''));
        $force_save = !empty($data['force_save']);

        // Verify ownership OR write permission in note_shares
        $auth_stmt = $conn->prepare(
            "SELECT note_id, title, content, updated_at, password_hash, pin_hash FROM notes
             WHERE note_id = ? 
               AND (user_id = ? OR note_id IN (
                   SELECT note_id FROM note_shares WHERE shared_with_user_id = ? AND permission = 'write'
               ))"
        );
        $auth_stmt->bind_param("iii", $note_id, $user_id, $user_id);
        $auth_stmt->execute();
        $auth_result = $auth_stmt->get_result();
        $current_note = $auth_result->fetch_assoc();
        if (!$current_note) {
            echo json_encode(["status" => "error", "message" => "Unauthorized — bạn không có quyền chỉnh sửa ghi chú này"]);
            exit();
        }
        $auth_stmt->close();

        if ((!empty($current_note['password_hash']) && empty($_SESSION['accessed_notes'][$note_id]))
            || (!empty($current_note['pin_hash']) && empty($_SESSION['pin_unlocked_notes'][$note_id]))) {
            http_response_code(423);
            echo json_encode(["status" => "error", "message" => "Hãy mở khóa ghi chú trước khi chỉnh sửa."]);
            exit();
        }

        if (!$force_save && $client_updated_at !== '' && !empty($current_note['updated_at']) && $current_note['updated_at'] > $client_updated_at) {
            http_response_code(409);
            echo json_encode([
                "status" => "error",
                "conflict" => true,
                "message" => "Note has been changed on another device.",
                "server_note" => [
                    "note_id" => $note_id,
                    "title" => $current_note['title'],
                    "content" => $current_note['content'],
                    "updated_at" => $current_note['updated_at'],
                    "version" => $current_note['updated_at']
                ]
            ]);
            exit();
        }

        // Build SET clause safely using individual prepared statements per field
        $allowed_fields = ['title', 'content', 'status', 'pinned', 'deadline', 'note_type', 'background_color', 'text_color', 'reminder_at'];
        $set_parts = [];
        $types = '';
        $values = [];
        foreach ($allowed_fields as $field) {
            if (!isset($data[$field])) continue;
            if ($field === 'pinned') {
                $nextPinned = (int) $data[$field];
                $set_parts[] = "pinned_at = CASE WHEN ? = 1 AND pinned = 0 THEN NOW() WHEN ? = 0 THEN NULL ELSE pinned_at END";
                $set_parts[] = "pinned = ?";
                $types .= 'iii';
                $values[] = $nextPinned;
                $values[] = $nextPinned;
                $values[] = $nextPinned;
            } elseif ($field === 'background_color' || $field === 'text_color') {
                $color = trim((string)$data[$field]);
                if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) continue;
                $set_parts[] = "$field = ?";
                $types .= 's';
                $values[] = $color;
            } else {
                $set_parts[] = "$field = ?";
                $types .= 's';
                $values[] = (string)$data[$field];
            }
        }

        if (count($set_parts) > 0) {
            // Luôn cập nhật updated_at = NOW() để các cộng tác viên phát hiện thay đổi realtime
            $set_parts[] = "updated_at = NOW()";
            $sql = "UPDATE notes SET " . implode(", ", $set_parts) . " WHERE note_id = ? AND (user_id = ? OR note_id IN (SELECT note_id FROM note_shares WHERE shared_with_user_id = ? AND permission = 'write'))";
            $types .= 'iii';
            $values[] = $note_id;
            $values[] = $user_id;
            $values[] = $user_id;
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$values);
            if ($stmt->execute()) {
                $ts_stmt = $conn->prepare("SELECT updated_at FROM notes WHERE note_id = ?");
                $ts_stmt->bind_param("i", $note_id);
                $ts_stmt->execute();
                $ts_row = $ts_stmt->get_result()->fetch_assoc();
                $ts_stmt->close();
                echo json_encode([
                    "status" => "success",
                    "message" => "Updated successfully",
                    "updated_at" => $ts_row['updated_at'] ?? null,
                    "version" => $ts_row['updated_at'] ?? null
                ]);
            } else {
                echo json_encode(["status" => "error", "message" => "Update failed: " . $conn->error]);
            }
            $stmt->close();
        } else {
            echo json_encode(["status" => "error", "message" => "No fields to update"]);
        }
        break;

    case 'DELETE':
        $note_id = isset($_GET['note_id']) ? (int)$_GET['note_id'] : 0;
        if (!$note_id) {
            echo json_encode(["status" => "error", "message" => "Note ID required"]);
            exit();
        }
        // Fetch image path first
        $img_stmt = $conn->prepare("SELECT image_path, password_hash, pin_hash FROM notes WHERE note_id = ? AND user_id = ?");
        $img_stmt->bind_param("ii", $note_id, $user_id);
        $img_stmt->execute();
        $img_row = $img_stmt->get_result()->fetch_assoc();
        if (!$img_row) {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "Note not found"]);
            exit();
        }
        if ((!empty($img_row['password_hash']) && empty($_SESSION['accessed_notes'][$note_id]))
            || (!empty($img_row['pin_hash']) && empty($_SESSION['pin_unlocked_notes'][$note_id]))) {
            http_response_code(423);
            echo json_encode(["status" => "error", "message" => "Hãy mở khóa ghi chú trước khi xóa."]);
            exit();
        }
        if (!empty($img_row['image_path'])) {
            $full_path = __DIR__ . '/../' . $img_row['image_path'];
            if (file_exists($full_path)) @unlink($full_path);
        }

        $del_stmt = $conn->prepare("DELETE FROM notes WHERE note_id = ? AND user_id = ?");
        $del_stmt->bind_param("ii", $note_id, $user_id);
        if ($del_stmt->execute() && $del_stmt->affected_rows > 0) {
            echo json_encode(["status" => "success", "message" => "Note deleted"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Delete failed or note not found"]);
        }
        break;
}
?>
