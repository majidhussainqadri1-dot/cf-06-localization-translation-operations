<?php

declare(strict_types=1);

namespace Sabri\Localization\Admin;

use Sabri\Localization\Application\LocaleService;
use Sabri\Localization\Plugin;

final class AdminPage
{
    public function __construct(private readonly LocaleService $locales)
    {
    }

    public function registerHooks(): void
    {
        add_action('admin_menu', array($this, 'registerMenu'));
    }

    public function registerMenu(): void
    {
        add_management_page(
            __('Localization Operations', 'sabri-localization-translation-operations'),
            __('Localization', 'sabri-localization-translation-operations'),
            'manage_sabri_localization',
            'sabri-localization-operations',
            array($this, 'render')
        );
    }

    public function render(): void
    {
        if (! current_user_can('manage_sabri_localization')) {
            wp_die(esc_html__('You are not authorized to view this page.', 'sabri-localization-translation-operations'));
        }
        $runtime = Plugin::runtimeEnabled();
        $locales = $this->locales->all(false);
        ?>
        <div class="wrap slto-admin">
            <h1><span class="dashicons dashicons-translation" aria-hidden="true"></span> <?php echo esc_html__('Localization and Translation Operations', 'sabri-localization-translation-operations'); ?></h1>
            <div class="notice notice-warning inline">
                <p><strong><?php echo esc_html__('Truthful status:', 'sabri-localization-translation-operations'); ?></strong>
                    <?php echo esc_html__('Foundation coding is present, but the conditional runtime is not staging-accepted or production-approved.', 'sabri-localization-translation-operations'); ?>
                </p>
            </div>
            <table class="widefat striped" role="table">
                <tbody>
                    <tr><th scope="row"><?php echo esc_html__('Plugin version', 'sabri-localization-translation-operations'); ?></th><td><?php echo esc_html(SABRI_SLTO_VERSION); ?></td></tr>
                    <tr><th scope="row"><?php echo esc_html__('Schema version', 'sabri-localization-translation-operations'); ?></th><td><?php echo esc_html((string) get_option('slto_schema_version', '')); ?></td></tr>
                    <tr><th scope="row"><?php echo esc_html__('Conditional runtime', 'sabri-localization-translation-operations'); ?></th><td><?php echo $runtime ? esc_html__('Enabled', 'sabri-localization-translation-operations') : esc_html__('Disabled by default', 'sabri-localization-translation-operations'); ?></td></tr>
                    <tr><th scope="row"><?php echo esc_html__('Current implementation phase', 'sabri-localization-translation-operations'); ?></th><td><?php echo esc_html__('C6-B foundation: locale and resource registry', 'sabri-localization-translation-operations'); ?></td></tr>
                </tbody>
            </table>
            <h2><?php echo esc_html__('Locale registry', 'sabri-localization-translation-operations'); ?></h2>
            <table class="widefat striped" role="table">
                <thead><tr><th><?php echo esc_html__('Locale', 'sabri-localization-translation-operations'); ?></th><th><?php echo esc_html__('Direction', 'sabri-localization-translation-operations'); ?></th><th><?php echo esc_html__('Fallback', 'sabri-localization-translation-operations'); ?></th><th><?php echo esc_html__('Status', 'sabri-localization-translation-operations'); ?></th></tr></thead>
                <tbody>
                <?php foreach ($locales as $locale) : ?>
                    <tr>
                        <td><code><?php echo esc_html((string) $locale['locale_tag']); ?></code></td>
                        <td><?php echo esc_html(strtoupper((string) $locale['direction'])); ?></td>
                        <td><?php echo esc_html((string) ($locale['fallback_tag'] ?: '—')); ?></td>
                        <td><?php echo esc_html((string) $locale['status']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <style>
            .slto-admin h1 .dashicons { color: #16803a; font-size: 30px; height: 30px; margin-inline-end: 8px; width: 30px; }
            .slto-admin h2 { border-inline-start: 4px solid #16803a; padding-inline-start: 10px; }
        </style>
        <?php
    }
}
