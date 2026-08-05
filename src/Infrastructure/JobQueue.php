<?php

declare(strict_types=1);

namespace Sabri\Localization\Infrastructure;

use RuntimeException;

final class JobQueue
{
    private array $handlers = array();

    public function register(string $type, callable $handler): void
    {
        $this->handlers[$type] = $handler;
    }

    public function enqueue(string $type, string $dedupeKey, array $payload, ?string $availableAt = null, int $maxAttempts = 5): string
    {
        global $wpdb;
        $table = Database::table('jobs');
        $uuid = Database::uuid();
        $ok = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (uuid,job_type,dedupe_key,payload_json,status,attempts,max_attempts,available_at,created_at,updated_at) VALUES (%s,%s,%s,%s,'queued',0,%d,%s,%s,%s) ON DUPLICATE KEY UPDATE payload_json=VALUES(payload_json),available_at=LEAST(available_at,VALUES(available_at)),updated_at=VALUES(updated_at)",
            $uuid,$type,$dedupeKey,wp_json_encode($payload),$maxAttempts,$availableAt ?: Database::now(),Database::now(),Database::now()
        ));
        if (false === $ok) {
            throw new RuntimeException('Localization job could not be queued.');
        }
        $stored=(string)$wpdb->get_var($wpdb->prepare("SELECT uuid FROM {$table} WHERE dedupe_key=%s LIMIT 1",$dedupeKey));
        return ''!==$stored?$stored:$uuid;
    }

    public function run(int $limit = 20): array
    {
        global $wpdb;
        $table = Database::table('jobs');
        $worker = 'wp-' . substr(hash('sha256',php_uname('n') . '|' . getmypid()),0,16);
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE status IN ('queued','retry') AND available_at<=%s AND (lease_until IS NULL OR lease_until<%s) ORDER BY id ASC LIMIT %d",Database::now(),Database::now(),$limit),ARRAY_A);
        $summary = array('completed'=>0,'retried'=>0,'dead_letter'=>0,'unsupported'=>0);
        foreach (is_array($rows) ? $rows : array() as $row) {
            $leased = $wpdb->query($wpdb->prepare("UPDATE {$table} SET status='running',lease_owner=%s,lease_until=%s,updated_at=%s WHERE uuid=%s AND status IN ('queued','retry') AND (lease_until IS NULL OR lease_until<%s)",$worker,gmdate('Y-m-d H:i:s',time()+300),Database::now(),$row['uuid'],Database::now()));
            if (1 !== $leased) {
                continue;
            }
            try {
                if (! isset($this->handlers[$row['job_type']])) {
                    throw new RuntimeException('No localization job handler is registered.');
                }
                ($this->handlers[$row['job_type']])(json_decode((string)$row['payload_json'],true) ?: array(),$row);
                $wpdb->update($table,array('status'=>'completed','lease_owner'=>null,'lease_until'=>null,'updated_at'=>Database::now()),array('uuid'=>$row['uuid']));
                $summary['completed']++;
            } catch (\Throwable $e) {
                $attempts=(int)$row['attempts']+1;
                $dead=$attempts >= (int)$row['max_attempts'];
                $wpdb->update($table,array('status'=>$dead?'dead_letter':'retry','attempts'=>$attempts,'available_at'=>gmdate('Y-m-d H:i:s',time()+min(21600,2**$attempts)),'lease_owner'=>null,'lease_until'=>null,'last_error'=>get_class($e).':'.hash('sha256',$e->getMessage()),'updated_at'=>Database::now()),array('uuid'=>$row['uuid']));
                $summary[$dead?'dead_letter':'retried']++;
            }
        }
        return $summary;
    }
}
