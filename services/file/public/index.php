<?php
declare(strict_types=1);
require __DIR__ . '/../../shared/bootstrap.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($path === '/health' || $path === '/ready') svc_health('file', true);
$userId = svc_bearer_user();
$db = svc_db();

function file_note_access(PDO $db, int $noteId, int $userId, bool $write): bool {
    $permission = $write ? " AND ns.permission = 'write'" : '';
    $stmt = $db->prepare("SELECT n.note_id FROM notes n LEFT JOIN note_shares ns ON ns.note_id=n.note_id AND ns.shared_with_user_id=? WHERE n.note_id=? AND (n.user_id=? OR (ns.note_id IS NOT NULL{$permission})) LIMIT 1");
    $stmt->execute([$userId, $noteId, $userId]);
    return (bool) $stmt->fetchColumn();
}

if ($path === '/attachments' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $noteId = (int) ($_GET['note_id'] ?? 0);
    if ($noteId < 1 || !file_note_access($db, $noteId, $userId, false)) svc_json(403, ['error' => 'NOTE_ACCESS_DENIED']);
    $stmt = $db->prepare('SELECT attachment_id,original_name,mime_type,file_size,attachment_type,created_at FROM note_attachments WHERE note_id=? ORDER BY created_at DESC');
    $stmt->execute([$noteId]);
    svc_json(200, ['data' => $stmt->fetchAll()]);
}

if ($path === '/attachments' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = svc_input();
    $noteId = (int) ($data['note_id'] ?? 0);
    if ($noteId < 1 || !file_note_access($db, $noteId, $userId, true)) svc_json(403, ['error' => 'NOTE_WRITE_DENIED']);
    $mime = (string) ($data['mime_type'] ?? '');
    $allowed = ['image/jpeg'=>['image','jpg',5242880], 'image/png'=>['image','png',5242880], 'image/gif'=>['image','gif',5242880], 'image/webp'=>['image','webp',5242880], 'video/mp4'=>['video','mp4',26214400], 'video/webm'=>['video','webm',26214400], 'application/pdf'=>['file','pdf',10485760], 'text/plain'=>['file','txt',2097152], 'application/zip'=>['file','zip',10485760], 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>['file','docx',10485760]];
    if (!isset($allowed[$mime])) svc_json(422, ['error' => 'UNSUPPORTED_FILE_TYPE']);
    $content = base64_decode((string) ($data['content_base64'] ?? ''), true);
    if ($content === false || $content === '' || strlen($content) > $allowed[$mime][2]) svc_json(422, ['error' => 'INVALID_FILE_CONTENT']);
    [$type, $extension] = $allowed[$mime];
    $directory = '/app/uploads/attachments';
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) svc_json(500, ['error' => 'UPLOAD_DIRECTORY_UNAVAILABLE']);
    $stored = bin2hex(random_bytes(16)) . '.' . $extension;
    if (file_put_contents($directory . '/' . $stored, $content, LOCK_EX) === false) svc_json(500, ['error' => 'FILE_WRITE_FAILED']);
    $name = mb_substr(basename((string) ($data['filename'] ?? 'attachment.' . $extension)), 0, 255);
    $stmt = $db->prepare('INSERT INTO note_attachments (note_id,uploaded_by_user_id,original_name,stored_name,mime_type,file_size,attachment_type) VALUES (?,?,?,?,?,?,?)');
    $stmt->execute([$noteId, $userId, $name, $stored, $mime, strlen($content), $type]);
    svc_json(201, ['attachment_id' => (int) $db->lastInsertId(), 'name' => $name, 'type' => $type]);
}

if (preg_match('#^/attachments/(\d+)$#', $path, $match) && $_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $stmt = $db->prepare('SELECT na.stored_name,n.user_id FROM note_attachments na JOIN notes n ON n.note_id=na.note_id WHERE na.attachment_id=? LIMIT 1');
    $stmt->execute([(int) $match[1]]); $file = $stmt->fetch();
    if (!$file || (int) $file['user_id'] !== $userId) svc_json(403, ['error' => 'ATTACHMENT_DELETE_DENIED']);
    $db->prepare('DELETE FROM note_attachments WHERE attachment_id=?')->execute([(int) $match[1]]);
    $disk = '/app/uploads/attachments/' . basename($file['stored_name']); if (is_file($disk)) @unlink($disk);
    svc_json(200, ['status' => 'deleted']);
}

svc_json(404, ['error' => 'ROUTE_NOT_FOUND']);
