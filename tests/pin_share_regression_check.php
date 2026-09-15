<?php
/**
 * Regression check: sharing must preserve a six-digit PIN lock for recipients,
 * while an ordinary shared note remains readable without any PIN state.
 */
require_once __DIR__ . '/../config/database.php';

$conn = create_connect(true);
$owner = $conn->query("SELECT id FROM users WHERE username = 'user1' LIMIT 1")->fetch_assoc();
$recipient = $conn->query("SELECT id FROM users WHERE username = 'user2' LIMIT 1")->fetch_assoc();
if (!$owner || !$recipient) {
    fwrite(STDERR, "SKIP: seed accounts user1/user2 are unavailable.\n");
    exit(0);
}

$ownerId = (int) $owner['id'];
$recipientId = (int) $recipient['id'];
$token = 'pin-share-test-' . bin2hex(random_bytes(6));
$secureId = 0;
$plainId = 0;

function expect_true(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: {$message}\n";
}

try {
    $pinHash = password_hash('246810', PASSWORD_DEFAULT);
    $insert = $conn->prepare('INSERT INTO notes (user_id, title, content, pin_hash) VALUES (?, ?, ?, ?)');
    $secureTitle = $token . ' secure';
    $secureContent = 'PIN-protected content must not be exposed before unlock.';
    $insert->bind_param('isss', $ownerId, $secureTitle, $secureContent, $pinHash);
    $insert->execute();
    $secureId = (int) $insert->insert_id;

    $plainTitle = $token . ' plain';
    $plainContent = 'Plain shared content is available immediately.';
    $nullPin = null;
    $insert->bind_param('isss', $ownerId, $plainTitle, $plainContent, $nullPin);
    $insert->execute();
    $plainId = (int) $insert->insert_id;
    $insert->close();

    $share = $conn->prepare("INSERT INTO note_shares (note_id, shared_with_user_id, permission, shared_by_user_id) VALUES (?, ?, 'read', ?)");
    foreach ([$secureId, $plainId] as $noteId) {
        $share->bind_param('iii', $noteId, $recipientId, $ownerId);
        $share->execute();
    }
    $share->close();

    $visible = $conn->prepare('SELECT n.note_id, n.pin_hash FROM notes n INNER JOIN note_shares s ON s.note_id = n.note_id WHERE s.shared_with_user_id = ? AND n.note_id IN (?, ?) ORDER BY n.note_id');
    $visible->bind_param('iii', $recipientId, $secureId, $plainId);
    $visible->execute();
    $rows = $visible->get_result()->fetch_all(MYSQLI_ASSOC);
    $visible->close();
    $byId = [];
    foreach ($rows as $row) $byId[(int) $row['note_id']] = $row;

    expect_true(isset($byId[$secureId]) && !empty($byId[$secureId]['pin_hash']), 'Recipient receives a shared PIN-protected note in locked state');
    expect_true(isset($byId[$plainId]) && empty($byId[$plainId]['pin_hash']), 'Recipient receives a shared note without PIN protection');
    expect_true(password_verify('246810', $byId[$secureId]['pin_hash']), 'Six-digit owner PIN is required for the protected shared note');

    $unlock = $conn->prepare('SELECT id FROM note_pin_unlocks WHERE note_id = ? AND user_id = ?');
    $unlock->bind_param('ii', $secureId, $recipientId);
    $unlock->execute();
    expect_true($unlock->get_result()->num_rows === 0, 'Recipient has no automatic PIN unlock after sharing');
    $unlock->close();

    $dashboard = file_get_contents(__DIR__ . '/../index_notezy.php');
    $autosave = file_get_contents(__DIR__ . '/../api/note_autosave.php');
    expect_true(strpos($dashboard, "'is_pin_locked'") !== false && strpos($dashboard, "'masked_content'") !== false, 'Dashboard masks locked shared-note content');
    expect_true(strpos($autosave, 'http_response_code(423)') !== false, 'Protected shared note cannot be edited before PIN unlock');
    echo "PIN share regression checks passed.\n";
} finally {
    if ($secureId > 0 || $plainId > 0) {
        $ids = array_filter([$secureId, $plainId]);
        $list = implode(',', array_map('intval', $ids));
        $conn->query("DELETE FROM notes WHERE note_id IN ({$list}) AND user_id = {$ownerId}");
    }
}
