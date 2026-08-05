<?php

declare(strict_types=1);

namespace Sabri\Localization\Infrastructure\Repository;

use RuntimeException;

final class ResourceRepository
{
    public function find(string $key): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'slto_resources';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE resource_key = %s", $key), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    public function upsert(array $record, int $actorId): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'slto_resources';
        $existing = $this->find((string) $record['resource_key']);
        $now = current_time('mysql', true);
        if ($existing && hash_equals((string) $existing['source_hash'], (string) $record['source_hash'])) {
            return array('id' => (int) $existing['id'], 'version' => (int) $existing['source_version'], 'changed' => false);
        }
        $version = $existing ? ((int) $existing['source_version'] + 1) : 1;
        $data = array_merge($record, array('source_version' => $version, 'updated_by' => $actorId, 'updated_at' => $now));
        if ($existing) {
            if (false === $wpdb->update($table, $data, array('id' => (int) $existing['id']))) {
                throw new RuntimeException('Resource catalog update failed.');
            }
            return array('id' => (int) $existing['id'], 'version' => $version, 'changed' => true);
        }
        $data['created_by'] = $actorId;
        $data['created_at'] = $now;
        if (false === $wpdb->insert($table, $data)) {
            throw new RuntimeException('Resource catalog insert failed.');
        }
        return array('id' => (int) $wpdb->insert_id, 'version' => $version, 'changed' => true);
    }
}
