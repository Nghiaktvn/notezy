(function () {
  let socket = null;
  let activeNoteId = 0;
  let reconnectTimer = null;
  function wsUrl(token) {
    // Codespaces exposes each forwarded port on a separate HTTPS hostname.
    if (location.hostname.endsWith('.app.github.dev')) {
      const host = location.hostname.replace(/-\d+(\.app\.github\.dev)$/, '-8766$1');
      return `wss://${host}/?token=${encodeURIComponent(token)}`;
    }
    const protocol = location.protocol === 'https:' ? 'wss:' : 'ws:';
    return `${protocol}//${location.hostname}:8766/?token=${encodeURIComponent(token)}`;
  }
  async function connect(noteId, onRemoteChange) {
    const requestedNoteId = Number(noteId);
    if (!requestedNoteId) return;
    if (activeNoteId === requestedNoteId && socket && (socket.readyState === WebSocket.OPEN || socket.readyState === WebSocket.CONNECTING)) return;
    clearTimeout(reconnectTimer);
    if (socket) socket.close();
    const response = await fetch(`api/collab_token.php?note_id=${encodeURIComponent(noteId)}`);
    const payload = await response.json();
    if (!payload.success) return;
    activeNoteId = requestedNoteId;
    const ws = new WebSocket(wsUrl(payload.token));
    socket = ws;
    ws.onmessage = ({data}) => {
      try { const change = JSON.parse(data); if (change.note_id === activeNoteId) onRemoteChange(change); } catch (_) {}
    };
    ws.onclose = () => {
      // Ignore the close event from a deliberately replaced connection.
      if (socket !== ws) return;
      socket = null;
      if (activeNoteId === requestedNoteId) reconnectTimer = setTimeout(() => connect(requestedNoteId, onRemoteChange), 3000);
    };
  }
  function send(change) {
    if (socket && socket.readyState === WebSocket.OPEN) socket.send(JSON.stringify(change));
  }
  window.NotezyCollaboration = { connect, send };
}());
