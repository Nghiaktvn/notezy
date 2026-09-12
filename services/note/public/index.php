<?php
declare(strict_types=1);

require __DIR__ . '/../../shared/bootstrap.php';

/** Resources are scoped to the bearer-token owner; callers never supply user_id. */
function note_payload(array $row): array {
    $row['labels'] = array_values(array_filter(array_map('trim', explode(',', (string)($row['labels'] ?? '')))));
    unset($row['pin_hash']);
    return $row;
}
function note_owned(PDO $db, int $noteId, int $userId): array {
    $q = $db->prepare('SELECT note_id,user_id,title,content,pinned,status,note_type,reminder_at,created_at,updated_at,pin_hash IS NOT NULL AS pin_locked FROM notes WHERE note_id=? AND user_id=? AND archived=0 LIMIT 1');
    $q->execute([$noteId, $userId]); $note = $q->fetch();
    if (!$note) svc_json(404, ['error' => 'NOTE_NOT_FOUND']);
    return $note;
}
function label_owned(PDO $db, int $labelId, int $userId): array {
    $q = $db->prepare('SELECT label_id,name,created_at FROM labels WHERE label_id=? AND user_id=? LIMIT 1');
    $q->execute([$labelId, $userId]); $label = $q->fetch();
    if (!$label) svc_json(404, ['error' => 'LABEL_NOT_FOUND']);
    return $label;
}
function valid_note_input(array $data, bool $partial = false): array {
    $out = [];
    foreach (['title', 'content'] as $field) if (array_key_exists($field, $data)) $out[$field] = trim((string)$data[$field]);
    if (!$partial && (!isset($out['title'], $out['content']) || $out['title'] === '' || $out['content'] === '')) svc_json(422, ['error' => 'INVALID_NOTE']);
    if (isset($out['title']) && ($out['title'] === '' || mb_strlen($out['title']) > 255)) svc_json(422, ['error' => 'INVALID_TITLE']);
    if (array_key_exists('note_type', $data)) $out['note_type'] = $data['note_type'] === 'task' ? 'task' : 'note';
    if (array_key_exists('status', $data)) {
        if (!in_array($data['status'], ['todo', 'in_progress', 'done'], true)) svc_json(422, ['error' => 'INVALID_STATUS']);
        $out['status'] = $data['status'];
    }
    if (array_key_exists('reminder_at', $data)) {
        $value = $data['reminder_at'];
        if ($value !== null && !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string)$value)) svc_json(422, ['error' => 'INVALID_REMINDER']);
        $out['reminder_at'] = $value;
    }
    return $out;
}
function note_cache_key(int $userId, string $search, ?int $labelId): string {
    $version = svc_cache_version('notes:' . $userId);
    return 'cache:notes:' . $userId . ':v' . $version . ':' . hash('sha256', $search . '|' . (string)$labelId);
}
function note_cache_invalidate(int $userId): void { svc_cache_bump('notes:' . $userId); }

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($path === '/health' || $path === '/ready') svc_health('note', true);
$userId = svc_bearer_user(); $db = svc_db(); $method = $_SERVER['REQUEST_METHOD'];

