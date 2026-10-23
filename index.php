<?php
require_once __DIR__ . '/includes/session.php';
require_once('account_db.php');

notezy_session_start();

// Kiểm tra nếu người dùng đã đăng nhập thì vào thẳng trang ứng dụng
if (isset($_SESSION['id'])) {
    header('Location: index_notezy.php');
    exit();
}

$error = '';
$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
    || isset($_POST['ajax']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'])) {
    $user = trim($_POST['username']);
    $pass = $_POST['password'];

    if (empty($user)) {
        $error = 'Vui lòng nhập tên người dùng';
    } elseif (empty($pass)) {
        $error = 'Vui lòng nhập mật khẩu';
    } elseif (strlen($pass) < 6) {
        $error = 'Mật khẩu phải có ít nhất 6 ký tự';
    } else {
        $result = login($user, $pass);
        if (is_array($result) && !empty($result['success'])) {
            $data = $result['user'];
            session_regenerate_id(true);
            $_SESSION['id'] = $data['id'];
            $_SESSION['theme'] = $data['theme'] ?? 'light';
            $_SESSION['language'] = $data['language'] ?? 'vi';

            if ($is_ajax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true, 'redirect' => 'index_notezy.php']);
                exit();
            }
            header("Location: index_notezy.php");
            exit();
        }

        if (is_array($result) && ($result['code'] ?? '') === 'not_activated') {
            $verify_url = 'verify_activation.php?email=' . urlencode($result['email']);
            if ($is_ajax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'not_activated' => true, 'redirect' => $verify_url]);
                exit();
            }
            header('Location: ' . $verify_url);
            exit();
        }

        $error = is_array($result) ? ($result['error'] ?? 'Thông tin đăng nhập không hợp lệ') : $result;
    }

    if ($is_ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => $error]);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="vi" class="scroll-smooth">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Notezy — Cuốn sổ diệu kỳ cho mọi ý tưởng</title>
    <meta name="description" content="Khám phá Notezy: không gian sổ tay 3D đầy màu sắc để ghi chú, sắp xếp ý tưởng, cộng tác và học cùng AI." />
    <link rel="icon" href="logo.svg" type="image/svg+xml" />

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet" />

    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                        display: ['"Space Grotesk"', '"Plus Jakarta Sans"', 'sans-serif'],
                    },
                    colors: {
                        // Light theme: White & Emerald Green
                        brandGreen: {
                            50: '#ecfdf5',
                            100: '#d1fae5',
                            200: '#a7f3d0',
                            500: '#10b981',
                            600: '#059669',
                            700: '#047857',
                            900: '#064e3b',
                        },
                        // Dark theme: Obsidian Black & Crimson Red
                        brandRed: {
                            50: '#fef2f2',
                            100: '#fee2e2',
                            500: '#ef4444',
                            600: '#dc2626',
                            700: '#b91c1c',
                            900: '#7f1d1d',
                            950: '#450a0a',
                        }
                    }
                }
            }
        };
    </script>

    <!-- React 18 & ReactDOM & Babel for JSX -->
    <script src="https://unpkg.com/react@18/umd/react.production.min.js" crossorigin></script>
    <script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js" crossorigin></script>
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>

    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            transition: background-color 0.3s ease, color 0.3s ease;
            background: radial-gradient(circle at 12% 8%, #ffe1f2 0, transparent 30%), linear-gradient(135deg, #faf0ff 0%, #f2e5ff 52%, #ffeaf7 100%) !important;
        }
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: rgba(150, 150, 150, 0.3);
            border-radius: 9999px;
        }
        .animate-fade-in {
            animation: fadeIn 0.4s ease-out forwards;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
    <link rel="stylesheet" href="CSS/landing-refresh.css?v=1" />
    <link rel="stylesheet" href="CSS/landing-rebuild.css?v=6" />
</head>
<body class="landing-refresh text-gray-900 antialiased">
    <div id="root"></div>

    <script type="text/babel">
        const { useState, useEffect } = React;

        // ── Notezy Wave Logo Component ──────────────────────────────────────────
        function NotezyLogo({ className = "h-8 w-8" }) {
            return (
                <div className={`notezy-brand-mark relative flex items-center justify-center rounded-xl p-2 text-white shadow-md bg-gradient-to-br from-purple-600 via-indigo-600 to-pink-500 shadow-purple-600/30 ${className}`}>
                    <i className="fas fa-book-open text-sm"></i>
                </div>
            );
        }

        // ── Main App Component ──────────────────────────────────────────────────
        function App() {
            // A single purple-pink appearance keeps the landing page consistent.
            const theme = 'light';

            // Active Tab in App Mockup ('ai' | 'search' | 'notes')
            const [mockupTab, setMockupTab] = useState('ai');
            const [isMockupPinUnlocked, setIsMockupPinUnlocked] = useState(false);
            const [mockupPinInput, setMockupPinInput] = useState('');
            const [mockupPinError, setMockupPinError] = useState('');

            // Demo Video Simulation State
            const [demoPlaying, setDemoPlaying] = useState(false);
            const [demoProgress, setDemoProgress] = useState(0);
            const [demoStep, setDemoStep] = useState(1);
            const [contactSent, setContactSent] = useState(false);

            // Auth Modal State
            const [isAuthModalOpen, setIsAuthModalOpen] = useState(false);
            const [authMode, setAuthMode] = useState('login'); // 'login' | 'register'
            const [loginSuccessMsg, setLoginSuccessMsg] = useState('');
            const [loginForm, setLoginForm] = useState({ username: '', password: '', error: '', loading: false });
            const [regForm, setRegForm] = useState({
                displayName: '',
                email: '',
                password: '',
                passwordConfirm: '',
                error: '',
                loading: false
            });

            // 3D Tilt Interaction for Collection Cards and Hero Section
            useEffect(() => {
                const handleMouseMove = (e) => {
                    const card = e.currentTarget;
                    const rect = card.getBoundingClientRect();
                    const x = e.clientX - rect.left;
                    const y = e.clientY - rect.top;
                    const centerX = rect.width / 2;
                    const centerY = rect.height / 2;
                    const rotateX = ((y - centerY) / centerY) * -11;
                    const rotateY = ((x - centerX) / centerX) * 11;
                    card.style.transform = `perspective(1000px) rotateX(${rotateX.toFixed(2)}deg) rotateY(${rotateY.toFixed(2)}deg) scale3d(1.025, 1.025, 1.025) translateY(-6px)`;
                };

                const handleMouseLeave = (e) => {
                    const card = e.currentTarget;
                    card.style.transform = '';
                };

                const tiltTargets = document.querySelectorAll('.nz2-card, .nz2-hero-copy, .nz2-about-visual, .nz2-contact-art');
                tiltTargets.forEach(el => {
                    el.addEventListener('mousemove', handleMouseMove);
                    el.addEventListener('mouseleave', handleMouseLeave);
                });

                return () => {
                    tiltTargets.forEach(el => {
                        el.removeEventListener('mousemove', handleMouseMove);
                        el.removeEventListener('mouseleave', handleMouseLeave);
                    });
                };
            }, []);

            // Check URL for ?registered=1
            useEffect(() => {
                const params = new URLSearchParams(window.location.search);
                if (params.get('auth') === 'login' || params.get('auth') === 'register') {
                    setAuthMode(params.get('auth'));
                    setIsAuthModalOpen(true);
                }
                if (params.get('registered') === '1') {
                    const u = params.get('username') || '';
                    setLoginForm(prev => ({ ...prev, username: u }));
                    setLoginSuccessMsg('Đăng ký tài khoản thành công! Vui lòng đăng nhập để bắt đầu.');
                    setAuthMode('login');
                    setIsAuthModalOpen(true);
                }
            }, []);

            // Demo video runner
            useEffect(() => {
                let interval;
                if (demoPlaying) {
                    interval = setInterval(() => {
                        setDemoProgress(prev => {
                            if (prev >= 100) {
                                setDemoPlaying(false);
                                return 100;
                            }
                            const next = prev + 2.5;
                            if (next < 35) setDemoStep(1);
                            else if (next < 70) setDemoStep(2);
                            else setDemoStep(3);
                            return next;
                        });
                    }, 450);
                }
                return () => clearInterval(interval);
            }, [demoPlaying]);

            const restartDemo = () => {
                setDemoProgress(0);
                setDemoStep(1);
                setDemoPlaying(true);
            };

            // Handle Pin unlock simulation
            const handleMockupUnlock = (e) => {
                e.preventDefault();
                if (mockupPinInput === '202606') {
                    setIsMockupPinUnlocked(true);
                    setMockupPinError('');
                } else {
                    setMockupPinError('Mã không đúng! Thử nhập 202606.');
                }
            };

            // Handle Login Form Submit
            const handleLoginSubmit = async (e) => {
                e.preventDefault();
                const formElement = e.currentTarget;
                setLoginForm(prev => ({ ...prev, loading: true, error: '' }));

                try {
                    const formData = new URLSearchParams();
                    formData.append('username', loginForm.username);
                    formData.append('password', loginForm.password);
                    formData.append('ajax', '1');

                    const res = await fetch('index.php', {
                        method: 'POST',
                        headers: { 'Accept': 'application/json' },
                        body: formData
                    });
                    const data = await res.json();

                    if (data.success) {
                        window.location.href = data.redirect || 'index_notezy.php';
                    } else if (data.not_activated) {
                        window.location.href = data.redirect;
                    } else {
                        setLoginForm(prev => ({ ...prev, loading: false, error: data.error || 'Đăng nhập không thành công' }));
                    }
                } catch (err) {
                    // Fall back to a normal form POST when the browser cannot complete fetch.
                    // The PHP handler above supports the same credentials without AJAX.
                    formElement.submit();
                }
            };

            // Handle Register Form Submit
            const handleRegisterSubmit = async (e) => {
                e.preventDefault();
                if (regForm.password.length < 6) {
                    setRegForm(prev => ({ ...prev, error: 'Mật khẩu phải có ít nhất 6 ký tự' }));
                    return;
                }
                if (regForm.password !== regForm.passwordConfirm) {
                    setRegForm(prev => ({ ...prev, error: 'Mật khẩu xác nhận không khớp' }));
                    return;
                }

                setRegForm(prev => ({ ...prev, loading: true, error: '' }));

                try {
                    const formData = new URLSearchParams();
                    formData.append('display_name', regForm.displayName);
                    formData.append('email', regForm.email);
                    formData.append('pass', regForm.password);
                    formData.append('pass-confirm', regForm.passwordConfirm);
                    formData.append('ajax', '1');

                    const res = await fetch('register.php', {
                        method: 'POST',
                        headers: { 'Accept': 'application/json' },
                        body: formData
                    });
                    const data = await res.json();

                    if (data.success) {
                        if (data.redirect) {
                            window.location.href = data.redirect;
                            return;
                        }
                        setLoginSuccessMsg('🎉 Đăng ký thành công! Bạn đã được đăng nhập.');
                        setRegForm({
                            displayName: '',
                            email: '',
                            password: '',
                            passwordConfirm: '',
                            error: '',
                            loading: false
                        });
                    } else {
                        setRegForm(prev => ({ ...prev, loading: false, error: data.error || 'Đăng ký không thành công' }));
                    }
                } catch (err) {
                    setRegForm(prev => ({ ...prev, loading: false, error: 'Không thể kết nối máy chủ. Vui lòng thử lại.' }));
                }
            };

            const isDark = theme === 'dark';

            return (
                <div className="min-h-screen transition-colors duration-300">
                    {/* ── 1. FLOATING NAVBAR (Theo phong cách NotaAI) ── */}
                    <header className="sticky top-0 z-50 pt-3.5 px-2 sm:px-8">
                        <nav className={`mx-auto flex max-w-6xl items-center justify-between px-3 sm:px-5 py-2.5 rounded-full transition-all duration-300 border ${
                            isDark
                                ? 'bg-black/90 backdrop-blur-xl border-red-900/40 shadow-xl shadow-red-950/20'
                                : 'bg-white/85 backdrop-blur-2xl border-white/85 shadow-lg shadow-purple-950/5'
                        }`}>
                            {/* Logo */}
                            <a href="#" className="flex items-center gap-2.5">
                                <NotezyLogo className="h-8 w-8" />
                                <span className="font-display text-lg font-bold tracking-tight text-gray-900 dark:text-white flex items-center gap-1.5">
                                    Notezy
                                    <span className="text-[10px] uppercase tracking-widest px-2 py-0.5 rounded-full font-bold bg-purple-100/90 text-purple-700 border border-purple-200/90">
                                        AI
                                    </span>
                                </span>
                            </a>

                            {/* Menu links */}
                            <div className="notezy-main-links flex items-center gap-8" aria-label="Điều hướng trang chủ">
                                <a href="#home">Home</a>
                                <a href="#contact">Contact</a>
                                <a href="#about">About</a>
                            </div>

                            {/* Right actions: Login + Start */}
                            <div className="flex items-center gap-1 sm:gap-3">
                                {/* Đăng nhập */}
                                <button
                                    type="button"
                                    onClick={() => { setAuthMode('login'); setIsAuthModalOpen(true); }}
                                    className="rounded-full px-2 sm:px-4 py-2 text-xs font-bold text-gray-700 hover:bg-purple-50 hover:text-purple-700 transition-all"
                                >
                                    Đăng nhập
                                </button>

                                {/* Bắt đầu miễn phí (mở tab Đăng ký trong modal) */}
                                <button
                                    type="button"
                                    onClick={() => { setAuthMode('register'); setIsAuthModalOpen(true); }}
                                    className="notezy-nav-primary hidden min-[360px]:block rounded-full px-3 sm:px-5 py-2 text-xs font-bold text-white shadow-md transition-all hover:scale-105 active:scale-95 bg-gradient-to-r from-purple-600 via-purple-700 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 shadow-purple-600/30"
                                >
                                    Bắt đầu
                                </button>
                            </div>
                        </nav>
                    </header>

                    <?php require __DIR__ . '/includes/landing-body.php'; ?>

                    {/* ── 8. INTEGRATED AUTH MODAL (2 TAB ĐĂNG NHẬP VÀ ĐĂNG KÝ TRỰC TIẾP) ── */}
                    {isAuthModalOpen && (
                        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 animate-fade-in">
                            <div className={`notezy-auth-card relative w-full max-w-md rounded-3xl border p-6 sm:p-8 shadow-2xl transition-all ${
                                isDark
                                    ? 'bg-neutral-950 border-red-900/40 text-white shadow-red-950/40'
                                    : 'bg-white border-gray-200 text-gray-900 shadow-2xl'
                            }`}>
                                {/* Close button */}
                                <button
                                    type="button"
                                    onClick={() => setIsAuthModalOpen(false)}
                                    className="absolute top-5 right-5 text-gray-400 hover:text-gray-600 dark:hover:text-white transition-colors"
                                >
                                    <i className="fas fa-times text-lg"></i>
                                </button>

                                {/* Logo & Header */}
                                <div className="text-center mb-5">
                                    <img className="notezy-auth-mascot" src="assets/notezy-mascot-2026.png" alt="Linh vật sổ tay Notezy chào mừng bạn" width="1302" height="1208" />
                                    <h3 className="font-display text-2xl font-bold">
                                        {authMode === 'login' ? 'Đăng nhập Notezy' : 'Tạo tài khoản mới'}
                                    </h3>
                                    <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        {authMode === 'login' ? 'Chào mừng bạn quay trở lại với không gian ghi chú' : 'Khám phá trợ lý AI và bảo mật 6 số ngay'}
                                    </p>
                                </div>

                                {/* 2 Tab Switcher: [ Đăng nhập ] và [ Đăng ký ] */}
                                <div className={`grid grid-cols-2 p-1 mb-5 rounded-2xl border ${
                                    isDark ? 'bg-neutral-900 border-white/10' : 'bg-gray-100 border-gray-200'
                                }`}>
                                    <button
                                        type="button"
                                        onClick={() => { setAuthMode('login'); setRegForm(r => ({ ...r, error: '', success: '' })); }}
                                        className={`py-2 text-xs font-bold rounded-xl transition-all ${
                                            authMode === 'login'
                                                ? (isDark ? 'bg-red-600 text-white shadow-md' : 'bg-white text-green-700 shadow-md')
                                                : 'text-gray-500 hover:text-gray-800 dark:hover:text-gray-200'
                                        }`}
                                    >
                                        <i className="fas fa-sign-in-alt me-1.5"></i>Đăng nhập
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => { setAuthMode('register'); setLoginForm(l => ({ ...l, error: '' })); }}
                                        className={`py-2 text-xs font-bold rounded-xl transition-all ${
                                            authMode === 'register'
                                                ? (isDark ? 'bg-red-600 text-white shadow-md' : 'bg-white text-green-700 shadow-md')
                                                : 'text-gray-500 hover:text-gray-800 dark:hover:text-gray-200'
                                        }`}
                                    >
                                        <i className="fas fa-user-plus me-1.5"></i>Đăng ký
                                    </button>
                                </div>

                                {loginSuccessMsg && (
                                    <div className="mb-4 rounded-xl bg-green-100 dark:bg-green-950/60 border border-green-300 dark:border-green-800/60 p-3 text-xs text-green-800 dark:text-green-300 flex items-center gap-2">
                                        <i className="fas fa-check-circle"></i>
                                        <span>{loginSuccessMsg}</span>
                                    </div>
                                )}

                                {authMode === 'login' && loginForm.error && (
                                    <div className="mb-4 rounded-xl bg-red-100 dark:bg-red-950/60 border border-red-300 dark:border-red-900/60 p-3 text-xs text-red-700 dark:text-red-300 flex items-center gap-2">
                                        <i className="fas fa-exclamation-circle"></i>
                                        <span>{loginForm.error}</span>
                                    </div>
                                )}

                                {authMode === 'register' && regForm.error && (
                                    <div className="mb-4 rounded-xl bg-red-100 dark:bg-red-950/60 border border-red-300 dark:border-red-900/60 p-3 text-xs text-red-700 dark:text-red-300 flex items-center gap-2">
                                        <i className="fas fa-exclamation-circle"></i>
                                        <span>{regForm.error}</span>
                                    </div>
                                )}

                                {/* FORM ĐĂNG NHẬP */}
                                {authMode === 'login' ? (
                                    <form onSubmit={handleLoginSubmit} method="POST" action="index.php" className="space-y-4">
                                        <div>
                                            <label className="block text-xs font-bold mb-1 text-gray-700 dark:text-gray-300">
                                                Tên người dùng
                                            </label>
                                            <input
                                                type="text"
                                                name="username"
                                                required
                                                value={loginForm.username}
                                                onChange={(e) => setLoginForm(prev => ({ ...prev, username: e.target.value }))}
                                                placeholder="Nhập tên tài khoản"
                                                className={`w-full rounded-xl border px-3.5 py-2.5 text-sm focus:outline-none ${
                                                    isDark
                                                        ? 'bg-neutral-900 border-white/10 text-white focus:border-red-500'
                                                        : 'bg-gray-50 border-gray-300 text-gray-900 focus:border-green-600'
                                                }`}
                                            />
                                        </div>

                                        <div>
                                            <div className="flex items-center justify-between mb-1">
                                                <label className="text-xs font-bold text-gray-700 dark:text-gray-300">
                                                    Mật khẩu
                                                </label>
                                                <a href="forgot.php" className={`text-xs hover:underline ${isDark ? 'text-red-400' : 'text-green-700'}`}>
                                                    Quên mật khẩu?
                                                </a>
                                            </div>
                                            <input
                                                type="password"
                                                name="password"
                                                required
                                                value={loginForm.password}
                                                onChange={(e) => setLoginForm(prev => ({ ...prev, password: e.target.value }))}
                                                placeholder="••••••••"
                                                className={`w-full rounded-xl border px-3.5 py-2.5 text-sm focus:outline-none ${
                                                    isDark
                                                        ? 'bg-neutral-900 border-white/10 text-white focus:border-red-500'
                                                        : 'bg-gray-50 border-gray-300 text-gray-900 focus:border-green-600'
                                                }`}
                                            />
                                        </div>

                                        <button
                                            type="submit"
                                            disabled={loginForm.loading}
                                            className={`w-full rounded-full py-3 text-sm font-bold text-white shadow-lg transition-all ${
                                                isDark
                                                    ? 'bg-red-600 hover:bg-red-500 shadow-red-600/30'
                                                    : 'bg-green-600 hover:bg-green-700 shadow-green-600/25'
                                            }`}
                                        >
                                            {loginForm.loading ? (
                                                <span><i className="fas fa-spinner fa-spin me-2"></i>Đang đăng nhập...</span>
                                            ) : (
                                                <span>Đăng nhập ngay</span>
                                            )}
                                        </button>

                                        <div className="text-center pt-2 text-xs text-gray-500">
                                            Chưa có tài khoản?{' '}
                                            <button
                                                type="button"
                                                onClick={() => setAuthMode('register')}
                                                className={`font-bold hover:underline ${isDark ? 'text-red-400' : 'text-green-700'}`}
                                            >
                                                Chuyển sang Đăng ký
                                            </button>
                                        </div>
                                    </form>
                                ) : (
                                    /* FORM ĐĂNG KÝ TRỰC TIẾP */
                                    <form onSubmit={handleRegisterSubmit} className="space-y-3">
                                        <div>
                                            <label className="block text-xs font-bold mb-1 text-gray-700 dark:text-gray-300">
                                                Tên hiển thị
                                            </label>
                                            <input
                                                type="text"
                                                required
                                                value={regForm.displayName}
                                                onChange={(e) => setRegForm(prev => ({ ...prev, displayName: e.target.value }))}
                                                placeholder="Nguyễn Văn A"
                                                className={`w-full rounded-xl border px-3.5 py-2 text-xs focus:outline-none ${
                                                    isDark ? 'bg-neutral-900 border-white/10 text-white focus:border-red-500' : 'bg-gray-50 border-gray-300 text-gray-900 focus:border-green-600'
                                                }`}
                                            />
                                        </div>

                                        <div>
                                            <label className="block text-xs font-bold mb-1 text-gray-700 dark:text-gray-300">
                                                Email
                                            </label>
                                            <input
                                                type="email"
                                                required
                                                value={regForm.email}
                                                onChange={(e) => setRegForm(prev => ({ ...prev, email: e.target.value }))}
                                                placeholder="vana@vidu.com"
                                                className={`w-full rounded-xl border px-3.5 py-2 text-xs focus:outline-none ${
                                                    isDark ? 'bg-neutral-900 border-white/10 text-white focus:border-red-500' : 'bg-gray-50 border-gray-300 text-gray-900 focus:border-green-600'
                                                }`}
                                            />
                                        </div>

                                        <div className="grid grid-cols-2 gap-2">
                                            <div>
                                                <label className="block text-xs font-bold mb-1 text-gray-700 dark:text-gray-300">
                                                    Mật khẩu
                                                </label>
                                                <input
                                                    type="password"
                                                    required
                                                    value={regForm.password}
                                                    onChange={(e) => setRegForm(prev => ({ ...prev, password: e.target.value }))}
                                                    placeholder="Tối thiểu 6 ký tự"
                                                    className={`w-full rounded-xl border px-3 py-2 text-xs focus:outline-none ${
                                                        isDark ? 'bg-neutral-900 border-white/10 text-white focus:border-red-500' : 'bg-gray-50 border-gray-300 text-gray-900 focus:border-green-600'
                                                    }`}
                                                />
                                            </div>
                                            <div>
                                                <label className="block text-xs font-bold mb-1 text-gray-700 dark:text-gray-300">
                                                    Xác nhận
                                                </label>
                                                <input
                                                    type="password"
                                                    required
                                                    value={regForm.passwordConfirm}
                                                    onChange={(e) => setRegForm(prev => ({ ...prev, passwordConfirm: e.target.value }))}
                                                    placeholder="Nhập lại mật khẩu"
                                                    className={`w-full rounded-xl border px-3 py-2 text-xs focus:outline-none ${
                                                        isDark ? 'bg-neutral-900 border-white/10 text-white focus:border-red-500' : 'bg-gray-50 border-gray-300 text-gray-900 focus:border-green-600'
                                                    }`}
                                                />
                                            </div>
                                        </div>

                                        <button
                                            type="submit"
                                            disabled={regForm.loading}
                                            className={`w-full rounded-full py-3 text-sm font-bold text-white shadow-lg transition-all ${
                                                isDark
                                                    ? 'bg-red-600 hover:bg-red-500 shadow-red-600/30'
                                                    : 'bg-green-600 hover:bg-green-700 shadow-green-600/25'
                                            }`}
                                        >
                                            {regForm.loading ? (
                                                <span><i className="fas fa-spinner fa-spin me-2"></i>Đang xử lý đăng ký...</span>
                                            ) : (
                                                <span>Tạo tài khoản ngay</span>
                                            )}
                                        </button>

                                        <div className="text-center pt-1 text-xs text-gray-500">
                                            Đã có tài khoản?{' '}
                                            <button
                                                type="button"
                                                onClick={() => setAuthMode('login')}
                                                className={`font-bold hover:underline ${isDark ? 'text-red-400' : 'text-green-700'}`}
                                            >
                                                Chuyển sang Đăng nhập
                                            </button>
                                        </div>
                                    </form>
                                )}
                            </div>
                        </div>
                    )}
                </div>
            );
        }

        // Mount React App
        ReactDOM.createRoot(document.getElementById('root')).render(<App />);
    </script>
</body>
</html>
