<?php
require_once __DIR__ . '/../db.php';
$conn = create_connect();

$findUser = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
$ownerUsername = 'user1';
$findUser->bind_param('s', $ownerUsername);
$findUser->execute();
$ownerRow = $findUser->get_result()->fetch_assoc();
$findUser->close();

$findCollaborator = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
$collaboratorUsername = 'user2';
$findCollaborator->bind_param('s', $collaboratorUsername);
$findCollaborator->execute();
$collaboratorRow = $findCollaborator->get_result()->fetch_assoc();
$findCollaborator->close();

if (!$ownerRow || !$collaboratorRow) {
    fwrite(STDERR, "Run tests/seed_demo_accounts.php before seeding the showcase.\n");
    exit(1);
}

$userId = (int) $ownerRow['id'];
$collaboratorId = (int) $collaboratorRow['id'];

// Create demo labels for user1
$labels = [
    'Học tập' => '#3b82f6',
    'Công việc Docker' => '#10b981',
    'Dự án Notezy' => '#8b5cf6',
    'Quan trọng' => '#ef4444'
];

$labelIds = [];
foreach ($labels as $name => $color) {
    $stmt = $conn->prepare("INSERT INTO labels (user_id, name) VALUES (?, ?) ON DUPLICATE KEY UPDATE name = name");
    $stmt->bind_param("is", $userId, $name);
    if (!$stmt->execute()) {
        throw new RuntimeException('Unable to create label: ' . $stmt->error);
    }
    $stmt->close();
    $stmt2 = $conn->prepare("SELECT label_id FROM labels WHERE user_id = ? AND name = ?");
    $stmt2->bind_param("is", $userId, $name);
    $stmt2->execute();
    $row = $stmt2->get_result()->fetch_assoc();
    $stmt2->close();
    if (!$row) {
        throw new RuntimeException('Unable to resolve label: ' . $name);
    }
    $labelIds[$name] = (int) $row['label_id'];
}

echo "Created labels: " . json_encode($labelIds) . PHP_EOL;

// Remove only previous showcase records; never erase unrelated user notes.
$findDemo = $conn->prepare(
    "SELECT note_id FROM notes
     WHERE user_id = ? AND (
       title LIKE '📌 [GHIM ĐẦU TRANG]%' OR
       title LIKE '🖼️ Ghi chú có đính kèm%' OR
       title LIKE '🔒 Ghi chú bảo mật%' OR
       title LIKE '👥 Ghi chú Cộng tác%'
     )"
);
$findDemo->bind_param('i', $userId);
$findDemo->execute();
$demoIds = array_column($findDemo->get_result()->fetch_all(MYSQLI_ASSOC), 'note_id');
$findDemo->close();
foreach ($demoIds as $demoId) {
    $demoId = (int) $demoId;
    $conn->query("DELETE FROM note_shares WHERE note_id = {$demoId}");
    $conn->query("DELETE FROM note_labels WHERE note_id = {$demoId}");
    $conn->query("DELETE FROM note_attachments WHERE note_id = {$demoId}");
    $conn->query("DELETE FROM notes WHERE note_id = {$demoId} AND user_id = {$userId}");
}

// Note 1: Pinned Note
$t1 = "📌 [GHIM ĐẦU TRANG] Kế hoạch kiểm thử Notezy trên Docker & MySQL";
$c1 = "Hệ thống Notezy đang chạy ổn định trên XAMPP và Docker:\n- Web: PHP 8.2 Apache\n- DB: MySQL/MariaDB\n- phpMyAdmin: Port 8081\n- AI Copilot: Python 3.12 Port 8765\n\nToàn bộ 32 tiêu chí trong Rubric đã được chuẩn bị cho buổi demo.";
$s1 = $conn->prepare("INSERT INTO notes (user_id, title, content, pinned, background_color, text_color, font_family, status, created_at, updated_at) VALUES (?, ?, ?, 1, '#fef08a', '#1e293b', 'Poppins', 'todo', NOW(), NOW())");
$s1->bind_param("iss", $userId, $t1, $c1);
$s1->execute();
$n1 = $conn->insert_id;

// Note 2: Note with image attachment
$t2 = "🖼️ Ghi chú có đính kèm ảnh & nhãn 'Học tập'";
$c2 = "Ghi chú này minh họa tính năng đính kèm tệp đa phương tiện (ảnh) và gắn nhiều nhãn để dễ dàng lọc và tìm kiếm theo phân loại.";
$s2 = $conn->prepare("INSERT INTO notes (user_id, title, content, pinned, background_color, text_color, image_path, created_at, updated_at) VALUES (?, ?, ?, 0, '#e0f2fe', '#0f172a', 'logo.png', NOW(), NOW())");
$s2->bind_param("iss", $userId, $t2, $c2);
$s2->execute();
$n2 = $conn->insert_id;

// Note 3: Note with Password protection
$t3 = "🔒 Ghi chú bảo mật mật khẩu (Password Protection)";
$c3 = "Nội dung bí mật được mã hóa và bảo vệ bằng mật khẩu riêng (Mật khẩu: 123456). Chỉ người có mật khẩu mới có thể mở xem.";
$passHash = password_hash("123456", PASSWORD_DEFAULT);
$s3 = $conn->prepare("INSERT INTO notes (user_id, title, content, pinned, background_color, password_hash, created_at, updated_at) VALUES (?, ?, ?, 0, '#fee2e2', ?, NOW(), NOW())");
$s3->bind_param("isss", $userId, $t3, $c3, $passHash);
$s3->execute();
$n3 = $conn->insert_id;

// Note 4: Shared Note / Collaboration
$t4 = "👥 Ghi chú Cộng tác thời gian thực (Realtime Collaboration)";
$c4 = "Ghi chú này đã được chia sẻ cho user2 (Editor) để cùng nhau chỉnh sửa trong thời gian thực. Hệ thống hỗ trợ phát hiện xung đột và hiển thị ai đang gõ (typing indicator).";
$s4 = $conn->prepare("INSERT INTO notes (user_id, title, content, pinned, background_color, created_at, updated_at) VALUES (?, ?, ?, 0, '#f3e8ff', NOW(), NOW())");
$s4->bind_param("iss", $userId, $t4, $c4);
$s4->execute();
$n4 = $conn->insert_id;

// Share note 4 with the actual user2 account.
$sShare = $conn->prepare("INSERT INTO note_shares (note_id, shared_with_user_id, permission, shared_by_user_id) VALUES (?, ?, 'write', ?)");
$sShare->bind_param("iii", $n4, $collaboratorId, $userId);
$sShare->execute();

// Attach labels to notes
if (!empty($labelIds['Công việc Docker'])) {
    $conn->query("INSERT IGNORE INTO note_labels (note_id, label_id) VALUES ($n1, {$labelIds['Công việc Docker']})");
    $conn->query("INSERT IGNORE INTO note_labels (note_id, label_id) VALUES ($n1, {$labelIds['Quan trọng']})");
}
if (!empty($labelIds['Học tập'])) {
    $conn->query("INSERT IGNORE INTO note_labels (note_id, label_id) VALUES ($n2, {$labelIds['Học tập']})");
}
if (!empty($labelIds['Dự án Notezy'])) {
    $conn->query("INSERT IGNORE INTO note_labels (note_id, label_id) VALUES ($n4, {$labelIds['Dự án Notezy']})");
}

echo "✅ Seeded 4 showcase notes for user1 (ID {$userId}) and shared with user2 (ID {$collaboratorId}) successfully!" . PHP_EOL;
