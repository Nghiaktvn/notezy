<?php
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__, 2) . '/includes/session.php';

header('Content-Type: application/json');

notezy_session_start();

if (empty($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Vui lòng đăng nhập Notezy trước.']);
    exit();
}
$user_id = (int)$_SESSION['id'];

function hosting_github_token($userId) {
    $conn = create_connect(true);
    $stmt = $conn->prepare("SELECT access_token FROM hosting_github_connections WHERE user_id = ? LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row['access_token'] ?? null;
}

function github_api($method, $path, $token, $query = [], $body = null) {
    $url = 'https://api.github.com' . $path;
    if (!empty($query)) {
        $url .= '?' . http_build_query($query);
    }
    $ch = curl_init($url);
    $headers = [
        'Authorization: Bearer ' . $token,
        'Accept: application/vnd.github+json',
        'X-GitHub-Api-Version: 2022-11-28',
        'User-Agent: Notezy-Hosting',
    ];
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => $headers,
    ];
    if (strtoupper($method) !== 'GET') {
        $opts[CURLOPT_CUSTOMREQUEST] = strtoupper($method);
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($body);
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_HTTPHEADER] = $headers;
        }
    }
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($resp === false) {
        return ['ok' => false, 'error' => $err, 'status' => 0];
    }
    $data = json_decode($resp, true);
    return ['ok' => $code >= 200 && $code < 300, 'status' => $code, 'data' => $data, 'raw' => $resp];
}

$endpoint = $_GET['endpoint'] ?? 'repos';
$token = hosting_github_token($user_id);

if (!$token) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Vui lòng kết nối GitHub trước (trang Hosting → Kết nối GitHub).']);
    exit();
}

if ($endpoint === 'repos') {
    $perPage = (int)($_GET['per_page'] ?? 100);
    $page    = (int)($_GET['page'] ?? 1);
    $type    = $_GET['type'] ?? 'owner';
    $sort    = $_GET['sort'] ?? 'updated';
    $r = github_api('GET', '/user/repos', $token, [
        'per_page' => $perPage,
        'page'     => $page,
        'type'     => $type,
        'sort'     => $sort,
    ]);
    if (!$r['ok']) {
        http_response_code(502);
        echo json_encode([
            'status'  => 'error',
            'message' => 'Không thể tải danh sách repository từ GitHub.',
            'detail'  => $r['data']['message'] ?? $r['error'] ?? null,
        ]);
        exit();
    }
    $repos = [];
    foreach ((array)$r['data'] as $repo) {
        $repos[] = [
            'id'          => $repo['id'],
            'full_name'   => $repo['full_name'],
            'name'        => $repo['name'],
            'owner'       => $repo['owner']['login'],
            'private'     => (bool)$repo['private'],
            'description' => $repo['description'],
            'default_branch' => $repo['default_branch'],
            'html_url'    => $repo['html_url'],
            'updated_at'  => $repo['updated_at'],
            'language'    => $repo['language'] ?? null,
            'has_dockerfile' => null,
        ];
    }
    echo json_encode(['status' => 'success', 'repos' => $repos]);
    exit();
}

if ($endpoint === 'branches') {
    $ownerRepo = $_GET['repo'] ?? '';
    if (!$ownerRepo || strpos($ownerRepo, '/') === false) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Thiếu tham số repo (owner/name).']);
        exit();
    }
    $r = github_api('GET', "/repos/$ownerRepo/branches", $token, ['per_page' => 50]);
    if (!$r['ok']) {
        http_response_code(502);
        echo json_encode([
            'status'  => 'error',
            'message' => 'Không thể tải danh sách nhánh.',
            'detail'  => $r['data']['message'] ?? $r['error'] ?? null,
        ]);
        exit();
    }
    $branches = array_map(fn($b) => ['name' => $b['name'], 'protected' => (bool)($b['protected'] ?? false)], (array)$r['data']);
    echo json_encode(['status' => 'success', 'branches' => $branches]);
    exit();
}

if ($endpoint === 'detect') {
    $ownerRepo = $_GET['repo'] ?? '';
    $branch    = $_GET['branch'] ?? 'main';
    if (!$ownerRepo || strpos($ownerRepo, '/') === false) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Thiếu tham số repo.']);
        exit();
    }
    $r = github_api('GET', "/repos/$ownerRepo/contents", $token, ['ref' => $branch]);
    $out = ['has_dockerfile' => false, 'has_package_json' => false, 'has_index_html' => false,
            'has_render_yaml' => false, 'has_requirements_txt' => false, 'has_go_mod' => false,
            'env_guess' => 'static', 'files' => []];
    if ($r['ok'] && is_array($r['data'])) {
        foreach ($r['data'] as $f) {
            $name = strtolower($f['name']);
            $out['files'][] = $f['name'];
            if ($name === 'dockerfile')          $out['has_dockerfile'] = true;
            if ($name === 'package.json')        $out['has_package_json'] = true;
            if ($name === 'index.html')          $out['has_index_html'] = true;
            if ($name === 'render.yaml' || $name === 'render.yml') $out['has_render_yaml'] = true;
            if ($name === 'requirements.txt')    $out['has_requirements_txt'] = true;
            if ($name === 'go.mod')              $out['has_go_mod'] = true;
        }
    }
    if ($out['has_dockerfile'])                  $out['env_guess'] = 'docker';
    elseif ($out['has_go_mod'])                  $out['env_guess'] = 'go';
    elseif ($out['has_requirements_txt'])        $out['env_guess'] = 'python';
    elseif ($out['has_package_json'])            $out['env_guess'] = 'node';
    elseif ($out['has_index_html'])              $out['env_guess'] = 'static_site';
    echo json_encode(['status' => 'success', 'detection' => $out]);
    exit();
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Endpoint không hợp lệ.']);
