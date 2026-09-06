/**
 * js/reminders.js — polls api/reminders.php and fires a Notification for
 * due reminders while this tab is open.
 *
 * LIMITATION (documented, not hidden): this only works while the tab is
 * open. It is NOT a background/OS alarm and NOT a location-based reminder.
 * See AI.md "Roadmap" for what real background push / geofencing needs.
 *
 * Include on any page after login, e.g.:
 *   <script src="js/reminders.js" defer></script>
 */
(function () {
  const POLL_MS = 30000;

  function askPermission() {
    if (!('Notification' in window)) return;
    if (Notification.permission === 'default') {
      Notification.requestPermission().catch(() => {});
    }
  }

  function notify(item) {
    const title = 'Nhắc nhở Notezy';
    const body = item.title || 'Bạn có một ghi chú đến hạn nhắc nhở.';
    if ('Notification' in window && Notification.permission === 'granted') {
      const n = new Notification(title, {
        body,
        icon: 'logo.png',
        tag: 'notezy-reminder-' + item.note_id,
      });
      n.onclick = () => {
        window.focus();
        window.location.href = 'edit_note.php?id=' + item.note_id;
      };
    } else {
      // Fallback: unobtrusive in-page toast when Notification isn't available/granted.
      const toast = document.createElement('div');
      toast.className = 'notezy-reminder-toast';
      toast.textContent = '⏰ ' + body;
      toast.onclick = () => { window.location.href = 'edit_note.php?id=' + item.note_id; };
      document.body.appendChild(toast);
      setTimeout(() => toast.remove(), 8000);
    }
  }

  async function poll() {
    try {
      const res = await fetch('api/reminders.php', { credentials: 'include' });
      if (!res.ok) return;
      const data = await res.json();
      if (data.status === 'success' && Array.isArray(data.due)) {
        data.due.forEach(notify);
      }
    } catch (e) {
      // Silent — offline or logged out; next poll will retry.
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    askPermission();
    poll();
    setInterval(poll, POLL_MS);
  });
})();
