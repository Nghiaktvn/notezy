<?php
/** Content is available only after every configured note lock is satisfied. */
function notezy_note_is_unlocked(array $note): bool
{
    $noteId = (int) ($note['note_id'] ?? 0);
    return (empty($note['password_hash']) || !empty($_SESSION['accessed_notes'][$noteId]))
        && (empty($note['pin_hash']) || !empty($_SESSION['pin_unlocked_notes'][$noteId]));
}
