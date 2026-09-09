<?php

function ai_risk_for(string $tool): string {
    static $map = [
        // ── Read tools ───────────────────────────────────────────────────────
        'search_notes'       => 'read',
        'semantic_search'    => 'read',
        'get_note'           => 'read',
        'get_recent_notes'   => 'read',
        'get_labels'         => 'read',
        'find_related_notes' => 'read',
        'get_deadlines'      => 'read',
        'get_note_statistics'=> 'read',
        // ── Write/AI-generate tools (no DB mutation, pass content to AI) ────
        'create_note'        => 'write',
        'update_note'        => 'write',
        'create_label'       => 'write',
        'suggest_labels'     => 'write',
        'extract_tasks'      => 'write',
        'summarize_note'     => 'write',
        'generate_flashcards'=> 'write',
        'generate_quiz'      => 'write',
        // ── Destructive ──────────────────────────────────────────────────────
        'delete_note'        => 'destructive',
    ];
    return $map[$tool] ?? 'unknown';
}

function ai_allowed_tools(): array {
    return [
        // Read
        'search_notes', 'semantic_search', 'get_note', 'get_recent_notes',
        'get_labels', 'find_related_notes', 'get_deadlines', 'get_note_statistics',
        // Write / AI-generate
        'create_note', 'update_note', 'create_label',
        'suggest_labels', 'extract_tasks', 'summarize_note',
        'generate_flashcards', 'generate_quiz',
        // Destructive
        'delete_note',
    ];
}

function ai_truncate(?string $text, int $max): string {
    $text = (string) $text;
    if (mb_strlen($text, 'UTF-8') <= $max) {
        return $text;
    }
    return mb_substr($text, 0, $max, 'UTF-8') . '…';
}

function ai_note_unlocked(int $note_id, ?string $password_hash): bool {
    if (!$password_hash) {
        return true;
    }
    return !empty($_SESSION['accessed_notes'][$note_id]);
}

class AiNoteTools {
    private mysqli $conn;
    private int $user_id;

    public function __construct(mysqli $conn, int $user_id) {
        $this->conn = $conn;
        $this->user_id = $user_id;
    }

    public function execute(string $name, array $args): array {
        switch ($name) {
            // ── Read tools ────────────────────────────────────────────────────
            case 'search_notes':
                return $this->searchNotes($args);
            case 'semantic_search':
                return $this->semanticSearch($args);
            case 'get_note':
                return $this->getNote($args);
            case 'get_recent_notes':
                return $this->getRecentNotes($args);
            case 'get_labels':
                return $this->getLabels();
            case 'find_related_notes':
                return $this->findRelatedNotes($args);
            case 'get_deadlines':
                return $this->getDeadlines($args);
            case 'get_note_statistics':
                return $this->getNoteStatistics();
            // ── AI-content tools (fetch note → return to Python for processing) ─
            case 'summarize_note':
            case 'extract_tasks':
            case 'suggest_labels':
            case 'generate_flashcards':
            case 'generate_quiz':
                return $this->getNoteForAi($args, $name);
            // ── Write / CRUD ──────────────────────────────────────────────────
            case 'create_note':
                return $this->createNote($args);
            case 'update_note':
                return $this->updateNote($args);
            case 'create_label':
                return $this->createLabel($args);
            case 'delete_note':
                return $this->deleteNote($args);
            default:
                return ['ok' => false, 'error' => 'Unknown tool'];
        }
    }

    // ── Keyword search ────────────────────────────────────────────────────────

