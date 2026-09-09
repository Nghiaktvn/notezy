<?php
$ch = curl_init('http://ai_agent:8765/v1/chat');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'X-Notezy-Agent-Secret: change_me_to_a_long_random_string'
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'messages' => [
            ['role' => 'user', 'content' => 'Xin chào Notezy Copilot, hãy tóm tắt nhiệm vụ của bạn.']
        ],
        'context' => [
            'notes' => [
                ['id' => 1, 'title' => 'Ghi chú demo', 'content' => 'Hệ thống chạy trên Docker container']
            ]
        ]
    ])
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $code\n";
echo "Response: $resp\n";
