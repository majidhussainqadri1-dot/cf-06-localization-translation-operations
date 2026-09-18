<?php

declare(strict_types=1);

namespace Sabri\Localization\Contract;

/**
 * Compatibility facade. Verified readiness is supplied by IntegrationService;
 * bare booleans from filters are never accepted as release evidence.
 */
final class IntegrationRegistry
{
    public static function required(): array
    {
        return array(
            'file00_membership',
            'file19_notifications',
            'file20_shell',
            'file22_composer',
            'file23_dashboard',
            'file24_assurance',
            'file25_visual',
            'file26_search',
            'domain_contracts',
            'unicode_cldr_icu',
        );
    }

    public static function publishManifest(): void
    {
        do_action('sabri_module_manifest_registered', Manifest::get());
    }
}
