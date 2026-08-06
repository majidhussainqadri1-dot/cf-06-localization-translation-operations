<?php

declare(strict_types=1);

namespace Sabri\Localization\Infrastructure\Repository;

use RuntimeException;
use Sabri\Localization\Infrastructure\Database;

final class AuditRepository
{
    public function record(string $objectType, string $objectKey, string $action, string $result, array $payload = array(), string $purpose = 'operations', ?string $traceId = null): string
    {
        global $wpdb;
        $table = Database::table('audit');
        $traceId = $traceId ?: Database::uuid();
        $lockName = $wpdb->prefix . 'slto_audit_chain';
        $locked = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,5)', $lockName));
        if ('' !== (string) $wpdb->last_error || 1 !== (int) $locked) {
            throw new RuntimeException('Localization audit chain lock is unavailable.');
        }
        $primaryError = null;
        try {
            $previousValue = $wpdb->get_var("SELECT event_hash FROM {$table} ORDER BY id DESC LIMIT 1");
            if ('' !== (string) $wpdb->last_error) {
                throw new RuntimeException('Localization audit chain head could not be read.');
            }
            $previous = is_string($previousValue) ? $previousValue : '';
            $payloadJson = wp_json_encode($this->minimize($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (! is_string($payloadJson)) {
                throw new RuntimeException('Localization audit payload could not be encoded.');
            }
            $payloadHash = hash('sha256', $payloadJson);
            $created = Database::now();
            $eventHash = hash('sha256', implode('|', array($previous,$traceId,$objectType,$objectKey,$action,$result,$payloadHash,$created)));
            $ok = $wpdb->insert($table, array(
                'uuid'=>Database::uuid(),'trace_id'=>$traceId,'object_type'=>sanitize_key($objectType),'object_key'=>substr($objectKey,0,191),
                'action_name'=>sanitize_key($action),'actor_id'=>get_current_user_id(),'purpose'=>sanitize_key($purpose),'result'=>sanitize_key($result),
                'payload_hash'=>$payloadHash,'previous_hash'=>$previous,'event_hash'=>$eventHash,'created_at'=>$created,
            ));
            if (false === $ok) {
                throw new RuntimeException('Localization audit evidence could not be recorded.');
            }
            return $traceId;
        } catch (\Throwable $e) {
            $primaryError = $e;
            throw $e;
        } finally {
            $released = $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lockName));
            if (null === $primaryError && ('' !== (string) $wpdb->last_error || 1 !== (int) $released)) {
                throw new RuntimeException('Localization audit chain lock could not be released.');
            }
        }
    }

    private function minimize(array $payload): array
    {
        $forbidden = array('source_text','target_text','password','otp','token','secret','ciphertext','clinical_note','message_body','card_number');
        array_walk_recursive($payload, static function (&$value, $key) use ($forbidden): void {
            if (in_array(strtolower((string)$key), $forbidden, true)) {
                $value = '[REDACTED]';
            }
        });
        $json = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_string($json) && strlen($json) > 65536) {
            return array('oversize_payload_hash'=>hash('sha256',$json),'bytes'=>strlen($json));
        }
        return $payload;
    }
}
