<?php

declare(strict_types=1);

namespace Sabri\Localization\Infrastructure;

use RuntimeException;

final class JobQueue
{
    private array $handlers = array();

    public function register(string $type, callable $handler): void
    {
        $type = sanitize_key($type);
        if ('' === $type || strlen($type) > 80) {
            throw new RuntimeException('Localization job type is invalid or exceeds the schema bound.');
        }
        $this->handlers[$type] = $handler;
    }

    public function enqueue(string $type, string $dedupeKey, array $payload, ?string $availableAt = null, int $maxAttempts = 5): string
    {
        global $wpdb;
        $type = sanitize_key($type);
        $dedupeKey = trim($dedupeKey);
        if ('' === $type || strlen($type) > 80 || '' === $dedupeKey || strlen($dedupeKey) > 191) {
            throw new RuntimeException('Localization job identity is invalid.');
        }
        $json = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (! is_string($json) || strlen($json) > 1048576) {
            throw new RuntimeException('Localization job payload is invalid or oversized.');
        }
        $scheduledAt = $availableAt ?: Database::now();
        if (false === strtotime($scheduledAt)) {
            throw new RuntimeException('Localization job availability timestamp is invalid.');
        }
        $scheduledAt = gmdate('Y-m-d H:i:s', strtotime($scheduledAt));
        $table = Database::table('jobs');
        $uuid = Database::uuid();
        $ok = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (uuid,job_type,dedupe_key,payload_json,status,attempts,max_attempts,available_at,created_at,updated_at) VALUES (%s,%s,%s,%s,'queued',0,%d,%s,%s,%s) ON DUPLICATE KEY UPDATE uuid=uuid",
            $uuid, $type, $dedupeKey, $json, max(1, min(20, $maxAttempts)), $scheduledAt, Database::now(), Database::now()
        ));
        if (false === $ok || '' !== (string)$wpdb->last_error) {
            throw new RuntimeException('Localization job could not be queued.');
        }
        $stored = $wpdb->get_row($wpdb->prepare("SELECT uuid,payload_json FROM {$table} WHERE job_type=%s AND dedupe_key=%s LIMIT 1", $type, $dedupeKey), ARRAY_A);
        if ('' !== (string)$wpdb->last_error || ! is_array($stored) || '' === (string)($stored['uuid']??'')) {
            throw new RuntimeException('Localization job identity could not be verified.');
        }
        if (! hash_equals(hash('sha256',$json), hash('sha256',(string)($stored['payload_json']??'')))) {
            throw new RuntimeException('Localization job dedupe key was reused with a different payload.');
        }
        return (string)$stored['uuid'];
    }

    public function run(int $limit = 20): array
    {
        global $wpdb;
        $limit = max(1, min(200, $limit));
        $table = Database::table('jobs');
        $worker = 'wp-' . substr(hash('sha256', php_uname('n') . '|' . getmypid()), 0, 16);
        $now = Database::now();
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE available_at<=%s AND ((status IN ('queued','retry') AND attempts<max_attempts AND (lease_until IS NULL OR lease_until<%s)) OR (status='running' AND lease_until IS NOT NULL AND lease_until<%s)) ORDER BY id ASC LIMIT %d",
            $now, $now, $now, $limit
        ), ARRAY_A);
        if ('' !== (string)$wpdb->last_error || ! is_array($rows)) {
            throw new RuntimeException('Localization job queue could not be read.');
        }
        $summary = array('completed'=>0, 'retried'=>0, 'dead_letter'=>0, 'unsupported'=>0, 'reclaimed'=>0);
        foreach ($rows as $row) {
            $wasRunning = 'running' === (string)$row['status'];
            if ($wasRunning && ((int)$row['attempts'] + 1) >= (int)$row['max_attempts']) {
                $dead = $wpdb->update($table, array(
                    'status'=>'dead_letter',
                    'attempts'=>(int)$row['attempts'] + 1,
                    'lease_owner'=>null,
                    'lease_until'=>null,
                    'last_error'=>'RuntimeException:' . hash('sha256', 'expired_worker_lease'),
                    'updated_at'=>Database::now(),
                ), array('uuid'=>$row['uuid'],'status'=>'running','lease_until'=>$row['lease_until']));
                if (false === $dead || '' !== (string)$wpdb->last_error) {
                    throw new RuntimeException('Expired localization job could not be moved to dead letter.');
                }
                if (1 === $dead) {
                    ++$summary['reclaimed'];
                    ++$summary['dead_letter'];
                }
                continue;
            }
            $leased = $wpdb->query($wpdb->prepare(
                "UPDATE {$table} SET status='running',lease_owner=%s,lease_until=%s,attempts=attempts+IF(status='running',1,0),updated_at=%s WHERE uuid=%s AND ((status IN ('queued','retry') AND attempts<max_attempts AND (lease_until IS NULL OR lease_until<%s)) OR (status='running' AND lease_until IS NOT NULL AND lease_until<%s))",
                $worker, gmdate('Y-m-d H:i:s', time() + 300), Database::now(), $row['uuid'], $now, $now
            ));
            if (false === $leased || '' !== (string)$wpdb->last_error) {
                throw new RuntimeException('Localization job lease could not be persisted.');
            }
            if (1 !== $leased) {
                continue;
            }
            if ($wasRunning) {
                ++$summary['reclaimed'];
            }
            try {
                if (! isset($this->handlers[$row['job_type']])) {
                    ++$summary['unsupported'];
                    throw new RuntimeException('No localization job handler is registered.');
                }
                $decoded = json_decode((string) $row['payload_json'], true, 128, JSON_THROW_ON_ERROR);
                if (! is_array($decoded)) {
                    throw new RuntimeException('Localization job payload is not an object.');
                }
                ($this->handlers[$row['job_type']])($decoded, $row);
                $saved = $wpdb->update($table, array('status'=>'completed','lease_owner'=>null,'lease_until'=>null,'updated_at'=>Database::now()), array('uuid'=>$row['uuid'],'status'=>'running','lease_owner'=>$worker));
                if (1 !== $saved || '' !== (string)$wpdb->last_error) {
                    throw new RuntimeException('Localization job completion could not be acknowledged.');
                }
                ++$summary['completed'];
            } catch (\Throwable $e) {
                $currentAttemptsValue = $wpdb->get_var($wpdb->prepare("SELECT attempts FROM {$table} WHERE uuid=%s", $row['uuid']));
                if ('' !== (string)$wpdb->last_error || null === $currentAttemptsValue) {
                    throw new RuntimeException('Localization job attempt state could not be read.', 0, $e);
                }
                $currentAttempts = (int)$currentAttemptsValue;
                $attempts = $wasRunning ? max($currentAttempts, (int)$row['attempts'] + 1) : (int)$row['attempts'] + 1;
                $dead = $attempts >= (int) $row['max_attempts'];
                $saved = $wpdb->update($table, array(
                    'status'=>$dead?'dead_letter':'retry',
                    'attempts'=>$attempts,
                    'available_at'=>gmdate('Y-m-d H:i:s', time() + min(21600, 2 ** min(20, $attempts))),
                    'lease_owner'=>null,
                    'lease_until'=>null,
                    'last_error'=>get_class($e) . ':' . hash('sha256', $e->getMessage()),
                    'updated_at'=>Database::now(),
                ), array('uuid'=>$row['uuid'],'status'=>'running','lease_owner'=>$worker));
                if (false === $saved || 0 === $saved || '' !== (string)$wpdb->last_error) {
                    throw new RuntimeException('Localization job failure state could not be persisted.', 0, $e);
                }
                ++$summary[$dead?'dead_letter':'retried'];
            }
        }
        return $summary;
    }
}
