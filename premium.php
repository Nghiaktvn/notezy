<?php
require_once __DIR__ . '/includes/session.php';
notezy_session_start();
if (!isset($_SESSION['id'])) { header('Location: index.php'); exit(); }
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gói Notezy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        :root { --ink:#172033; --muted:#64748b; --line:#dbe4ee; --brand:#2563eb; --canvas:#f6f8fc; }
        body { background:var(--canvas); color:var(--ink); font-family:Inter,system-ui,sans-serif; }
        .shell { max-width:1120px; }
        .topbar { background:#fff; border-bottom:1px solid var(--line); }
        .brand { color:var(--brand); font-weight:800; text-decoration:none; }
        .plan { background:#fff; border:1px solid var(--line); border-radius:8px; min-height:350px; }
        .plan.featured { border:2px solid var(--brand); }
        .price { font-size:2rem; font-weight:800; }
        .feature { border:0; padding:.45rem 0; color:#334155; }
        .status { border-left:4px solid var(--brand); background:#eff6ff; }
    </style>
</head>
<body>
    <nav class="topbar py-3 mb-4">
        <div class="shell container d-flex justify-content-between align-items-center">
            <a class="brand fs-5" href="index_notezy.php"><i class="fa-solid fa-note-sticky me-2"></i>Notezy</a>
            <div class="d-flex gap-3 align-items-center">
                <a class="text-decoration-none text-secondary" href="thoikhoabieu.php">Lịch</a>
                <a class="text-decoration-none fw-semibold text-primary" href="premium.php">Gói dịch vụ</a>
                <a class="text-decoration-none text-secondary" href="account.php">Tài khoản</a>
            </div>
        </div>
    </nav>
    <main class="shell container pb-5">
        <div class="row align-items-end mb-4">
            <div class="col-md-8">
                <h1 class="h2 fw-bold mb-2">Chọn gói phù hợp với nhịp học và công việc</h1>
                <p class="text-secondary mb-0">Nâng cấp khi bạn cần nhiều AI hơn, chia sẻ lịch học hoặc làm việc cùng nhóm.</p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0"><span class="badge text-bg-light border" id="planBadge">Đang tải gói</span></div>
        </div>
        <section class="status p-3 mb-4 d-none" id="subscriptionStatus"></section>
        <div class="row g-3">
            <div class="col-md-6 col-lg-3"><article class="plan p-4 h-100"><h2 class="h5">Miễn phí</h2><div class="price">0đ</div><p class="text-secondary small">Ghi chú và lịch cá nhân.</p><ul class="list-group list-group-flush mb-4"><li class="list-group-item feature">Ghi chú, nhãn, nhắc lịch</li><li class="list-group-item feature">5 lượt AI mỗi ngày</li></ul><button class="btn btn-outline-secondary w-100" disabled>Gói hiện có</button></article></div>
            <div class="col-md-6 col-lg-3"><article class="plan p-4 h-100"><h2 class="h5">Sinh viên</h2><div class="price">29.000đ<span class="fs-6 fw-normal">/tháng</span></div><p class="text-secondary small">Tập trung học tập.</p><ul class="list-group list-group-flush mb-4"><li class="list-group-item feature">40 lượt AI/ngày</li><li class="list-group-item feature">Flashcard và quiz</li><li class="list-group-item feature">Chia sẻ lịch học</li></ul><button class="btn btn-outline-primary w-100" data-upgrade="STUDENT">Chọn gói</button></article></div>
            <div class="col-md-6 col-lg-3"><article class="plan featured p-4 h-100"><span class="badge text-bg-primary mb-2">Phổ biến</span><h2 class="h5">Cá nhân</h2><div class="price">59.000đ<span class="fs-6 fw-normal">/tháng</span></div><p class="text-secondary small">Dành cho lịch làm việc dày đặc.</p><ul class="list-group list-group-flush mb-4"><li class="list-group-item feature">150 lượt AI/ngày</li><li class="list-group-item feature">Chia sẻ ghi chú và lịch</li><li class="list-group-item feature">Ưu tiên hỗ trợ</li></ul><button class="btn btn-primary w-100" id="trialButton">Dùng thử 7 ngày</button></article></div>
            <div class="col-md-6 col-lg-3"><article class="plan p-4 h-100"><h2 class="h5">Nhóm / Lớp</h2><div class="price">149.000đ<span class="fs-6 fw-normal">/tháng</span></div><p class="text-secondary small">Cộng tác cho nhóm nhỏ.</p><ul class="list-group list-group-flush mb-4"><li class="list-group-item feature">300 lượt AI/ngày</li><li class="list-group-item feature">Lịch và ghi chú chung</li><li class="list-group-item feature">Phân quyền thành viên</li></ul><button class="btn btn-outline-primary w-100" data-upgrade="TEAM">Chọn gói</button></article></div>
        </div>
        <p class="text-secondary small mt-4 mb-0">Thanh toán MoMo, VNPay và ZaloPay sẽ được xác nhận trước khi kích hoạt gói trả phí. Gói dùng thử không tự gia hạn.</p>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        async function loadPremium() {
            const response = await fetch('api/premium.php', {credentials:'same-origin'});
            const data = await response.json();
            if (data.subscription) {
                document.getElementById('planBadge').textContent = `Gói ${data.subscription.name}`;
                const box = document.getElementById('subscriptionStatus');
                box.classList.remove('d-none');
                box.textContent = data.subscription.ends_at ? `Gói ${data.subscription.name} có hiệu lực đến ${new Date(data.subscription.ends_at.replace(' ', 'T')).toLocaleString('vi-VN')}.` : `Bạn đang dùng gói ${data.subscription.name}.`;
                document.getElementById('trialButton').disabled = true;
            } else { document.getElementById('planBadge').textContent = 'Gói Miễn phí'; }
        }
        document.getElementById('trialButton').addEventListener('click', async () => {
            const result = await Swal.fire({title:'Bắt đầu dùng thử?', text:'Premium Cá nhân miễn phí trong 7 ngày, không tự gia hạn.', icon:'question', showCancelButton:true, confirmButtonText:'Bắt đầu', cancelButtonText:'Để sau'});
            if (!result.isConfirmed) return;
            const response = await fetch('api/premium.php', {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'start_trial'})});
            const data = await response.json();
            await Swal.fire({icon:data.status === 'success' ? 'success' : 'error', title:data.status === 'success' ? 'Đã kích hoạt' : 'Chưa thể kích hoạt', text:data.message});
            if (data.status === 'success') loadPremium();
        });
        document.querySelectorAll('[data-upgrade]').forEach(button => button.addEventListener('click', () => Swal.fire({icon:'info', title:'Sắp mở thanh toán', text:'Gói trả phí sẽ hiển thị phương thức MoMo, VNPay hoặc ZaloPay khi cửa hàng được kết nối.'})));
        loadPremium().catch(() => { document.getElementById('planBadge').textContent = 'Không tải được gói'; });
    </script>
</body>
</html>
