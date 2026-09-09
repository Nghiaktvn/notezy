<?php
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__, 2) . '/includes/session.php';

header('Content-Type: application/json');

notezy_session_start();

function hosting_require_user() {
    if (empty($_SESSION['id'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Vui lòng đăng nhập Notezy trước.']);
        exit();
    }
    return (int)$_SESSION['id'];
}

function hosting_github_config() {
    $clientId     = getenv('GITHUB_CLIENT_ID');
    $clientSecret = getenv('GITHUB_CLIENT_SECRET');
    $redirectUri  = getenv('GITHUB_REDIRECT_URI');
    if (!$redirectUri) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
        $redirectUri = "$scheme://$host/api/hosting/github_auth.php?action=callback";
    }
    return [
        'client_id'     => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri'  => $redirectUri,
        'auth_url'      => 'https://github.com/login/oauth/authorize',
        'token_url'     => 'https://github.com/login/oauth/access_token',
        'api_base'      => 'https://api.github.com',
    ];
}

function hosting_github_store_connection($userId, $tokenData, $userData) {
    $conn = create_connect(true);
    $stmt = $conn->prepare(
        "INSERT INTO hosting_github_connections
            (user_id, github_user_id, github_login, github_avatar_url, access_token, refresh_token, token_expires_at, scopes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            github_user_id = VALUES(github_user_id),
            github_login = VALUES(github_login),
            github_avatar_url = VALUES(github_avatar_url),
            access_token = VALUES(access_token),
            refresh_token = VALUES(refresh_token),
            token_expires_at = VALUES(token_expires_at),
            scopes = VALUES(scopes),
            updated_at = CURRENT_TIMESTAMP"
    );
    $accessToken  = $tokenData['access_token'] ?? '';
    $refreshToken = $tokenData['refresh_token'] ?? null;
    $expiresIn    = $tokenData['expires_in'] ?? null;
    $expiresAt    = $expiresIn ? date('Y-m-d H:i:s', time() + (int)$expiresIn) : null;
    $scopes       = $tokenData['scope'] ?? '';
    $ghUserId     = (int)($userData['id'] ?? 0);
    $ghLogin      = $userData['login'] ?? '';
    $ghAvatar     = $userData['avatar_url'] ?? null;
    $stmt->bind_param(
        'iissssss',
        $userId,
        $ghUserId,
        $ghLogin,
        $ghAvatar,
        $accessToken,
        $refreshToken,
        $expiresAt,
        $scopes
    );
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

$action = $_GET['action'] ?? 'status';

if ($action === 'login') {
    $user_id = hosting_require_user();
    $cfg = hosting_github_config();
    if (empty($cfg['client_id'])) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'GITHUB_CLIENT_ID chưa được cấu hình.']);
        exit();
    }
    $state = bin2hex(random_bytes(16));
    $_SESSION['github_oauth_state'] = $state;
    $params = http_build_query([
        'client_id'    => $cfg['client_id'],
        'redirect_uri' => $cfg['redirect_uri'],
        'scope'        => 'repo read:user user:email',
        'state'        => $state,
        'response_type' => 'code',
    ]);
    $url = $cfg['auth_url'] . '?' . $params;
    echo json_encode(['status' => 'success', 'auth_url' => $url]);
    exit();
}

if ($action === 'callback') {
    $user_id = hosting_require_user();
    $code  = $_GET['code'] ?? '';
    $state = $_GET['state'] ?? '';
    $savedState = $_SESSION['github_oauth_state'] ?? '';
    unset($_SESSION['github_oauth_state']);

    if (!$code || !$state || $state !== $savedState) {
        http_response_code(400);
        echo "<script>window.opener.postMessage({type:'github_auth',success:false,message:'OAuth state không khớp'},'*'); window.close();</script>";
        exit();
    }

    $cfg = hosting_github_config();
    if (empty($cfg['client_id']) || empty($cfg['client_secret'])) {
        echo "<script>window.opener.postMessage({type:'github_auth',success:false,message:'GitHub OAuth chưa cấu hình client secret'},'*'); window.close();</script>";
        exit();
    }

    $ch = curl_init($cfg['token_url']);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([
            'client_id'     => $cfg['client_id'],
            'client_secret' => $cfg['client_secret'],
            'code'          => $code,
            'redirect_uri'  => $cfg['redirect_uri'],
            'state'         => $state,
        ]),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($resp === false) {
        echo "<script>window.opener.postMessage({type:'github_auth',success:false,message:'Lỗi kết nối GitHub: $err'},'*'); window.close();</script>";
        exit();
    }
    $tokenData = json_decode($resp, true);
    if (empty($tokenData['access_token'])) {
        $msg = $tokenData['error_description'] ?? 'Không nhận được access token';
        echo "<script>window.opener.postMessage({type:'github_auth',success:false,message:'$msg'},'*'); window.close();</script>";
        exit();
    }

    $ch2 = curl_init($cfg['api_base'] . '/user');
    curl_setopt_array($ch2, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $tokenData['access_token'],
            'Accept: application/vnd.github+json',
            'User-Agent: Notezy-Hosting',
        ],
    ]);
    $userResp = curl_exec($ch2);
    $userErr  = curl_error($ch2);
    curl_close($ch2);
    $userData = $userResp !== false ? json_decode($userResp, true) : [];

    hosting_github_store_connection($user_id, $tokenData, $userData);

    $ghLogin = $userData['login'] ?? '';
    echo "<script>window.opener.postMessage({type:'github_auth',success:true,login:'$ghLogin'},'*'); window.close();</script>";
    exit();
}

if ($action === 'status') {
    $user_id = hosting_require_user();
    $conn = create_connect(true);
    $stmt = $conn->prepare(
        "SELECT github_login, github_avatar_url, scopes, created_at, updated_at, token_expires_at
         FROM hosting_github_connections WHERE user_id = ? LIMIT 1"
    );
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    echo json_encode([
        'status'       => 'success',
        'connected'    => (bool)$row,
        'github'       => $row ? [
            'login'          => $row['github_login'],
            'avatar_url'     => $row['github_avatar_url'],
            'scopes'         => $row['scopes'],
            'expires_at'     => $row['token_expires_at'],
        ] : null,
    ]);
    exit();
}

if ($action === 'disconnect') {
    $user_id = hosting_require_user();
    $conn = create_connect(true);
    $stmt = $conn->prepare("DELETE FROM hosting_github_connections WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['status' => 'success', 'message' => 'Đã ngắt kết nối GitHub.']);
    exit();
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Hành động không hợp lệ.']);
