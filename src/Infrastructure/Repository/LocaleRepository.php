<?php

declare(strict_types=1);

namespace Sabri\Localization\Infrastructure\Repository;

use RuntimeException;

final class LocaleRepository
{
    public function all(bool $enabledOnly = false): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'slto_locales';
        $where = $enabledOnly ? " WHERE enabled = 1 AND status = 'enabled'" : '';
        $rows  = $wpdb->get_results("SELECT * FROM {$table}{$where} ORDER BY locale_tag ASC", ARRAY_A);
        return is_array($rows) ? $rows : array();
    }

    public function find(string $tag, bool $enabledOnly = false): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'slto_locales';
        $query = "SELECT * FROM {$table} WHERE locale_tag = %s";
        if ($enabledOnly) {
            $query .= " AND enabled = 1 AND status = 'enabled'";
        }
        $row = $wpdb->get_row($wpdb->prepare($query, $tag), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    public function upsert(array $data): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'slto_locales';
        $now   = current_time('mysql', true);
        $found = $this->find((string) $data['locale_tag']);
        $record = array(
            'locale_tag' => $data['locale_tag'],
            'language_subtag' => $data['language_subtag'],
            'script_subtag' => $data['script_subtag'],
            'region_subtag' => $data['region_subtag'],
            'direction' => $data['direction'],
            'fallback_tag' => $data['fallback_tag'],
            'plural_rules_version' => $data['plural_rules_version'],
            'format_data_version' => $data['format_data_version'],
            'enabled' => $data['enabled'],
            'status' => $data['status'],
            'owner' => $data['owner'],
            'updated_at' => $now,
        );
        if ($found) {
            if (false === $wpdb->update($table, $record, array('id' => (int) $found['id']))) {
                throw new RuntimeException('Locale registry update failed.');
            }
            return;
        }
        $record['created_at'] = $now;
        if (false === $wpdb->insert($table, $record)) {
            throw new RuntimeException('Locale registry insert failed.');
        }
    }

    public function fallbackMap(bool $enabledOnly = false): array
    {
        $map = array();
        foreach ($this->all($enabledOnly) as $locale) {
            $fallback = (string) ($locale['fallback_tag'] ?? '');
            $map[(string) $locale['locale_tag']] = '' === $fallback ? null : $fallback;
        }
        return $map;
    }
}
