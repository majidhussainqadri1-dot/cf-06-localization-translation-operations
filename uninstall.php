<?php
/**
 * CF-06 conservative uninstall.
 * Canonical localization data is retained by default. Purge is a separate, owner-approved operation.
 */
if (! defined('WP_UNINSTALL_PLUGIN')) { exit; }
delete_option('slto_runtime_enabled');
delete_option('slto_last_release_status');
wp_clear_scheduled_hook('slto_process_jobs');
wp_clear_scheduled_hook('slto_daily_reconciliation');
// Tables, translations, terminology, audit, provider deletion evidence and rollback bundles are intentionally retained.
