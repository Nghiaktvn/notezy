<?php
/**
 * Static regression checks for high-risk safeguards. Run: php tests/security_regression_check.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$root = dirname(__DIR__);
$checks = [
    ['api/note_pin.php', 'd{6}', 'PIN API requires exactly six digits'],
    ['themghichu.php', 'maxlength="6"', 'Create-note form accepts six-digit PIN'],
    ['api/notes.php', '$is_pin_locked', 'Notes API protects PIN-locked content'],
    ['api/note_pin.php', 'pin_verify_allowed', 'PIN attempts are rate limited'],
    ['api/note_pin.php', 'note_id = ? AND user_id = ? AND session_id = ?', 'PIN unlock is bound to the recipient account'],
    ['api/note_password.php', "if (\$action === 'verify')", 'Per-note password API verifies protected notes'],
    ['api/note_password.php', "if (\$action === 'disable')", 'Per-note password removal requires confirmation'],
    ['index_notezy.php', 'openManagePasswordModal(<?= $note[\'note_id\'] ?>', 'Per-note password management is reachable from the dashboard'],
    ['edit_note.php', 'A normal content edit must never silently remove', 'Editing note content preserves its password protection'],
    ['index_notezy.php', '.note-actions .btn.btn-action-icon', 'Mobile note actions keep compact touch targets without horizontal overflow'],
    ['index_notezy.php', 'themghichu.php?id=', 'Create and edit actions use the same note editor interface'],
    ['includes/session.php', 'Asia/Ho_Chi_Minh', 'Vietnam timezone is set centrally'],
    ['api/ai/lib/AiServices.php', 'normalizeVietnamReminder', 'AI-created reminders use Vietnam-local validation'],
    ['index_notezy.php', "note_id: document.getElementById('tt_note_id').value", 'Send-to-timetable keeps the source note link'],
    ['index_notezy.php', '<option value="-1">Không đặt báo thức</option>', 'Send-to-timetable supports saving without an alarm'],
    ['index_notezy.php', '<option value="7">Chủ Nhật</option>', 'Sunday uses the API day-of-week contract'],
    ['js/timetable-alarm.js', 'if (Number.isFinite(reminderValue) && reminderValue < 0) return;', 'Disabled timetable alarms never fire'],
    ['js/collaboration-ws.js', "endsWith('.app.github.dev')", 'Realtime collaboration supports GitHub Codespaces HTTPS tunnels'],
    ['.devcontainer/initialize.sh', 'openssl rand -hex 32', 'Codespaces generates secrets outside source control'],
    ['.devcontainer/devcontainer.json', 'docker-in-docker:2', 'Codespaces uses an isolated Docker runtime with a real workspace'],
    ['.devcontainer/devcontainer.json', '"forwardPorts": [8080, 8081, 8765, 8766, 8088]', 'Codespaces forwards the actual Compose host ports'],
    ['docker-compose.services.yml', 'AI_AGENT_SHARED_SECRET:?Set', 'Docker requires a configured service token secret'],
];

$failed = [];
foreach ($checks as [$file, $needle, $label]) {
    $content = file_get_contents($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file));
    if ($content === false || strpos($content, $needle) === false) {
        $failed[] = $label;
        continue;
    }
    echo "PASS: {$label}\n";
}

if ($failed) {
    fwrite(STDERR, "FAILED:\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}
echo "Security regression checks passed.\n";
