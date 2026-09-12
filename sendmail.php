<?php
require_once __DIR__ . '/env.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';
require 'PHPMailer-master/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function notezy_valid_email($value): ?string
{
    $value = trim((string) $value, " \t\n\r\0\x0B\"'");
    if ($value === '' || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
        return null;
    }
    return $value;
}

/**
 * Build a configured PHPMailer instance from environment variables.
 * From address is always a valid email so registration never fails on empty MAIL_FROM.
 */
function notezy_mailer(): PHPMailer {
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    $mail->isSMTP();

    $host = trim((string) (getenv('MAIL_HOST') ?: ''));
    $port = (int) (getenv('MAIL_PORT') ?: 0);

    if ($host === '') {
        $host = 'smtp.gmail.com';
    }
    if ($port <= 0) {
        $port = 587;
    }

    $user = trim((string) (getenv('MAIL_USERNAME') ?: ''));
    $pass = (string) (getenv('MAIL_PASSWORD') ?: '');
    $from = notezy_valid_email(getenv('MAIL_FROM'))
         ?? notezy_valid_email($user)
         ?? 'noreply@notezy.local';
    $fromName = trim((string) (getenv('MAIL_FROM_NAME') ?: 'Notezy'), " \t\"'");
    if ($fromName === '') {
        $fromName = 'Notezy';
    }

    $mail->Host = $host;
    $mail->Port = $port;

    $mail->SMTPAuth   = $user !== '';
    $mail->Username   = $user;
    $mail->Password   = $pass;
    $mail->SMTPSecure = 'tls';

    $mail->setFrom($from, $fromName);
    $mail->Sender = $from;
    return $mail;
}

/**
 * Gửi email OTP kích hoạt tài khoản.
 * @return true|string   true nếu gửi thành công, string lỗi nếu thất bại
 */
function sendActivationEmail($email, $otp = '', $toName = 'User', $activationLink = '') {
    $email = notezy_valid_email($email);
    if ($email === null) {
        return 'Lỗi gửi email: địa chỉ nhận không hợp lệ';
    }
    // Ghi nhận log OTP phục vụ kiểm tra và demo
    $logDir = __DIR__ . '/uploads';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0777, true);
    }
    @file_put_contents($logDir . '/mail_log.txt', date('[Y-m-d H:i:s] ') . "ACTIVATION OTP for {$email} ({$toName}): {$otp} (Hiệu lực: 5 phút)\n", FILE_APPEND);

    try {
        $mail = notezy_mailer();
        $mail->addAddress($email, $toName);
        $mail->isHTML(true);
        $mail->Subject = 'Kích hoạt tài khoản Notezy';
        $safeLink = filter_var($activationLink, FILTER_VALIDATE_URL) ? $activationLink : '';
        $linkHtml = $safeLink !== ''
            ? "<p><a href=\"" . htmlspecialchars($safeLink, ENT_QUOTES, 'UTF-8') . "\" style=\"display:inline-block;background:#2563eb;color:#fff;padding:10px 16px;border-radius:6px;text-decoration:none\">Kích hoạt tài khoản</a></p>"
            : '';
        $mail->Body    = "Chào <b>$toName</b>,<br><br>"
                       . "Bấm liên kết dưới đây để kích hoạt tài khoản:<br>"
                       . $linkHtml
                       . "<p>Hoặc nhập mã OTP kích hoạt:</p>"
                       . "<h2 style='letter-spacing:4px;color:#2563eb;'>$otp</h2>"
                       . "<p>Mã có hiệu lực trong <strong>5 phút</strong>.</p>"
                       . "<p>Nếu bạn không thực hiện yêu cầu này, hãy bỏ qua email này.</p>"
                       . "<p>-- Đội ngũ Notezy</p>";
        $mail->AltBody = ($safeLink !== '' ? "Kích hoạt tài khoản: $safeLink\n" : '') . "Mã OTP kích hoạt tài khoản: $otp (hiệu lực 5 phút)";
        $mail->send();
        return true;
    } catch (Exception $e) {
        return 'Lỗi gửi email: ' . ($e->getMessage() ?: 'không gửi được');
    }
}

/**
 * Gửi email OTP đặt lại mật khẩu.
 * @return true|string
 */
function sendResetPasswordEmail($email, $otp = '', $toName = 'User') {
    $email = notezy_valid_email($email);
    if ($email === null) {
        return 'Lỗi gửi email: địa chỉ nhận không hợp lệ';
    }
    try {
        $mail = notezy_mailer();
        $mail->addAddress($email, $toName);
        $mail->isHTML(true);
        $mail->Subject = 'Khôi phục mật khẩu Notezy';
        $mail->Body    = "Chào <b>$toName</b>,<br><br>"
                       . "Mã OTP đặt lại mật khẩu của bạn là:<br>"
                       . "<h2 style='letter-spacing:4px;color:#dc2626;'>$otp</h2>"
                       . "<p>Mã có hiệu lực trong <strong>15 phút</strong>.</p>"
                       . "<p>Nếu bạn không yêu cầu đặt lại mật khẩu, hãy bỏ qua email này.</p>"
                       . "<p>-- Đội ngũ Notezy</p>";
        $mail->AltBody = "Mã OTP đặt lại mật khẩu: $otp (hiệu lực 15 phút)";
        $mail->send();
        return true;
    } catch (Exception $e) {
        return 'Lỗi gửi email: ' . ($e->getMessage() ?: 'không gửi được');
    }
}
?>
