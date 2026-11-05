<?php
/** CLI-only bootstrap for an already-created cloud database (e.g. defaultdb). */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}
require_once dirname(__DIR__) . '/config/database.php';
$db = create_connect(false);
if (!$db) {
    fwrite(STDERR, "Database unavailable. Check DB_HOST/PORT/NAME/USER/PASSWORD and TLS configuration.\n");
    exit(1);
}

function apply_schema_file(mysqli $db, string $file): void {
    $sql = file_get_contents($file);
    if ($sql === false) throw new RuntimeException('Cannot read schema file.');
    // Managed database users may not have CREATE DATABASE permission. Use the
    // configured database, never the hard-coded name in the original export.
    $sql = preg_replace('/^\s*(?:CREATE DATABASE\b[^;]*|USE\s+`?notezy`?)\s*;\s*$/mi', '', $sql);
    if (!$db->multi_query($sql)) throw new RuntimeException('Schema statement failed: ' . $db->error);
    do {
        if ($result = $db->store_result()) $result->free();
        if (!$db->more_results()) break;
        if (!$db->next_result()) throw new RuntimeException('Schema statement failed: ' . $db->error);
    } while (true);
}

$lock = $db->query("SELECT GET_LOCK('notezy_cloud_schema', 20) AS acquired");
if (!$lock || (int) $lock->fetch_assoc()['acquired'] !== 1) {
    fwrite(STDERR, "Another schema initialization is running. Try again later.\n");
    exit(1);
}
try {
    $tables = $db->query("SHOW TABLES LIKE 'users'");
    if (!$tables) throw new RuntimeException('Cannot inspect schema.');
    if ($tables->num_rows === 0) {
        // Never replay the baseline ALTER statements over an existing database.
        $existing = $db->query('SHOW TABLES');
        if (!$existing || $existing->num_rows !== 0) {
            throw new RuntimeException('Database is not empty but users table is missing; inspect it before import.');
        }
        apply_schema_file($db, dirname(__DIR__) . '/note.sql');
    }
    apply_schema_file($db, dirname(__DIR__) . '/migrations.sql');
    apply_schema_file($db, dirname(__DIR__) . '/services/db/premium.sql');
    echo "Notezy schema is ready in the configured database.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
} finally {
    $db->query("SELECT RELEASE_LOCK('notezy_cloud_schema')");
}
