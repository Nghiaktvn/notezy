/**
 * js/timetable-alarm.js — Hệ thống Báo Giờ & Chuông Thông Báo cho Thời Khóa Biểu Notezy
 */
(function(window) {
    'use strict';

    const TimetableAlarm = {
        items: [],
        checkedAlarms: new Set(), // Tránh lặp chuông trong cùng 1 ngày
        audioCtx: null,
        timerId: null,

        init: function() {
            this.requestNotificationPermission();
            this.loadScheduleData();
            
            // Bắt đầu chu kỳ kiểm tra mỗi 15 giây
            if (this.timerId) clearInterval(this.timerId);
            this.timerId = setInterval(() => this.checkAlarms(), 15000);
            this.checkAlarms();
        },

        // Xin quyền gửi thông báo trình duyệt
        requestNotificationPermission: function() {
            if ('Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission().catch(() => {});
            }
        },

        // Tải dữ liệu lịch trình từ API
        loadScheduleData: async function() {
            try {
                const res = await fetch('api/timetable.php', { credentials: 'same-origin' });
                if (!res.ok) return;
                const json = await res.json();
                if (json.status === 'success' && Array.isArray(json.data)) {
                    this.items = json.data;
                    this.updateUpcomingWidget();
                }
            } catch (err) {
                console.warn('Không thể tải lịch trình thời khóa biểu:', err);
            }
        },

        // Phát âm thanh chuông báo bằng Web Audio API
        playAlarmSound: function() {
            try {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                if (!AudioContext) return;

                if (!this.audioCtx) {
                    this.audioCtx = new AudioContext();
                }
                if (this.audioCtx.state === 'suspended') {
                    this.audioCtx.resume();
                }

                const now = this.audioCtx.currentTime;
                // Chuỗi nốt nhạc ngân vang dễ chịu: C5, E5, G5, C6
                const notes = [523.25, 659.25, 783.99, 1046.50, 783.99, 1046.50];
                notes.forEach((freq, idx) => {
                    const osc = this.audioCtx.createOscillator();
                    const gain = this.audioCtx.createGain();

                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(freq, now + idx * 0.18);

                    gain.gain.setValueAtTime(0.001, now + idx * 0.18);
                    gain.gain.exponentialRampToValueAtTime(0.3, now + idx * 0.18 + 0.04);
                    gain.gain.exponentialRampToValueAtTime(0.0001, now + idx * 0.18 + 0.35);

                    osc.connect(gain);
                    gain.connect(this.audioCtx.destination);

                    osc.start(now + idx * 0.18);
                    osc.stop(now + idx * 0.18 + 0.36);
                });
            } catch (e) {
                console.warn('Lỗi phát âm thanh chuông báo:', e);
            }
        },

        // Kiểm tra thời gian và kích hoạt báo thời
        checkAlarms: function() {
            if (!this.items.length) return;

            const now = new Date();
            // Trong JS: 0 là Chủ Nhật, 1 là Thứ 2, ..., 6 là Thứ 7
            let currentDay = now.getDay(); 
            // Chuẩn hóa: Thứ 2 = 1, Thứ 7 = 6, Chủ nhật = 7
            let dayOfWeek = currentDay === 0 ? 7 : currentDay;

            const currentHours = now.getHours();
            const currentMins = now.getMinutes();
            const currentTotalMinutes = currentHours * 60 + currentMins;

            const dateStr = now.toISOString().split('T')[0];

            this.items.forEach(item => {
                // Kiểm tra điều kiện ngày (theo thứ hàng tuần hoặc theo ngày cụ thể)
                const matchDay = (item.specific_date && item.specific_date === dateStr) || 
                                 (!item.specific_date && item.day_of_week === dayOfWeek);

                if (!matchDay) return;

                const [startH, startM] = item.start_time.split(':').map(Number);
                const classStartTotalMinutes = startH * 60 + startM;
                const remindBefore = parseInt(item.reminder_minutes, 10) || 0;
                const alarmTargetMinutes = classStartTotalMinutes - remindBefore;

                // Khóa nhận diện duy nhất cho lần báo trong ngày
                const alarmKey = `${dateStr}_${item.id}_${alarmTargetMinutes}`;

                if (currentTotalMinutes === alarmTargetMinutes && !this.checkedAlarms.has(alarmKey)) {
                    this.checkedAlarms.add(alarmKey);
                    this.triggerNotification(item, remindBefore);
                }
            });

            this.updateUpcomingWidget();
        },

        // Kích hoạt thông báo và chuông
        triggerNotification: function(item, remindBefore) {
            this.playAlarmSound();

            const timeNotice = remindBefore > 0 ? `sau ${remindBefore} phút nữa` : 'ngay bây giờ';
            const title = `⏰ Báo Thời Khóa Biểu: ${item.title}`;
            const message = `Môn học "${item.title}" sẽ bắt đầu lúc ${item.start_time} (${timeNotice}) tại ${item.location || 'lớp học'}.`;

            // 1. Web Notification
            if ('Notification' in window && Notification.permission === 'granted') {
                try {
                    const n = new Notification(title, {
                        body: message,
                        icon: 'logo.png',
                        tag: 'tkb-alarm-' + item.id
                    });
                    n.onclick = () => {
                        window.focus();
                        window.location.href = 'thoikhoabieu.php';
                    };
                } catch (e) {}
            }

            // 2. In-app Toast / SweetAlert2
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: `⏰ Đến Giờ Học!`,
                    html: `<b>${item.title}</b><br>
                           <span class="text-primary"><i class="fas fa-clock"></i> ${item.start_time} - ${item.end_time}</span><br>
                           ${item.location ? `<span><i class="fas fa-map-marker-alt"></i> ${item.location}</span><br>` : ''}
                           ${item.teacher ? `<span><i class="fas fa-user"></i> ${item.teacher}</span>` : ''}`,
                    icon: 'info',
                    confirmButtonText: 'Đã rõ',
                    confirmButtonColor: item.color || '#0d6efd',
                    timer: 15000,
                    timerProgressBar: true
                });
            } else {
                alert(`${title}\n${message}`);
            }
        },

        // Cập nhật thẻ widget hiển thị tiết học sắp tới nếu có phần tử trên giao diện
        updateUpcomingWidget: function() {
            const container = document.getElementById('upcomingClassAlert');
            if (!container) return;

            const now = new Date();
            let currentDay = now.getDay() === 0 ? 7 : now.getDay();
            const dateStr = now.toISOString().split('T')[0];
            const currentTotalMinutes = now.getHours() * 60 + now.getMinutes();

            // Tìm môn học tiếp theo trong ngày hôm nay
            let upcoming = null;
            let minDiff = Infinity;

            this.items.forEach(item => {
                const matchDay = (item.specific_date && item.specific_date === dateStr) || 
                                 (!item.specific_date && item.day_of_week === currentDay);
                if (!matchDay) return;

                const [startH, startM] = item.start_time.split(':').map(Number);
                const classStartTotalMinutes = startH * 60 + startM;
                const diff = classStartTotalMinutes - currentTotalMinutes;

                // Lớp học sắp tới trong vòng 12 tiếng tới
                if (diff >= 0 && diff < minDiff) {
                    minDiff = diff;
                    upcoming = item;
                }
            });

            if (upcoming) {
                const diffHours = Math.floor(minDiff / 60);
                const diffMins = minDiff % 60;
                let timeRemainingText = '';
                if (diffHours > 0) {
                    timeRemainingText = `còn ${diffHours} giờ ${diffMins} phút`;
                } else if (diffMins > 0) {
                    timeRemainingText = `còn ${diffMins} phút nữa`;
                } else {
                    timeRemainingText = `đang diễn ra hoặc bắt đầu ngay bây giờ!`;
                }

                container.innerHTML = `
                    <div class="alert alert-info d-flex align-items-center justify-content-between p-2 mb-3 shadow-sm rounded-3" style="border-left: 5px solid ${upcoming.color || '#0d6efd'};">
                        <div class="d-flex align-items-center">
                            <span class="fs-4 me-3">🔔</span>
                            <div>
                                <span class="badge bg-primary me-2">Tiết học kế tiếp</span>
                                <strong>${upcoming.title}</strong> &bull; 
                                <span class="text-muted">${upcoming.start_time} - ${upcoming.end_time}</span>
                                ${upcoming.location ? ` &bull; <i class="fas fa-map-marker-alt"></i> ${upcoming.location}` : ''}
                                <span class="badge bg-warning text-dark ms-2">${timeRemainingText}</span>
                            </div>
                        </div>
                        <button class="btn btn-sm btn-outline-primary" onclick="TimetableAlarm.playAlarmSound();" title="Thử chuông">
                            <i class="fas fa-volume-up"></i>
                        </button>
                    </div>
                `;
            } else {
                container.innerHTML = '';
            }
        }
    };

    window.TimetableAlarm = TimetableAlarm;

    document.addEventListener('DOMContentLoaded', () => {
        TimetableAlarm.init();
    });

})(window);
