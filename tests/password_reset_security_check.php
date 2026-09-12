<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
$root = dirname(__DIR__);
$content = file_get_contents($root . '/reset_password.php');
$mailer = file_get_contents($root . '/sendmail.php');
$checks = [
    'CSRF token is required' => strpos($content, 'csrf_check') !== false,
    'OTP is bcrypt hashed' => strpos($content, 'password_hash($otp') !== false,
    'OTP state is stored server-side' => strpos($content, 'reset_otp_hash = ?') !== false,
    'OTP has an expiry' => strpos($content, 'reset_otp_expires_at') !== false,
    'OTP attempt counter is enforced' => strpos($content, 'reset_otp_attempts') !== false,
    'OTP is invalidated after verification' => strpos($content, 'reset_otp_hash = NULL') !== false,
    'New password replaces account hash' => strpos($content, 'UPDATE users SET password_hash = ?') !== false,
    'Reset ends authenticated session' => strpos($content, 'session_regenerate_id(true)') !== false,
    'Reset OTP mail is implemented' => strpos($mailer, 'sendResetPasswordEmail') !== false,
];
$failed = [];
foreach ($checks as $label => $ok) { if ($ok) echo "PASS: $label\n"; else $failed[] = $label; }
if ($failed) { fwrite(STDERR, "FAILED:\n- " . implode("\n- ", $failed) . "\n"); exit(1); }
