/**
 * js/reminders.js — Hệ thống nhắc nhở ghi chú Notezy
 * - Poll api/reminders.php mỗi 30 giây để kiểm tra báo thức đến hạn
 * - Hiện SweetAlert2 popup khi đến giờ
 * - Cập nhật badge chuông 🔔 trên navbar
 * - Hiển thị danh sách upcoming reminders trong dropdown
 */
(function () {
    'use strict';

    const POLL_MS = 30000; // Poll mỗi 30 giây

    // ── Xin quyền browser notification ──────────────────────────────────────
    function askPermission() {
        if (!('Notification' in window)) return;
        if (Notification.permission === 'default') {
            Notification.requestPermission().catch(() => {});
        }
    }

    // ── Phát âm thanh chuông nhẹ ────────────────────────────────────────────
    function playBell() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const notes = [523.25, 659.25, 783.99];
            notes.forEach((freq, i) => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.value = freq;
                gain.gain.setValueAtTime(0.001, ctx.currentTime + i * 0.2);
                gain.gain.exponentialRampToValueAtTime(0.25, ctx.currentTime + i * 0.2 + 0.05);
                gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + i * 0.2 + 0.4);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(ctx.currentTime + i * 0.2);
                osc.stop(ctx.currentTime + i * 0.2 + 0.41);
            });
        } catch (e) {}
    }

    // ── Cập nhật badge số và dropdown ────────────────────────────────────────
    function updateBell(items) {
        const badge   = document.getElementById('notifBadge');
        const empty   = document.getElementById('notifEmpty');
        const dropdown = document.getElementById('notifDropdown');
        if (!badge || !dropdown) return;

        // Xóa các item cũ (giữ lại tiêu đề và notifEmpty)
        Array.from(dropdown.querySelectorAll('.notif-item')).forEach(el => el.remove());

        if (!items || items.length === 0) {
            badge.classList.add('d-none');
            if (empty) empty.style.display = '';
            return;
        }

        if (empty) empty.style.display = 'none';
        badge.classList.remove('d-none');
        badge.textContent = items.length > 9 ? '9+' : items.length;

        items.forEach(item => {
            const li = document.createElement('li');
            li.className = 'notif-item border-bottom';
            li.innerHTML = `
                <a class="dropdown-item py-2 px-3" href="themghichu.php?id=${item.note_id}" style="white-space:normal;">
                    <div class="d-flex align-items-start gap-2">
                        <i class="fas fa-bell text-warning mt-1 flex-shrink-0"></i>
                        <div>
                            <div class="fw-semibold" style="font-size:.9rem;">${escHtml(item.title)}</div>
                            <div class="text-muted" style="font-size:.78rem;">
                                <i class="fas fa-clock me-1"></i>${escHtml(item.reminder_at)}
                            </div>
                        </div>
                    </div>
                </a>`;
            // Chèn sau tiêu đề (li đầu tiên)
            const header = dropdown.querySelector('li:first-child');
            header.insertAdjacentElement('afterend', li);
        });
    }

    // ── Hiện SweetAlert2 popup + browser notification khi đến giờ ──────────
    function fireDueReminder(item) {
        playBell();

        // Browser Notification (nếu được cấp quyền)
        if ('Notification' in window && Notification.permission === 'granted') {
            try {
                const n = new Notification('⏰ Nhắc nhở Notezy', {
                    body: item.title,
                    icon: 'logo.png',
                    tag: 'notezy-reminder-' + item.note_id,
                });
                n.onclick = () => { window.focus(); window.location.href = 'themghichu.php?id=' + item.note_id; };
            } catch (e) {}
        }

        // SweetAlert2 popup đẹp
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'info',
                title: '⏰ Báo Thức Ghi Chú!',
                html: `
                    <div class="text-start">
                        <p class="mb-1"><i class="fas fa-sticky-note text-warning me-2"></i>
                            <strong>${escHtml(item.title)}</strong>
                        </p>
                        <p class="text-muted mb-0" style="font-size:.85rem;">
                            <i class="fas fa-clock me-1"></i>${escHtml(item.reminder_at)}
                        </p>
                    </div>`,
                confirmButtonText: '<i class="fas fa-eye me-1"></i> Xem ghi chú',
                showCancelButton: true,
                cancelButtonText: 'Bỏ qua',
                confirmButtonColor: '#4f46e5',
                timer: 20000,
                timerProgressBar: true,
            }).then(res => {
                if (res.isConfirmed) {
                    window.location.href = 'themghichu.php?id=' + item.note_id;
                }
            });
        } else {
            // Fallback toast
            const toast = document.createElement('div');
            toast.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9999;background:#4f46e5;color:#fff;padding:14px 20px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.25);cursor:pointer;max-width:320px;font-family:inherit;';
            toast.innerHTML = `<strong>⏰ Nhắc nhở:</strong><br>${escHtml(item.title)}`;
            toast.onclick = () => { window.location.href = 'themghichu.php?id=' + item.note_id; };
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 10000);
        }
    }

    function escHtml(str) {
        return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ── Load danh sách upcoming reminders cho dropdown ──────────────────────
    async function loadUpcoming() {
        try {
            const res = await fetch('api/reminders.php?upcoming=1', { credentials: 'include' });
            if (!res.ok) return;
            const data = await res.json();
            if (data.status === 'success' && Array.isArray(data.upcoming)) {
                updateBell(data.upcoming);
            }
        } catch (e) {}
    }

    // ── Poll reminders đến hạn ───────────────────────────────────────────────
    async function poll() {
        try {
            const res = await fetch('api/reminders.php', { credentials: 'include' });
            if (!res.ok) return;
            const data = await res.json();
            if (data.status === 'success' && Array.isArray(data.due) && data.due.length > 0) {
                // Hiện popup cho từng reminder đến hạn
                data.due.forEach(item => fireDueReminder(item));
                // Refresh dropdown sau khi có reminder fired
                setTimeout(loadUpcoming, 1000);
            }
        } catch (e) {}
    }

    // ── Init ────────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        askPermission();
        loadUpcoming(); // Load upcoming reminders vào dropdown ngay khi trang load
        poll();         // Kiểm tra reminder đến hạn ngay lập tức
        setInterval(poll, POLL_MS);
        setInterval(loadUpcoming, POLL_MS);
    });

})();
