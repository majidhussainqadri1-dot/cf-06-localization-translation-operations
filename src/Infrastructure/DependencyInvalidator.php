<?php

declare(strict_types=1);

namespace Sabri\Localization\Infrastructure;

use RuntimeException;

/**
 * Cross-projection invalidation kept inside infrastructure so application services
 * do not become owners of translated publication truth. Domain owners still decide
 * correction/publication; CF-06 only makes stale relationships and stale bundles
 * ineligible for delivery.
 */
final class DependencyInvalidator
{
    public static function markContentLinksStale(string $resourceUuid): int
    {
        global $wpdb;
        $table = Database::table('content_links');
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET publication_status='stale', row_version=row_version+1, updated_at=%s WHERE resource_uuid=%s AND publication_status IN ('review','approved','published')",
            Database::now(),
            $resourceUuid
        ));
        if (false === $updated) {
            throw new RuntimeException('Dependent content translation relationships could not be marked stale.');
        }
        return (int) $updated;
    }

    /**
     * Containment path for source correction/rights expiry. An invalidated bundle
     * is intentionally not a rollback candidate and is excluded by activeBundle().
     * A newly reviewed signed bundle must pass the normal staged release flow.
     */
    public static function invalidateActiveBundles(string $resourceUuid): int
    {
        global $wpdb;
        $unitsTable = Database::table('units');
        $bundlesTable = Database::table('bundles');
        $unitUuids = $wpdb->get_col($wpdb->prepare("SELECT uuid FROM {$unitsTable} WHERE resource_uuid=%s", $resourceUuid));
        if ('' !== (string) $wpdb->last_error) {
            throw new RuntimeException('Dependent translation-unit inventory could not be read.');
        }
        if (! is_array($unitUuids) || empty($unitUuids)) {
            return 0;
        }
        $unitSet = array_fill_keys(array_map('strval', $unitUuids), true);
        $bundles = $wpdb->get_results("SELECT uuid,source_list_json FROM {$bundlesTable} WHERE status='active'", ARRAY_A);
        if ('' !== (string) $wpdb->last_error) {
            throw new RuntimeException('Active locale-bundle inventory could not be read.');
        }

        $invalidated = 0;
        foreach (is_array($bundles) ? $bundles : array() as $bundle) {
            try {
                $sources = json_decode((string) $bundle['source_list_json'], true, 128, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new RuntimeException('Active locale-bundle source evidence is malformed.', 0, $e);
            }
            $affected = false;
            foreach (is_array($sources) ? $sources : array() as $source) {
                if (is_array($source) && isset($unitSet[(string) ($source['unit_uuid'] ?? '')])) {
                    $affected = true;
                    break;
                }
            }
            if (! $affected) {
                continue;
            }
            $changed = $wpdb->query($wpdb->prepare(
                "UPDATE {$bundlesTable} SET status='invalidated', row_version=row_version+1, updated_at=%s WHERE uuid=%s AND status='active'",
                Database::now(),
                (string) $bundle['uuid']
            ));
            if (false === $changed) {
                throw new RuntimeException('Stale active locale bundle could not be invalidated.');
            }
            $invalidated += (int) $changed;
        }
        return $invalidated;
    }
}
