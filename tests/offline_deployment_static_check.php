<?php
$root = dirname(__DIR__);
$checks = [
    'offline store exported globally' => ['js/offline-store.js', 'window.NotezyOffline'],
    'indexeddb notes store' => ['js/offline-store.js', "createObjectStore('notes'"],
    'indexeddb labels store' => ['js/offline-store.js', "createObjectStore('labels'"],
    'indexeddb sync queue' => ['js/offline-store.js', "createObjectStore('sync_queue'"],
    'offline create queue' => ['js/offline-store.js', 'CREATE_NOTE'],
    'offline update queue' => ['js/offline-store.js', 'UPDATE_NOTE'],
    'offline delete queue' => ['js/offline-store.js', 'DELETE_NOTE'],
    'offline label queue' => ['js/offline-store.js', 'ADD_LABEL'],
    'offline conflict message' => ['js/offline-store.js', 'Note has been changed on another device.'],
    'api conflict status' => ['api/notes.php', 'http_response_code(409)'],
    'service worker version' => ['sw.js', 'notezy-v4'],
    'health endpoint exists' => ['health.php', "'database' => 'connected'"],
    'docker healthcheck' => ['docker-compose.yml', 'http://localhost/health.php'],
    'env example exists' => ['.env.example', 'DB_HOST='],
    'deployment procedure exists' => ['OFFLINE_TEST_PROCEDURE.md', 'sync_queue'],
];

$failed = [];
foreach ($checks as $name => [$file, $needle]) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
    $content = is_file($path) ? file_get_contents($path) : '';
    if ($content === false || strpos($content, $needle) === false) {
        $failed[] = $name . " ($file)";
    } else {
        echo "PASS: $name\n";
    }
}

if ($failed) {
    echo "\nFAILED:\n";
    foreach ($failed as $item) echo "- $item\n";
    exit(1);
}

echo "\nAll offline/deployment static checks passed.\n";
exit(0);
?>
