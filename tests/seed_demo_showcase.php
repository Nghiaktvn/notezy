<?php
require_once __DIR__ . '/../db.php';
$conn = create_connect();

$userId = 5; // user1

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
    $stmt->execute();
    $stmt2 = $conn->prepare("SELECT label_id FROM labels WHERE user_id = ? AND name = ?");
    $stmt2->bind_param("is", $userId, $name);
    $stmt2->execute();
    $row = $stmt2->get_result()->fetch_assoc();
    $labelIds[$name] = $row['label_id'];
}

echo "Created labels: " . json_encode($labelIds) . PHP_EOL;

// Clean existing test notes for user1
$conn->query("DELETE FROM notes WHERE user_id = $userId");

// Note 1: Pinned Note
$t1 = "📌 [GHIM ĐẦU TRANG] Kế hoạch kiểm thử Notezy trên Docker & MySQL";
$c1 = "Hệ thống Notezy đang chạy trên nền tảng Docker hoàn chỉnh với 4 container:\n- Web: PHP 8.2 Apache\n- DB: MySQL 8.0\n- phpMyAdmin: Port 8081\n- AI Copilot: Python 3.12 Port 8765\n\nTất cả 24 tiêu chí trong Rubric đã được kiểm thử 100% tự động.";
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

// Share note 4 with user2 (user ID 6)
$sShare = $conn->prepare("INSERT INTO note_shares (note_id, shared_with_user_id, permission, shared_by_user_id) VALUES (?, 6, 'write', ?)");
$sShare->bind_param("ii", $n4, $userId);
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

echo "✅ Seeded 4 showcase notes for user1 (ID 5) successfully!" . PHP_EOL;
