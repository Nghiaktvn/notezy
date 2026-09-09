<?php

function render_api_base() { return 'https://api.render.com/v1'; }

function render_api_key($userId) {
    $conn = create_connect(true);
    $stmt = $conn->prepare("SELECT render_api_key, render_owner_id FROM hosting_render_connections WHERE user_id = ? LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function render_api($method, $path, $apiKey, $query = [], $body = null) {
    $url = render_api_base() . $path;
    if (!empty($query)) {
        $url .= '?' . http_build_query($query);
    }
    $ch = curl_init($url);
    $headers = [
        'Authorization: Bearer ' . $apiKey,
        'Accept: application/json',
    ];
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => $headers,
    ];
    $m = strtoupper($method);
    if ($m !== 'GET') {
        $opts[CURLOPT_CUSTOMREQUEST] = $m;
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
        return ['ok' => false, 'error' => $err, 'status' => 0, 'data' => null, 'raw' => null];
    }
    $data = json_decode($resp, true);
    return ['ok' => $code >= 200 && $code < 300, 'status' => $code, 'data' => $data, 'raw' => $resp];
}

function render_map_env_guess_to_image($guess) {
    switch ($guess) {
        case 'node':        return 'node';
        case 'python':      return 'python_3';
        case 'go':          return 'go';
        case 'docker':      return 'docker';
        case 'static_site': return 'static_site';
        default:            return 'docker';
    }
}

function render_sanitize_service_name($fullName) {
    $name = preg_replace('/[^a-z0-9-]/', '-', strtolower($fullName));
    $name = trim($name, '-');
    if (strlen($name) < 3) $name = $name . '-app';
    return substr($name, 0, 40);
}

function render_update_deployment_status($deploymentId, $updates) {
    $conn = create_connect(true);
    $fields = [];
    $values = [];
    $types  = '';
    foreach ($updates as $k => $v) {
        $fields[] = "`$k` = ?";
        $values[] = $v;
        $types  .= is_int($v) ? 'i' : 's';
    }
    $values[] = $deploymentId;
    $types .= 'i';
    $sql = "UPDATE hosting_deployments SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$values);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}
