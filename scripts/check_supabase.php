<?php
declare(strict_types=1);

/**
 * Read-only Supabase/PostgreSQL preflight.
 *
 * Usage:
 *   php scripts/check_supabase.php
 *
 * Required server-only environment variable:
 *   SUPABASE_DB_PASSWORD
 */

require_once dirname(__DIR__) . '/env.php';

$expectedTables = [
    'users', 'notes', 'labels', 'note_labels', 'note_shares',
    'note_pin_unlocks', 'note_attachments', 'note_collab_presence',
    'ai_conversations', 'ai_messages', 'ai_pending_actions',
    'ai_rate_limits', 'timetable', 'timetable_shares',
    'hosting_github_connections', 'hosting_render_connections',
    'hosting_deployments', 'hosting_custom_domains', 'flashcards',
    'quiz_results', 'chat_messages', 'plans', 'entitlements',
    'subscriptions', 'usage_records', 'premium_trials', 'service_outbox',
];

function requiredEnv(string $name): string
{
    $value = getenv($name);
    if ($value === false || trim($value) === '' || str_starts_with($value, 'replace_with_')) {
        fwrite(STDERR, "Missing required environment variable: {$name}\n");
        exit(2);
    }

    return $value;
}

if (!extension_loaded('pdo_pgsql')) {
    fwrite(STDERR, "PDO PostgreSQL is not enabled. Enable extension=pdo_pgsql or use the project Docker image.\n");
    exit(2);
}

$host = getenv('SUPABASE_DB_HOST') ?: 'db.rnadeaoggiigfekccirb.supabase.co';
$port = getenv('SUPABASE_DB_PORT') ?: '5432';
$database = getenv('SUPABASE_DB_NAME') ?: 'postgres';
$user = getenv('SUPABASE_DB_USER') ?: 'postgres';
$password = requiredEnv('SUPABASE_DB_PASSWORD');

$dsn = sprintf(
    'pgsql:host=%s;port=%s;dbname=%s;sslmode=require',
    $host,
    $port,
    $database
);

try {
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 10,
    ]);

    $placeholders = implode(',', array_fill(0, count($expectedTables), '?'));
    $statement = $pdo->prepare(
        "SELECT c.relname AS table_name, c.relrowsecurity AS rls_enabled
         FROM pg_class c
         JOIN pg_namespace n ON n.oid = c.relnamespace
         WHERE n.nspname = 'public'
           AND c.relkind = 'r'
           AND c.relname IN ({$placeholders})
         ORDER BY c.relname"
    );
    $statement->execute($expectedTables);
    $rows = $statement->fetchAll();

    $actual = [];
    foreach ($rows as $row) {
        $actual[$row['table_name']] = in_array(
            $row['rls_enabled'],
            [true, 1, '1', 't', 'true'],
            true
        );
    }

    $missing = array_values(array_diff($expectedTables, array_keys($actual)));
    $rlsDisabled = array_keys(array_filter($actual, static fn (bool $enabled): bool => !$enabled));

    echo "Supabase connection: OK\n";
    echo 'Expected tables: ' . count($expectedTables) . "\n";
    echo 'Found tables: ' . count($actual) . "\n";
    echo 'RLS enabled: ' . (count($actual) - count($rlsDisabled)) . '/' . count($actual) . "\n";

    if ($missing !== []) {
        fwrite(STDERR, 'Missing tables: ' . implode(', ', $missing) . "\n");
    }
    if ($rlsDisabled !== []) {
        fwrite(STDERR, 'RLS disabled: ' . implode(', ', $rlsDisabled) . "\n");
    }

    exit($missing === [] && $rlsDisabled === [] ? 0 : 1);
} catch (Throwable $error) {
    fwrite(STDERR, 'Supabase connection failed: ' . $error->getMessage() . "\n");
    exit(1);
}
