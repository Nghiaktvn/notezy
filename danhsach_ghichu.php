<?php
require_once __DIR__ . '/includes/session.php';
require_once('db.php');
notezy_session_start();

if (!isset($_SESSION['id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Chưa đăng nhập']);
    exit;
}

$user_id = (int)$_SESSION['id'];
$sql = "SELECT * FROM notes WHERE user_id = ? ORDER BY pinned DESC, id DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$notes = [];
while ($row = $result->fetch_assoc()) {
    $notes[] = $row;
}

header('Content-Type: application/json');
echo json_encode($notes);
?>
<script>
    // Hiển thị danh sách ghi chú
function loadNotes() {
    fetch('danhsach_ghichu.php')
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('notesDisplay');
            container.innerHTML = '';

            if (!data.length) {
                container.innerHTML = '<p class="text-muted">Chưa có ghi chú nào.</p>';
                return;
            }

            data.forEach(note => {
                const noteBox = document.createElement('div');
                noteBox.className = 'p-3 mb-3 rounded';
                noteBox.style.backgroundColor = note.background_color || '#ffffff';
                noteBox.style.color = note.text_color || '#000000';
                noteBox.style.fontFamily = note.font_family || 'Poppins';
                noteBox.style.border = '1px solid #ddd';

                noteBox.innerHTML = `
                    <h5>${note.title}</h5>
                    <p>${note.content}</p>
                    ${note.pinned == 1 ? '<i class="fas fa-thumbtack text-danger"></i>' : ''}
                `;

                container.appendChild(noteBox);
            });
        })
        .catch(err => {
            console.error('Lỗi khi tải ghi chú:', err);
        });
}

// Tải lần đầu khi mở trang
loadNotes();

// Tự động làm mới ghi chú mỗi 10 giây
setInterval(loadNotes, 10000);

// Sau khi lưu, tự reload lại danh sách
function autoSave() {
    ...
    fetch('themghichu1.php', {
        ...
    })
    .then(response => {
        if (response.ok) {
            loadNotes(); // cập nhật ngay ghi chú sau khi lưu
        }
    })
    .catch(error => console.error('Lỗi khi lưu:', error));
}
</script>
