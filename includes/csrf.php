<?php
/**
 * includes/csrf.php
 *
 * Minimal session-bound CSRF token helper for legacy, server-rendered PHP
 * pages (forms posted directly, not through the JSON api/ layer).
 *
 * Context (security audit 2026-09-02): the app already sets the session
 * cookie's SameSite=Lax attribute on login (see api/login.php), which blocks
 * the classic cross-site auto-submitting <form> attack in all modern
 * browsers — so CSRF risk here was already Low, not Critical. This token
 * adds defense-in-depth (protects users on older/misconfigured browsers,
 * and covers any future subdomain the app might be deployed under) without
 * relying on SameSite alone. Must be call require_once'd AFTER session_start().
 *
 * Usage in a page that renders a form:
 *   require_once __DIR__ . '/includes/csrf.php';
 *   ... <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"> ...
 *
 * Usage when handling that form's POST:
 *   if (!csrf_check($_POST['csrf_token'] ?? '')) { reject } 
 *
 * NOTE: only wired into manage_labels.php so far as a proof-of-concept fix
 * tied directly to the reported label bug. Other legacy POST-handling pages
 * (edit_note.php, delete_note.php, archive_note.php, account.php,
 * update_password.php, etc.) still rely solely on SameSite=Lax and should be
 * migrated to use this helper too — see the audit report for the full list.
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    // Caller forgot to session_start() first — token would not persist.
    throw new RuntimeException('csrf.php requires an active session — call session_start() first');
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_check(string $submitted): bool {
    if (empty($_SESSION['csrf_token']) || $submitted === '') {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $submitted);
}
?>
