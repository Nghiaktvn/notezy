<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/db.php';
notezy_session_start();

if (!isset($_SESSION['id'])) {
    header('Location: index.php');
    exit;
}

$current_user_id = (int) $_SESSION['id'];
$initial_user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
$conn = create_connect();
if ($conn && !$conn->connect_error) {
    notezy_ensure_user_last_seen_column($conn);
    $stmt = $conn->prepare("UPDATE users SET last_seen_at = NOW() WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $current_user_id);
        $stmt->execute();
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notezy - Chat & Chia sẻ ghi chú</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root {
            --chat-bg: #f4f7fb;
            --panel: #ffffff;
            --line: #dfe7f2;
            --ink: #182033;
            --muted: #697386;
            --brand: #2563eb;
            --incoming: #eef3f9;
            --outgoing: #2563eb;
        }
        * { box-sizing: border-box; }
        body {
            min-height: 100vh;
            margin: 0;
            background: var(--chat-bg);
            color: var(--ink);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .chat-shell {
            height: 100vh;
            display: grid;
            grid-template-columns: 340px minmax(0, 1fr);
            background: var(--panel);
        }
        .people-panel {
            border-right: 1px solid var(--line);
            display: flex;
            flex-direction: column;
            min-width: 0;
        }
        .people-top {
            padding: 18px 18px 12px;
            border-bottom: 1px solid var(--line);
        }
        .brand-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 14px;
        }
        .brand-row a {
            color: var(--ink);
            font-weight: 800;
            text-decoration: none;
        }
        .search-wrap {
            height: 42px;
            display: grid;
            grid-template-columns: 32px 1fr 32px;
            align-items: center;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #f8fafc;
            padding: 0 6px;
        }
        .search-wrap input {
            border: 0;
            outline: 0;
            background: transparent;
            min-width: 0;
            color: var(--ink);
        }
        .icon-btn {
            width: 36px;
            height: 36px;
            display: inline-grid;
            place-items: center;
            border: 1px solid transparent;
            border-radius: 8px;
            background: transparent;
            color: var(--muted);
        }
        .icon-btn:hover { background: #eef3f9; color: var(--ink); }
        .tabs {
            display: flex;
            gap: 6px;
            padding: 12px 18px;
            border-bottom: 1px solid var(--line);
        }
        .tabs button {
            border: 1px solid var(--line);
            background: #fff;
            border-radius: 8px;
            padding: 7px 10px;
            font-size: 0.86rem;
            color: var(--muted);
        }
        .tabs button.active {
            border-color: var(--brand);
            background: #eff6ff;
            color: var(--brand);
            font-weight: 700;
        }
        .people-list {
            overflow: auto;
            padding: 8px;
        }
        .group-label {
            color: var(--muted);
            font-size: 0.74rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 14px 10px 6px;
        }
        .person {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 0;
            border-radius: 8px;
            padding: 10px;
            text-align: left;
            background: transparent;
            color: var(--ink);
        }
        .person:hover, .person.active { background: #eef3f9; }
        .avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: #dbeafe;
            color: var(--brand);
            display: grid;
            place-items: center;
            font-weight: 800;
            flex: 0 0 42px;
            position: relative;
        }
        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            border: 2px solid #fff;
            background: #94a3b8;
            position: absolute;
            right: 0;
            bottom: 1px;
        }
        .avatar-img {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            object-fit: cover;
            display: block;
        }
        .status-dot.online { background: #22c55e; }
        .person-main { min-width: 0; }
        .person-name {
            font-weight: 750;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .person-meta {
            color: var(--muted);
            font-size: 0.8rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .conversation {
            min-width: 0;
            display: grid;
            grid-template-rows: auto minmax(0, 1fr) auto;
            background: #fbfdff;
        }
        .chat-header {
            min-height: 74px;
            border-bottom: 1px solid var(--line);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 14px 22px;
            background: #fff;
        }
        .selected-user {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }
        .chat-name {
            font-size: 1.03rem;
            font-weight: 800;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .chat-status {
            color: var(--muted);
            font-size: 0.83rem;
        }
        .messages {
            padding: 22px;
            overflow: auto;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .empty-state {
            margin: auto;
            text-align: center;
            color: var(--muted);
        }
        .bubble-row {
            display: flex;
            justify-content: flex-start;
        }
        .bubble-row.me { justify-content: flex-end; }
        .bubble {
            max-width: min(620px, 78%);
            border-radius: 8px;
            padding: 10px 12px;
            background: var(--incoming);
            color: var(--ink);
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
            overflow-wrap: anywhere;
        }
        .bubble-row.me .bubble {
            background: var(--outgoing);
            color: #fff;
        }
        .bubble-time {
            display: block;
            margin-top: 5px;
            font-size: 0.72rem;
            opacity: 0.72;
        }
        .note-card-chat {
            width: min(460px, 78vw);
            border: 1px solid #c7d2fe;
            border-radius: 8px;
            padding: 14px;
            background: #fff;
            color: var(--ink);
        }
        .bubble-row.me .note-card-chat { border-color: rgba(255,255,255,0.4); }
        .note-card-title {
            display: flex;
            align-items: center;
            gap: 9px;
            font-weight: 800;
            margin-bottom: 8px;
        }
        .note-card-body {
            color: var(--muted);
            font-size: 0.9rem;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .composer {
            border-top: 1px solid var(--line);
            padding: 14px 18px;
            display: grid;
            grid-template-columns: 40px minmax(0, 1fr) 44px;
            gap: 10px;
            align-items: center;
            background: #fff;
        }
        .composer input {
            height: 44px;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 0 14px;
            outline: 0;
        }
        .send-btn {
            width: 44px;
            height: 44px;
            border: 0;
            border-radius: 8px;
            background: var(--brand);
            color: #fff;
        }
        @media (max-width: 820px) {
            .chat-shell { grid-template-columns: 1fr; }
            .people-panel { height: 42vh; border-right: 0; border-bottom: 1px solid var(--line); }
            .conversation { height: 58vh; }
            .bubble, .note-card-chat { max-width: 90%; }
        }
    </style>
</head>
<body>
<div class="chat-shell">
    <aside class="people-panel">
        <div class="people-top">
            <div class="brand-row">
                <a href="index_notezy.php"><i class="fa-solid fa-arrow-left me-2"></i>Notezy Chat</a>
                <button class="icon-btn" type="button" onclick="loadUsers()" title="Làm mới"><i class="fa-solid fa-rotate"></i></button>
            </div>
            <div class="search-wrap">
                <button class="icon-btn" type="button" onclick="clearSearch()" title="Xóa tìm kiếm"><i class="fa-solid fa-xmark"></i></button>
                <input id="searchInput" type="text" placeholder="Tìm kiếm..." autocomplete="off">
                <i class="fa-solid fa-magnifying-glass text-secondary text-center"></i>
            </div>
        </div>
        <div class="tabs">
            <button type="button" class="active" data-filter="all">Tất cả tài khoản</button>
            <button type="button" data-filter="contacts">Contacts</button>
            <button type="button" data-filter="noncontacts">Non-contacts</button>
        </div>
        <div class="people-list" id="peopleList"></div>
    </aside>

    <section class="conversation">
        <header class="chat-header">
            <div class="selected-user" id="selectedUser">
                <div class="avatar">N</div>
                <div>
                    <div class="chat-name">Chọn người để trò chuyện</div>
                    <div class="chat-status">Tin nhắn và ghi chú chia sẻ sẽ hiển thị tại đây</div>
                </div>
            </div>
            <button class="icon-btn" type="button" title="Tùy chọn"><i class="fa-solid fa-ellipsis"></i></button>
        </header>
        <main class="messages" id="messages">
            <div class="empty-state">
                <i class="fa-regular fa-comments fa-3x mb-3"></i>
                <div>Chọn một tài khoản ở bên trái để bắt đầu.</div>
            </div>
        </main>
        <form class="composer" id="composer">
            <button class="icon-btn" type="button" onclick="openShareModal()" title="Chia sẻ ghi chú"><i class="fa-solid fa-paperclip"></i></button>
            <input id="messageInput" type="text" placeholder="Write a message..." autocomplete="off">
            <button class="send-btn" type="submit" title="Gửi"><i class="fa-solid fa-paper-plane"></i></button>
        </form>
    </section>
</div>

<div class="modal fade" id="shareModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-share-nodes me-2"></i>Chia sẻ ghi chú</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="shareForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Ghi chú của tôi</label>
                        <select class="form-select" id="noteSelect" name="note_id" required></select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Quyền truy cập</label>
                        <select class="form-select" id="permissionSelect" name="permission">
                            <option value="read">Viewer (Chỉ xem)</option>
                            <option value="write">Editor (Chỉnh sửa)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Đóng</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane me-1"></i> Chia sẻ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const currentUserId = <?= (int)$current_user_id ?>;
    const initialUserId = <?= (int)$initial_user_id ?>;
    let users = [];
    let selectedUser = null;
    let activeFilter = 'all';
    let pollTimer = null;

    const peopleList = document.getElementById('peopleList');
    const messagesEl = document.getElementById('messages');
    const selectedUserEl = document.getElementById('selectedUser');
    const searchInput = document.getElementById('searchInput');
    const shareModal = new bootstrap.Modal(document.getElementById('shareModal'));

    function esc(value) {
        return String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
    }

    function initials(user) {
        return (user.display_name || user.username || 'N').trim().charAt(0).toUpperCase();
    }

    function avatarHtml(user) {
        const dot = `<span class="status-dot ${user.is_online ? 'online' : ''}"></span>`;
        if (user.avatar) {
            return `<div class="avatar"><img src="${esc(user.avatar)}" alt="" class="avatar-img">${dot}</div>`;
        }
        return `<div class="avatar">${esc(initials(user))}${dot}</div>`;
    }

    function filteredUsers() {
        return users.filter(user => {
            if (activeFilter === 'contacts') return user.is_contact;
            if (activeFilter === 'noncontacts') return !user.is_contact;
            return true;
        });
    }

    function renderUsers() {
        const list = filteredUsers();
        if (!list.length) {
            peopleList.innerHTML = '<div class="p-4 text-center text-muted">Không tìm thấy tài khoản phù hợp.</div>';
            return;
        }
        const groups = activeFilter === 'all'
            ? [['Contacts', list.filter(u => u.is_contact)], ['Non-contacts', list.filter(u => !u.is_contact)]]
            : [[activeFilter === 'contacts' ? 'Contacts' : 'Non-contacts', list]];
        peopleList.innerHTML = groups.map(([label, members]) => {
            if (!members.length) return '';
            return `<div class="group-label">${label}</div>` + members.map(user => `
                <button class="person ${selectedUser && selectedUser.id === user.id ? 'active' : ''}" type="button" onclick="selectUser(${user.id})">
                    ${avatarHtml(user)}
                    <span class="person-main">
                        <span class="person-name">${esc(user.display_name)}</span>
                        <span class="person-meta">${esc(user.email)} · ${user.is_online ? 'Online' : 'Offline'}</span>
                    </span>
                </button>
            `).join('');
        }).join('');
    }

    async function loadUsers() {
        const q = searchInput.value.trim();
        const res = await fetch(`api/chat_api.php?action=get_users&q=${encodeURIComponent(q)}`);
        const data = await res.json();
        if (!data.success) return;
        users = data.users || [];
        if (!selectedUser && initialUserId) {
            const match = users.find(u => u.id === initialUserId);
            if (match) selectUser(match.id);
        }
        renderUsers();
    }

    function selectUser(userId) {
        selectedUser = users.find(u => u.id === userId);
        if (!selectedUser) return;
        selectedUserEl.innerHTML = `
            ${avatarHtml(selectedUser)}
            <div>
                <div class="chat-name">${esc(selectedUser.display_name)}</div>
                <div class="chat-status">${selectedUser.is_online ? 'Online' : 'Offline'} · @${esc(selectedUser.username)}</div>
            </div>
        `;
        renderUsers();
        loadMessages();
        clearInterval(pollTimer);
        pollTimer = setInterval(loadMessages, 2000);
        history.replaceState(null, '', `chat.php?user_id=${selectedUser.id}`);
    }

    async function loadMessages() {
        if (!selectedUser) return;
        const res = await fetch(`api/chat_api.php?action=get_messages&with_user_id=${selectedUser.id}`);
        const data = await res.json();
        if (!data.success) return;
        const rows = data.messages || [];
        if (!rows.length) {
            messagesEl.innerHTML = '<div class="empty-state"><i class="fa-regular fa-message fa-3x mb-3"></i><div>Chưa có tin nhắn nào.</div></div>';
            return;
        }
        messagesEl.innerHTML = rows.map(row => {
            const mine = Number(row.sender_id) === currentUserId;
            const time = esc(row.created_at || '');
            if (row.message_type === 'note') {
                const permission = row.permission === 'write' ? 'Editor' : 'Viewer';
                const url = row.permission === 'write' || Number(row.can_edit) === 1 ? `themghichu.php?id=${row.note_id}` : `notepass.php?id=${row.note_id}`;
                return `<div class="bubble-row ${mine ? 'me' : ''}">
                    <div class="note-card-chat">
                        <div class="note-card-title"><i class="fa-solid fa-note-sticky text-primary"></i>${esc(row.note_title || 'Ghi chú được chia sẻ')}</div>
                        <div class="note-card-body">${esc(row.note_content || row.message || '')}</div>
                        <div class="d-flex align-items-center justify-content-between gap-2 mt-3">
                            <span class="badge ${row.permission === 'write' ? 'text-bg-success' : 'text-bg-primary'}">${permission}</span>
                            <a class="btn btn-sm btn-outline-primary" href="${url}">Xem ghi chú</a>
                        </div>
                        <span class="bubble-time">${time}</span>
                    </div>
                </div>`;
            }
            return `<div class="bubble-row ${mine ? 'me' : ''}"><div class="bubble">${esc(row.message)}<span class="bubble-time">${time}</span></div></div>`;
        }).join('');
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    async function openShareModal() {
        if (!selectedUser) return;
        const noteSelect = document.getElementById('noteSelect');
        noteSelect.innerHTML = '<option>Đang tải...</option>';
        shareModal.show();
        const res = await fetch('api/chat_api.php?action=get_my_notes');
        const data = await res.json();
        const notes = data.notes || [];
        noteSelect.innerHTML = notes.length
            ? notes.map(note => `<option value="${note.note_id}">${esc(note.title)}</option>`).join('')
            : '<option value="">Bạn chưa có ghi chú để chia sẻ</option>';
    }

    function clearSearch() {
        searchInput.value = '';
        loadUsers();
    }

    document.querySelectorAll('.tabs button').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.tabs button').forEach(item => item.classList.remove('active'));
            btn.classList.add('active');
            activeFilter = btn.dataset.filter;
            renderUsers();
        });
    });

    searchInput.addEventListener('input', () => loadUsers());

    document.getElementById('composer').addEventListener('submit', async event => {
        event.preventDefault();
        if (!selectedUser) return;
        const input = document.getElementById('messageInput');
        const message = input.value.trim();
        if (!message) return;
        input.value = '';
        await fetch('api/chat_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({action: 'send_message', with_user_id: selectedUser.id, message})
        });
        await loadMessages();
        await loadUsers();
    });

    document.getElementById('shareForm').addEventListener('submit', async event => {
        event.preventDefault();
        if (!selectedUser) return;
        const noteId = document.getElementById('noteSelect').value;
        if (!noteId) return;
        const permission = document.getElementById('permissionSelect').value;
        const res = await fetch('api/chat_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({action: 'share_note_chat', with_user_id: selectedUser.id, note_id: noteId, permission})
        });
        const data = await res.json();
        if (data.success) {
            shareModal.hide();
            await loadMessages();
            await loadUsers();
        } else {
            alert(data.message || 'Không thể chia sẻ ghi chú.');
        }
    });

    loadUsers();
</script>
</body>
</html>
