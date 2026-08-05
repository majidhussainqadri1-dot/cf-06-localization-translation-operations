<?php
/**
 * Conservative uninstall: preserve operational data unless an explicit, reviewed
 * destruction constant is enabled before uninstalling the plugin.
 */

declare(strict_types=1);

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

if (! defined('SABRI_SLTO_REMOVE_DATA_ON_UNINSTALL') || true !== SABRI_SLTO_REMOVE_DATA_ON_UNINSTALL) {
    return;
}

global $wpdb;

foreach (array('slto_audit_events', 'slto_resources', 'slto_locales') as $suffix) {
    $table = $wpdb->prefix . $suffix;
    $wpdb->query("DROP TABLE IF EXISTS {$table}"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

delete_option('slto_schema_version');
delete_option('slto_runtime_enabled');
delete_option('slto_default_locale');
