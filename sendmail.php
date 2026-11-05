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

    // Bound SMTP failures so a blocked provider cannot hold a signup or
    // password-reset request for PHPMailer’s five-minute default.
    $smtpTimeout = (int) (getenv('MAIL_TIMEOUT_SECONDS') ?: 10);
    $smtpTimeout = max(3, min($smtpTimeout, 30));
    $smtpTimeLimit = (int) (getenv('MAIL_TIMELIMIT_SECONDS') ?: 15);
    $smtpTimeLimit = max($smtpTimeout, min($smtpTimeLimit, 60));
    $mail->Timeout = $smtpTimeout;
    $mail->getSMTPInstance()->Timelimit = $smtpTimeLimit;

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
    // MAIL_FROM_ADDRESS is the documented application setting. MAIL_FROM is
    // retained for Docker Compose/backward compatibility.
    $from = notezy_valid_email(getenv('MAIL_FROM_ADDRESS'))
         ?? notezy_valid_email(getenv('MAIL_FROM'))
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
    $encryption = strtolower(trim((string) (getenv('MAIL_ENCRYPTION') ?: 'tls')));
    $mail->SMTPSecure = match ($encryption) {
        'ssl', 'smtps' => PHPMailer::ENCRYPTION_SMTPS,
        'none' => '',
        default => PHPMailer::ENCRYPTION_STARTTLS,
    };

    $mail->setFrom($from, $fromName);
    $mail->Sender = $from;
    return $mail;
}

/** Send through Resend's HTTPS API when SMTP ports are unavailable (Render Free). */
function notezy_send_resend(string $email, string $subject, string $html, string $plain): bool
{
    $key = trim((string) (getenv('RESEND_API_KEY') ?: ''));
    $from = notezy_valid_email(getenv('MAIL_FROM_ADDRESS'))
         ?? notezy_valid_email(getenv('MAIL_FROM'));
    if ($key === '' || $from === null) {
        error_log('Notezy mail: RESEND_API_KEY or MAIL_FROM_ADDRESS is missing');
        return false;
    }

    $fromName = trim((string) (getenv('MAIL_FROM_NAME') ?: 'Notezy'));
    $payload = json_encode([
        'from' => ($fromName !== '' ? $fromName . ' <' . $from . '>' : $from),
        'to' => [$email],
        'subject' => $subject,
        'html' => $html,
        'text' => $plain,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        return false;
    }

    $timeout = max(3, min((int) (getenv('MAIL_TIMEOUT_SECONDS') ?: 10), 30));
    $context = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => "Authorization: Bearer {$key}\r\nContent-Type: application/json\r\n",
        'content' => $payload,
        'timeout' => $timeout,
        'ignore_errors' => true,
    ]]);
    $response = @file_get_contents('https://api.resend.com/emails', false, $context);
    $statusLine = $http_response_header[0] ?? '';
    if ($response === false || !preg_match('~^HTTP/\S+ 2\d\d\b~', $statusLine)) {
        error_log('Notezy mail: Resend request failed (' . preg_replace('/[^0-9 ]/', '', $statusLine) . ')');
        return false;
    }
    $result = json_decode($response, true);
    return is_array($result) && !empty($result['id']);
}

/** Send through Gmail's HTTPS API using an offline OAuth refresh token. */
function notezy_send_gmail_api(string $email, string $toName, string $subject, string $html, string $plain): bool
{
    $from = notezy_valid_email(getenv('MAIL_FROM_ADDRESS'));
    $clientId = trim((string) (getenv('GMAIL_CLIENT_ID') ?: ''));
    $clientSecret = trim((string) (getenv('GMAIL_CLIENT_SECRET') ?: ''));
    $refreshToken = trim((string) (getenv('GMAIL_REFRESH_TOKEN') ?: ''));
    if ($from === null || $clientId === '' || $clientSecret === '' || $refreshToken === '') {
        error_log('Notezy mail: Gmail API configuration is incomplete');
        return false;
    }

    $timeout = max(3, min((int) (getenv('MAIL_TIMEOUT_SECONDS') ?: 10), 30));
    $tokenBody = http_build_query([
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'refresh_token' => $refreshToken,
        'grant_type' => 'refresh_token',
    ]);
    $tokenContext = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => $tokenBody,
        'timeout' => $timeout,
        'ignore_errors' => true,
    ]]);
    $tokenResponse = @file_get_contents('https://oauth2.googleapis.com/token', false, $tokenContext);
    $tokenStatus = $http_response_header[0] ?? '';
    $tokenData = $tokenResponse === false ? null : json_decode($tokenResponse, true);
    if (!preg_match('~^HTTP/\S+ 2\d\d\b~', $tokenStatus)
        || !is_array($tokenData) || empty($tokenData['access_token'])) {
        error_log('Notezy mail: Gmail token refresh failed (' . preg_replace('/[^0-9 ]/', '', $tokenStatus) . ')');
        return false;
    }

    try {
        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($from, trim((string) (getenv('MAIL_FROM_NAME') ?: 'Notezy')));
        $mail->addAddress($email, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = $plain;
        if (!$mail->preSend()) {
            return false;
        }
        $raw = rtrim(strtr(base64_encode($mail->getSentMIMEMessage()), '+/', '-_'), '=');
    } catch (Throwable $e) {
        error_log('Notezy mail: could not build Gmail message');
        return false;
    }

    $sendContext = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => "Authorization: Bearer {$tokenData['access_token']}\r\nContent-Type: application/json\r\n",
        'content' => json_encode(['raw' => $raw]),
        'timeout' => $timeout,
        'ignore_errors' => true,
    ]]);
    $sendResponse = @file_get_contents('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', false, $sendContext);
    $sendStatus = $http_response_header[0] ?? '';
    $sendData = $sendResponse === false ? null : json_decode($sendResponse, true);
    if (!preg_match('~^HTTP/\S+ 2\d\d\b~', $sendStatus)
        || !is_array($sendData) || empty($sendData['id'])) {
        error_log('Notezy mail: Gmail send failed (' . preg_replace('/[^0-9 ]/', '', $sendStatus) . ')');
        return false;
    }
    return true;
}

