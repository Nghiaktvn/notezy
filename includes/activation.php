<?php
require_once dirname(__DIR__) . '/sendmail.php';
require_once dirname(__DIR__) . '/config/demo.php';

/** Shared by the HTML form and JSON API; never returns the OTP or its hash. */
function notezy_activation_cooldown(array $user): int {
    $expires = strtotime((string)($user['activation_otp_expires_at'] ?? ''));
    return $expires ? max(0, min(60, 60 - (time() - ($expires - 300)))) : 0;
}

function notezy_activation_deliver(mysqli $db, string $email, string $otp, string $name): bool {
    $sent = sendActivationEmail($email, $otp, $name);
    if ($sent === true) return true;
    // Never leave a usable challenge when delivery failed. Do not clear a newer resend.
    $find = $db->prepare('SELECT id, activation_otp_hash FROM users WHERE email = ? AND activated = 0');
    $find->bind_param('s', $email);
    $find->execute();
    $row = $find->get_result()->fetch_assoc();
    if ($row && password_verify($otp, (string)$row['activation_otp_hash'])) {
        $clear = $db->prepare('UPDATE users SET activation_otp_hash = NULL, activation_token_hash = NULL, activation_token_expires_at = NULL, activated_tokens = NULL WHERE id = ? AND activation_otp_hash = ? AND activated = 0');
        $clear->bind_param('is', $row['id'], $row['activation_otp_hash']);
        $clear->execute();
    }
    return false;
}

function notezy_activation_verify(mysqli $db, string $email, string $otp): array {
    $db->begin_transaction();
    try {
        $stmt = $db->prepare('SELECT id, activated, activation_otp_hash, activation_otp_expires_at, activation_otp_attempts FROM users WHERE email = ? FOR UPDATE');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) $result = ['status' => 'error', 'message' => 'Không tìm thấy yêu cầu đăng ký.'];
        elseif ((int)$row['activated'] === 1) $result = ['status' => 'success', 'message' => 'Tài khoản đã được kích hoạt.'];
        elseif (empty($row['activation_otp_hash']) || (int)$row['activation_otp_attempts'] >= 5) {
            $result = ['status' => 'error', 'message' => 'Hãy yêu cầu gửi lại mã xác thực rồi nhập mã mới nhất.', 'requires_resend' => true];
        } elseif (strtotime((string)$row['activation_otp_expires_at']) <= time()) {
            $result = ['status' => 'error', 'message' => 'Mã xác thực đã hết hạn. Hãy yêu cầu gửi lại mã.', 'requires_resend' => true];
        } elseif ((!notezy_demo_otp_enabled() && $otp === '123456') || !preg_match('/^[0-9]{6}$/D', $otp) || !password_verify($otp, $row['activation_otp_hash'])) {
            if (notezy_demo_otp_enabled()) {
                $bad = $db->prepare('UPDATE users SET activation_otp_attempts = activation_otp_attempts + 1 WHERE id = ?');
            } else {
                // Real-email mode requires a fresh challenge after a wrong code.
                $bad = $db->prepare('UPDATE users SET activation_otp_attempts = 5, activation_token_hash = NULL, activation_token_expires_at = NULL, activated_tokens = NULL WHERE id = ?');
            }
            $bad->bind_param('i', $row['id']);
            if (!$bad->execute()) throw new RuntimeException('OTP update failed');
            $result = ['status' => 'error', 'message' => 'Mã xác thực không đúng. Tài khoản chưa được kích hoạt; hãy yêu cầu gửi lại mã.', 'requires_resend' => !notezy_demo_otp_enabled()];
        } else {
            $ok = $db->prepare('UPDATE users SET activated = 1, activation_otp_hash = NULL, activation_otp_expires_at = NULL, activation_otp_attempts = 0, activation_token_hash = NULL, activation_token_expires_at = NULL, activated_tokens = NULL WHERE id = ? AND activated = 0');
            $ok->bind_param('i', $row['id']);
            if (!$ok->execute() || $ok->affected_rows !== 1) throw new RuntimeException('Activation failed');
            $result = ['status' => 'success', 'message' => 'Kích hoạt tài khoản thành công. Hãy đăng nhập.'];
        }
        $db->commit();
        return $result;
    } catch (Throwable $error) {
        $db->rollback();
        return ['status' => 'error', 'message' => 'Không thể xác thực lúc này. Vui lòng thử lại.'];
    }
}

function notezy_activation_resend(mysqli $db, string $email): array {
    $db->begin_transaction();
    try {
        $find = $db->prepare('SELECT id, firstname, activated, activation_otp_hash, activation_otp_expires_at FROM users WHERE email = ? FOR UPDATE');
        $find->bind_param('s', $email);
        $find->execute();
        $row = $find->get_result()->fetch_assoc();
        if (!$row || (int)$row['activated'] === 1) {
            $db->rollback();
            return ['status' => 'error', 'message' => 'Thư điện tử không hợp lệ hoặc tài khoản đã kích hoạt.'];
        }
        $wait = notezy_activation_cooldown($row);
        if ($wait > 0) {
            $db->rollback();
            return ['status' => 'error', 'message' => "Vui lòng chờ {$wait} giây trước khi gửi lại mã.", 'retry_after' => $wait];
        }
        do { $otp = notezy_activation_otp(); }
        while (!notezy_demo_otp_enabled() && !empty($row['activation_otp_hash']) && password_verify($otp, $row['activation_otp_hash']));
        $hash = password_hash($otp, PASSWORD_DEFAULT);
        $expires = date('Y-m-d H:i:s', time() + 300);
        $set = $db->prepare('UPDATE users SET activation_otp_hash = ?, activation_otp_expires_at = ?, activation_otp_attempts = 0, activation_token_hash = NULL, activation_token_expires_at = NULL, activated_tokens = NULL WHERE id = ? AND activated = 0');
        $set->bind_param('ssi', $hash, $expires, $row['id']);
        if (!$set->execute()) throw new RuntimeException('Resend update failed');
        $db->commit();
    } catch (Throwable $error) {
        $db->rollback();
        return ['status' => 'error', 'message' => 'Không thể tạo mã xác thực. Vui lòng thử lại.'];
    }
    if (!notezy_activation_deliver($db, $email, $otp, (string)$row['firstname'])) {
        return ['status' => 'error', 'message' => 'Chưa gửi được thư xác thực. Hãy thử gửi lại sau hoặc liên hệ nhóm Notezy.', 'delivery_status' => 'failed', 'retry_after' => 60];
    }
    return ['status' => 'success', 'message' => notezy_demo_otp_enabled() ? 'Chế độ trình diễn: dùng mã 123456.' : 'Đã gửi mã mới. Kiểm tra hộp thư và thư rác; mã có hiệu lực 5 phút.', 'delivery_status' => notezy_demo_otp_enabled() ? 'demo' : 'sent', 'retry_after' => 60];
}
