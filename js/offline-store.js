/**
 * Notezy offline engine.
 * Caches notes/labels in IndexedDB, queues offline mutations, then syncs them
 * sequentially with retry and conflict handling when connectivity returns.
 */
(function () {
    const DB_NAME = 'notezy_offline_db';
    const DB_VERSION = 2;
    const MAX_RETRY = 5;
    const BASE_BACKOFF_MS = 1000;
    let dbInstance = null;
    let isSyncing = false;

    function openDB() {
        return new Promise((resolve, reject) => {
            if (dbInstance) return resolve(dbInstance);
            const request = indexedDB.open(DB_NAME, DB_VERSION);
            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                if (!db.objectStoreNames.contains('notes')) {
                    const notes = db.createObjectStore('notes', { keyPath: 'client_id' });
                    notes.createIndex('note_id', 'note_id', { unique: false });
                    notes.createIndex('updated_at', 'updated_at', { unique: false });
                }
                if (!db.objectStoreNames.contains('labels')) {
                    const labels = db.createObjectStore('labels', { keyPath: 'name' });
                    labels.createIndex('updated_at', 'updated_at', { unique: false });
                }
                if (!db.objectStoreNames.contains('sync_queue')) {
                    const queue = db.createObjectStore('sync_queue', { keyPath: 'id', autoIncrement: true });
                    queue.createIndex('createdAt', 'createdAt', { unique: false });
                    queue.createIndex('entityId', 'entityId', { unique: false });
                }
                if (!db.objectStoreNames.contains('metadata')) {
                    db.createObjectStore('metadata', { keyPath: 'key' });
                }
            };
            request.onsuccess = (event) => {
                dbInstance = event.target.result;
                resolve(dbInstance);
            };
            request.onerror = (event) => reject(event.target.error);
        });
    }

    function txDone(tx) {
        return new Promise((resolve, reject) => {
            tx.oncomplete = () => resolve();
            tx.onerror = () => reject(tx.error);
            tx.onabort = () => reject(tx.error);
        });
    }

    function nowSql() {
        return new Date().toISOString().slice(0, 19).replace('T', ' ');
    }

    function labelsFrom(value) {
        return Array.isArray(value) ? value : String(value || '').split(',').map(s => s.trim()).filter(Boolean);
    }

    function normalizeNote(note) {
        const noteId = note.note_id || note.id || note.client_id;
        const clientId = note.client_id || (String(noteId).startsWith('temp_') ? String(noteId) : `server_${noteId}`);
        return {
            ...note,
            client_id: clientId,
            note_id: noteId,
            id: noteId,
            title: note.title || 'Untitled',
            content: note.content || '',
            labels: labelsFrom(note.labels),
            pinned: Number(note.pinned || 0),
            archived: Number(note.archived || 0),
            deleted: Boolean(note.deleted),
            updated_at: note.updated_at || note.updatedAt || nowSql(),
            version: note.version || note.updated_at || note.updatedAt || nowSql(),
            sync_pending: Boolean(note.sync_pending)
        };
    }

    async function putNote(note) {
        const db = await openDB();
        const tx = db.transaction('notes', 'readwrite');
        tx.objectStore('notes').put(normalizeNote(note));
        await txDone(tx);
    }

    async function getAllLocalNotes(includeDeleted = false) {
        const db = await openDB();
        return new Promise((resolve) => {
            const req = db.transaction('notes', 'readonly').objectStore('notes').getAll();
            req.onsuccess = () => {
                const notes = (req.result || [])
                    .filter(n => includeDeleted || !n.deleted)
                    .sort((a, b) => Number(b.pinned || 0) - Number(a.pinned || 0) || String(b.updated_at || '').localeCompare(String(a.updated_at || '')));
                resolve(notes);
            };
            req.onerror = () => resolve([]);
        });
    }

    async function getNote(entityId) {
        const all = await getAllLocalNotes(true);
        return all.find(n => String(n.note_id) === String(entityId) || String(n.client_id) === String(entityId)) || null;
    }

    async function saveNotesSnapshot(notesArray) {
        const db = await openDB();
        const tx = db.transaction(['notes', 'labels', 'metadata'], 'readwrite');
        const notesStore = tx.objectStore('notes');
        const labelsStore = tx.objectStore('labels');
        const seenLabels = new Set();
        (notesArray || []).forEach((raw) => {
            const note = normalizeNote(raw);
            note.sync_pending = false;
            note.deleted = false;
            notesStore.put(note);
            note.labels.forEach((name) => {
                if (!seenLabels.has(name)) {
                    seenLabels.add(name);
                    labelsStore.put({ name, updated_at: nowSql() });
                }
            });
        });
        tx.objectStore('metadata').put({ key: 'lastSnapshotAt', value: Date.now() });
        await txDone(tx);
        await refreshQueueStatus();
    }

    async function enqueueSyncAction(action, entity, entityId, payload) {
        const db = await openDB();
        const tx = db.transaction('sync_queue', 'readwrite');
        tx.objectStore('sync_queue').add({
            action,
            entity,
            entityId: String(entityId || payload.note_id || payload.client_temp_id || ''),
            payload,
            createdAt: Date.now(),
            retryCount: 0,
            lastError: ''
        });
        await txDone(tx);
        await refreshQueueStatus('offline_saved');
    }

    async function getSyncQueue() {
        const db = await openDB();
        return new Promise((resolve) => {
            const req = db.transaction('sync_queue', 'readonly').objectStore('sync_queue').getAll();
            req.onsuccess = () => resolve((req.result || []).sort((a, b) => a.createdAt - b.createdAt));
            req.onerror = () => resolve([]);
        });
    }

    async function removeSyncItem(id) {
        const db = await openDB();
        const tx = db.transaction('sync_queue', 'readwrite');
        tx.objectStore('sync_queue').delete(id);
        await txDone(tx);
    }

    async function updateSyncItem(item, errorMessage) {
        const retryCount = Number(item.retryCount || 0) + 1;
        const db = await openDB();
        const tx = db.transaction('sync_queue', 'readwrite');
        tx.objectStore('sync_queue').put({
            ...item,
            retryCount,
            lastError: errorMessage || 'Sync failed',
            nextRetryAt: Date.now() + Math.min(BASE_BACKOFF_MS * Math.pow(2, retryCount - 1), 8000)
        });
        await txDone(tx);
    }

    async function createNoteOffline(title, content, labels = '', extras = {}) {
        const tempId = `temp_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`;
        const note = normalizeNote({
            client_id: tempId,
            note_id: tempId,
            title: title || 'Ghi chú ngoại tuyến',
            content: content || '',
            labels: labelsFrom(labels),
            pinned: extras.pinned ? 1 : 0,
            background_color: extras.background_color || '#ffffff',
            text_color: extras.text_color || '#000000',
            font_family: extras.font_family || 'Poppins',
            reminder_at: extras.reminder_at || '',
            created_at: nowSql(),
            updated_at: nowSql(),
            is_offline_created: true,
            sync_pending: true
        });
        await putNote(note);
        await enqueueSyncAction('CREATE_NOTE', 'note', tempId, { ...note, client_temp_id: tempId });
        return note;
    }

    async function updateNoteOffline(noteId, patch) {
        const existing = await getNote(noteId);
        const note = normalizeNote({ ...(existing || { note_id: noteId, client_id: `server_${noteId}` }), ...patch, updated_at: nowSql(), sync_pending: true });
        await putNote(note);
        await enqueueSyncAction('UPDATE_NOTE', 'note', noteId, {
            note_id: noteId,
            ...patch,
            labels: patch.labels ? labelsFrom(patch.labels) : undefined,
            client_updated_at: existing?.updated_at || patch.client_updated_at || ''
        });
        return note;
    }

    async function deleteNoteOffline(noteId) {
        const existing = await getNote(noteId);
        if (existing) await putNote({ ...existing, deleted: true, sync_pending: true, updated_at: nowSql() });
        if (!String(noteId).startsWith('temp_')) {
            await enqueueSyncAction('DELETE_NOTE', 'note', noteId, { note_id: noteId });
        }
    }

    async function togglePinOffline(noteId, pinned) {
        return updateNoteOffline(noteId, { pinned: pinned ? 1 : 0 });
    }

    async function addLabelOffline(noteId, name) {
        const label = String(name || '').trim();
        if (!label) return null;
        const existing = await getNote(noteId);
        const labels = Array.from(new Set([...(existing?.labels || []), label]));
        await putNote({ ...(existing || { note_id: noteId, client_id: `server_${noteId}` }), labels, sync_pending: true, updated_at: nowSql() });
        await enqueueSyncAction('ADD_LABEL', 'label', noteId, { note_id: noteId, name: label });
        return labels;
    }

    async function removeLabelOffline(noteId, name) {
        const label = String(name || '').trim();
        const existing = await getNote(noteId);
        const labels = (existing?.labels || []).filter(item => item !== label);
        await putNote({ ...(existing || { note_id: noteId, client_id: `server_${noteId}` }), labels, sync_pending: true, updated_at: nowSql() });
        await enqueueSyncAction('REMOVE_LABEL', 'label', noteId, { note_id: noteId, name: label });
        return labels;
    }

    async function requestJson(url, options) {
        const res = await fetch(url, options);
        let json = {};
        try { json = await res.json(); } catch (_) {}
        if (res.status === 409 || json.conflict) {
            const err = new Error(json.message || 'Conflict');
            err.conflict = true;
            err.serverNote = json.server_note || json.data || null;
            throw err;
        }
        if (!res.ok) throw new Error(json.message || `HTTP ${res.status}`);
        return json;
    }

    async function syncItem(item) {
        const payload = item.payload || {};
        if (item.action === 'CREATE_NOTE') {
            const json = await requestJson('api/notes.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(payload)
            });
            if (json.status !== 'success') throw new Error(json.message || 'Create failed');
            const local = await getNote(payload.client_temp_id);
            if (local) {
                await putNote({ ...local, note_id: json.note_id, id: json.note_id, client_id: `server_${json.note_id}`, is_offline_created: false, sync_pending: false, updated_at: json.updated_at || nowSql() });
                const db = await openDB();
                const tx = db.transaction('notes', 'readwrite');
                tx.objectStore('notes').delete(payload.client_temp_id);
                await txDone(tx);
            }
            for (const label of payload.labels || []) {
                await requestJson('api/labels.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ note_id: json.note_id, name: label })
                });
            }
            return true;
        }
        if (item.action === 'UPDATE_NOTE') {
            const json = await requestJson('api/notes.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(payload)
            });
            if (json.status !== 'success') throw new Error(json.message || 'Update failed');
            const local = await getNote(payload.note_id);
            if (local) await putNote({ ...local, sync_pending: false, updated_at: json.updated_at || local.updated_at });
            return true;
        }
        if (item.action === 'DELETE_NOTE') {
            const json = await requestJson(`api/notes.php?note_id=${encodeURIComponent(payload.note_id)}`, { method: 'DELETE', credentials: 'same-origin' });
            if (json.status !== 'success') throw new Error(json.message || 'Delete failed');
            return true;
        }
        if (item.action === 'ADD_LABEL') {
            const json = await requestJson('api/labels.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ note_id: payload.note_id, name: payload.name })
            });
            if (json.status !== 'success') throw new Error(json.message || 'Add label failed');
            return true;
        }
        if (item.action === 'REMOVE_LABEL') {
            const json = await requestJson(`api/labels.php?note_id=${encodeURIComponent(payload.note_id)}&name=${encodeURIComponent(payload.name)}`, { method: 'DELETE', credentials: 'same-origin' });
            if (json.status !== 'success') throw new Error(json.message || 'Remove label failed');
            return true;
        }
        throw new Error(`Unknown sync action: ${item.action}`);
    }

    async function syncQueueWithServer() {
        if (!navigator.onLine || isSyncing) return;
        let queue = await getSyncQueue();
        if (queue.length === 0) {
            updateUIStatus('synced', 0);
            return;
        }
        isSyncing = true;
        updateUIStatus('syncing', queue.length);
        for (const item of queue) {
            if (item.nextRetryAt && item.nextRetryAt > Date.now()) continue;
            try {
                await syncItem(item);
                await removeSyncItem(item.id);
            } catch (err) {
                await updateSyncItem(item, err.conflict ? 'Conflict' : err.message);
                if (err.conflict) showConflictNotice(err.serverNote);
                updateUIStatus('sync_error', (await getSyncQueue()).length);
                break;
            }
        }
        isSyncing = false;
        queue = await getSyncQueue();
        updateUIStatus(queue.length ? 'sync_error' : 'synced', queue.length);
        if (!queue.length) document.dispatchEvent(new CustomEvent('notezy:sync-complete'));
    }

    function showConflictNotice(serverNote) {
        const message = 'Note has been changed on another device.';
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'warning',
                title: 'Xung đột đồng bộ',
                text: `${message} Chọn bản server hoặc mở ghi chú để giữ bản của bạn.`,
                showCancelButton: true,
                confirmButtonText: 'Keep Server Version',
                cancelButtonText: 'Keep My Version'
            }).then((result) => {
                if (result.isConfirmed && serverNote) putNote(normalizeNote(serverNote));
            });
        } else {
            alert(message);
        }
    }

    async function refreshQueueStatus(forcedStatus) {
        const queue = await getSyncQueue();
        if (forcedStatus) updateUIStatus(forcedStatus, queue.length);
        else updateUIStatus(navigator.onLine ? (queue.length ? 'sync_error' : 'synced') : 'offline', queue.length);
        document.dispatchEvent(new CustomEvent('notezy:queue-change', { detail: { count: queue.length } }));
    }

    function createStatusIndicator() {
        if (document.getElementById('notezy_network_badge')) return;
        const badge = document.createElement('div');
        badge.id = 'notezy_network_badge';
        badge.style.cssText = 'position:fixed;bottom:18px;left:18px;z-index:99999;font-size:.82rem;font-weight:700;padding:8px 14px;border-radius:22px;box-shadow:0 3px 12px rgba(0,0,0,.18);display:flex;align-items:center;gap:8px;max-width:min(92vw,360px);';
        document.body.appendChild(badge);
    }

    function updateUIStatus(status, count = 0) {
        const badge = document.getElementById('notezy_network_badge');
        if (!badge) return;
        const map = {
            online: ['#0ea5e9', 'ONLINE', 'Dang ket noi server'],
            offline: ['#dc2626', 'OFFLINE', 'Changes will be saved locally'],
            offline_saved: ['#b45309', 'OFFLINE', 'Saved locally'],
            syncing: ['#f59e0b', 'SYNCING', `${count || ''} changes waiting to sync`],
            synced: ['#16a34a', 'SYNCED', 'All changes synced'],
            sync_error: ['#be123c', 'SYNC ERROR', `${count || 1} changes waiting to sync`]
        };
        const [bg, label, text] = map[status] || map.online;
        badge.style.background = bg;
        badge.style.color = '#fff';
        badge.innerHTML = `<span style="font-size:10px">●</span><span>${label}</span><span style="font-weight:500;opacity:.95">${text}</span>`;
    }

    async function renderOfflineNotes(containerSelector = '#ownNotesGrid') {
        const container = document.querySelector(containerSelector);
        if (!container) return;
        const notes = await getAllLocalNotes();
        const own = notes.filter(n => (n.ownership || 'own') === 'own' && Number(n.archived || 0) === 0);
        if (!own.length) return;
        container.innerHTML = `
            <div class="d-flex align-items-center justify-content-between mb-3 w-100 col-12">
                <h3 class="mb-0 fw-bold"><i class="fas fa-database text-danger me-2"></i>Ghi chú offline</h3>
                <span class="badge bg-danger rounded-pill px-3 py-2">${own.length} cached</span>
            </div>
            ${own.map(note => `
                <div class="note-item position-relative" data-note-id="${escapeAttr(note.note_id)}" data-labels="${escapeAttr((note.labels || []).join(','))}">
                    ${note.pinned ? '<span class="pin-icon"><i class="fas fa-thumbtack text-warning"></i></span>' : ''}
                    ${note.sync_pending ? '<span class="badge bg-warning text-dark mb-2">Saved locally</span>' : ''}
                    <h5 class="mb-2 fw-bold text-truncate">${escapeHtml(note.title)}</h5>
                    <p class="note-body-text mb-2">${escapeHtml(note.content).replace(/\n/g, '<br>')}</p>
                    ${(note.labels || []).length ? `<div class="note-labels mb-2">${note.labels.map(l => `<span class="note-label">${escapeHtml(l)}</span>`).join('')}</div>` : ''}
                    <div class="note-actions mt-3 pt-2 border-top d-flex justify-content-end gap-1 flex-wrap">
                        <button type="button" class="btn btn-action-icon btn-pin-toggle ${note.pinned ? 'active' : ''}" onclick="NotezyOffline.togglePinOffline('${escapeAttr(note.note_id)}', ${note.pinned ? 0 : 1}).then(() => NotezyOffline.renderOfflineNotes())"><i class="fas fa-thumbtack"></i></button>
                        <button type="button" class="btn btn-action-icon btn-delete" onclick="NotezyOffline.deleteNoteOffline('${escapeAttr(note.note_id)}').then(() => NotezyOffline.renderOfflineNotes())"><i class="fas fa-trash-alt"></i></button>
                    </div>
                </div>
            `).join('')}
        `;
    }

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[ch]));
    }

    function escapeAttr(value) {
        return escapeHtml(value).replace(/`/g, '&#096;');
    }

    function init() {
        createStatusIndicator();
        openDB()
            .then(() => refreshQueueStatus())
            .then(() => {
                if (!navigator.onLine) renderOfflineNotes();
                if (navigator.onLine) syncQueueWithServer();
            })
            .catch(() => updateUIStatus('sync_error', 1));
        window.addEventListener('online', () => {
            updateUIStatus('syncing');
            syncQueueWithServer();
        });
        window.addEventListener('offline', () => {
            updateUIStatus('offline');
            renderOfflineNotes();
        });
    }

    window.NotezyOffline = {
        init,
        saveNotesSnapshot,
        getAllLocalNotes,
        createNoteOffline,
        updateNoteOffline,
        deleteNoteOffline,
        togglePinOffline,
        addLabelOffline,
        removeLabelOffline,
        syncQueueWithServer,
        renderOfflineNotes,
        updateUIStatus
    };

    document.addEventListener('DOMContentLoaded', init);
})();
