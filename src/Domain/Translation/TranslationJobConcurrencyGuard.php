<?php

declare(strict_types=1);

namespace Sabri\Localization\Domain\Translation;

use DomainException;
use RuntimeException;
use Sabri\Localization\Infrastructure\Database;

/**
 * Prevent a second active project unit for one canonical resource/source-version/locale.
 *
 * Call ONLY inside the same open InnoDB transaction that inserts project/units.
 * Locks all source rows in deterministic order. No schema migration and no global
 * site-wide mutex. All legitimate creators must enter through ProjectService.
 */
final class TranslationJobConcurrencyGuard
{
    public static function assertAvailable(array $resources, array $targetLocales, string $sourceLocale): void
    {
        global $wpdb;

        if ($resources === [] || $targetLocales === [] || count($resources) > 5000
            || count($targetLocales) > 25 || count(array_unique($targetLocales)) !== count($targetLocales)) {
            throw new \InvalidArgumentException('Translation job guard requires a bounded, distinct scope.');
        }

        $tables = [
            'resources' => Database::table('resources'),
            'units' => Database::table('units'),
            'projects' => Database::table('projects'),
        ];

        // A row lock on MyISAM is not a transaction and cannot prove uniqueness.
        foreach ($tables as $name => $table) {
            $status = $wpdb->get_row(
                $wpdb->prepare('SHOW TABLE STATUS LIKE %s', $wpdb->esc_like($table)), ARRAY_A
            );
            if ('' !== (string)$wpdb->last_error || !is_array($status)
                || 'innodb' !== strtolower((string)($status['Engine'] ?? ''))) {
                throw new RuntimeException('Translation job guard requires verified transactional InnoDB tables: '.$name);
            }
        }

        $sorted = [];
        foreach ($resources as $resource) {
            if (!is_array($resource) || !isset($resource['uuid'], $resource['source_version'], $resource['source_hash'])) {
                throw new \InvalidArgumentException('Translation job resource snapshot is incomplete.');
            }
            $uuid = (string)$resource['uuid'];
            if (isset($sorted[$uuid]) || !preg_match('/^[a-f0-9-]{36}$/D', $uuid)) {
                throw new \InvalidArgumentException('Translation job resource UUID is duplicated or malformed.');
            }
            $sorted[$uuid] = $resource;
        }
        ksort($sorted, SORT_STRING);

        // Lock every canonical source row before any conflict query or insertion.
        // Overlapping projects serialize on at least one shared resource row.
        $locked = [];
        foreach ($sorted as $uuid => $snapshot) {
            $current = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT uuid,source_locale,source_version,source_hash,status FROM {$tables['resources']} WHERE uuid=%s FOR UPDATE",
                    $uuid
                ), ARRAY_A
            );
            if ('' !== (string)$wpdb->last_error) {
                throw new RuntimeException('Source row lock failed: project creation aborted.');
            }
            if (!is_array($current) || 'active' !== (string)$current['status']
                || $sourceLocale !== (string)$current['source_locale']
                || (int)$snapshot['source_version'] !== (int)$current['source_version']
                || !hash_equals((string)$snapshot['source_hash'], (string)$current['source_hash'])) {
                throw new \InvalidArgumentException('Translation source changed before locked project creation.');
            }
            $locked[] = $current;
        }

        $placeholders = implode(',', array_fill(0, count($targetLocales), '%s'));
        foreach ($locked as $row) {
            $params = array_merge(
                [(string)$row['uuid'], (int)$row['source_version'], (string)$row['source_hash']],
                $targetLocales
            );
            $query = $wpdb->prepare(
                "SELECT u.uuid FROM {$tables['units']} u
                 INNER JOIN {$tables['projects']} p ON p.uuid=u.project_uuid
                 WHERE u.resource_uuid=%s AND u.source_version=%d AND u.source_hash=%s
                   AND u.target_locale IN ({$placeholders})
                   AND u.status NOT IN ('stale','retired','released')
                   AND p.status IN ('draft','active','paused')
                 LIMIT 1 FOR UPDATE",
                ...$params
            );
            $existing = $wpdb->get_var($query);
            if ('' !== (string)$wpdb->last_error) {
                throw new RuntimeException('Existing translation job query failed: project creation aborted.');
            }
            if (null !== $existing) {
                throw new DomainException('translation_job_already_active');
            }
        }
    }
}
