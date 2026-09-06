<?php
require_once __DIR__ . '/includes/session.php';
require_once('db.php');
notezy_session_start();

if (!isset($_SESSION['id'])) {
    echo '<p class="text-danger">Bạn chưa đăng nhập.</p>';
    exit;
}

$user_id = (int) $_SESSION['id'];
$notes = [];

try {
    // Lấy tham số tìm kiếm
    $title = isset($_POST['searchTitle']) ? trim($_POST['searchTitle']) : '';
    $content = isset($_POST['searchContent']) ? trim($_POST['searchContent']) : '';
    $labels = isset($_POST['searchLabels']) ? trim($_POST['searchLabels']) : '';
    $pinned = isset($_POST['searchPinned']) && $_POST['searchPinned'] == 1 ? 1 : null;

    // Xây dựng truy vấn SQL (tìm kiếm trên cả ghi chú cá nhân và ghi chú được chia sẻ)
    $sql = "
        SELECT n.note_id, n.title, n.content, n.pinned, n.created_at, n.password_hash,
               GROUP_CONCAT(l.name SEPARATOR ',') AS labels,
               CASE WHEN n.user_id = ? THEN 'own' ELSE 'shared' END AS ownership
        FROM notes n
        LEFT JOIN note_labels nl ON n.note_id = nl.note_id
        LEFT JOIN labels l ON nl.label_id = l.label_id
        LEFT JOIN note_shares ns ON n.note_id = ns.note_id AND ns.shared_with_user_id = ?
        WHERE (n.user_id = ? OR ns.note_id IS NOT NULL)
    ";
    
    $params = [$user_id, $user_id, $user_id];
    $types = 'iii';

    // Thêm điều kiện tìm kiếm
    if ($title !== '') {
        $sql .= " AND LOWER(n.title) LIKE ?";
        $params[] = '%' . strtolower($title) . '%';
        $types .= 's';
    }

    if ($content !== '') {
        $sql .= " AND LOWER(n.content) LIKE ?";
        $params[] = '%' . strtolower($content) . '%';
        $types .= 's';
    }

    if ($labels !== '') {
        $labels_array = array_filter(array_map('trim', explode(',', $labels)));
        if (!empty($labels_array)) {
            $placeholders = implode(',', array_fill(0, count($labels_array), '?'));
            $sql .= " AND n.note_id IN (
                        SELECT nl.note_id 
                        FROM note_labels nl 
                        JOIN labels l ON nl.label_id = l.label_id 
                        WHERE LOWER(l.name) IN ($placeholders)
                     )";
            foreach ($labels_array as $label) {
                $params[] = strtolower($label);
                $types .= 's';
            }
        }
    }

    if ($pinned !== null) {
        $sql .= " AND n.pinned = ?";
        $params[] = $pinned;
        $types .= 'i';
    }

    $sql .= " GROUP BY n.note_id, n.title, n.content, n.pinned, n.created_at
              ORDER BY n.pinned DESC, n.created_at DESC";

    // Chuẩn bị và thực thi truy vấn
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo '<p class="text-danger">Lỗi chuẩn bị truy vấn: ' . htmlspecialchars($conn->error) . '</p>';
        exit;
    }

    // Gắn các tham số
    $stmt->bind_param($types, ...$params);
    
    if (!$stmt->execute()) {
        echo '<p class="text-danger">Lỗi thực thi truy vấn: ' . htmlspecialchars($stmt->error) . '</p>';
        exit;
    }

    $result = $stmt->get_result();
    if (!$result) {
        echo '<p class="text-danger">Lỗi lấy kết quả: ' . htmlspecialchars($stmt->error) . '</p>';
        exit;
    }

    $notes = $result->fetch_all(MYSQLI_ASSOC);
    
    // Xử lý nhãn
    foreach ($notes as &$row) {
        $row['labels'] = $row['labels'] ? explode(',', $row['labels']) : [];
    }

    $stmt->close();
} catch (Exception $e) {
    echo '<p class="text-danger">Lỗi truy vấn: ' . htmlspecialchars($e->getMessage()) . '</p>';
    exit;
}

// Hiển thị kết quả tìm kiếm
if (empty($notes)) {
    echo '<div class="text-center mt-4"><p class="text-muted">Không tìm thấy ghi chú nào.</p></div>';
} else {
    $unlocked_notes = isset($_SESSION['accessed_notes']) ? $_SESSION['accessed_notes'] : [];
    foreach ($notes as $note) {
        $nid = (int)$note['note_id'];
        $is_locked = !empty($note['password_hash']) && empty($unlocked_notes[$nid]);

        echo '<div class="note-item position-relative">';
        if (!empty($note['pinned'])) {
            echo '<span class="pin-icon" title="Ghi chú đã ghim"><i class="fas fa-thumbtack"></i></span>';
        }
        if (!empty($note['password_hash'])) {
            echo '<span class="badge bg-secondary mb-1"><i class="fas fa-key me-1"></i>Pass</span> ';
        }
        echo '<h5 class="mb-2">' . htmlspecialchars($note['title']) . '</h5>';
        
        if ($is_locked) {
            echo '<p class="text-muted fst-italic"><i class="fas fa-lock me-1"></i>[Ghi chú được bảo vệ bằng mật khẩu. Nhấp Xem để mở khóa]</p>';
        } else {
            echo '<p>' . nl2br(htmlspecialchars($note['content'])) . '</p>';
        }
        
        if (!empty($note['labels'])) {
            echo '<div class="note-labels">';
            foreach ($note['labels'] as $label) {
                echo '<span class="note-label">' . htmlspecialchars($label) . '</span>';
            }
            echo '</div>';
        }
        
        echo '<div class="text-end mt-2">';
        echo '<a href="notepass.php?id=' . urlencode($note['note_id']) . '" class="btn btn-sm btn-outline-primary">Xem</a> ';
        if ($note['ownership'] === 'own') {
            echo '<button class="btn btn-sm btn-outline-danger ms-1" onclick="confirmDelete(' . (int)$note['note_id'] . ')">Xóa</button>';
        } else {
            echo '<span class="badge bg-info text-dark ms-1"><i class="fas fa-share me-1"></i>Được chia sẻ</span>';
        }
        echo '</div>';
        echo '</div>';
    }
}

$conn->close();
?>