<?php

declare(strict_types=1);

namespace Sabri\Localization\Infrastructure\Repository;

use RuntimeException;
use Sabri\Localization\Infrastructure\Database;
use Throwable;

final class AuditRepository
{
    public function record(string $objectType, string $objectKey, string $action, string $result, array $payload = array(), string $purpose = 'operations', ?string $traceId = null): string
    {
        global $wpdb;
        $table = Database::table('audit');
        $traceId = $traceId ?: Database::uuid();
        $lockName = $wpdb->prefix . 'slto_audit_chain';
        $locked = (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,5)', $lockName));
        if (1 !== $locked) {
            throw new RuntimeException('Localization audit chain lock is unavailable.');
        }
        try {
            $previous = (string) $wpdb->get_var("SELECT event_hash FROM {$table} ORDER BY id DESC LIMIT 1");
            $payloadJson = wp_json_encode($this->minimize($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $payloadHash = hash('sha256', is_string($payloadJson) ? $payloadJson : '');
            $created = Database::now();
            $eventHash = hash('sha256', implode('|', array($previous, $traceId, $objectType, $objectKey, $action, $result, $payloadHash, $created)));
            $ok = $wpdb->insert($table, array(
                'uuid' => Database::uuid(), 'trace_id' => $traceId, 'object_type' => $objectType, 'object_key' => $objectKey,
                'action_name' => $action, 'actor_id' => get_current_user_id(), 'purpose' => $purpose, 'result' => $result,
                'payload_hash' => $payloadHash, 'previous_hash' => $previous, 'event_hash' => $eventHash, 'created_at' => $created,
            ), array('%s','%s','%s','%s','%s','%d','%s','%s','%s','%s','%s','%s'));
            if (false === $ok) {
                throw new RuntimeException('Localization audit evidence could not be recorded.');
            }
            return $traceId;
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lockName));
        }
    }

    private function minimize(array $payload): array
    {
        $forbidden = array('source_text', 'target_text', 'password', 'otp', 'token', 'secret', 'ciphertext', 'clinical_note', 'message_body', 'card_number');
        foreach ($forbidden as $key) {
            unset($payload[$key]);
        }
        // Bound nested operational evidence to prevent accidental log amplification.
        $json = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_string($json) && strlen($json) > 65536) {
            return array('oversize_payload_hash' => hash('sha256', $json), 'bytes' => strlen($json));
        }
        return $payload;
    }
}