    private function searchNotes(array $args): array {
        $query = trim((string) ($args['query'] ?? ''));
        $limit = min(20, max(1, (int) ($args['limit'] ?? 8)));
        if ($query === '' || mb_strlen($query, 'UTF-8') > 200) {
            return ['ok' => false, 'error' => 'Invalid search query'];
        }
        $like = '%' . $query . '%';
        $sql = "SELECT n.note_id, n.title, n.content, n.updated_at, n.password_hash,
                       GROUP_CONCAT(l.name) AS labels
                FROM notes n
                LEFT JOIN note_labels nl ON n.note_id = nl.note_id
                LEFT JOIN labels l ON nl.label_id = l.label_id
                WHERE n.user_id = ?
                  AND n.archived = 0
                  AND (n.title LIKE ? OR n.content LIKE ? OR l.name LIKE ?)
                GROUP BY n.note_id
                ORDER BY n.pinned DESC, n.updated_at DESC
                LIMIT ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('isssi', $this->user_id, $like, $like, $like, $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        $notes = [];
        while ($row = $res->fetch_assoc()) {
            $notes[] = $this->publicNote($row, true);
        }
        return ['ok' => true, 'notes' => $notes];
    }

    // ── Semantic search — results pre-computed by Python RAG engine ───────────

    private function semanticSearch(array $args): array {
        // Python already ran cosine similarity and provided note_ids in 'semantic_results'.
        // We hydrate them from DB to ensure freshness and access control.
        $precomputed = $args['semantic_results'] ?? [];
        if (!empty($precomputed) && is_array($precomputed)) {
            return $this->hydrateSemanticResults($precomputed);
        }
        // Fallback: keyword search if RAG didn't return results
        return $this->searchNotes([
            'query' => $args['query'] ?? '',
            'limit' => $args['top_k'] ?? 5,
        ]);
    }

    private function hydrateSemanticResults(array $results): array {
        $notes = [];
        foreach ($results as $r) {
            $note_id = (int) ($r['note_id'] ?? 0);
            if ($note_id <= 0) continue;
            $got = $this->getNote(['note_id' => $note_id]);
            if ($got['ok']) {
                $note = $got['note'];
                $note['similarity_score'] = round((float) ($r['score'] ?? 0), 3);
                $notes[] = $note;
            }
        }
        return ['ok' => true, 'notes' => $notes, 'mode' => 'semantic'];
    }

    // ── Find related notes using precomputed semantic results ─────────────────

    private function findRelatedNotes(array $args): array {
        // Python's retriever provides 'related_results' via the retrieval hints,
        // or we fall back to same-label notes.
        $related = $args['related_results'] ?? [];
        if (!empty($related) && is_array($related)) {
            return $this->hydrateSemanticResults($related);
        }
        // Fallback: find notes sharing the same labels
        $note_id = (int) ($args['note_id'] ?? 0);
        if ($note_id <= 0) return ['ok' => false, 'error' => 'Invalid note_id'];
        $limit = min(10, max(1, (int) ($args['limit'] ?? 5)));
        $sql = "SELECT DISTINCT n.note_id, n.title, n.content, n.updated_at,
                       n.password_hash, GROUP_CONCAT(l2.name) AS labels
                FROM note_labels nl1
                JOIN note_labels nl2 ON nl1.label_id = nl2.label_id AND nl2.note_id != ?
                JOIN notes n ON nl2.note_id = n.note_id
                LEFT JOIN note_labels nl3 ON n.note_id = nl3.note_id
                LEFT JOIN labels l2 ON nl3.label_id = l2.label_id
                WHERE nl1.note_id = ? AND n.user_id = ? AND n.archived = 0
                GROUP BY n.note_id
                ORDER BY n.updated_at DESC
                LIMIT ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('iiii', $note_id, $note_id, $this->user_id, $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        $notes = [];
        while ($row = $res->fetch_assoc()) {
            $notes[] = $this->publicNote($row, true);
        }
        return ['ok' => true, 'notes' => $notes, 'mode' => 'related_by_label'];
    }

    // ── Get note content for AI processing (summarize/quiz/flashcard/etc.) ────

    private function getNoteForAi(array $args, string $tool): array {
        $note_id = (int) ($args['note_id'] ?? 0);
        if ($note_id <= 0) {
            return ['ok' => false, 'error' => 'Invalid note_id'];
        }
        $got = $this->getNote(['note_id' => $note_id]);
        if (!$got['ok']) return $got;
        $note = $got['note'];
        // Return note content so Python/Gemini can process it
        return [
            'ok'      => true,
            'tool'    => $tool,
            'note_id' => $note_id,
            'title'   => $note['title'],
            'content' => $note['content'],   // Full content (up to 8000 chars, from publicNote)
            'labels'  => $note['labels'],
            'style'   => $args['style'] ?? 'short',   // for summarize_note
            'count'   => (int) ($args['count'] ?? 5), // for quiz/flashcard
        ];
    }

    // ── Note statistics ───────────────────────────────────────────────────────

    private function getNoteStatistics(): array {
        // Total notes
        $stmt = $this->conn->prepare(
            'SELECT COUNT(*) AS total FROM notes WHERE user_id = ? AND archived = 0'
        );
        $stmt->bind_param('i', $this->user_id);
        $stmt->execute();
        $total = (int) $stmt->get_result()->fetch_assoc()['total'];

        // Task notes
        $stmt2 = $this->conn->prepare(
            "SELECT COUNT(*) AS tasks FROM notes WHERE user_id = ? AND note_type = 'task' AND archived = 0"
        );
        $stmt2->bind_param('i', $this->user_id);
        $stmt2->execute();
        $tasks = (int) $stmt2->get_result()->fetch_assoc()['tasks'];

        // Notes this week
        $stmt3 = $this->conn->prepare(
            'SELECT COUNT(*) AS week FROM notes WHERE user_id = ? AND archived = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'
        );
        $stmt3->bind_param('i', $this->user_id);
        $stmt3->execute();
        $this_week = (int) ($stmt3->get_result()->fetch_assoc()['week'] ?? 0);

        // Top labels
        $stmt4 = $this->conn->prepare(
            'SELECT l.name, COUNT(nl.note_id) AS cnt
             FROM labels l
             JOIN note_labels nl ON l.label_id = nl.label_id
             JOIN notes n ON nl.note_id = n.note_id
             WHERE n.user_id = ? AND n.archived = 0
             GROUP BY l.label_id ORDER BY cnt DESC LIMIT 10'
        );
        $stmt4->bind_param('i', $this->user_id);
        $stmt4->execute();
        $res4 = $stmt4->get_result();
        $top_labels = [];
        while ($row = $res4->fetch_assoc()) {
            $top_labels[] = ['label' => $row['name'], 'count' => (int) $row['cnt']];
        }

        return [
            'ok'            => true,
            'total_notes'   => $total,
            'task_notes'    => $tasks,
            'notes_this_week' => $this_week,
            'top_topics'    => $top_labels,
        ];
    }

    // ── Deadlines (task notes with dates in content or title) ─────────────────

    private function getDeadlines(array $args): array {
        $range = $args['range'] ?? 'this_week';
        $interval_map = [
            'today'      => 'INTERVAL 1 DAY',
            'this_week'  => 'INTERVAL 7 DAY',
            'this_month' => 'INTERVAL 30 DAY',
            'all'        => 'INTERVAL 3650 DAY',
        ];
        $interval = $interval_map[$range] ?? 'INTERVAL 7 DAY';

        // Get recent task-type notes as potential deadline holders
        $sql = "SELECT n.note_id, n.title, n.content, n.created_at, n.updated_at,
                       n.password_hash, GROUP_CONCAT(l.name) AS labels
                FROM notes n
                LEFT JOIN note_labels nl ON n.note_id = nl.note_id
                LEFT JOIN labels l ON nl.label_id = l.label_id
                WHERE n.user_id = ?
                  AND n.archived = 0
                  AND (n.note_type = 'task'
                       OR n.title LIKE '%deadline%'
                       OR n.title LIKE '%hạn%'
                       OR n.content LIKE '%deadline%'
                       OR n.content LIKE '%hạn chót%'
                       OR n.content LIKE '%cần hoàn thành%')
                  AND n.updated_at >= DATE_SUB(NOW(), {$interval})
                GROUP BY n.note_id
                ORDER BY n.updated_at DESC
                LIMIT 20";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $this->user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $notes = [];
        while ($row = $res->fetch_assoc()) {
            $notes[] = $this->publicNote($row, false); // full content for AI analysis
        }
        return [
            'ok'    => true,
            'range' => $range,
            'notes' => $notes,
            'hint'  => 'Analyze each note content to identify specific tasks and deadlines. Present them in priority order.',
        ];
    }

    private function getNote(array $args): array {
        $note_id = (int) ($args['note_id'] ?? 0);
        if ($note_id <= 0) {
            return ['ok' => false, 'error' => 'Invalid note_id'];
        }
        $stmt = $this->conn->prepare(
            "SELECT n.note_id, n.title, n.content, n.updated_at, n.password_hash,
                    GROUP_CONCAT(l.name) AS labels
             FROM notes n
             LEFT JOIN note_labels nl ON n.note_id = nl.note_id
             LEFT JOIN labels l ON nl.label_id = l.label_id
             WHERE n.note_id = ? AND n.user_id = ?
             GROUP BY n.note_id
             LIMIT 1"
        );
        $stmt->bind_param('ii', $note_id, $this->user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) {
            return ['ok' => false, 'error' => 'Note not found or unauthorized'];
        }
        return ['ok' => true, 'note' => $this->publicNote($row, false)];
    }

    private function getRecentNotes(array $args): array {
        $limit = min(10, max(1, (int) ($args['limit'] ?? 5)));
        $stmt = $this->conn->prepare(
            "SELECT n.note_id, n.title, n.content, n.updated_at, n.password_hash,
                    GROUP_CONCAT(l.name) AS labels
             FROM notes n
             LEFT JOIN note_labels nl ON n.note_id = nl.note_id
             LEFT JOIN labels l ON nl.label_id = l.label_id
             WHERE n.user_id = ? AND n.archived = 0
             GROUP BY n.note_id
             ORDER BY n.updated_at DESC
             LIMIT ?"
        );
        $stmt->bind_param('ii', $this->user_id, $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        $notes = [];
        while ($row = $res->fetch_assoc()) {
            $notes[] = $this->publicNote($row, true);
        }
        return ['ok' => true, 'notes' => $notes];
    }

    private function getLabels(): array {
        $stmt = $this->conn->prepare(
            "SELECT DISTINCT l.label_id, l.name
             FROM labels l
             LEFT JOIN note_labels nl ON l.label_id = nl.label_id
             LEFT JOIN notes n ON nl.note_id = n.note_id
             WHERE l.user_id = ? OR n.user_id = ?
             ORDER BY l.name ASC
             LIMIT 50"
        );
        $stmt->bind_param('ii', $this->user_id, $this->user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $labels = [];
        while ($row = $res->fetch_assoc()) {
            $labels[] = ['label_id' => (int) $row['label_id'], 'name' => $row['name']];
        }
        return ['ok' => true, 'labels' => $labels];
    }

    private function createNote(array $args): array {
        $title = trim((string) ($args['title'] ?? ''));
        $content = trim((string) ($args['content'] ?? ''));
        $labels = $this->normalizeLabels($args['labels'] ?? []);
        $note_type = $this->normalizeNoteType($args['note_type'] ?? 'note');
        $background_color = $this->normalizeHexColor($args['background_color'] ?? null, '#ffffff');
        $text_color = $this->normalizeHexColor($args['text_color'] ?? null, '#000000');
        if ($title === '' || mb_strlen($title, 'UTF-8') > 255) {
            return ['ok' => false, 'error' => 'Invalid title'];
        }
        if ($content === '' || mb_strlen($content, 'UTF-8') > 50000) {
            return ['ok' => false, 'error' => 'Invalid content'];
        }
        $stmt = $this->conn->prepare(
            "INSERT INTO notes (user_id, title, content, note_type, background_color, text_color)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('isssss', $this->user_id, $title, $content, $note_type, $background_color, $text_color);
        if (!$stmt->execute()) {
            return ['ok' => false, 'error' => 'Failed to create note'];
        }
        $note_id = (int) $this->conn->insert_id;
        $this->attachLabels($note_id, $labels);

        // Async: trigger RAG indexing for the new note
        $this->indexNoteForRag($note_id, $title, $content, $labels);

        return [
            'ok'               => true,
            'note_id'          => $note_id,
            'title'            => $title,
            'content'          => $content,
            'labels'           => $labels,
            'note_type'        => $note_type,
            'background_color' => $background_color,
            'text_color'       => $text_color,
        ];
    }

    // ── RAG Indexing helper ───────────────────────────────────────────────────

    /**
     * Call Python /v1/index to embed and store the note for semantic search.
     * Non-blocking: uses cURL with a short timeout; failure is silent.
     */
    private function indexNoteForRag(int $note_id, string $title, string $content, array $labels): void {
        $url = rtrim((string)(getenv('AI_AGENT_URL') ?: 'http://127.0.0.1:8765'), '/') . '/v1/index';
        $secret = (string) getenv('AI_AGENT_SHARED_SECRET');
        $payload = json_encode([
            'note_id' => $note_id,
            'user_id' => $this->user_id,
            'title'   => $title,
            'content' => $content,
            'labels'  => $labels,
        ], JSON_UNESCAPED_UNICODE);

        if (!function_exists('curl_init')) return;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-Notezy-Agent-Secret: ' . $secret,
            ],
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 3,   // Non-blocking: fail fast
            CURLOPT_CONNECTTIMEOUT => 1,
        ]);
        @curl_exec($ch);
        curl_close($ch);
    }

    private function normalizeNoteType($raw): string {
        $val = trim((string) $raw);
        return $val === 'task' ? 'task' : 'note';
    }

    private function normalizeHexColor($raw, string $default): string {
        $val = trim((string) $raw);
        return preg_match('/^#[0-9a-fA-F]{6}$/', $val) ? $val : $default;
    }

    private function updateNote(array $args): array {
        $note_id = (int) ($args['note_id'] ?? 0);
        if ($note_id <= 0 || !$this->ownsNote($note_id)) {
            return ['ok' => false, 'error' => 'Note not found or unauthorized'];
        }
        $sets = [];
        $types = '';
        $values = [];
        if (isset($args['title'])) {
            $title = trim((string) $args['title']);
            if ($title === '' || mb_strlen($title, 'UTF-8') > 255) {
                return ['ok' => false, 'error' => 'Invalid title'];
            }
            $sets[] = 'title = ?';
            $types .= 's';
            $values[] = $title;
        }
        if (isset($args['content'])) {
            $content = (string) $args['content'];
            if (mb_strlen($content, 'UTF-8') > 50000) {
                return ['ok' => false, 'error' => 'Invalid content'];
            }
            $sets[] = 'content = ?';
            $types .= 's';
            $values[] = $content;
        }
        if (isset($args['note_type'])) {
            $sets[] = 'note_type = ?';
            $types .= 's';
            $values[] = $this->normalizeNoteType($args['note_type']);
        }
        if (isset($args['background_color'])) {
            $sets[] = 'background_color = ?';
            $types .= 's';
            $values[] = $this->normalizeHexColor($args['background_color'], '#ffffff');
        }
        if (isset($args['text_color'])) {
            $sets[] = 'text_color = ?';
            $types .= 's';
            $values[] = $this->normalizeHexColor($args['text_color'], '#000000');
        }
        if ($sets) {
            $sql = 'UPDATE notes SET ' . implode(', ', $sets) . ' WHERE note_id = ? AND user_id = ?';
            $types .= 'ii';
            $values[] = $note_id;
            $values[] = $this->user_id;
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param($types, ...$values);
            if (!$stmt->execute()) {
                return ['ok' => false, 'error' => 'Failed to update note'];
            }
        }
        if (isset($args['labels'])) {
            $labels = $this->normalizeLabels($args['labels']);
            $del = $this->conn->prepare('DELETE FROM note_labels WHERE note_id = ?');
            $del->bind_param('i', $note_id);
            $del->execute();
            $this->attachLabels($note_id, $labels);
        }
        return ['ok' => true, 'note_id' => $note_id];
    }

    private function createLabel(array $args): array {
        $name = trim((string) ($args['name'] ?? ''));
        $note_id = (int) ($args['note_id'] ?? 0);
        if ($name === '' || mb_strlen($name, 'UTF-8') > 100) {
            return ['ok' => false, 'error' => 'Invalid label name'];
        }
        if ($note_id > 0 && !$this->ownsNote($note_id)) {
            return ['ok' => false, 'error' => 'Note not found or unauthorized'];
        }
        $label_id = $this->findOrCreateLabel($name);
        if ($note_id > 0) {
            $nl = $this->conn->prepare('INSERT IGNORE INTO note_labels (note_id, label_id) VALUES (?, ?)');
            $nl->bind_param('ii', $note_id, $label_id);
            $nl->execute();
        }
        return ['ok' => true, 'label_id' => $label_id, 'name' => $name];
    }

    private function deleteNote(array $args): array {
        $note_id = (int) ($args['note_id'] ?? 0);
        if ($note_id <= 0) {
            return ['ok' => false, 'error' => 'Invalid note_id'];
        }
        $img_stmt = $this->conn->prepare('SELECT image_path FROM notes WHERE note_id = ? AND user_id = ?');
        $img_stmt->bind_param('ii', $note_id, $this->user_id);
        $img_stmt->execute();
        $img_row = $img_stmt->get_result()->fetch_assoc();
        if (!$img_row) {
            return ['ok' => false, 'error' => 'Note not found or unauthorized'];
        }
        if (!empty($img_row['image_path'])) {
            $full_path = __DIR__ . '/../../../' . $img_row['image_path'];
            $real_uploads = realpath(__DIR__ . '/../../../uploads');
            $real_file = realpath($full_path);
            if ($real_file && $real_uploads && str_starts_with($real_file, $real_uploads) && is_file($real_file)) {
                @unlink($real_file);
            }
        }
        $del = $this->conn->prepare('DELETE FROM notes WHERE note_id = ? AND user_id = ?');
        $del->bind_param('ii', $note_id, $this->user_id);
        if ($del->execute() && $del->affected_rows > 0) {
            return ['ok' => true, 'note_id' => $note_id];
        }
        return ['ok' => false, 'error' => 'Delete failed or note not found'];
    }

    private function ownsNote(int $note_id): bool {
        $stmt = $this->conn->prepare('SELECT note_id FROM notes WHERE note_id = ? AND user_id = ? LIMIT 1');
        $stmt->bind_param('ii', $note_id, $this->user_id);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }

    private function publicNote(array $row, bool $preview): array {
        $locked = !ai_note_unlocked((int) $row['note_id'], $row['password_hash'] ?? null);
        $content = $locked ? '[Password protected note]' : (string) $row['content'];
        return [
            'note_id'            => (int) $row['note_id'],
            'title'              => $row['title'],
            'content'            => $preview ? ai_truncate($content, 400) : ai_truncate($content, 8000),
            'labels'             => $row['labels'] ? explode(',', $row['labels']) : [],
            'updated_at'         => $row['updated_at'] ?? null,
            'created_at'         => $row['created_at'] ?? null,
            'password_protected' => $locked,
        ];
    }

    private function normalizeLabels($raw): array {
        if (!is_array($raw)) {
            $raw = preg_split('/[,;]+/', (string) $raw) ?: [];
        }
        $out = [];
        foreach ($raw as $name) {
            $name = trim((string) $name);
            if ($name === '' || mb_strlen($name, 'UTF-8') > 100) {
                continue;
            }
            $out[] = $name;
            if (count($out) >= 8) {
                break;
            }
        }
        return array_values(array_unique($out));
    }

    private function attachLabels(int $note_id, array $labels): void {
        foreach ($labels as $name) {
            $label_id = $this->findOrCreateLabel($name);
            $nl = $this->conn->prepare('INSERT IGNORE INTO note_labels (note_id, label_id) VALUES (?, ?)');
            $nl->bind_param('ii', $note_id, $label_id);
            $nl->execute();
        }
    }

    private function findOrCreateLabel(string $name): int {
        $sel = $this->conn->prepare('SELECT label_id FROM labels WHERE name = ? AND (user_id = ? OR user_id IS NULL) LIMIT 1');
        $sel->bind_param('si', $name, $this->user_id);
        $sel->execute();
        $row = $sel->get_result()->fetch_assoc();
        if ($row) {
            return (int) $row['label_id'];
        }
        $ins = $this->conn->prepare('INSERT INTO labels (name, user_id) VALUES (?, ?)');
        $ins->bind_param('si', $name, $this->user_id);
        if ($ins->execute()) {
            return (int) $this->conn->insert_id;
        }
        // SECURITY FIX (audit 2026-09-02): race-condition fallback was
        // scoped globally (no user_id), reintroducing the cross-user label
        // collision this class otherwise avoids. Scope it like the primary lookup.
        $sel2 = $this->conn->prepare('SELECT label_id FROM labels WHERE name = ? AND user_id = ? LIMIT 1');
        $sel2->bind_param('si', $name, $this->user_id);
        $sel2->execute();
        $row2 = $sel2->get_result()->fetch_assoc();
        return $row2 ? (int) $row2['label_id'] : 0;
    }
}

class AiConversationManager {
    private mysqli $conn;
    private int $user_id;

    public function __construct(mysqli $conn, int $user_id) {
        $this->conn = $conn;
        $this->user_id = $user_id;
    }

    public function getOrCreate(?int $conversation_id, string $first_message): int {
        if ($conversation_id) {
            $stmt = $this->conn->prepare('SELECT id FROM ai_conversations WHERE id = ? AND user_id = ?');
            $stmt->bind_param('ii', $conversation_id, $this->user_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 1) {
                return $conversation_id;
            }
        }
        $title = ai_truncate($first_message, 80);
        $ins = $this->conn->prepare('INSERT INTO ai_conversations (user_id, title) VALUES (?, ?)');
        $ins->bind_param('is', $this->user_id, $title);
        $ins->execute();
        return (int) $this->conn->insert_id;
    }

    public function addMessage(int $conversation_id, string $role, string $content, ?array $metadata = null): void {
        $meta = $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null;
        $stmt = $this->conn->prepare(
            'INSERT INTO ai_messages (conversation_id, user_id, role, content, metadata) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('iisss', $conversation_id, $this->user_id, $role, $content, $meta);
        $stmt->execute();
        $upd = $this->conn->prepare('UPDATE ai_conversations SET updated_at = NOW() WHERE id = ? AND user_id = ?');
        $upd->bind_param('ii', $conversation_id, $this->user_id);
        $upd->execute();
    }

    public function history(int $conversation_id, int $limit = 20): array {
        $stmt = $this->conn->prepare(
            'SELECT role, content, metadata FROM ai_messages
             WHERE conversation_id = ? AND user_id = ?
             ORDER BY id DESC LIMIT ?'
        );
        $stmt->bind_param('iii', $conversation_id, $this->user_id, $limit);
        $stmt->execute();
        $rows = [];
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        return array_reverse($rows);
    }

    public function listConversations(): array {
        $stmt = $this->conn->prepare(
            'SELECT id, title, updated_at FROM ai_conversations WHERE user_id = ? ORDER BY updated_at DESC LIMIT 30'
        );
        $stmt->bind_param('i', $this->user_id);
        $stmt->execute();
        $out = [];
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $out[] = $row;
        }
        return $out;
    }
}

class AiContextManager {
    private mysqli $conn;
    private int $user_id;

    public function __construct(mysqli $conn, int $user_id) {
        $this->conn = $conn;
        $this->user_id = $user_id;
    }

    public function build(array $client_context): array {
        $page = substr((string) ($client_context['page'] ?? 'notes_list'), 0, 40);
        $current_note_id = (int) ($client_context['current_note_id'] ?? 0);
        $tools = new AiNoteTools($this->conn, $this->user_id);
        $recent = $tools->execute('get_recent_notes', ['limit' => 5]);
        $labels = $tools->execute('get_labels', []);
        $current = null;
        if ($current_note_id > 0) {
            $got = $tools->execute('get_note', ['note_id' => $current_note_id]);
            $current = $got['ok'] ? $got['note'] : null;
        }
        return [
            'authenticated_user' => true,
            'page' => $page,
            'current_note' => $current,
            'recent_notes' => $recent['notes'] ?? [],
            'labels' => $labels['labels'] ?? [],
            'last_created_note_id' => isset($_SESSION['ai_last_created_note_id']) ? (int) $_SESSION['ai_last_created_note_id'] : null,
        ];
    }
}

class AiPendingStore {
    private mysqli $conn;
    private int $user_id;

    public function __construct(mysqli $conn, int $user_id) {
        $this->conn = $conn;
        $this->user_id = $user_id;
    }

    public function create(int $conversation_id, string $action, string $risk, array $payload): string {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $stmt = $this->conn->prepare(
            'INSERT INTO ai_pending_actions (user_id, conversation_id, token_hash, action, risk, payload, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))'
        );
        $stmt->bind_param('iissss', $this->user_id, $conversation_id, $hash, $action, $risk, $json);
        $stmt->execute();
        return $token;
    }

    public function consume(string $token): ?array {
        $hash = hash('sha256', $token);
        $stmt = $this->conn->prepare(
            'SELECT id, conversation_id, action, risk, payload FROM ai_pending_actions
             WHERE token_hash = ? AND user_id = ? AND used_at IS NULL AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->bind_param('si', $hash, $this->user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) {
            return null;
        }
        $upd = $this->conn->prepare('UPDATE ai_pending_actions SET used_at = NOW() WHERE id = ? AND user_id = ? AND used_at IS NULL');
        $id = (int) $row['id'];
        $upd->bind_param('ii', $id, $this->user_id);
        $upd->execute();
        if ($upd->affected_rows !== 1) {
            return null;
        }
        $row['payload'] = json_decode($row['payload'], true) ?: [];
        return $row;
    }
}

function ai_rate_limit(mysqli $conn, int $user_id): void {
    $max = ai_env_int('AI_RATE_LIMIT_PER_MINUTE', 30);
    $window = date('Y-m-d H:i:00');
    $sel = $conn->prepare('SELECT id, request_count FROM ai_rate_limits WHERE user_id = ? AND window_start = ?');
    $sel->bind_param('is', $user_id, $window);
    $sel->execute();
    $row = $sel->get_result()->fetch_assoc();
    if (!$row) {
        $ins = $conn->prepare('INSERT INTO ai_rate_limits (user_id, window_start, request_count) VALUES (?, ?, 1)');
        $ins->bind_param('is', $user_id, $window);
        @$ins->execute();
        return;
    }
    if ((int) $row['request_count'] >= $max) {
        ai_json(429, ['status' => 'error', 'message' => 'Too many AI requests. Please wait a minute.']);
    }
    $upd = $conn->prepare('UPDATE ai_rate_limits SET request_count = request_count + 1 WHERE id = ?');
    $id = (int) $row['id'];
    $upd->bind_param('i', $id);
    $upd->execute();
}

function ai_call_agent(array $payload): array {
    $url = rtrim((string) (getenv('AI_AGENT_URL') ?: 'http://127.0.0.1:8765'), '/') . '/v1/chat';
    $secret = (string) getenv('AI_AGENT_SHARED_SECRET');
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $raw = false;
    $http = 0;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Notezy-Agent-Secret: ' . $secret,
            ],
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_TIMEOUT => 50,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($errno || $raw === false) {
            return ['ok' => false, 'error' => 'AI service is unavailable. Start the Python agent.', 'status' => 503];
        }
    } else {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nX-Notezy-Agent-Secret: {$secret}\r\n",
                'content' => $json,
                'timeout' => 50,
                'ignore_errors' => true,
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $http = (int) $m[1];
        }
        if ($raw === false) {
            return ['ok' => false, 'error' => 'AI service is unavailable. Start the Python agent.', 'status' => 503];
        }
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return ['ok' => false, 'error' => 'AI service returned an invalid response.', 'status' => 502];
    }
    $decoded['http'] = $http;
    return $decoded;
}

function ai_history_for_llm(array $rows): array {
    $out = [];
    foreach ($rows as $row) {
        $role = $row['role'];
        if (!in_array($role, ['user', 'assistant', 'tool'], true)) {
            continue;
        }
        $item = ['role' => $role, 'content' => (string) $row['content']];
        $meta = [];
        if (!empty($row['metadata'])) {
            $meta = is_array($row['metadata']) ? $row['metadata'] : (json_decode($row['metadata'], true) ?: []);
        }
        if (!empty($meta['tool_calls'])) {
            $item['tool_calls'] = $meta['tool_calls'];
        }
        $out[] = $item;
    }
    return $out;
}
