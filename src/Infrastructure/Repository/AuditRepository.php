<?php

declare(strict_types=1);

namespace Sabri\Localization\Infrastructure\Repository;

use RuntimeException;
use Sabri\Localization\Infrastructure\Database;

final class AuditRepository
{
    /** @var list<string> */
    private static array $traceStack = array();

    public static function withTrace(string $traceId, callable $callback): mixed
    {
        if (1 !== preg_match('/^[a-f0-9-]{36}$/D', $traceId)) {
            throw new RuntimeException('Localization trace identity is invalid.');
        }
        self::$traceStack[] = $traceId;
        try {
            return $callback();
        } finally {
            array_pop(self::$traceStack);
        }
    }

    public static function currentTraceId(): ?string
    {
        if (empty(self::$traceStack)) {
            return null;
        }
        $traceId = end(self::$traceStack);
        return is_string($traceId) ? $traceId : null;
    }

    public function record(string $objectType, string $objectKey, string $action, string $result, array $payload = array(), string $purpose = 'operations', ?string $traceId = null): string
    {
        global $wpdb;
        $table = Database::table('audit');
        $traceId = $traceId ?: self::currentTraceId() ?: Database::uuid();
        $objectType = sanitize_key($objectType);
        $action = sanitize_key($action);
        $purpose = sanitize_key($purpose);
        $result = sanitize_key($result);
        $objectKey = trim($objectKey);
        if (1 !== preg_match('/^[a-f0-9-]{36}$/D', $traceId)
            || '' === $objectType || strlen($objectType) > 40
            || '' === $objectKey || strlen($objectKey) > 191
            || '' === $action || strlen($action) > 80
            || '' === $purpose || strlen($purpose) > 80
            || '' === $result || strlen($result) > 20) {
            throw new RuntimeException('Localization audit identity is invalid or exceeds the canonical schema bound.');
        }
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
                'uuid'=>Database::uuid(),'trace_id'=>$traceId,'object_type'=>$objectType,'object_key'=>$objectKey,
                'action_name'=>$action,'actor_id'=>get_current_user_id(),'purpose'=>$purpose,'result'=>$result,
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
        $forbidden = array('source_text','target_text','password','otp','token','secret','ciphertext','clinical_note','message_body','card_number','authorization','api_key','access_token','refresh_token','credential','cookie','session');
        array_walk_recursive($payload, static function (&$value, $key) use ($forbidden): void {
            $normalized = strtolower((string)$key);
            foreach ($forbidden as $needle) {
                if ($normalized === $needle || str_contains($normalized, $needle)) {
                    $value = '[REDACTED]';
                    break;
                }
            }
        });
        $json = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_string($json) && strlen($json) > 65536) {
            return array('oversize_payload_hash'=>hash('sha256',$json),'bytes'=>strlen($json));
        }
        return $payload;
    }
}
