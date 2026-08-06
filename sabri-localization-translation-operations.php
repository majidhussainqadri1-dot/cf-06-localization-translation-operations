<?php
/**
 * Plugin Name:       Sabri Localization and Translation Operations
 * Plugin URI:        https://sabrihomeopathy.com/
 * Description:       Conditional localization operations: locale/resource registry, translation workflow, terminology, translation memory, draft-only MT, linguistic QA, signed locale bundles, rollback, privacy and cross-module contracts.
 * Version:           1.0.0-rc.3
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            Dr. Allamah Majid Hussain Sabri Muhaddith Mursheed
 * Text Domain:       sabri-localization-translation-operations
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('SABRI_SLTO_VERSION', '1.0.0-rc.3');
define('SABRI_SLTO_SCHEMA_VERSION', '1.0.1');
define('SABRI_SLTO_CONTRACT_VERSION', '1.1.0');
define('SABRI_SLTO_FILE', __FILE__);
define('SABRI_SLTO_DIR', plugin_dir_path(__FILE__));
define('SABRI_SLTO_URL', plugin_dir_url(__FILE__));

require_once SABRI_SLTO_DIR . 'src/Autoloader.php';
require_once SABRI_SLTO_DIR . 'src/functions.php';

Sabri\Localization\Autoloader::register(SABRI_SLTO_DIR . 'src');

register_activation_hook(SABRI_SLTO_FILE, array(Sabri\Localization\Infrastructure\Activator::class, 'activate'));
register_deactivation_hook(SABRI_SLTO_FILE, array(Sabri\Localization\Infrastructure\Activator::class, 'deactivate'));

add_action('plugins_loaded', static function (): void {
    Sabri\Localization\Plugin::instance()->boot();
});
