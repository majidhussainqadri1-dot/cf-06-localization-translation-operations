<?php

declare(strict_types=1);

namespace Sabri\Localization\Infrastructure\Repository;

use RuntimeException;

final class AuditRepository
{
    public function record(string $objectType, string $objectKey, string $action, string $result, array $safePayload = array()): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'slto_audit_events';
        $json  = wp_json_encode($safePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $inserted = $wpdb->insert(
            $table,
            array(
                'trace_id' => wp_generate_uuid4(),
                'object_type' => sanitize_key($objectType),
                'object_key' => substr(sanitize_text_field($objectKey), 0, 191),
                'action_name' => sanitize_key($action),
                'actor_id' => get_current_user_id(),
                'result' => sanitize_key($result),
                'payload_hash' => hash('sha256', is_string($json) ? $json : ''),
                'created_at' => current_time('mysql', true),
            )
        );
        if (false === $inserted) {
            throw new RuntimeException('Localization audit write failed.');
        }
    }
}
