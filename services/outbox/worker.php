<?php
declare(strict_types=1);

require __DIR__ . '/../shared/bootstrap.php';

const OUTBOX_BATCH_SIZE = 50;
const OUTBOX_IDLE_MICROSECONDS = 500000;

function worker_log(string $event, array $context = []): void {
    error_log(json_encode(['service' => 'outbox_worker', 'event' => $event, 'at' => gmdate('c')] + $context, JSON_UNESCAPED_SLASHES));
}

function publish_outbox_event(array $event): bool {
    $reply = svc_redis_command([
        'XADD', 'notezy:events', 'MAXLEN', '~', '10000', '*',
        'event_id', (string)$event['id'],
        'event_type', $event['event_type'],
        'aggregate_type', $event['aggregate_type'],
        'aggregate_id', (string)$event['aggregate_id'],
        'payload', (string)$event['payload'],
        'created_at', (string)$event['created_at'],
    ]);
    return is_string($reply) && $reply !== '';
}

worker_log('started');
while (true) {
    touch('/tmp/outbox-worker.heartbeat');
    try {
        $db = svc_db();
        $rows = $db->query('SELECT id,aggregate_type,aggregate_id,event_type,payload,created_at FROM service_outbox WHERE published_at IS NULL ORDER BY id LIMIT ' . OUTBOX_BATCH_SIZE)->fetchAll();
        foreach ($rows as $event) {
            if (publish_outbox_event($event)) {
                $done = $db->prepare('UPDATE service_outbox SET published_at=NOW(),attempts=attempts+1,last_error=NULL WHERE id=? AND published_at IS NULL');
                $done->execute([(int)$event['id']]);
            } else {
                $failed = $db->prepare('UPDATE service_outbox SET attempts=attempts+1,last_error=? WHERE id=?');
                $failed->execute(['Redis unavailable', (int)$event['id']]);
                worker_log('publish_failed', ['event_id' => (int)$event['id']]);
                break;
            }
        }
    } catch (Throwable $e) {
        worker_log('loop_failed', ['error' => $e->getMessage()]);
    }
    usleep(OUTBOX_IDLE_MICROSECONDS);
}
