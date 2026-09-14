<?php
require_once dirname(__DIR__) . '/includes/session.php';
require_once __DIR__ . '/../config/database.php';
notezy_session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Vui lòng đăng nhập.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Phương thức không được hỗ trợ.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true) ?: [];
$title = trim((string) ($payload['title'] ?? 'Nội dung cần hiểu'));
$text = trim((string) ($payload['text'] ?? ''));
$noteId = (int) ($payload['note_id'] ?? 0);

if ($noteId > 0) {
    $stmt = $conn->prepare('SELECT title, content FROM notes WHERE note_id = ? AND user_id = ? AND archived = 0 LIMIT 1');
    $userId = (int) $_SESSION['id'];
    $stmt->bind_param('ii', $noteId, $userId);
    $stmt->execute();
    $note = $stmt->get_result()->fetch_assoc();
    if (!$note) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy ghi chú.']);
        exit;
    }
    $title = trim((string) $note['title']);
    $text = trim(strip_tags((string) $note['content']));
}

if ($text === '') {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Hãy nhập văn bản hoặc chọn một ghi chú.']);
    exit;
}
if (mb_strlen($text, 'UTF-8') > 20000) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Văn bản tối đa 20.000 ký tự.']);
    exit;
}

$normalized = preg_replace('/\s+/u', ' ', $text);
$sentences = preg_split('/(?<=[.!?])\s+|[\r\n]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY);
$sentences = array_values(array_filter(array_map(static function ($sentence) {
    $sentence = trim($sentence);
    return mb_strlen($sentence, 'UTF-8') > 140 ? mb_substr($sentence, 0, 137, 'UTF-8') . '…' : $sentence;
}, $sentences)));
if (!$sentences) $sentences = [mb_substr($normalized, 0, 140, 'UTF-8')];

$actions = [];
$schedule = [];
$ideas = [];
foreach ($sentences as $sentence) {
    if (preg_match('/\b(cần|phải|hãy|làm|hoàn thành|chuẩn bị|chọn|chốt|kiểm thử|nộp)\b/ui', $sentence)) $actions[] = $sentence;
    elseif (preg_match('/\b(hôm nay|ngày mai|tuần|tháng|giờ|phút|deadline|hạn|thứ [2-7]|chủ nhật)\b/ui', $sentence)) $schedule[] = $sentence;
    else $ideas[] = $sentence;
}
$ideas = array_slice(array_values(array_unique(array_merge($ideas, $sentences))), 0, 4);
$actions = array_slice(array_values(array_unique($actions)), 0, 4);
$schedule = array_slice(array_values(array_unique($schedule)), 0, 3);
if (!$actions) $actions = ['Xác định bước tiếp theo từ các ý chính.'];
if (!$schedule) $schedule = ['Chưa phát hiện mốc thời gian cụ thể.'];

echo json_encode([
    'status' => 'success',
    'data' => [
        'topic' => $title !== '' ? mb_substr($title, 0, 120, 'UTF-8') : 'Nội dung cần hiểu',
        'summary' => implode(' ', array_slice($sentences, 0, 2)),
        'branches' => [
            ['label' => 'Ý chính', 'icon' => 'lightbulb', 'items' => $ideas],
            ['label' => 'Việc cần làm', 'icon' => 'check', 'items' => $actions],
            ['label' => 'Mốc thời gian', 'icon' => 'clock', 'items' => $schedule],
        ],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
