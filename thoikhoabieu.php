<?php
require_once __DIR__ . '/includes/session.php';
/**
 * thoikhoabieu.php — Giao diện Thời Khóa Biểu, Lịch Calendar & Báo Giờ (Notezy)
 */
require_once('db.php');
notezy_session_start();

if (!isset($_SESSION['id'])) {
    header('Location: index.php');
    exit();
}

$user_id = (int) $_SESSION['id'];

// Lấy thông tin người dùng
$sql = "SELECT username, avatar, theme, language FROM users WHERE id = ?";
$stm = $conn->prepare($sql);
$stm->bind_param('i', $user_id);
$stm->execute();
$res = $stm->get_result();
$user_info = $res->fetch_assoc() ?: [];
$avatar = !empty($user_info['avatar']) ? $user_info['avatar'] : 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png';
$theme = $_SESSION['theme'] ?? ($user_info['theme'] ?? 'light');

$available_notes = [];
$note_stmt = $conn->prepare('SELECT note_id, title FROM notes WHERE user_id = ? AND archived = 0 ORDER BY pinned DESC, updated_at DESC LIMIT 200');
if ($note_stmt) {
    $note_stmt->bind_param('i', $user_id);
    $note_stmt->execute();
    $available_notes = $note_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $note_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notezy - Thời Khóa Biểu & Lịch Calendar</title>
    <link rel="icon" type="image/x-icon" href="logo.png">
    <!-- Bootstrap 5.3.3 & FontAwesome 6 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --primary-soft: rgba(79, 70, 229, 0.1);
            --bg-page: #f3f0ff;
            --card-bg: #ffffff;
            --card-border: #e2e8f0;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --header-bg: rgba(255, 255, 255, 0.95);
            --today-highlight: #ecfdf5;
            --today-border: #10b981;
        }

        body.dark-mode {
            --primary: #6366f1;
            --primary-hover: #818cf8;
            --primary-soft: rgba(99, 102, 241, 0.15);
            --bg-page: #171426;
            --card-bg: #1e293b;
            --card-border: #334155;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --header-bg: rgba(15, 23, 42, 0.95);
            --today-highlight: rgba(16, 185, 129, 0.1);
            --today-border: #10b981;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-page);
            background-image: linear-gradient(135deg, #f8f7ff 0%, #f3f0ff 48%, #eef2ff 100%);
            color: var(--text-main);
            min-height: 100vh;
            padding-top: 80px;
            transition: background-color 0.3s, color 0.3s;
        }

        body.dark-mode {
            background-image: linear-gradient(135deg, #171426 0%, #171b35 48%, #1e1b3a 100%);
        }

        /* Navbar */
        .navbar {
            background: var(--header-bg) !important;
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--card-border);
            box-shadow: 0 4px 20px rgba(0,0,0,0.04);
        }
        .navbar-brand {
            font-weight: 800;
            font-size: 1.4rem;
            color: var(--primary) !important;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .nav-link {
            font-weight: 600;
            color: var(--text-muted) !important;
            padding: 8px 16px !important;
            border-radius: 8px;
            transition: all 0.2s;
        }
        .nav-link:hover, .nav-link.active {
            color: var(--primary) !important;
            background: var(--primary-soft);
        }
        .avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary);
        }

        /* Hero / Top Info Bar */
        .tkb-hero {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.03);
        }
        .live-clock-card {
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            color: #ffffff;
            border-radius: 14px;
            padding: 16px 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 8px 20px rgba(79, 70, 229, 0.25);
        }
        .live-clock-time {
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: 1px;
            line-height: 1;
        }
        .live-clock-date {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        /* View switcher tabs */
        .view-switcher-pill {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 6px;
            display: inline-flex;
            gap: 6px;
        }
        .view-switcher-btn {
            border: none;
            background: transparent;
            color: var(--text-muted);
            font-weight: 600;
            padding: 8px 18px;
            border-radius: 8px;
            transition: all 0.2s;
        }
        .view-switcher-btn.active {
            background: var(--primary);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
        }

        /* Weekly Table Grid */
        .tkb-table-container {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            overflow-x: auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.03);
            margin-bottom: 40px;
        }
        .tkb-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            min-width: 900px;
        }
        .tkb-table th {
            background: var(--primary-soft);
            color: var(--primary);
            font-weight: 700;
            text-align: center;
            padding: 16px 12px;
            border-bottom: 2px solid var(--card-border);
            border-right: 1px solid var(--card-border);
            font-size: 0.95rem;
        }
        .tkb-table th.today-col-header {
            background: var(--today-highlight);
            color: #059669;
            border-top: 3px solid #10b981;
        }
        .tkb-table td {
            vertical-align: top;
            padding: 12px;
            border-bottom: 1px solid var(--card-border);
            border-right: 1px solid var(--card-border);
            height: 140px;
            width: 13.5%;
            transition: background 0.15s;
        }
        .tkb-table td:hover {
            background: rgba(79, 70, 229, 0.02);
        }
        .tkb-table td.today-col-cell {
            background: var(--today-highlight);
        }
        .tkb-table td.time-period-cell {
            width: 90px;
            background: var(--primary-soft);
            color: var(--text-main);
            font-weight: 700;
            text-align: center;
            font-size: 0.85rem;
            vertical-align: middle;
        }

        /* Schedule Card */
        .schedule-card {
            border-radius: 10px;
            padding: 10px;
            margin-bottom: 8px;
            color: #ffffff;
            font-size: 0.85rem;
            position: relative;
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
        }
        .schedule-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.15);
        }
        .schedule-card .card-title {
            font-weight: 700;
            font-size: 0.95rem;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .schedule-card .card-time {
            font-size: 0.8rem;
            opacity: 0.95;
            display: flex;
            align-items: center;
            gap: 4px;
            margin-bottom: 3px;
        }
        .schedule-card .card-info {
            font-size: 0.78rem;
            opacity: 0.9;
        }
        .schedule-card-actions {
            position: absolute;
            top: 6px;
            right: 6px;
            display: none;
            gap: 4px;
        }
        .schedule-card:hover .schedule-card-actions {
            display: flex;
        }
        .card-act-btn {
            background: rgba(0,0,0,0.4);
            color: #fff;
            border: none;
            border-radius: 4px;
            padding: 2px 6px;
            font-size: 0.7rem;
            cursor: pointer;
        }
        .card-act-btn:hover {
            background: rgba(0,0,0,0.8);
        }

        /* Monthly Calendar View */
        .calendar-container {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.03);
            margin-bottom: 40px;
        }
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
        }
        .calendar-day-header {
            text-align: center;
            font-weight: 700;
            padding: 10px 0;
            color: var(--primary);
            font-size: 0.9rem;
        }
        .calendar-cell {
            background: var(--bg-page);
            border: 1px solid var(--card-border);
            border-radius: 10px;
            min-height: 110px;
            padding: 8px;
            position: relative;
            transition: all 0.2s;
        }
        .calendar-cell:hover {
            border-color: var(--primary);
        }
        .calendar-cell.other-month {
            opacity: 0.35;
        }
        .calendar-cell.is-today {
            border: 2px solid var(--primary);
            background: var(--today-highlight);
        }
        .calendar-date-number {
            font-weight: 700;
            font-size: 0.85rem;
            margin-bottom: 6px;
            color: var(--text-main);
        }
        .calendar-event-pill {
            font-size: 0.75rem;
            padding: 3px 6px;
            border-radius: 6px;
            margin-bottom: 4px;
            color: #fff;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            cursor: pointer;
        }

        /* Color Circle Selector */
        .color-dot-radio {
            display: inline-block;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            cursor: pointer;
            border: 2px solid transparent;
            transition: transform 0.2s, border-color 0.2s;
        }
        .color-dot-radio:hover, .color-dot-radio.selected {
            transform: scale(1.15);
            border-color: #000;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .live-clock-card {
                margin-top: 14px;
            }
            .view-switcher-pill {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body class="<?= $theme === 'dark' ? 'dark-mode' : '' ?>">

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg fixed-top">
    <div class="container">
        <a class="navbar-brand" href="index_notezy.php">
            <img src="logo.png" alt="Notezy" width="40" height="40">
            Notezy
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-3">
                <li class="nav-item">
                    <a class="nav-link" href="index_notezy.php">
                        <i class="fas fa-sticky-note me-1"></i> Ghi chú của tôi
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="thoikhoabieu.php">
                        <i class="fas fa-calendar-alt me-1"></i> Thời khóa biểu & Lịch
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="manage_labels.php">
                        <i class="fas fa-tags me-1"></i> Quản lý nhãn
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="premium.php">
                        <i class="fas fa-crown me-1"></i> Premium
                    </a>
                </li>
            </ul>

            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-outline-primary btn-sm rounded-pill px-3" onclick="TimetableAlarm.playAlarmSound();" title="Thử chuông báo giờ">
                    <i class="fas fa-bell me-1"></i> Thử chuông
                </button>
                <div class="dropdown">
                    <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                        <img src="<?= htmlspecialchars($avatar) ?>" alt="Avatar" class="avatar">
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li><a class="dropdown-item" href="account.php"><i class="fas fa-user me-2"></i>Tài khoản</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Đăng xuất</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- MAIN CONTAINER -->
<main class="container my-4">

    <!-- UPCOMING CLASS ALERT WIDGET -->
    <div id="upcomingClassAlert"></div>

    <!-- HERO & REAL-TIME CLOCK -->
    <div class="tkb-hero">
        <div class="row align-items-center">
            <div class="col-md-7 mb-3 mb-md-0">
                <h2 class="fw-bold mb-1">
                    <i class="fas fa-calendar-check text-primary me-2"></i>Thời Khóa Biểu & Lịch Học
                </h2>
                <p class="text-muted mb-0">
                    Theo dõi lịch học, công việc hàng ngày với hệ thống <strong>báo giờ & chuông nhắc nhở</strong> thông minh.
                </p>
            </div>
            <div class="col-md-5">
                <div class="live-clock-card">
                    <div>
                        <div class="live-clock-date" id="liveClockDate">Đang tải ngày...</div>
                        <div class="live-clock-time" id="liveClockTime">00:00:00</div>
                    </div>
                    <div class="fs-1 opacity-75">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ACTION & VIEW CONTROLS -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <!-- View Switcher -->
        <div class="view-switcher-pill">
            <button class="view-switcher-btn active" id="btnWeeklyView" onclick="switchView('weekly');">
                <i class="fas fa-table me-1"></i> Thời khóa biểu tuần
            </button>
            <button class="view-switcher-btn" id="btnMonthlyView" onclick="switchView('monthly');">
                <i class="fas fa-calendar-day me-1"></i> Lịch tháng
            </button>
        </div>

        <!-- Action Buttons -->
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary rounded-pill" onclick="window.print();">
                <i class="fas fa-print me-1"></i> In lịch
            </button>
            <button class="btn btn-primary rounded-pill px-4" onclick="openAddModal();">
                <i class="fas fa-plus me-1"></i> Thêm môn học / Tiết học
            </button>
        </div>
    </div>

    <!-- VIEW 1: WEEKLY TIMETABLE GRID -->
    <div id="weeklyViewContainer" class="tkb-table-container">
        <table class="tkb-table">
            <thead>
                <tr>
                    <th style="width: 100px;"><i class="fas fa-sun me-1"></i> Buổi</th>
                    <th id="th-day-1">Thứ Hai</th>
                    <th id="th-day-2">Thứ Ba</th>
                    <th id="th-day-3">Thứ Tư</th>
                    <th id="th-day-4">Thứ Năm</th>
                    <th id="th-day-5">Thứ Sáu</th>
                    <th id="th-day-6">Thứ Bảy</th>
                    <th id="th-day-7">Chủ Nhật</th>
                </tr>
            </thead>
            <tbody>
                <!-- CA SÁNG -->
                <tr>
                    <td class="time-period-cell">
                        <i class="fas fa-cloud-sun text-warning fs-5 d-block mb-1"></i>
                        <span>SÁNG</span>
                        <div class="small text-muted">07:00 - 12:00</div>
                    </td>
                    <td id="cell-morning-1" class="day-col-1"></td>
                    <td id="cell-morning-2" class="day-col-2"></td>
                    <td id="cell-morning-3" class="day-col-3"></td>
                    <td id="cell-morning-4" class="day-col-4"></td>
                    <td id="cell-morning-5" class="day-col-5"></td>
                    <td id="cell-morning-6" class="day-col-6"></td>
                    <td id="cell-morning-7" class="day-col-7"></td>
                </tr>
                <!-- CA CHIỀU -->
                <tr>
                    <td class="time-period-cell">
                        <i class="fas fa-sun text-danger fs-5 d-block mb-1"></i>
                        <span>CHIỀU</span>
                        <div class="small text-muted">12:30 - 17:30</div>
                    </td>
                    <td id="cell-afternoon-1" class="day-col-1"></td>
                    <td id="cell-afternoon-2" class="day-col-2"></td>
                    <td id="cell-afternoon-3" class="day-col-3"></td>
                    <td id="cell-afternoon-4" class="day-col-4"></td>
                    <td id="cell-afternoon-5" class="day-col-5"></td>
                    <td id="cell-afternoon-6" class="day-col-6"></td>
                    <td id="cell-afternoon-7" class="day-col-7"></td>
                </tr>
                <!-- CA TỐI -->
                <tr>
                    <td class="time-period-cell">
                        <i class="fas fa-moon text-primary fs-5 d-block mb-1"></i>
                        <span>TỐI</span>
                        <div class="small text-muted">18:00 - 21:30</div>
                    </td>
                    <td id="cell-evening-1" class="day-col-1"></td>
                    <td id="cell-evening-2" class="day-col-2"></td>
                    <td id="cell-evening-3" class="day-col-3"></td>
                    <td id="cell-evening-4" class="day-col-4"></td>
                    <td id="cell-evening-5" class="day-col-5"></td>
                    <td id="cell-evening-6" class="day-col-6"></td>
                    <td id="cell-evening-7" class="day-col-7"></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- VIEW 2: MONTHLY CALENDAR -->
    <div id="monthlyViewContainer" class="calendar-container" style="display: none;">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <h4 class="fw-bold mb-0" id="calendarMonthTitle">Tháng 9, 2026</h4>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-secondary btn-sm" onclick="changeMonth(-1);"><i class="fas fa-chevron-left"></i> Tháng trước</button>
                <button class="btn btn-outline-primary btn-sm" onclick="goToday();">Hôm nay</button>
                <button class="btn btn-outline-secondary btn-sm" onclick="changeMonth(1);">Tháng sau <i class="fas fa-chevron-right"></i></button>
            </div>
        </div>
        <div class="calendar-grid mb-2">
            <div class="calendar-day-header">Thứ 2</div>
            <div class="calendar-day-header">Thứ 3</div>
            <div class="calendar-day-header">Thứ 4</div>
            <div class="calendar-day-header">Thứ 5</div>
            <div class="calendar-day-header">Thứ 6</div>
            <div class="calendar-day-header text-primary">Thứ 7</div>
            <div class="calendar-day-header text-danger">Chủ Nhật</div>
        </div>
        <div class="calendar-grid" id="calendarCells">
            <!-- Rendered by JS -->
        </div>
    </div>

</main>

<!-- MODAL THÊM / SỬA MÔN HỌC -->
<div class="modal fade" id="scheduleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold" id="scheduleModalTitle">
                    <i class="fas fa-book-reader text-primary me-2"></i>Thêm Môn Học / Lịch Học
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="scheduleForm">
                <div class="modal-body p-4">
                    <input type="hidden" id="schId" name="id">

                    <!-- Tên môn học -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tên môn học / Hoạt động <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="schTitle" name="title" placeholder="VD: Lập trình Web, Cơ sở dữ liệu..." required>
                    </div>

                    <!-- Kiểu lặp: Thứ trong tuần hoặc Ngày cụ thể -->
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Thứ trong tuần</label>
                            <select class="form-select" id="schDayOfWeek" name="day_of_week">
                                <option value="1">Thứ Hai</option>
                                <option value="2">Thứ Ba</option>
                                <option value="3">Thứ Tư</option>
                                <option value="4">Thứ Năm</option>
                                <option value="5">Thứ Sáu</option>
                                <option value="6">Thứ Bảy</option>
                                <option value="7">Chủ Nhật</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Hoặc ngày cụ thể (Lịch)</label>
                            <input type="date" class="form-control" id="schSpecificDate" name="specific_date">
                        </div>
                    </div>

                    <!-- Giờ bắt đầu & Giờ kết thúc -->
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Giờ bắt đầu <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="schStartTime" name="start_time" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Giờ kết thúc <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="schEndTime" name="end_time" required>
                        </div>
                    </div>

                    <!-- Phòng học & Giảng viên -->
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phòng học / Địa điểm</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                                <input type="text" class="form-control" id="schLocation" name="location" placeholder="VD: A2-302, Giảng đường C">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Giảng viên / Phụ trách</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-chalkboard-teacher"></i></span>
                                <input type="text" class="form-control" id="schTeacher" name="teacher" placeholder="VD: ThS. Nguyễn Văn A">
                            </div>
                        </div>
                    </div>

                    <!-- Báo thời / Chuông nhắc giờ -->
                    <div class="mb-3 p-3 rounded-3 bg-light border">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <label class="form-label fw-bold mb-0 text-primary">
                                <i class="fas fa-bell me-1"></i> Báo thời (Chuông thông báo)
                            </label>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="TimetableAlarm.playAlarmSound();">
                                <i class="fas fa-volume-up me-1"></i> Nghe thử
                            </button>
                        </div>
                        <select class="form-select" id="schReminderMinutes" name="reminder_minutes">
                            <option value="0">Báo đúng giờ bắt đầu tiết học</option>
                            <option value="5">Báo trước 5 phút</option>
                            <option value="10">Báo trước 10 phút</option>
                            <option value="15" selected>Báo trước 15 phút (Khuyên dùng)</option>
                            <option value="30">Báo trước 30 phút</option>
                            <option value="60">Báo trước 1 tiếng</option>
                            <option value="-1">Tắt chuông báo</option>
                        </select>
                        <small class="text-muted mt-1 d-block">Hệ thống sẽ tự động reo chuông và hiển thị pop-up thông báo khi đến giờ nhắc nhở.</small>
                    </div>

                    <!-- Màu sắc thẻ -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold d-block">Màu sắc hiển thị</label>
                        <div class="d-flex gap-2 align-items-center">
                            <input type="hidden" id="schColor" name="color" value="#4f46e5">
                            <span class="color-dot-radio selected" style="background:#4f46e5;" data-color="#4f46e5" onclick="selectColor(this);"></span>
                            <span class="color-dot-radio" style="background:#0284c7;" data-color="#0284c7" onclick="selectColor(this);"></span>
                            <span class="color-dot-radio" style="background:#059669;" data-color="#059669" onclick="selectColor(this);"></span>
                            <span class="color-dot-radio" style="background:#d97706;" data-color="#d97706" onclick="selectColor(this);"></span>
                            <span class="color-dot-radio" style="background:#dc2626;" data-color="#dc2626" onclick="selectColor(this);"></span>
                            <span class="color-dot-radio" style="background:#7c3aed;" data-color="#7c3aed" onclick="selectColor(this);"></span>
                            <span class="color-dot-radio" style="background:#db2777;" data-color="#db2777" onclick="selectColor(this);"></span>
                        </div>
                    </div>

                    <!-- Ghi chú thêm -->
                    <div class="mb-2">
                        <label class="form-label fw-semibold">Ghi chú thêm</label>
                        <textarea class="form-control" id="schNote" name="note" rows="2" placeholder="Mang giáo trình, nộp bài tập..."></textarea>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-semibold" for="schNoteId">Gắn ghi chú</label>
                        <select class="form-select" id="schNoteId" name="note_id">
                            <option value="">Chưa gắn ghi chú</option>
                            <?php foreach ($available_notes as $available_note): ?>
                                <option value="<?= (int) $available_note['note_id'] ?>"><?= htmlspecialchars($available_note['title'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="modal-footer border-top px-4 py-3">
                    <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Đóng</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4" id="schSaveBtn">
                        <i class="fas fa-save me-1"></i> Lưu lại
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- SCRIPTS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="js/timetable-alarm.js"></script>
<script>
    let scheduleList = [];
    let scheduleModal = null;
    let currentCalendarDate = new Date();

    document.addEventListener('DOMContentLoaded', () => {
        scheduleModal = new bootstrap.Modal(document.getElementById('scheduleModal'));
        updateLiveClock();
        setInterval(updateLiveClock, 1000);
        highlightTodayColumn();
        loadSchedules();

        // Form submit
        document.getElementById('scheduleForm').addEventListener('submit', handleScheduleSubmit);
    });

    // ── Đồng hồ thời gian thực ──────────────────────────────────────
    function updateLiveClock() {
        const now = new Date();
        const timeStr = now.toTimeString().split(' ')[0];
        const days = ['Chủ Nhật', 'Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy'];
        const dayName = days[now.getDay()];
        const dateStr = `${dayName}, ${now.getDate()}/${now.getMonth() + 1}/${now.getFullYear()}`;

        const timeEl = document.getElementById('liveClockTime');
        const dateEl = document.getElementById('liveClockDate');
        if (timeEl) timeEl.textContent = timeStr;
        if (dateEl) dateEl.textContent = dateStr;
    }

    // ── Highlight cột ngày hôm nay trong bảng tuần ──────────────────
    function highlightTodayColumn() {
        const now = new Date();
        let dayOfWeek = now.getDay() === 0 ? 7 : now.getDay();
        const header = document.getElementById(`th-day-${dayOfWeek}`);
        if (header) {
            header.classList.add('today-col-header');
            header.innerHTML += ' <span class="badge bg-success ms-1">Hôm nay</span>';
        }
        document.querySelectorAll(`.day-col-${dayOfWeek}`).forEach(td => {
            td.classList.add('today-col-cell');
        });
    }

    // ── Chuyển đổi View Tuần / Tháng ───────────────────────────────
    function switchView(view) {
        const weeklyBox = document.getElementById('weeklyViewContainer');
        const monthlyBox = document.getElementById('monthlyViewContainer');
        const btnWeekly = document.getElementById('btnWeeklyView');
        const btnMonthly = document.getElementById('btnMonthlyView');

        if (view === 'weekly') {
            weeklyBox.style.display = 'block';
            monthlyBox.style.display = 'none';
            btnWeekly.classList.add('active');
            btnMonthly.classList.remove('active');
        } else {
            weeklyBox.style.display = 'none';
            monthlyBox.style.display = 'block';
            btnWeekly.classList.remove('active');
            btnMonthly.classList.add('active');
            renderMonthlyCalendar();
        }
    }

    // ── Tải danh sách môn học từ API ────────────────────────────────
    async function loadSchedules() {
        try {
            const res = await fetch('api/timetable.php', { credentials: 'same-origin' });
            const json = await res.json();
            if (json.status === 'success') {
                scheduleList = json.data;
                renderWeeklyTable();
                renderMonthlyCalendar();
                if (window.TimetableAlarm) {
                    window.TimetableAlarm.items = scheduleList;
                    window.TimetableAlarm.updateUpcomingWidget();
                }
            }
        } catch (e) {
            console.error('Lỗi khi tải lịch trình:', e);
        }
    }

    // ── Hiển thị dữ liệu lên bảng tuần ──────────────────────────────
    function renderWeeklyTable() {
        // Xóa nội dung cũ trong các ô
        for (let d = 1; d <= 7; d++) {
            document.getElementById(`cell-morning-${d}`).innerHTML = '';
            document.getElementById(`cell-afternoon-${d}`).innerHTML = '';
            document.getElementById(`cell-evening-${d}`).innerHTML = '';
        }

        scheduleList.forEach(item => {
            const day = item.day_of_week;
            const [h] = item.start_time.split(':').map(Number);

            let period = 'morning';
            if (h >= 12 && h < 18) period = 'afternoon';
            else if (h >= 18) period = 'evening';

            const cell = document.getElementById(`cell-${period}-${day}`);
            if (cell) {
                cell.appendChild(createScheduleCard(item));
            }
        });
    }

    // ── Tạo thẻ Môn Học trên bảng ────────────────────────────────────
    function createScheduleCard(item) {
        const card = document.createElement('div');
        card.className = 'schedule-card';
        card.style.backgroundColor = item.color || '#4f46e5';

        card.innerHTML = `
            <div class="card-title">
                <span>${escapeHtml(item.title)}</span>
                ${item.reminder_minutes >= 0 ? `<i class="fas fa-bell" title="Báo trước ${item.reminder_minutes}p"></i>` : ''}
            </div>
            <div class="card-time">
                <i class="far fa-clock"></i> ${item.start_time} - ${item.end_time}
            </div>
            ${item.location ? `<div class="card-info"><i class="fas fa-map-marker-alt"></i> ${escapeHtml(item.location)}</div>` : ''}
            ${item.teacher ? `<div class="card-info"><i class="fas fa-user-graduate"></i> ${escapeHtml(item.teacher)}</div>` : ''}
            ${item.note_id && !item.is_shared ? `<button type="button" class="card-act-btn mt-2" data-note-id="${item.note_id}" title="Mở ghi chú liên kết"><i class="fas fa-sticky-note"></i> ${escapeHtml(item.linked_note_title || 'Mở ghi chú')}</button>` : ''}
            <div class="schedule-card-actions">
                ${!item.is_shared ? `<button class="card-act-btn" data-share-id="${item.id}" title="Chia sẻ"><i class="fas fa-user-plus"></i></button><button class="card-act-btn" onclick="event.stopPropagation(); editSchedule(${item.id});" title="Sửa"><i class="fas fa-pen"></i></button><button class="card-act-btn text-danger" onclick="event.stopPropagation(); deleteSchedule(${item.id});" title="Xóa"><i class="fas fa-trash"></i></button>` : '<span class="badge text-bg-light">Được chia sẻ</span>'}
            </div>
        `;

        const noteButton = card.querySelector('[data-note-id]');
        if (noteButton) {
            noteButton.addEventListener('click', (event) => {
                event.stopPropagation();
                window.location.href = `edit_note.php?id=${noteButton.dataset.noteId}`;
            });
        }

        const shareButton = card.querySelector('[data-share-id]');
        if (shareButton) {
            shareButton.addEventListener('click', (event) => {
                event.stopPropagation();
                openSharePrompt(item);
            });
        }

        card.onclick = () => { if (!item.is_shared) editSchedule(item.id); };
        return card;
    }

    // ── Render Monthly Calendar ──────────────────────────────────────
    function renderMonthlyCalendar() {
        const year = currentCalendarDate.getFullYear();
        const month = currentCalendarDate.getMonth();

        const monthNames = [
            'Tháng 1', 'Tháng 2', 'Tháng 3', 'Tháng 4', 'Tháng 5', 'Tháng 6',
            'Tháng 7', 'Tháng 8', 'Tháng 9', 'Tháng 10', 'Tháng 11', 'Tháng 12'
        ];
        document.getElementById('calendarMonthTitle').textContent = `${monthNames[month]}, ${year}`;

        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);

        // Thứ 2 là 0, Chủ nhật là 6 trong tuần của bảng
        let startDay = firstDay.getDay() - 1;
        if (startDay === -1) startDay = 6;

        const totalDays = lastDay.getDate();
        const prevMonthLastDay = new Date(year, month, 0).getDate();

        const container = document.getElementById('calendarCells');
        container.innerHTML = '';

        const today = new Date();
        const isCurrentMonth = today.getFullYear() === year && today.getMonth() === month;

        // Ngày tháng trước
        for (let i = startDay - 1; i >= 0; i--) {
            const dayNum = prevMonthLastDay - i;
            const cell = document.createElement('div');
            cell.className = 'calendar-cell other-month';
            cell.innerHTML = `<div class="calendar-date-number">${dayNum}</div>`;
            container.appendChild(cell);
        }

        // Ngày trong tháng
        for (let d = 1; d <= totalDays; d++) {
            const thisDate = new Date(year, month, d);
            let dayOfWeek = thisDate.getDay() === 0 ? 7 : thisDate.getDay();
            const dateStr = thisDate.toISOString().split('T')[0];

            const cell = document.createElement('div');
            cell.className = 'calendar-cell';
            if (isCurrentMonth && today.getDate() === d) {
                cell.classList.add('is-today');
            }

            cell.innerHTML = `<div class="calendar-date-number">${d}</div>`;

            // Lọc môn học diễn ra vào ngày này
            scheduleList.forEach(item => {
                const match = (item.specific_date && item.specific_date === dateStr) ||
                              (!item.specific_date && item.day_of_week === dayOfWeek);

                if (match) {
                    const pill = document.createElement('div');
                    pill.className = 'calendar-event-pill';
                    pill.style.backgroundColor = item.color || '#4f46e5';
                    pill.innerHTML = `<i class="far fa-clock me-1"></i>${item.start_time} ${escapeHtml(item.title)}`;
                    pill.onclick = (e) => { e.stopPropagation(); editSchedule(item.id); };
                    cell.appendChild(pill);
                }
            });

            cell.onclick = () => {
                openAddModal();
                document.getElementById('schSpecificDate').value = dateStr;
                document.getElementById('schDayOfWeek').value = dayOfWeek;
            };

            container.appendChild(cell);
        }
    }

    function changeMonth(delta) {
        currentCalendarDate.setMonth(currentCalendarDate.getMonth() + delta);
        renderMonthlyCalendar();
    }

    function goToday() {
        currentCalendarDate = new Date();
        renderMonthlyCalendar();
    }

    // ── Modal & Color Select ─────────────────────────────────────────
    function selectColor(el) {
        document.querySelectorAll('.color-dot-radio').forEach(d => d.classList.remove('selected'));
        el.classList.add('selected');
        document.getElementById('schColor').value = el.getAttribute('data-color');
    }

    function openAddModal() {
        document.getElementById('scheduleForm').reset();
        document.getElementById('schId').value = '';
        document.getElementById('scheduleModalTitle').innerHTML = '<i class="fas fa-book-reader text-primary me-2"></i>Thêm Môn Học / Lịch Học';
        selectColor(document.querySelector('.color-dot-radio[data-color="#4f46e5"]'));
        scheduleModal.show();
    }

    function editSchedule(id) {
        const item = scheduleList.find(s => s.id === id);
        if (!item) return;

        document.getElementById('schId').value = item.id;
        document.getElementById('schTitle').value = item.title;
        document.getElementById('schDayOfWeek').value = item.day_of_week;
        document.getElementById('schSpecificDate').value = item.specific_date || '';
        document.getElementById('schStartTime').value = item.start_time;
        document.getElementById('schEndTime').value = item.end_time;
        document.getElementById('schLocation').value = item.location;
        document.getElementById('schTeacher').value = item.teacher;
        document.getElementById('schReminderMinutes').value = item.reminder_minutes;
        document.getElementById('schNote').value = item.note;
        document.getElementById('schNoteId').value = item.note_id || '';

        const colorDot = document.querySelector(`.color-dot-radio[data-color="${item.color}"]`) || 
                         document.querySelector('.color-dot-radio[data-color="#4f46e5"]');
        if (colorDot) selectColor(colorDot);

        document.getElementById('scheduleModalTitle').innerHTML = '<i class="fas fa-edit text-primary me-2"></i>Chỉnh Sửa Tiết Học';
        scheduleModal.show();
    }

    // ── Xử lý lưu (Thêm / Sửa) ───────────────────────────────────────
    async function handleScheduleSubmit(e) {
        e.preventDefault();
        const form = e.target;
        const id = document.getElementById('schId').value;
        const method = id ? 'PUT' : 'POST';

        const data = {
            id: id ? parseInt(id, 10) : undefined,
            title: document.getElementById('schTitle').value.trim(),
            day_of_week: parseInt(document.getElementById('schDayOfWeek').value, 10),
            specific_date: document.getElementById('schSpecificDate').value || null,
            start_time: document.getElementById('schStartTime').value,
            end_time: document.getElementById('schEndTime').value,
            location: document.getElementById('schLocation').value.trim(),
            teacher: document.getElementById('schTeacher').value.trim(),
            color: document.getElementById('schColor').value,
            reminder_minutes: parseInt(document.getElementById('schReminderMinutes').value, 10),
            note: document.getElementById('schNote').value.trim(),
            note_id: document.getElementById('schNoteId').value ? parseInt(document.getElementById('schNoteId').value, 10) : null
        };

        const btn = document.getElementById('schSaveBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Đang lưu...';

        try {
            const res = await fetch('api/timetable.php', {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(data)
            });
            const json = await res.json();
            if (json.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'Thành công!',
                    text: json.message,
                    timer: 1200,
                    showConfirmButton: false
                });
                scheduleModal.hide();
                loadSchedules();
            } else {
                Swal.fire({ icon: 'error', title: 'Lỗi', text: json.message || 'Không thể lưu lịch trình' });
            }
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Lỗi kết nối', text: 'Vui lòng kiểm tra lại đường truyền mạng' });
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save me-1"></i> Lưu lại';
        }
    }

    function escapeHtml(value) {
        const element = document.createElement('div');
        element.textContent = value == null ? '' : String(value);
        return element.innerHTML;
    }

    async function openSharePrompt(item) {
        const result = await Swal.fire({
            title: 'Chia sẻ lịch học',
            html: `<p class="text-muted small mb-3">${escapeHtml(item.title)}</p><input id="shareRecipient" class="swal2-input" placeholder="Email hoặc tên đăng nhập"><select id="sharePermission" class="swal2-select"><option value="read">Chỉ xem</option><option value="write">Có thể sửa</option></select>`,
            showCancelButton: true,
            confirmButtonText: 'Chia sẻ',
            cancelButtonText: 'Hủy',
            preConfirm: async () => {
                const recipient = document.getElementById('shareRecipient').value.trim();
                const permission = document.getElementById('sharePermission').value;
                if (!recipient) {
                    Swal.showValidationMessage('Nhập email hoặc tên đăng nhập.');
                    return false;
                }
                const response = await fetch('api/timetable_share.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin',
                    body: JSON.stringify({ timetable_id: item.id, recipient, permission })
                });
                const json = await response.json();
                if (!response.ok || json.status !== 'success') {
                    Swal.showValidationMessage(json.message || 'Không thể chia sẻ lịch.');
                    return false;
                }
                return json;
            }
        });
        if (result.isConfirmed) {
            Swal.fire({ icon: 'success', title: 'Đã chia sẻ', timer: 1200, showConfirmButton: false });
        }
    }

    // ── Xử lý xóa môn học ───────────────────────────────────────────
    async function deleteSchedule(id) {
        const item = scheduleList.find(s => s.id === id);
        const result = await Swal.fire({
            title: `Xóa môn "${item ? item.title : ''}"?`,
            text: 'Tiết học này sẽ được gỡ bỏ khỏi thời khóa biểu của bạn.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            confirmButtonText: 'Xóa',
            cancelButtonText: 'Hủy'
        });

        if (result.isConfirmed) {
            try {
                const res = await fetch(`api/timetable.php?id=${id}`, {
                    method: 'DELETE',
                    credentials: 'same-origin'
                });
                const json = await res.json();
                if (json.status === 'success') {
                    Swal.fire({ icon: 'success', title: 'Đã xóa', timer: 1000, showConfirmButton: false });
                    loadSchedules();
                } else {
                    Swal.fire({ icon: 'error', title: 'Lỗi', text: json.message || 'Xóa thất bại' });
                }
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Lỗi', text: 'Không thể kết nối đến máy chủ' });
            }
        }
    }
</script>

</body>
</html>
