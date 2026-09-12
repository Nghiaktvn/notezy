<?php
$baseUrl = rtrim((string) (getenv('AI_AGENT_TEST_URL') ?: getenv('AI_AGENT_URL') ?: 'http://127.0.0.1:8765'), '/');
$sharedSecret = (string) getenv('AI_AGENT_SHARED_SECRET');
if ($sharedSecret === '') {
    fwrite(STDERR, "AI_AGENT_SHARED_SECRET is required for the live integration test.\n");
    exit(2);
}
$ch = curl_init($baseUrl . '/v1/chat');
$timeoutSeconds = max(20, (int) (getenv('AI_AGENT_TEST_TIMEOUT') ?: 40));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => $timeoutSeconds,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'X-Notezy-Agent-Secret: ' . $sharedSecret
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'messages' => [
            ['role' => 'user', 'content' => 'Xin chào Notezy Copilot.']
        ]
    ])
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $code\n";
echo "Response: $resp\n";
if ($code !== 200 || !is_string($resp) || $resp === '') {
    if ($curlError !== '') fwrite(STDERR, "cURL error: {$curlError}\n");
    exit(1);
}