function notezy_send_message(string $email, string $toName, string $subject, string $html, string $plain)
{
    $mailer = strtolower(trim((string) (getenv('MAIL_MAILER') ?: 'smtp')));
    if ($mailer === 'gmail_api') {
        return notezy_send_gmail_api($email, $toName, $subject, $html, $plain)
            ? true : 'Lỗi gửi email qua Gmail API. Vui lòng thử lại sau.';
    }
    if ($mailer === 'resend') {
        return notezy_send_resend($email, $subject, $html, $plain)
            ? true : 'Lỗi gửi email: không thể gửi qua dịch vụ HTTPS. Vui lòng thử lại sau.';
    }
    try {
        $mail = notezy_mailer();
        $mail->addAddress($email, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = $plain;
        $mail->send();
        return true;
    } catch (Throwable $e) {
        return notezy_mail_error_message($e);
    }
}

/**
 * Convert SMTP exceptions into an actionable, non-sensitive message for users.
 * SMTP credentials and server debug output must never be shown in the UI.
 */
function notezy_mail_error_message(Throwable $e): string {
    $message = (string) $e->getMessage();
    if (stripos($message, 'Username and Password not accepted') !== false
        || stripos($message, 'SMTP Error: Could not authenticate') !== false) {
        return 'Lỗi gửi email: Gmail từ chối xác thực SMTP. Kiểm tra Mật khẩu ứng dụng của tài khoản gửi.';
    }

    return 'Lỗi gửi email: không thể gửi email lúc này. Vui lòng thử lại sau.';
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
    try {
        $safeLink = filter_var($activationLink, FILTER_VALIDATE_URL) ? $activationLink : '';
        $linkHtml = $safeLink !== ''
            ? "<p><a href=\"" . htmlspecialchars($safeLink, ENT_QUOTES, 'UTF-8') . "\" style=\"display:inline-block;background:#2563eb;color:#fff;padding:10px 16px;border-radius:6px;text-decoration:none\">Kích hoạt tài khoản</a></p>"
            : '';
        $safeName = htmlspecialchars((string) $toName, ENT_QUOTES, 'UTF-8');
        $html = "Chào <b>$safeName</b>,<br><br>"
                       . "Bấm liên kết dưới đây để kích hoạt tài khoản:<br>"
                       . $linkHtml
                       . "<p>Hoặc nhập mã OTP kích hoạt:</p>"
                       . "<h2 style='letter-spacing:4px;color:#2563eb;'>$otp</h2>"
                       . "<p>Mã có hiệu lực trong <strong>5 phút</strong>.</p>"
                       . "<p>Nếu bạn không thực hiện yêu cầu này, hãy bỏ qua email này.</p>"
                       . "<p>-- Đội ngũ Notezy</p>";
        $plain = ($safeLink !== '' ? "Kích hoạt tài khoản: $safeLink\n" : '') . "Mã OTP kích hoạt tài khoản: $otp (hiệu lực 5 phút)";
        return notezy_send_message($email, (string) $toName, 'Kích hoạt tài khoản Notezy', $html, $plain);
    } catch (Throwable $e) {
        return notezy_mail_error_message($e);
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
        $safeName = htmlspecialchars((string) $toName, ENT_QUOTES, 'UTF-8');
        $html = "Chào <b>$safeName</b>,<br><br>"
                       . "Mã OTP đặt lại mật khẩu của bạn là:<br>"
                       . "<h2 style='letter-spacing:4px;color:#dc2626;'>$otp</h2>"
                       . "<p>Mã có hiệu lực trong <strong>15 phút</strong>.</p>"
                       . "<p>Nếu bạn không yêu cầu đặt lại mật khẩu, hãy bỏ qua email này.</p>"
                       . "<p>-- Đội ngũ Notezy</p>";
        $plain = "Mã OTP đặt lại mật khẩu: $otp (hiệu lực 15 phút)";
        return notezy_send_message($email, (string) $toName, 'Khôi phục mật khẩu Notezy', $html, $plain);
    } catch (Throwable $e) {
        return notezy_mail_error_message($e);
    }
}
?>
