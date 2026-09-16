<?php

declare(strict_types=1);

namespace Sabri\Localization\Infrastructure;

use RuntimeException;

/**
 * Cross-projection invalidation kept inside infrastructure so application services
 * do not become owners of translated publication truth. Domain owners still decide
 * correction/publication; CF-06 only marks its relationship projection stale.
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
}
