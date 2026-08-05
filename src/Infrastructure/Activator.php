<?php

declare(strict_types=1);

namespace Sabri\Localization\Infrastructure;

use Sabri\Localization\Domain\Locale\LocaleValidator;

final class Activator
{
    public static function maybeUpgrade(): void
    {
        $installed = (string) get_option('slto_schema_version', '0.0.0');
        if (version_compare($installed, SABRI_SLTO_SCHEMA_VERSION, '>=')) {
            return;
        }

        if (get_transient('slto_schema_upgrade_lock')) {
            return;
        }

        set_transient('slto_schema_upgrade_lock', 1, 5 * MINUTE_IN_SECONDS);
        try {
            self::activate();
        } finally {
            delete_transient('slto_schema_upgrade_lock');
        }
    }

    public static function activate(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charsetCollate = $wpdb->get_charset_collate();
        $localesTable   = $wpdb->prefix . 'slto_locales';
        $resourcesTable = $wpdb->prefix . 'slto_resources';
        $auditTable     = $wpdb->prefix . 'slto_audit_events';

        dbDelta(
            "CREATE TABLE {$localesTable} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                locale_tag varchar(35) NOT NULL,
                language_subtag varchar(8) NOT NULL,
                script_subtag varchar(8) NOT NULL DEFAULT '',
                region_subtag varchar(8) NOT NULL DEFAULT '',
                direction varchar(3) NOT NULL DEFAULT 'ltr',
                fallback_tag varchar(35) NULL,
                plural_rules_version varchar(40) NOT NULL DEFAULT '',
                format_data_version varchar(40) NOT NULL DEFAULT '',
                enabled tinyint(1) NOT NULL DEFAULT 0,
                status varchar(20) NOT NULL DEFAULT 'planned',
                owner varchar(191) NOT NULL DEFAULT '',
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY locale_tag (locale_tag),
                KEY enabled_status (enabled,status)
            ) {$charsetCollate};"
        );

        dbDelta(
            "CREATE TABLE {$resourcesTable} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                resource_key varchar(191) NOT NULL,
                source_locale varchar(35) NOT NULL,
                source_text longtext NOT NULL,
                source_version bigint(20) unsigned NOT NULL DEFAULT 1,
                source_hash char(64) NOT NULL,
                context longtext NULL,
                description text NULL,
                domain_name varchar(80) NOT NULL DEFAULT 'platform',
                risk_class varchar(20) NOT NULL DEFAULT 'low',
                data_class varchar(3) NOT NULL DEFAULT 'C1',
                placeholders longtext NULL,
                references_json longtext NULL,
                status varchar(20) NOT NULL DEFAULT 'active',
                created_by bigint(20) unsigned NOT NULL DEFAULT 0,
                updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY resource_key (resource_key),
                KEY source_locale (source_locale),
                KEY domain_risk (domain_name,risk_class),
                KEY status (status)
            ) {$charsetCollate};"
        );

        dbDelta(
            "CREATE TABLE {$auditTable} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                trace_id char(36) NOT NULL,
                object_type varchar(40) NOT NULL,
                object_key varchar(191) NOT NULL,
                action_name varchar(80) NOT NULL,
                actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
                result varchar(20) NOT NULL,
                payload_hash char(64) NOT NULL DEFAULT '',
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY trace_id (trace_id),
                KEY object_lookup (object_type,object_key),
                KEY created_at (created_at)
            ) {$charsetCollate};"
        );

        update_option('slto_schema_version', SABRI_SLTO_SCHEMA_VERSION, false);
        add_option('slto_runtime_enabled', false, '', false);
        add_option('slto_default_locale', 'en-US', '', false);

        self::seedLocales($localesTable);
        self::grantCapabilities();
    }

    private static function seedLocales(string $table): void
    {
        global $wpdb;

        $now = current_time('mysql', true);

        $seeds = array(
            array('tag' => 'en-US', 'direction' => 'ltr', 'fallback' => null, 'enabled' => 1, 'status' => 'enabled'),
            array('tag' => 'ur-PK', 'direction' => 'rtl', 'fallback' => 'en-US', 'enabled' => 1, 'status' => 'enabled'),
            array('tag' => 'ar', 'direction' => 'rtl', 'fallback' => 'en-US', 'enabled' => 0, 'status' => 'planned'),
        );

        foreach ($seeds as $seed) {
            $parsed = LocaleValidator::parse($seed['tag']);
            if (null === $parsed) {
                continue;
            }

            $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$table}
                    (locale_tag, language_subtag, script_subtag, region_subtag, direction, fallback_tag, plural_rules_version, format_data_version, enabled, status, owner, created_at, updated_at)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %d, %s, %s, %s, %s)
                    ON DUPLICATE KEY UPDATE
                        direction = VALUES(direction),
                        fallback_tag = VALUES(fallback_tag),
                        updated_at = VALUES(updated_at)",
                    $parsed['tag'],
                    $parsed['language'],
                    $parsed['script'],
                    $parsed['region'],
                    $seed['direction'],
                    $seed['fallback'],
                    'initial',
                    'initial',
                    $seed['enabled'],
                    $seed['status'],
                    'CF-06',
                    $now,
                    $now
                )
            );
        }
    }

    private static function grantCapabilities(): void
    {
        $administrator = get_role('administrator');
        if (! $administrator) {
            return;
        }

        foreach (array('manage_sabri_localization', 'review_sabri_localization', 'release_sabri_localization') as $capability) {
            $administrator->add_cap($capability);
        }
    }
}
