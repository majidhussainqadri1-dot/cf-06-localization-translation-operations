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

    /**
     * Conservative locale/domain invalidation for terminology or style-policy
     * changes. It never changes canonical source records; it only removes stale
     * translation projections from release eligibility until re-review.
     */
    public static function invalidateLocaleDomain(string $locale,string $domain,string $reason): array
    {
        global $wpdb;
        $units=Database::table('units');$resources=Database::table('resources');$links=Database::table('content_links');$bundles=Database::table('bundles');
        $resourceRows=$wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT r.uuid FROM {$resources} r JOIN {$units} u ON u.resource_uuid=r.uuid WHERE r.status='active' AND r.domain_name=%s AND u.target_locale=%s",
            $domain,$locale
        ));
        if(''!==(string)$wpdb->last_error){throw new RuntimeException('Locale/domain policy dependency inventory could not be read.');}
        $resourceUuids=array_values(array_unique(array_map('strval',is_array($resourceRows)?$resourceRows:array())));
        if(empty($resourceUuids)){return array('resources'=>0,'stale_units'=>0,'stale_content_links'=>0,'invalidated_bundles'=>0);}

        $ph=implode(',',array_fill(0,count($resourceUuids),'%s'));$now=Database::now();
        $stale=$wpdb->query($wpdb->prepare(
            "UPDATE {$units} SET status='stale',stale_reason=%s,row_version=row_version+1,updated_at=%s WHERE target_locale=%s AND resource_uuid IN ({$ph}) AND status NOT IN ('new','retired','stale')",
            $reason,$now,$locale,...$resourceUuids
        ));
        if(false===$stale){throw new RuntimeException('Locale/domain dependent units could not be marked stale.');}
        $staleLinks=$wpdb->query($wpdb->prepare(
            "UPDATE {$links} SET publication_status='stale',row_version=row_version+1,updated_at=%s WHERE target_locale=%s AND resource_uuid IN ({$ph}) AND publication_status IN ('review','approved','published')",
            $now,$locale,...$resourceUuids
        ));
        if(false===$staleLinks){throw new RuntimeException('Locale/domain content relationships could not be marked stale.');}

        $unitRows=$wpdb->get_col($wpdb->prepare(
            "SELECT uuid FROM {$units} WHERE target_locale=%s AND resource_uuid IN ({$ph})",
            $locale,...$resourceUuids
        ));
        if(''!==(string)$wpdb->last_error){throw new RuntimeException('Locale/domain unit dependency inventory could not be read.');}
        $unitSet=array_fill_keys(array_map('strval',is_array($unitRows)?$unitRows:array()),true);
        $active=$wpdb->get_results($wpdb->prepare("SELECT uuid,source_list_json FROM {$bundles} WHERE locale_tag=%s AND status='active'",$locale),ARRAY_A);
        if(''!==(string)$wpdb->last_error){throw new RuntimeException('Locale/domain active bundle inventory could not be read.');}
        $invalidated=0;
        foreach(is_array($active)?$active:array() as $bundle){
            try{$sources=json_decode((string)$bundle['source_list_json'],true,128,JSON_THROW_ON_ERROR);}catch(\JsonException $exception){throw new RuntimeException('Locale/domain active bundle source evidence is malformed.',0,$exception);}
            $affected=false;
            foreach(is_array($sources)?$sources:array() as $source){
                if(is_array($source)&&isset($unitSet[(string)($source['unit_uuid']??'')])){$affected=true;break;}
            }
            if(!$affected){continue;}
            $changed=$wpdb->query($wpdb->prepare(
                "UPDATE {$bundles} SET status='invalidated',row_version=row_version+1,updated_at=%s WHERE uuid=%s AND status='active'",
                $now,(string)$bundle['uuid']
            ));
            if(false===$changed){throw new RuntimeException('Locale/domain active bundle could not be invalidated.');}
            $invalidated+=(int)$changed;
        }
        return array('resources'=>count($resourceUuids),'stale_units'=>(int)$stale,'stale_content_links'=>(int)$staleLinks,'invalidated_bundles'=>$invalidated);
    }
}
