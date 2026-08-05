<?php

declare(strict_types=1);

namespace Sabri\Localization\Infrastructure;

use RuntimeException;

final class Outbox
{
    public function enqueue(string $eventName, string $aggregateType, string $aggregateUuid, array $payload): string
    {
        global $wpdb;
        $table = Database::table('outbox');
        $uuid = Database::uuid();
        $json = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $ok = $wpdb->insert($table, array(
            'uuid'=>$uuid,'event_name'=>$eventName,'aggregate_type'=>$aggregateType,'aggregate_uuid'=>$aggregateUuid,
            'contract_version'=>SABRI_SLTO_CONTRACT_VERSION,'payload_json'=>$json,'payload_hash'=>hash('sha256',(string)$json),
            'status'=>'pending','lease_owner'=>null,'lease_until'=>null,'attempts'=>0,'available_at'=>Database::now(),'created_at'=>Database::now(),
        ));
        if (false === $ok) {
            throw new RuntimeException('Localization outbox write failed.');
        }
        return $uuid;
    }

    public function dispatch(int $limit = 50): int
    {
        global $wpdb;
        $table = Database::table('outbox');
        $now = Database::now();
        $worker = 'outbox-' . substr(hash('sha256', php_uname('n') . '|' . getmypid()), 0, 16);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE status IN ('pending','retry','delivering') AND available_at<=%s AND (lease_until IS NULL OR lease_until<%s) ORDER BY id ASC LIMIT %d",
            $now, $now, max(1, min(500, $limit))
        ), ARRAY_A);
        $count = 0;
        foreach (is_array($rows) ? $rows : array() as $row) {
            $leased = $wpdb->query($wpdb->prepare(
                "UPDATE {$table} SET status='delivering',lease_owner=%s,lease_until=%s WHERE uuid=%s AND status IN ('pending','retry','delivering') AND (lease_until IS NULL OR lease_until<%s)",
                $worker, gmdate('Y-m-d H:i:s', time() + 300), $row['uuid'], $now
            ));
            if (1 !== $leased) {
                continue;
            }
            try {
                $payload = json_decode((string) $row['payload_json'], true) ?: array();
                if (! hash_equals((string) $row['payload_hash'], hash('sha256', (string) $row['payload_json']))) {
                    throw new RuntimeException('Outbox payload integrity check failed.');
                }
                do_action('slto_event', (string) $row['event_name'], $payload, (string) $row['uuid']);
                do_action('slto_event_' . sanitize_key((string) $row['event_name']), $payload, (string) $row['uuid']);
                $ok = $wpdb->update($table, array(
                    'status'=>'delivered','delivered_at'=>Database::now(),'attempts'=>(int)$row['attempts']+1,
                    'lease_owner'=>null,'lease_until'=>null,'last_error'=>null,
                ), array('uuid'=>$row['uuid'],'status'=>'delivering','lease_owner'=>$worker));
                if (1 !== $ok) {
                    throw new RuntimeException('Outbox delivery acknowledgement failed.');
                }
                ++$count;
            } catch (\Throwable $throwable) {
                $attempts = (int) $row['attempts'] + 1;
                $status = $attempts >= 8 ? 'dead_letter' : 'retry';
                $wpdb->update($table, array(
                    'status'=>$status,'attempts'=>$attempts,
                    'available_at'=>gmdate('Y-m-d H:i:s',time()+min(3600,2**$attempts)),
                    'lease_owner'=>null,'lease_until'=>null,
                    'last_error'=>get_class($throwable) . ':' . hash('sha256', $throwable->getMessage()),
                ), array('uuid'=>$row['uuid'],'lease_owner'=>$worker));
            }
        }
        return $count;
    }
}
