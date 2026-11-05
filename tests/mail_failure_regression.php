<?php
// CLI-only and synthetic local fixtures; never an endpoint on the deployed site.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once dirname(__DIR__) . '/account_db.php';
require_once dirname(__DIR__) . '/includes/activation.php';
if (!in_array(getenv('APP_ENV'), ['local', 'demo'], true)) exit('Local test environment required.');
putenv('NOTEZY_DEMO_OTP=false');
putenv('MAIL_MAILER=gmail_api');
putenv('GMAIL_CLIENT_ID=');
putenv('GMAIL_CLIENT_SECRET=');
putenv('GMAIL_REFRESH_TOKEN=');
$name = 'otp_failure_' . bin2hex(random_bytes(5));
$email = $name . '@example.com';
$created = register($name, 'Synthetic failure', '', $email, 'SyntheticFixture1357!');
$db = create_connect();
if (empty($created['success'])) exit(1);
if (notezy_activation_deliver($db, $email, $created['otp'], 'Synthetic failure')) exit(2);
$find = $db->prepare('SELECT activated, activation_otp_hash FROM users WHERE email = ?');
$find->bind_param('s', $email);
$find->execute();
$user = $find->get_result()->fetch_assoc();
if ((int)$user['activated'] !== 0 || $user['activation_otp_hash'] !== null) exit(3);
echo "PASS Missing Gmail configuration cannot report delivery success or leave a usable OTP\n";
putenv('RENDER_SERVICE_ID=synthetic-render-service');
putenv('MAIL_MAILER=smtp');
putenv('MAIL_PORT=587');
$start = microtime(true);
$result = notezy_send_message($email, 'Fixture', 'Fixture', 'Fixture', 'Fixture');
if ($result === true || microtime(true) - $start > 1) exit(4);
echo "PASS Render SMTP block fails immediately with actionable configuration error\n";
