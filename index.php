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
    <title>Notezy - Your Thoughts, Organized & Intelligent | AI Notepad</title>
    <meta name="description" content="Ghi chú thông minh thế hệ mới kết hợp trợ lý AI, bảo mật ghi chú bằng mã PIN 6 số và tùy chỉnh giao diện Sáng/Tối." />
    <link rel="icon" href="logo.png" type="image/png" />

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
</head>
<body class="bg-white text-gray-900 antialiased dark:bg-[#050505] dark:text-gray-100">
    <div id="root"></div>

    <script type="text/babel">
        const { useState, useEffect } = React;

        // ── Notezy Wave Logo Component ──────────────────────────────────────────
        function NotezyLogo({ className = "h-8 w-8" }) {
            return (
                <div className={`relative flex items-center justify-center rounded-xl bg-gradient-to-tr from-green-600 to-emerald-400 p-2 text-white shadow-md shadow-green-600/20 dark:from-red-600 dark:to-rose-500 dark:shadow-red-600/30 ${className}`}>
                    <i className="fas fa-layer-group text-sm"></i>
                </div>
            );
        }

        // ── Main App Component ──────────────────────────────────────────────────
        function App() {
            // Theme state: 'light' (White + Green) vs 'dark' (Black + Red)
            const [theme, setTheme] = useState(() => {
                return localStorage.getItem('notezy_theme') || 'light';
            });

            // Active Tab in App Mockup ('ai' | 'search' | 'notes')
            const [mockupTab, setMockupTab] = useState('ai');
            const [isMockupPinUnlocked, setIsMockupPinUnlocked] = useState(false);
            const [mockupPinInput, setMockupPinInput] = useState('');
            const [mockupPinError, setMockupPinError] = useState('');

            // Demo Video Simulation State
            const [demoPlaying, setDemoPlaying] = useState(false);
            const [demoProgress, setDemoProgress] = useState(0);
            const [demoStep, setDemoStep] = useState(1);

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

            // Check URL for ?registered=1
            useEffect(() => {
                const params = new URLSearchParams(window.location.search);
                if (params.get('registered') === '1') {
                    const u = params.get('username') || '';
                    setLoginForm(prev => ({ ...prev, username: u }));
                    setLoginSuccessMsg('Đăng ký tài khoản thành công! Vui lòng đăng nhập để bắt đầu.');
                    setAuthMode('login');
                    setIsAuthModalOpen(true);
                }
            }, []);

            // Apply theme to <html> root
            useEffect(() => {
                const root = document.documentElement;
                if (theme === 'dark') {
                    root.classList.add('dark');
                } else {
                    root.classList.remove('dark');
                }
                localStorage.setItem('notezy_theme', theme);
            }, [theme]);

            // Toggle theme function
            const toggleTheme = () => {
                setTheme(prev => (prev === 'dark' ? 'light' : 'dark'));
            };

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
                    setLoginForm(prev => ({ ...prev, loading: false, error: 'Không thể kết nối máy chủ. Vui lòng thử lại.' }));
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
                                : 'bg-white/90 backdrop-blur-xl border-gray-200/80 shadow-lg shadow-green-950/5'
                        }`}>
                            {/* Logo */}
                            <a href="#" className="flex items-center gap-2.5">
                                <NotezyLogo className="h-8 w-8" />
                                <span className="font-display text-lg font-bold tracking-tight text-gray-900 dark:text-white flex items-center gap-1.5">
                                    Notezy
                                    <span className={`text-[10px] uppercase tracking-widest px-1.5 py-0.5 rounded-full font-bold border ${
                                        isDark
                                            ? 'bg-red-950/60 text-red-400 border-red-900/60'
                                            : 'bg-green-100 text-green-700 border-green-200'
                                    }`}>
                                        AI
                                    </span>
                                </span>
                            </a>

                            {/* Menu links */}
                            <div className="hidden lg:flex items-center gap-8">
                                <a href="#features" className="text-xs font-semibold tracking-wide text-gray-600 hover:text-green-600 dark:text-gray-300 dark:hover:text-red-400 transition-colors">Tính năng</a>
                                <a href="#ai" className="text-xs font-semibold tracking-wide text-gray-600 hover:text-green-600 dark:text-gray-300 dark:hover:text-red-400 transition-colors">Trợ lý AI</a>
                                <a href="#security" className="text-xs font-semibold tracking-wide text-gray-600 hover:text-green-600 dark:text-red-400 transition-colors">Bảo mật 6 số</a>
                                <a href="#demo" className="text-xs font-semibold tracking-wide text-gray-600 hover:text-green-600 dark:text-gray-300 dark:hover:text-red-400 transition-colors">Xem Demo</a>
                                <a href="#pricing" className="text-xs font-semibold tracking-wide text-gray-600 hover:text-green-600 dark:text-gray-300 dark:hover:text-red-400 transition-colors">Bảng giá</a>
                            </div>

                            {/* Right actions: Theme Toggle + Login + Start */}
                            <div className="flex items-center gap-1 sm:gap-3">
                                {/* Button 2 chế độ: Sáng (Trắng - Xanh) & Tối (Đen - Đỏ) */}
                                <button
                                    type="button"
                                    onClick={toggleTheme}
                                    title={isDark ? 'Chế độ Tối: Đen & Đỏ (Bấm để đổi sang Sáng: Trắng & Xanh)' : 'Chế độ Sáng: Trắng & Xanh (Bấm để đổi sang Tối: Đen & Đỏ)'}
                                    className={`group flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-bold transition-all duration-300 ${
                                        isDark
                                            ? 'border-red-600/40 bg-red-950/40 text-red-400 hover:border-red-500 hover:bg-red-900/50'
                                            : 'border-green-600/30 bg-green-50 text-green-700 hover:border-green-500 hover:bg-green-100'
                                    }`}
                                >
                                    <i className={`fas ${isDark ? 'fa-moon text-red-500' : 'fa-sun text-green-600'}`}></i>
                                    <span className="hidden sm:inline">
                                        {isDark ? 'Tối (Đen & Đỏ)' : 'Sáng (Trắng & Xanh)'}
                                    </span>
                                    <span className={`inline-block h-2 w-2 rounded-full animate-pulse ${
                                        isDark ? 'bg-red-500 shadow-[0_0_8px_rgba(239,68,68,0.8)]' : 'bg-green-500 shadow-[0_0_8px_rgba(34,197,94,0.8)]'
                                    }`}></span>
                                </button>

                                {/* Đăng nhập */}
                                <button
                                    type="button"
                                    onClick={() => { setAuthMode('login'); setIsAuthModalOpen(true); }}
                                    className="rounded-full px-2 sm:px-4 py-2 text-xs font-bold text-gray-700 hover:bg-gray-100 hover:text-green-700 dark:text-gray-200 dark:hover:bg-neutral-900 dark:hover:text-red-400 transition-all"
                                >
                                    Đăng nhập
                                </button>

                                {/* Bắt đầu miễn phí (mở tab Đăng ký trong modal) */}
                                <button
                                    type="button"
                                    onClick={() => { setAuthMode('register'); setIsAuthModalOpen(true); }}
                                    className={`hidden min-[360px]:block rounded-full px-3 sm:px-5 py-2 text-xs font-bold text-white shadow-md transition-all hover:scale-105 active:scale-95 ${
                                        isDark
                                            ? 'bg-red-600 hover:bg-red-500 shadow-red-600/30'
                                            : 'bg-green-600 hover:bg-green-700 shadow-green-600/25'
                                    }`}
                                >
                                    Bắt đầu
                                </button>
                            </div>
                        </nav>
                    </header>

                    {/* ── 2. HERO SECTION (YOUR THOUGHTS, ORGANIZED & INTELLIGENT) ── */}
                    <section className="relative overflow-hidden pt-14 pb-20 sm:pt-20 sm:pb-28">
                        {/* Glow Background */}
                        <div
                            aria-hidden="true"
                            className={`pointer-events-none absolute inset-x-0 -top-32 -z-10 h-[640px] transition-all duration-700 ${
                                isDark
                                    ? 'bg-[radial-gradient(ellipse_75%_65%_at_50%_0%,rgba(220,38,38,0.22),transparent)]'
                                    : 'bg-[radial-gradient(ellipse_75%_65%_at_50%_0%,rgba(16,185,129,0.18),transparent)]'
                            }`}
                        />

                        <div className="mx-auto max-w-4xl px-5 text-center sm:px-8">
                            {/* Pill Badge */}
                            <div className={`inline-flex items-center gap-2 rounded-full border px-4 py-1.5 text-xs font-semibold shadow-sm transition-all hover:scale-105 ${
                                isDark
                                    ? 'border-red-900/60 bg-red-950/40 text-red-300'
                                    : 'border-green-300/80 bg-green-50/90 text-green-800'
                            }`}>
                                <span className={`rounded-full px-2 py-0.5 text-[10px] font-bold text-white uppercase tracking-wider ${
                                    isDark ? 'bg-red-600' : 'bg-green-600'
                                }`}>
                                    Mới
                                </span>
                                <span>Trợ lý AI Tóm tắt • Bảo mật sổ mã 6 số</span>
                                <span className={isDark ? 'text-red-400' : 'text-green-600'}>→</span>
                            </div>

                            {/* Headline */}
                            <h1 className="mt-8 font-display text-4xl sm:text-6xl md:text-7xl font-extrabold tracking-tight uppercase leading-[1.08] text-gray-950 dark:text-white">
                                YOUR THOUGHTS,
                                <br />
                                <span className={`bg-clip-text text-transparent bg-gradient-to-r ${
                                    isDark
                                        ? 'from-red-500 via-rose-500 to-orange-500'
                                        : 'from-green-600 via-emerald-500 to-teal-600'
                                }`}>
                                    ORGANIZED & INTELLIGENT.
                                </span>
                            </h1>

                            {/* Subtitle */}
                            <p className="mx-auto mt-6 max-w-2xl text-base sm:text-xl leading-relaxed text-gray-600 dark:text-gray-300 font-normal">
                                Capture your ideas. Let AI organize the rest.
                                <br className="hidden sm:inline" />
                                {' '}Sổ ghi chép thông minh thế hệ mới, tích hợp khóa bảo vệ riêng tư 6 số và trợ lý AI tóm tắt tức thì.
                            </p>

                            {/* CTA Buttons */}
                            <div className="mt-9 flex flex-col sm:flex-row items-center justify-center gap-4">
                                <button
                                    type="button"
                                    onClick={() => { setAuthMode('register'); setIsAuthModalOpen(true); }}
                                    className={`w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-full px-8 py-3.5 text-sm font-bold text-white shadow-xl transition-all hover:scale-105 active:scale-95 ${
                                        isDark
                                            ? 'bg-red-600 hover:bg-red-500 shadow-red-600/30'
                                            : 'bg-green-600 hover:bg-green-700 shadow-green-600/30'
                                    }`}
                                >
                                    <span>Bắt đầu miễn phí</span>
                                    <i className="fas fa-arrow-right text-xs"></i>
                                </button>

                                <a
                                    href="#demo"
                                    className={`w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-full border px-7 py-3.5 text-sm font-bold shadow-sm transition-all ${
                                        isDark
                                            ? 'border-white/15 bg-neutral-900/60 text-gray-200 hover:border-red-500 hover:text-red-400 hover:bg-red-950/30'
                                            : 'border-gray-300 bg-white/80 text-gray-800 hover:border-green-600 hover:text-green-700 hover:bg-green-50/50'
                                    }`}
                                >
                                    <i className={`fas fa-play text-xs ${isDark ? 'text-red-500' : 'text-green-600'}`}></i>
                                    <span>Xem demo (30s)</span>
                                </a>
                            </div>

                            <p className="mt-4 text-xs font-medium text-gray-400 dark:text-gray-500">
                                ✦ Miễn phí trải nghiệm · Không cần thẻ tín dụng · Dữ liệu lưu trữ an toàn
                            </p>
                        </div>

                        {/* ── 3. NOTEZY APP UI MOCKUP (3 TABS) ── */}
                        <div className="mx-auto mt-14 max-w-5xl px-4 sm:px-8">
                            <div className="relative mx-auto w-full">
                                {/* Tab selector buttons matching user mockup */}
                                <div className="mb-4 flex flex-wrap items-center justify-center gap-2">
                                    <button
                                        type="button"
                                        onClick={() => setMockupTab('ai')}
                                        className={`flex items-center gap-2 rounded-full px-5 py-2 text-xs font-bold transition-all ${
                                            mockupTab === 'ai'
                                                ? (isDark ? 'bg-red-600 text-white shadow-lg shadow-red-600/30 scale-105' : 'bg-green-600 text-white shadow-lg shadow-green-600/30 scale-105')
                                                : (isDark ? 'bg-neutral-900 text-gray-300 border border-white/10 hover:bg-neutral-800' : 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-100')
                                        }`}
                                    >
                                        <i className="fas fa-wand-magic-sparkles"></i>
                                        <span>✦ AI summarizes</span>
                                    </button>

                                    <button
                                        type="button"
                                        onClick={() => setMockupTab('search')}
                                        className={`flex items-center gap-2 rounded-full px-5 py-2 text-xs font-bold transition-all ${
                                            mockupTab === 'search'
                                                ? (isDark ? 'bg-red-600 text-white shadow-lg shadow-red-600/30 scale-105' : 'bg-green-600 text-white shadow-lg shadow-green-600/30 scale-105')
                                                : (isDark ? 'bg-neutral-900 text-gray-300 border border-white/10 hover:bg-neutral-800' : 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-100')
                                        }`}
                                    >
                                        <i className="fas fa-search"></i>
                                        <span>🔍 Smart search</span>
                                    </button>

                                    <button
                                        type="button"
                                        onClick={() => setMockupTab('notes')}
                                        className={`flex items-center gap-2 rounded-full px-5 py-2 text-xs font-bold transition-all ${
                                            mockupTab === 'notes'
                                                ? (isDark ? 'bg-red-600 text-white shadow-lg shadow-red-600/30 scale-105' : 'bg-green-600 text-white shadow-lg shadow-green-600/30 scale-105')
                                                : (isDark ? 'bg-neutral-900 text-gray-300 border border-white/10 hover:bg-neutral-800' : 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-100')
                                        }`}
                                    >
                                        <i className="fas fa-lock"></i>
                                        <span>📝 Quick notes & Khóa 6 số</span>
                                    </button>
                                </div>

                                {/* Window container */}
                                <div className={`overflow-hidden rounded-3xl border shadow-2xl transition-all ${
                                    isDark
                                        ? 'bg-neutral-950 border-red-900/30 shadow-red-950/40'
                                        : 'bg-white border-gray-200 shadow-xl shadow-green-950/10'
                                }`}>
                                    {/* Window titlebar */}
                                    <div className={`flex items-center justify-between border-b px-4 py-3 ${
                                        isDark ? 'bg-neutral-900 border-white/10' : 'bg-gray-50 border-gray-100'
                                    }`}>
                                        <div className="flex items-center gap-2">
                                            <span className="h-3 w-3 rounded-full bg-red-500"></span>
                                            <span className="h-3 w-3 rounded-full bg-amber-400"></span>
                                            <span className="h-3 w-3 rounded-full bg-green-500"></span>
                                            <span className="ml-3 text-xs font-semibold text-gray-500">
                                                Notezy Workspace Preview
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-2 text-xs text-gray-400">
                                            <span className="h-2 w-2 rounded-full bg-green-500 animate-ping"></span>
                                            <span>Sẵn sàng kết nối</span>
                                        </div>
                                    </div>

                                    {/* Window Body: Interactive Demonstration */}
                                    <div className="p-5 sm:p-7 text-left">
                                        {/* TAB 1: AI SUMMARIZES */}
                                        {mockupTab === 'ai' && (
                                            <div className="grid grid-cols-1 md:grid-cols-2 gap-5 animate-fade-in">
                                                <div className={`p-4 rounded-2xl border ${
                                                    isDark ? 'bg-neutral-900 border-white/10' : 'bg-gray-50 border-gray-200'
                                                }`}>
                                                    <div className="flex items-center justify-between mb-2">
                                                        <span className="text-xs font-bold text-gray-500 uppercase">
                                                            📝 Ghi chú cuộc họp thảo luận
                                                        </span>
                                                        <span className="text-[10px] text-gray-400">Hôm nay 10:00</span>
                                                    </div>
                                                    <h4 className="font-bold text-sm text-gray-900 dark:text-white mb-2">
                                                        Kế hoạch ra mắt tính năng bảo mật Notezy
                                                    </h4>
                                                    <p className="text-xs leading-relaxed text-gray-600 dark:text-gray-300">
                                                        Nhóm phát triển đã bổ sung mã PIN 6 số bảo vệ từng ghi chú, tích hợp trợ lý AI thông minh tóm tắt nội dung và xây dựng 2 chế độ màu Sáng/Tối. Toàn bộ hệ thống chạy mượt mà trên môi trường thực tế...
                                                    </p>
                                                </div>

                                                <div className={`p-4 rounded-2xl border ${
                                                    isDark
                                                        ? 'bg-red-950/25 border-red-900/50'
                                                        : 'bg-green-50/70 border-green-300/80'
                                                }`}>
                                                    <div className="flex items-center justify-between mb-2">
                                                        <span className={`text-xs font-bold flex items-center gap-1.5 ${
                                                            isDark ? 'text-red-400' : 'text-green-700'
                                                        }`}>
                                                            <i className="fas fa-sparkles"></i>
                                                            <span>✦ AI Tóm Tắt Tự Động</span>
                                                        </span>
                                                        <span className={`text-[10px] font-bold px-2 py-0.5 rounded-full ${
                                                            isDark ? 'bg-red-900/60 text-red-200' : 'bg-green-200/80 text-green-800'
                                                        }`}>
                                                            0.3s hoàn thành
                                                        </span>
                                                    </div>

                                                    <div className="space-y-2 mt-3 text-xs text-gray-800 dark:text-gray-200">
                                                        <div className="flex items-start gap-2">
                                                            <i className={`fas fa-check-circle mt-0.5 ${isDark ? 'text-red-500' : 'text-green-600'}`}></i>
                                                            <span><strong>Khóa 6 số (PIN):</strong> Bảo vệ ghi chú độc lập, chỉ xem khi nhập đúng mã.</span>
                                                        </div>
                                                        <div className="flex items-start gap-2">
                                                            <i className={`fas fa-check-circle mt-0.5 ${isDark ? 'text-red-500' : 'text-green-600'}`}></i>
                                                            <span><strong>Chế độ 2 màu:</strong> Trắng & Xanh lá (Sáng) | Đen & Đỏ (Tối).</span>
                                                        </div>
                                                        <div className="flex items-start gap-2">
                                                            <i className={`fas fa-check-circle mt-0.5 ${isDark ? 'text-red-500' : 'text-green-600'}`}></i>
                                                            <span><strong>Hẹn giờ sự kiện:</strong> Chuông báo thức nhắc nhở đúng giờ cho từng sổ.</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        )}

                                        {/* TAB 2: SMART SEARCH */}
                                        {mockupTab === 'search' && (
                                            <div className="animate-fade-in">
                                                <div className={`flex items-center gap-3 mb-4 rounded-xl border px-4 py-2.5 ${
                                                    isDark ? 'bg-neutral-900 border-white/10' : 'bg-gray-50 border-gray-200'
                                                }`}>
                                                    <i className={`fas fa-search ${isDark ? 'text-red-500' : 'text-green-600'}`}></i>
                                                    <input
                                                        type="text"
                                                        value="bảo mật"
                                                        readOnly
                                                        className="bg-transparent text-sm font-semibold w-full text-gray-900 dark:text-white focus:outline-none"
                                                    />
                                                    <span className="text-[10px] text-gray-400 bg-gray-200 dark:bg-neutral-800 px-2 py-0.5 rounded">
                                                        Tìm theo nhãn & nội dung
                                                    </span>
                                                </div>

                                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                                                    <div className={`p-3.5 rounded-xl border ${
                                                        isDark ? 'bg-neutral-900 border-red-900/40' : 'bg-green-50/40 border-green-300'
                                                    }`}>
                                                        <div className="flex items-center justify-between mb-1">
                                                            <h5 className="text-xs font-bold text-gray-900 dark:text-white">
                                                                Sổ ghi chú <span className="bg-yellow-200 text-yellow-900 px-1 rounded">bảo mật</span> 6 số
                                                            </h5>
                                                            <span className={`text-[10px] font-bold ${isDark ? 'text-red-400' : 'text-green-700'}`}>
                                                                Đã ghim
                                                            </span>
                                                        </div>
                                                        <p className="text-xs text-gray-600 dark:text-gray-400">
                                                            Thiết lập mã bảo vệ riêng tư cho nhật ký công việc.
                                                        </p>
                                                        <div className="flex gap-1.5 mt-2">
                                                            <span className="text-[9px] bg-green-200/80 text-green-800 px-2 py-0.5 rounded dark:bg-red-950 dark:text-red-300 font-bold">Bảo mật</span>
                                                            <span className="text-[9px] bg-gray-200 text-gray-700 px-2 py-0.5 rounded dark:bg-neutral-800 dark:text-gray-300 font-bold">Cá nhân</span>
                                                        </div>
                                                    </div>

                                                    <div className={`p-3.5 rounded-xl border ${
                                                        isDark ? 'bg-neutral-900 border-white/10' : 'bg-gray-50 border-gray-200'
                                                    }`}>
                                                        <div className="flex items-center justify-between mb-1">
                                                            <h5 className="text-xs font-bold text-gray-900 dark:text-white">
                                                                Nguyên tắc <span className="bg-yellow-200 text-yellow-900 px-1 rounded">bảo mật</span> dữ liệu
                                                            </h5>
                                                            <span className="text-[10px] text-gray-400 font-bold">Quan trọng</span>
                                                        </div>
                                                        <p className="text-xs text-gray-600 dark:text-gray-400">
                                                            Mã hóa session và xác thực an toàn trên mọi thiết bị.
                                                        </p>
                                                        <div className="flex gap-1.5 mt-2">
                                                            <span className="text-[9px] bg-blue-100 text-blue-800 px-2 py-0.5 rounded dark:bg-blue-950 dark:text-blue-300 font-bold">Kỹ thuật</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        )}

                                        {/* TAB 3: QUICK NOTES & KHÓA 4 SỐ */}
                                        {mockupTab === 'notes' && (
                                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-5 items-center animate-fade-in">
                                                {/* Card mô phỏng nhập mã */}
                                                <div className={`p-5 rounded-2xl border text-center ${
                                                    isDark ? 'bg-neutral-900 border-red-900/50' : 'bg-amber-50/50 border-amber-300'
                                                }`}>
                                                    <div className={`mx-auto mb-2 flex h-11 w-11 items-center justify-center rounded-full ${
                                                        isDark ? 'bg-red-950 text-red-400' : 'bg-amber-200 text-amber-800'
                                                    }`}>
                                                        <i className={`fas ${isMockupPinUnlocked ? 'fa-lock-open text-base' : 'fa-lock text-base'}`}></i>
                                                    </div>
                                                    <h5 className="text-sm font-bold text-gray-900 dark:text-white">
                                                        {isMockupPinUnlocked ? 'Sổ đã được mở khóa thành công!' : 'Sổ ghi chú đã khóa bằng mã 6 số'}
                                                    </h5>
                                                    <p className="text-xs text-gray-500 mt-1 mb-3">
                                                        {isMockupPinUnlocked
                                                            ? 'Nội dung và sự kiện đã hiển thị đầy đủ.'
                                                            : 'Nhập đúng mã 6 số (Ví dụ: 202606) để mở sổ:'}
                                                    </p>

                                                    {!isMockupPinUnlocked ? (
                                                        <form onSubmit={handleMockupUnlock} className="flex flex-col items-center gap-2">
                                                            <input
                                                                type="password"
                                                                maxLength="6"
                                                                value={mockupPinInput}
                                                                onChange={(e) => setMockupPinInput(e.target.value)}
                                                                placeholder="•••••• (Nhập 202606)"
                                                                className={`w-36 text-center text-lg font-bold tracking-widest px-3 py-1.5 rounded-lg border focus:outline-none ${
                                                                    isDark
                                                                        ? 'bg-black border-red-800 text-white focus:border-red-500'
                                                                        : 'bg-white border-amber-400 text-gray-900 focus:border-green-600'
                                                                }`}
                                                            />
                                                            {mockupPinError && (
                                                                <span className="text-[11px] text-red-500 font-bold">{mockupPinError}</span>
                                                            )}
                                                            <button
                                                                type="submit"
                                                                className={`mt-1 rounded-full px-5 py-1.5 text-xs font-bold text-white shadow-md transition-all ${
                                                                    isDark ? 'bg-red-600 hover:bg-red-500' : 'bg-green-600 hover:bg-green-700'
                                                                }`}
                                                            >
                                                                Mở khóa sổ ngay
                                                            </button>
                                                        </form>
                                                    ) : (
                                                        <button
                                                            type="button"
                                                            onClick={() => { setIsMockupPinUnlocked(false); setMockupPinInput(''); }}
                                                            className="rounded-full bg-gray-700 hover:bg-gray-800 px-4 py-1.5 text-xs font-bold text-white"
                                                        >
                                                            Khóa lại sổ
                                                        </button>
                                                    )}
                                                </div>

                                                {/* Card nội dung sau khi mở hoặc bị khóa */}
                                                <div className={`p-5 rounded-2xl border shadow-sm transition-all ${
                                                    isDark ? 'bg-neutral-900 border-white/10' : 'bg-white border-gray-200'
                                                }`}>
                                                    <div className="flex items-center justify-between mb-2">
                                                        <span className="text-xs font-bold text-gray-900 dark:text-white">
                                                            Nhật ký chiến lược 2026
                                                        </span>
                                                        <span className="flex items-center gap-1 text-[10px] font-bold text-amber-800 bg-amber-100 px-2 py-0.5 rounded-full dark:bg-amber-950 dark:text-amber-300">
                                                            <i className="fas fa-bell"></i> Sự kiện: 09:30 15/09
                                                        </span>
                                                    </div>

                                                    {isMockupPinUnlocked ? (
                                                        <div className={`text-xs space-y-1.5 p-3 rounded-lg border ${
                                                            isDark ? 'bg-red-950/20 border-red-900/40 text-gray-200' : 'bg-green-50 border-green-200 text-gray-800'
                                                        }`}>
                                                            <p>✅ <strong>Nội dung bí mật:</strong> Kế hoạch nộp đồ án và bảo vệ dự án Notezy.</p>
                                                            <p>🎯 Đã hẹn giờ nhắc nhở báo thức sự kiện chính xác.</p>
                                                            <p className={`text-[11px] font-bold mt-1 ${isDark ? 'text-red-400' : 'text-green-700'}`}>
                                                                → Bạn có thể chỉnh sửa nội dung và tùy chỉnh sự kiện!
                                                            </p>
                                                        </div>
                                                    ) : (
                                                        <div className="flex flex-col items-center justify-center p-6 bg-gray-100 dark:bg-neutral-800/60 rounded-lg text-center">
                                                            <i className="fas fa-shield-alt text-2xl text-gray-400 mb-2"></i>
                                                            <span className="text-xs font-bold text-gray-500">
                                                                Nội dung và sự kiện bị khóa bảo mật
                                                            </span>
                                                            <span className="text-[10px] text-gray-400 mt-1">
                                                                Nhập mã 202606 ở bên trái để mở sổ
                                                            </span>
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    {/* ── 4. FEATURES SECTION ("Everything you need to capture and understand ideas") ── */}
                    <section id="features" className={`py-20 px-5 sm:px-8 transition-colors duration-300 ${
                        isDark ? 'bg-neutral-950/50' : 'bg-gray-50/50'
                    }`}>
                        <div className="mx-auto max-w-6xl">
                            <div className="text-center max-w-3xl mx-auto mb-14">
                                <span className={`text-xs font-bold uppercase tracking-widest px-3.5 py-1 rounded-full border ${
                                    isDark
                                        ? 'bg-red-950/60 text-red-400 border-red-900/60'
                                        : 'bg-green-100 text-green-700 border-green-200'
                                }`}>
                                    Hệ sinh thái thông minh
                                </span>
                                <h2 className="mt-4 font-display text-3xl sm:text-4xl md:text-5xl font-extrabold tracking-tight text-gray-950 dark:text-white uppercase">
                                    Everything you need to capture
                                    <br />
                                    <span className={`bg-clip-text text-transparent bg-gradient-to-r ${
                                        isDark ? 'from-red-500 to-rose-500' : 'from-green-600 to-emerald-500'
                                    }`}>
                                        and understand ideas
                                    </span>
                                </h2>
                                <p className="mt-4 text-base text-gray-600 dark:text-gray-400">
                                    Mọi công cụ ghi chép, trợ lý AI tóm tắt, tìm kiếm tức thì và bảo mật 6 số riêng tư.
                                </p>
                            </div>

                            {/* Cards Grid */}
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                {/* Card 1 */}
                                <div className={`rounded-3xl p-6 border shadow-sm transition-all hover:-translate-y-1.5 ${
                                    isDark ? 'bg-neutral-900 border-white/10' : 'bg-white border-gray-200'
                                }`}>
                                    <div className={`flex h-12 w-12 items-center justify-center rounded-2xl mb-4 ${
                                        isDark ? 'bg-neutral-800 text-gray-300' : 'bg-gray-100 text-gray-700'
                                    }`}>
                                        <i className="fas fa-pencil-alt text-lg"></i>
                                    </div>
                                    <h3 className="font-bold text-lg text-gray-900 dark:text-white mb-2">
                                        📝 Ghi chú nhanh & Soạn thảo
                                    </h3>
                                    <p className="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                                        Đổi màu nền, màu chữ, phông chữ linh hoạt, chèn ảnh đính kèm và ghim sổ quan trọng lên đầu trang.
                                    </p>
                                </div>

                                {/* Card 2: AI */}
                                <div className={`rounded-3xl p-6 border shadow-md transition-all hover:-translate-y-1.5 ${
                                    isDark
                                        ? 'bg-neutral-900 border-red-900/50 shadow-red-950/20'
                                        : 'bg-white border-green-300 shadow-green-950/5'
                                }`}>
                                    <div className={`flex h-12 w-12 items-center justify-center rounded-2xl mb-4 ${
                                        isDark ? 'bg-red-950 text-red-400' : 'bg-green-100 text-green-700'
                                    }`}>
                                        <i className="fas fa-sparkles text-lg"></i>
                                    </div>
                                    <h3 className="font-bold text-lg text-gray-900 dark:text-white mb-2">
                                        ✦ AI Copilot Thông Minh
                                    </h3>
                                    <p className="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                                        Tóm tắt biên bản họp dài, phân tích đầu việc cần làm, viết lại nội dung và mở rộng ý tưởng nhanh chóng.
                                    </p>
                                </div>

                                {/* Card 3: Search */}
                                <div className={`rounded-3xl p-6 border shadow-sm transition-all hover:-translate-y-1.5 ${
                                    isDark ? 'bg-neutral-900 border-white/10' : 'bg-white border-gray-200'
                                }`}>
                                    <div className={`flex h-12 w-12 items-center justify-center rounded-2xl mb-4 ${
                                        isDark ? 'bg-neutral-800 text-gray-300' : 'bg-gray-100 text-gray-700'
                                    }`}>
                                        <i className="fas fa-search text-lg"></i>
                                    </div>
                                    <h3 className="font-bold text-lg text-gray-900 dark:text-white mb-2">
                                        🔍 Tìm kiếm & Gắn nhãn
                                    </h3>
                                    <p className="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                                        Tìm kiếm toàn văn theo tiêu đề, nội dung và phân loại nhanh theo các nhãn Học tập, Công việc, Dự án.
                                    </p>
                                </div>

                                {/* Card 4: PIN Security */}
                                <div className={`rounded-3xl p-6 border-2 transition-all hover:-translate-y-1.5 ${
                                    isDark
                                        ? 'bg-gradient-to-b from-red-950/40 to-neutral-900 border-red-600/60 shadow-xl shadow-red-950/30'
                                        : 'bg-gradient-to-b from-amber-50/40 to-white border-amber-400 shadow-xl shadow-amber-500/10'
                                }`}>
                                    <div className={`flex h-12 w-12 items-center justify-center rounded-2xl mb-4 ${
                                        isDark ? 'bg-red-900/60 text-red-300' : 'bg-amber-100 text-amber-800'
                                    }`}>
                                        <i className="fas fa-shield-halved text-lg"></i>
                                    </div>
                                    <h3 className="font-bold text-lg text-gray-900 dark:text-white mb-2">
                                        🔒 Bảo mật sổ với mã 6 số
                                    </h3>
                                    <p className="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                                        Đặt mã bảo mật 6 số độc lập cho từng ghi chú. Bìa khóa che kín nội dung, chỉ mở khi nhập đúng mã 6 số.
                                    </p>
                                </div>

                                {/* Card 5: Alarm & Reminder */}
                                <div className={`rounded-3xl p-6 border shadow-sm transition-all hover:-translate-y-1.5 ${
                                    isDark ? 'bg-neutral-900 border-white/10' : 'bg-white border-gray-200'
                                }`}>
                                    <div className={`flex h-12 w-12 items-center justify-center rounded-2xl mb-4 ${
                                        isDark ? 'bg-neutral-800 text-gray-300' : 'bg-gray-100 text-gray-700'
                                    }`}>
                                        <i className="fas fa-bell text-lg"></i>
                                    </div>
                                    <h3 className="font-bold text-lg text-gray-900 dark:text-white mb-2">
                                        ⏰ Báo thức & Hẹn giờ sự kiện
                                    </h3>
                                    <p className="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                                        Thiết lập chuông báo thức sự kiện cho từng sổ ghi chú, hiển thị huy hiệu thời gian rõ ràng trên trang chủ.
                                    </p>
                                </div>

                                {/* Card 6: Timetable */}
                                <div className={`rounded-3xl p-6 border shadow-sm transition-all hover:-translate-y-1.5 ${
                                    isDark ? 'bg-neutral-900 border-white/10' : 'bg-white border-gray-200'
                                }`}>
                                    <div className={`flex h-12 w-12 items-center justify-center rounded-2xl mb-4 ${
                                        isDark ? 'bg-neutral-800 text-gray-300' : 'bg-gray-100 text-gray-700'
                                    }`}>
                                        <i className="fas fa-calendar-alt text-lg"></i>
                                    </div>
                                    <h3 className="font-bold text-lg text-gray-900 dark:text-white mb-2">
                                        📅 Thời khóa biểu & Lịch trình
                                    </h3>
                                    <p className="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                                        Sắp xếp lịch học, lịch họp theo ngày trong tuần, kết hợp chuông báo thức âm thanh tự động nhắc nhở.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>

                    {/* ── 5. VIDEO DEMO SECTION ("See Notezy in action" - 30-60s) ── */}
                    <section id="demo" className="py-20 sm:py-28 px-5 sm:px-8">
                        <div className="mx-auto max-w-5xl text-center">
                            <span className={`text-xs font-bold uppercase tracking-widest px-3.5 py-1 rounded-full border ${
                                isDark
                                    ? 'bg-red-950/60 text-red-400 border-red-900/60'
                                    : 'bg-green-100 text-green-700 border-green-200'
                            }`}>
                                Video Demo 30–60 Giây
                            </span>
                            <h2 className="mt-4 font-display text-3xl sm:text-4xl md:text-5xl font-extrabold tracking-tight text-gray-950 dark:text-white uppercase">
                                See Notezy in action
                            </h2>
                            <p className="mx-auto mt-3 max-w-xl text-sm sm:text-base text-gray-600 dark:text-gray-400">
                                Trải nghiệm cách ghi chú, cài đặt mã bảo vệ 6 số và nhờ trợ lý AI tóm tắt chỉ trong 45 giây.
                            </p>

                            {/* Video Player Box */}
                            <div className={`relative mx-auto mt-10 aspect-video max-w-3xl overflow-hidden rounded-3xl border shadow-2xl transition-all ${
                                isDark ? 'border-red-900/40 bg-neutral-950 shadow-red-950/40' : 'border-gray-200 bg-neutral-950 shadow-xl'
                            }`}>
                                {!demoPlaying && demoProgress === 0 ? (
                                    <button
                                        type="button"
                                        onClick={() => setDemoPlaying(true)}
                                        className={`group absolute inset-0 flex flex-col items-center justify-center gap-4 transition-all ${
                                            isDark
                                                ? 'bg-gradient-to-tr from-red-950/90 via-black to-neutral-950'
                                                : 'bg-gradient-to-tr from-green-700/90 via-emerald-950 to-neutral-950'
                                        }`}
                                    >
                                        <span className={`flex h-20 w-20 items-center justify-center rounded-full bg-white shadow-2xl transition-transform duration-300 group-hover:scale-110 ${
                                            isDark ? 'text-red-600' : 'text-green-700'
                                        }`}>
                                            <i className="fas fa-play text-2xl ml-1"></i>
                                        </span>
                                        <div className="text-center">
                                            <span className="block text-base font-bold text-white">
                                                ▶ Phát Video Demo Notezy (45 giây)
                                            </span>
                                            <span className="text-xs text-white/80">
                                                Bấm để xem mô phỏng hoạt động trực quan
                                            </span>
                                        </div>
                                    </button>
                                ) : (
                                    <div className="absolute inset-0 flex flex-col justify-between p-6 text-left text-white bg-neutral-900/95">
                                        {/* Status header */}
                                        <div className="flex items-center justify-between border-b border-white/10 pb-3">
                                            <div className="flex items-center gap-2">
                                                <span className="h-2.5 w-2.5 rounded-full bg-red-500 animate-pulse"></span>
                                                <span className="text-xs font-bold uppercase tracking-wider text-gray-300">
                                                    {demoStep === 1 && 'Bước 1/3: Tạo sổ ghi chú & Gắn nhãn màu'}
                                                    {demoStep === 2 && 'Bước 2/3: Khóa bảo mật ghi chú với mã 6 số bí mật'}
                                                    {demoStep === 3 && 'Bước 3/3: Trợ lý AI tóm tắt & lập danh sách việc'}
                                                </span>
                                            </div>
                                            <span className="text-xs font-mono text-gray-400">
                                                {Math.round((demoProgress / 100) * 45)}s / 45s
                                            </span>
                                        </div>

                                        {/* Demo stage */}
                                        <div className="my-auto max-w-lg mx-auto w-full">
                                            {demoStep === 1 && (
                                                <div className="bg-neutral-800 p-5 rounded-2xl border border-white/10 animate-fade-in shadow-xl">
                                                    <div className="flex items-center justify-between mb-2">
                                                        <span className="text-xs font-bold text-green-400 dark:text-red-400">
                                                            📝 Sổ ghi chép mới
                                                        </span>
                                                        <span className="text-[10px] bg-green-900/60 text-green-300 px-2 py-0.5 rounded-full font-bold">
                                                            Lưu trang chủ
                                                        </span>
                                                    </div>
                                                    <h4 className="font-bold text-sm text-white">Kế hoạch tuần và đồ án tốt nghiệp</h4>
                                                    <p className="text-xs text-gray-300 mt-2 leading-relaxed">
                                                        "Ghi chú chi tiết các mốc thời gian hoàn thành, phân chia công việc và chuẩn bị tài liệu báo cáo..."
                                                    </p>
                                                </div>
                                            )}

                                            {demoStep === 2 && (
                                                <div className="bg-neutral-800 p-5 rounded-2xl border border-amber-500/50 animate-fade-in shadow-xl text-center">
                                                    <div className="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-amber-500/20 text-amber-400">
                                                        <i className="fas fa-lock"></i>
                                                    </div>
                                                    <h4 className="font-bold text-sm text-white">Kích hoạt mã bảo mật 6 số</h4>
                                                    <div className="my-3 flex justify-center gap-2">
                                                        {['2', '0', '2', '6', '0', '6'].map((n, i) => (
                                                            <span key={i} className="h-9 w-9 rounded-lg bg-black border border-white/20 flex items-center justify-center font-bold text-lg text-green-400 dark:text-red-400">
                                                                {n}
                                                            </span>
                                                        ))}
                                                    </div>
                                                    <p className="text-xs text-gray-400">
                                                        Bìa khóa che phủ nội dung. Nhập đúng 6 số để mở ghi chú xem nội dung và tùy chỉnh!
                                                    </p>
                                                </div>
                                            )}

                                            {demoStep === 3 && (
                                                <div className="bg-neutral-800 p-5 rounded-2xl border border-green-500/50 dark:border-red-500/50 animate-fade-in shadow-xl">
                                                    <div className="flex items-center gap-2 text-xs font-bold text-green-400 dark:text-red-400 mb-2">
                                                        <i className="fas fa-sparkles"></i>
                                                        <span>AI đã phân tích & trích xuất 3 đầu việc</span>
                                                    </div>
                                                    <div className="space-y-1.5 text-xs text-gray-200">
                                                        <div className="flex items-center gap-2">
                                                            <i className="fas fa-check text-green-500 dark:text-red-500"></i>
                                                            <span>Kiểm tra giao diện Sáng & Tối theo yêu cầu</span>
                                                        </div>
                                                        <div className="flex items-center gap-2">
                                                            <i className="fas fa-check text-green-500 dark:text-red-500"></i>
                                                            <span>Kích hoạt nút Bảo mật 6 số trên toàn bộ ghi chú</span>
                                                        </div>
                                                        <div className="flex items-center gap-2">
                                                            <i className="fas fa-check text-green-500 dark:text-red-500"></i>
                                                            <span>Sẵn sàng đưa vào sử dụng ngay</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            )}
                                        </div>

                                        {/* Bottom timeline controls */}
                                        <div className="pt-3 border-t border-white/10">
                                            <div className="w-full bg-white/15 h-1.5 rounded-full overflow-hidden mb-2">
                                                <div
                                                    className={`h-full transition-all duration-300 ${isDark ? 'bg-red-500' : 'bg-green-500'}`}
                                                    style={{ width: `${demoProgress}%` }}
                                                ></div>
                                            </div>
                                            <div className="flex items-center justify-between text-xs text-gray-400">
                                                <div className="flex items-center gap-3">
                                                    <button
                                                        type="button"
                                                        onClick={() => setDemoPlaying(!demoPlaying)}
                                                        className="hover:text-white"
                                                    >
                                                        <i className={`fas ${demoPlaying ? 'fa-pause' : 'fa-play'}`}></i>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={restartDemo}
                                                        className="hover:text-white flex items-center gap-1"
                                                    >
                                                        <i className="fas fa-rotate-right"></i>
                                                        <span>Phát lại</span>
                                                    </button>
                                                </div>
                                                <span>{demoProgress >= 100 ? 'Hoàn thành!' : 'Đang phát mô phỏng...'}</span>
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    </section>

                    {/* ── 6. CTA SECTION ([ Start using Notezy ]) ── */}
                    <section className="px-5 pb-20 sm:px-8 sm:pb-28">
                        <div className={`mx-auto max-w-5xl overflow-hidden rounded-3xl px-8 py-14 text-center shadow-2xl transition-all ${
                            isDark
                                ? 'bg-gradient-to-tr from-red-950 via-neutral-950 to-black border border-red-900/40 shadow-red-950/30'
                                : 'bg-gradient-to-tr from-green-600 via-emerald-700 to-teal-800 shadow-xl shadow-green-950/15'
                        }`}>
                            <span className="inline-flex items-center gap-1.5 rounded-full bg-white/20 px-3.5 py-1 text-xs font-bold text-white uppercase tracking-wider">
                                Trải nghiệm ngay hôm nay
                            </span>
                            <h2 className="mx-auto mt-4 max-w-lg font-display text-3xl sm:text-4xl font-bold text-white tracking-tight">
                                Sẵn sàng biến ý tưởng thành hành động?
                            </h2>
                            <p className="mx-auto mt-3 max-w-md text-sm sm:text-base text-white/80">
                                Tạo tài khoản miễn phí và khám phá toàn bộ tính năng ghi chú bảo mật kết hợp AI.
                            </p>

                            <div className="mt-8 flex flex-col sm:flex-row items-center justify-center gap-4">
                                <button
                                    type="button"
                                    onClick={() => { setAuthMode('register'); setIsAuthModalOpen(true); }}
                                    className="rounded-full bg-white px-8 py-3.5 text-sm font-bold text-green-700 dark:text-red-700 shadow-xl transition-all hover:scale-105 active:scale-95 hover:shadow-2xl"
                                >
                                    Start using Notezy
                                </button>
                                <button
                                    type="button"
                                    onClick={() => { setAuthMode('login'); setIsAuthModalOpen(true); }}
                                    className="rounded-full border border-white/50 px-7 py-3.5 text-sm font-bold text-white transition-all hover:bg-white/10"
                                >
                                    Đăng nhập tài khoản
                                </button>
                            </div>

                            <p className="mt-6 text-xs text-white/70 font-medium">
                                Miễn phí mãi mãi · Riêng tư tuyệt đối · Khóa bảo mật 6 số an toàn
                            </p>
                        </div>
                    </section>

                    {/* ── 7. FOOTER ── */}
                    <footer className={`border-t py-10 px-5 sm:px-8 text-xs text-gray-500 transition-colors ${
                        isDark ? 'border-white/10 bg-black' : 'border-gray-200 bg-white'
                    }`}>
                        <div className="mx-auto max-w-6xl flex flex-col sm:flex-row items-center justify-between gap-4">
                            <div className="flex items-center gap-2">
                                <NotezyLogo className="h-6 w-6" />
                                <span className="font-bold text-gray-900 dark:text-white">Notezy</span>
                                <span>— Smart Note-Taking & AI Assistant</span>
                            </div>
                            <div className="flex items-center gap-6">
                                <a href="privacy.php" className="hover:text-green-600 dark:hover:text-red-400">Chính sách bảo mật</a>
                                <a href="contact.php" className="hover:text-green-600 dark:hover:text-red-400">Liên hệ hỗ trợ</a>
                                <span>© 2026 Notezy. Tất cả quyền được bảo lưu.</span>
                            </div>
                        </div>
                    </footer>

                    {/* ── 8. INTEGRATED AUTH MODAL (2 TAB ĐĂNG NHẬP VÀ ĐĂNG KÝ TRỰC TIẾP) ── */}
                    {isAuthModalOpen && (
                        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 animate-fade-in">
                            <div className={`relative w-full max-w-md rounded-3xl border p-6 sm:p-8 shadow-2xl transition-all ${
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
                                    <NotezyLogo className="h-10 w-10 mx-auto mb-2" />
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
                                    <form onSubmit={handleLoginSubmit} className="space-y-4">
                                        <div>
                                            <label className="block text-xs font-bold mb-1 text-gray-700 dark:text-gray-300">
                                                Tên người dùng
                                            </label>
                                            <input
                                                type="text"
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
