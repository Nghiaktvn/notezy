const API_BASE = import.meta.env.VITE_API_BASE || '/api/ai'

async function postJson(path, body) {
  const res = await fetch(`${API_BASE}/${path}`, {
    method: 'POST',
    credentials: 'include',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  })
  const data = await res.json().catch(() => ({}))
  if (!res.ok || data.status === 'error') {
    throw new Error(data.message || 'AI request failed')
  }
  return data
}

export function chat(message, conversationId, context) {
  return postJson('chat.php', {
    message,
    conversation_id: conversationId,
    context,
  })
}

export function confirmAction(confirmationToken, edits = {}) {
  return postJson('confirm.php', {
    confirmation_token: confirmationToken,
    edits,
  })
}
