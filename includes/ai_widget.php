<?php
$page = isset($notezy_ai_page) ? $notezy_ai_page : 'notes_list';
$noteId = isset($notezy_ai_note_id) ? (int) $notezy_ai_note_id : 0;
?>
<link rel="stylesheet" href="CSS/ai-assistant.css">
<div id="notezy-ai-root"></div>
<script>
window.NOTEZY_AI = {
  apiBase: 'api/ai',
  page: <?php echo json_encode($page); ?>,
  currentNoteId: <?php echo $noteId; ?>
};
</script>
<script src="js/ai-assistant.js" defer></script>
<script src="js/reminders.js" defer></script>