// List, search and filter notes. Labels are returned with every card.
if ($path === '/notes' && $method === 'GET') {
    $search = trim((string)($_GET['q'] ?? ''));
    $labelId = filter_input(INPUT_GET, 'label_id', FILTER_VALIDATE_INT) ?: null;
    $cacheKey = note_cache_key($userId, $search, $labelId);
    $cached = svc_cache_get($cacheKey);
    if ($cached !== null && is_array($payload = json_decode($cached, true))) {
        $payload['meta'] = ['cache' => 'hit'];
        svc_json(200, $payload);
    }
    $sql = "SELECT n.note_id,n.title,n.content,n.pinned,n.status,n.note_type,n.reminder_at,n.created_at,n.updated_at,n.pin_hash IS NOT NULL AS pin_locked,GROUP_CONCAT(DISTINCT l.name ORDER BY l.name SEPARATOR ',') AS labels FROM notes n LEFT JOIN note_labels nl ON nl.note_id=n.note_id LEFT JOIN labels l ON l.label_id=nl.label_id WHERE n.user_id=? AND n.archived=0";
    $params = [$userId];
    if ($search !== '') { $sql .= ' AND (n.title LIKE ? OR n.content LIKE ?)'; $like = '%' . $search . '%'; array_push($params, $like, $like); }
    if ($labelId !== null) { $sql .= ' AND EXISTS (SELECT 1 FROM note_labels f WHERE f.note_id=n.note_id AND f.label_id=?)'; $params[] = $labelId; }
    $q = $db->prepare($sql . ' GROUP BY n.note_id ORDER BY n.pinned DESC,n.updated_at DESC'); $q->execute($params);
    $payload = ['data' => array_map('note_payload', $q->fetchAll()), 'timezone' => 'Asia/Ho_Chi_Minh'];
    svc_cache_set($cacheKey, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 30);
    $payload['meta'] = ['cache' => 'miss'];
    svc_json(200, $payload);
}
if ($path === '/notes' && $method === 'POST') {
    $data = valid_note_input(svc_input());
    $q = $db->prepare('INSERT INTO notes (user_id,title,content,note_type,status,reminder_at,reminder_sent) VALUES (?,?,?,?,?,?,0)');
    $q->execute([$userId, $data['title'], $data['content'], $data['note_type'] ?? 'note', $data['status'] ?? 'todo', $data['reminder_at'] ?? null]);
    note_cache_invalidate($userId);
    svc_json(201, ['note_id' => (int)$db->lastInsertId(), 'timezone' => 'Asia/Ho_Chi_Minh']);
}
if (preg_match('#^/notes/(\d+)$#', $path, $m)) {
    $noteId = (int)$m[1];
    if ($method === 'GET') svc_json(200, ['data' => note_payload(note_owned($db, $noteId, $userId))]);
    if (in_array($method, ['PUT', 'PATCH'], true)) {
        $data = valid_note_input(svc_input(), true); if (!$data) svc_json(422, ['error' => 'NO_CHANGES']); note_owned($db, $noteId, $userId);
        $sets = []; $params = []; foreach ($data as $field => $value) { $sets[] = "{$field}=?"; $params[] = $value; }
        $params[] = $noteId; $params[] = $userId;
        $q = $db->prepare('UPDATE notes SET ' . implode(',', $sets) . ',updated_at=NOW() WHERE note_id=? AND user_id=?'); $q->execute($params);
        note_cache_invalidate($userId);
        svc_json(200, ['status' => 'updated']);
    }
    if ($method === 'DELETE') { note_owned($db, $noteId, $userId); $q = $db->prepare('UPDATE notes SET archived=1,updated_at=NOW() WHERE note_id=? AND user_id=?'); $q->execute([$noteId, $userId]); note_cache_invalidate($userId); svc_json(200, ['status' => 'archived']); }
}
if (preg_match('#^/notes/(\d+)/pin$#', $path, $m) && $method === 'POST') {
    $pin = (string)(svc_input()['pin'] ?? ''); if (!preg_match('/^\d{6}$/', $pin)) svc_json(422, ['error' => 'PIN_MUST_BE_6_DIGITS']);
    note_owned($db, (int)$m[1], $userId); $q = $db->prepare('UPDATE notes SET pin_hash=?,pin_set_at=NOW(),updated_at=NOW() WHERE note_id=? AND user_id=?');
    $q->execute([password_hash($pin, PASSWORD_DEFAULT), (int)$m[1], $userId]); note_cache_invalidate($userId); svc_json(200, ['status' => 'ok']);
}

// User-owned label CRUD and attachment/detachment.
if ($path === '/labels' && $method === 'GET') {
    $q = $db->prepare('SELECT l.label_id,l.name,l.created_at,COUNT(nl.note_id) AS note_count FROM labels l LEFT JOIN note_labels nl ON nl.label_id=l.label_id WHERE l.user_id=? GROUP BY l.label_id ORDER BY l.name');
    $q->execute([$userId]); svc_json(200, ['data' => $q->fetchAll()]);
}
if ($path === '/labels' && $method === 'POST') {
    $name = trim((string)(svc_input()['name'] ?? '')); if ($name === '' || mb_strlen($name) > 100) svc_json(422, ['error' => 'INVALID_LABEL']);
    try { $q = $db->prepare('INSERT INTO labels (name,user_id) VALUES (?,?)'); $q->execute([$name, $userId]); note_cache_invalidate($userId); svc_json(201, ['label_id' => (int)$db->lastInsertId()]); }
    catch (PDOException $e) { svc_json(409, ['error' => 'LABEL_EXISTS']); }
}
if (preg_match('#^/labels/(\d+)$#', $path, $m)) {
    $labelId = (int)$m[1];
    if (in_array($method, ['PUT', 'PATCH'], true)) {
        label_owned($db, $labelId, $userId); $name = trim((string)(svc_input()['name'] ?? '')); if ($name === '' || mb_strlen($name) > 100) svc_json(422, ['error' => 'INVALID_LABEL']);
        try { $q = $db->prepare('UPDATE labels SET name=? WHERE label_id=? AND user_id=?'); $q->execute([$name, $labelId, $userId]); note_cache_invalidate($userId); svc_json(200, ['status' => 'updated']); }
        catch (PDOException $e) { svc_json(409, ['error' => 'LABEL_EXISTS']); }
    }
    if ($method === 'DELETE') { label_owned($db, $labelId, $userId); $q = $db->prepare('DELETE FROM labels WHERE label_id=? AND user_id=?'); $q->execute([$labelId, $userId]); note_cache_invalidate($userId); svc_json(200, ['status' => 'deleted']); }
}
if (preg_match('#^/notes/(\d+)/labels/(\d+)$#', $path, $m)) {
    $noteId = (int)$m[1]; $labelId = (int)$m[2]; note_owned($db, $noteId, $userId); label_owned($db, $labelId, $userId);
    if ($method === 'POST') { $q = $db->prepare('INSERT IGNORE INTO note_labels (note_id,label_id) VALUES (?,?)'); $q->execute([$noteId, $labelId]); note_cache_invalidate($userId); svc_json(200, ['status' => 'attached']); }
    if ($method === 'DELETE') { $q = $db->prepare('DELETE FROM note_labels WHERE note_id=? AND label_id=?'); $q->execute([$noteId, $labelId]); note_cache_invalidate($userId); svc_json(200, ['status' => 'detached']); }
}
svc_json(404, ['error' => 'ROUTE_NOT_FOUND']);
