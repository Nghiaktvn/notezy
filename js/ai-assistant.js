(function () {
  const cfg = window.NOTEZY_AI || {};
  const apiBase = cfg.apiBase || 'api/ai';
  const root = document.getElementById('notezy-ai-root');
  if (!root) return;

  const state = {
    open: false,
    loading: false,
    conversationId: null,
    messages: [
      { role: 'assistant', content: 'Xin chào! Tôi là trợ lý ghi chú Notezy. Bạn muốn tìm, tóm tắt hay tạo note?' }
    ]
  };

  root.innerHTML = `
    <div class="notezy-ai-panel" id="notezyAiPanel" role="dialog" aria-label="Notezy AI">
      <div class="notezy-ai-header">
        <span>Notezy AI</span>
        <button type="button" id="notezyAiClose" aria-label="Đóng">✕</button>
      </div>
      <div class="notezy-ai-messages" id="notezyAiMessages"></div>
      <div class="notezy-ai-input">
        <textarea id="notezyAiInput" rows="1" placeholder="Nhắn với AI..."></textarea>
        <button type="button" id="notezyAiSend">Gửi</button>
      </div>
    </div>
    <button type="button" class="notezy-ai-fab" id="notezyAiFab" aria-label="Mở Notezy AI">✦</button>
  `;

  const panel = document.getElementById('notezyAiPanel');
  const messagesEl = document.getElementById('notezyAiMessages');
  const input = document.getElementById('notezyAiInput');

  function esc(s) {
    return String(s || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function render() {
    panel.classList.toggle('open', state.open);
    messagesEl.innerHTML = '';
    state.messages.forEach((msg, idx) => {
      if (msg.response && msg.response.type === 'note_created') {
        messagesEl.appendChild(createdCard(msg.response));
        return;
      }
      if (msg.response && msg.response.type === 'note_preview') {
        messagesEl.appendChild(previewCard(msg.response, idx));
        return;
      }
      if (msg.response && msg.response.type === 'action_confirmation') {
        messagesEl.appendChild(confirmCard(msg.response, idx));
        return;
      }
      const div = document.createElement('div');
      div.className = 'notezy-ai-bubble ' + (msg.role === 'user' ? 'user' : 'assistant');
      div.textContent = msg.content || '';
      messagesEl.appendChild(div);
    });
    if (state.loading) {
      const wait = document.createElement('div');
      wait.className = 'notezy-ai-muted';
      wait.textContent = 'Đang suy nghĩ...';
      messagesEl.appendChild(wait);
    }
    messagesEl.scrollTop = messagesEl.scrollHeight;
  }

  function createdCard(resp) {
    // Note was already auto-created server-side — this is a confirmation
    // card only, no confirm/cancel actions needed.
    const wrap = document.createElement('div');
    wrap.className = 'notezy-ai-card notezy-ai-created';
    wrap.style.background = resp.background_color || '#ffffff';
    wrap.style.color = resp.text_color || '#000000';
    wrap.style.borderRadius = '10px';
    wrap.innerHTML = `
      <h4>✅ Đã tạo ghi chú</h4>
      <strong>${esc(resp.title)}</strong>
      <div class="notezy-ai-note-body">${esc(resp.note_content).replace(/\n/g, '<br>')}</div>
      <div class="notezy-ai-labels">${(resp.labels || []).map(l => '<span>' + esc(l) + '</span>').join('')}</div>
      <div class="notezy-ai-actions">
        <a href="edit_note.php?id=${resp.note_id}" class="notezy-ai-open-link">Mở ghi chú</a>
      </div>
    `;
    return wrap;
  }

  function previewCard(resp, idx) {
    const wrap = document.createElement('div');
    wrap.className = 'notezy-ai-card';
    wrap.innerHTML = `
      <h4>Note Preview</h4>
      <input class="form-control mb-2" data-ai-title value="${esc(resp.title)}">
      <textarea class="form-control" data-ai-content rows="6">${esc(resp.content)}</textarea>
      <div class="notezy-ai-labels">${(resp.labels || []).map(l => '<span>' + esc(l) + '</span>').join('')}</div>
      <div class="notezy-ai-actions">
        <button type="button" data-ai-confirm="${idx}">${resp.action === 'update_note' ? 'Update' : 'Create'}</button>
        <button type="button" class="secondary" data-ai-cancel="${idx}">Cancel</button>
      </div>
    `;
    return wrap;
  }

  function confirmCard(resp, idx) {
    const wrap = document.createElement('div');
    wrap.className = 'notezy-ai-card';
    wrap.innerHTML = `
      <h4>${resp.action === 'delete_note' ? 'Xóa ghi chú?' : 'Xác nhận'}</h4>
      <p>${esc(resp.content)}</p>
      <div class="notezy-ai-actions">
        <button type="button" class="${resp.action === 'delete_note' ? 'danger' : ''}" data-ai-confirm="${idx}">${resp.action === 'delete_note' ? 'Delete' : 'Confirm'}</button>
        <button type="button" class="secondary" data-ai-cancel="${idx}">Cancel</button>
      </div>
    `;
    return wrap;
  }

  async function post(path, body) {
    const res = await fetch(apiBase + '/' + path, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || data.status === 'error') {
      throw new Error(data.message || 'AI request failed');
    }
    return data;
  }

  async function send(text) {
    if (!text.trim() || state.loading) return;
    state.messages.push({ role: 'user', content: text.trim() });
    state.loading = true;
    render();
    try {
      const data = await post('chat.php', {
        message: text.trim(),
        conversation_id: state.conversationId,
        context: {
          page: cfg.page || 'notes_list',
          current_note_id: cfg.currentNoteId || 0
        }
      });
      state.conversationId = data.conversation_id;
      state.messages.push({ role: 'assistant', content: data.response.content || '', response: data.response });
    } catch (err) {
      state.messages.push({ role: 'assistant', content: err.message, error: true });
    } finally {
      state.loading = false;
      render();
    }
  }

  async function confirm(idx, edits) {
    const msg = state.messages[idx];
    const token = msg && msg.response && msg.response.confirmation_token;
    if (!token) return;
    state.loading = true;
    render();
    try {
      const data = await post('confirm.php', { confirmation_token: token, edits: edits || {} });
      state.messages.push({ role: 'assistant', content: data.response.content, response: data.response });
    } catch (err) {
      state.messages.push({ role: 'assistant', content: err.message, error: true });
    } finally {
      state.loading = false;
      render();
    }
  }

  document.getElementById('notezyAiFab').addEventListener('click', () => {
    state.open = !state.open;
    render();
  });
  document.getElementById('notezyAiClose').addEventListener('click', () => {
    state.open = false;
    render();
  });
  document.getElementById('notezyAiSend').addEventListener('click', () => {
    const v = input.value;
    input.value = '';
    send(v);
  });
  input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      const v = input.value;
      input.value = '';
      send(v);
    }
  });
  messagesEl.addEventListener('click', (e) => {
    const confirmBtn = e.target.closest('[data-ai-confirm]');
    const cancelBtn = e.target.closest('[data-ai-cancel]');
    if (cancelBtn) {
      state.messages.push({ role: 'assistant', content: 'Đã hủy thao tác.' });
      render();
      return;
    }
    if (confirmBtn) {
      const idx = parseInt(confirmBtn.getAttribute('data-ai-confirm'), 10);
      const card = confirmBtn.closest('.notezy-ai-card');
      const titleEl = card && card.querySelector('[data-ai-title]');
      const contentEl = card && card.querySelector('[data-ai-content]');
      const edits = {};
      if (titleEl) edits.title = titleEl.value;
      if (contentEl) edits.content = contentEl.value;
      confirm(idx, edits);
    }
  });

  render();
})();
