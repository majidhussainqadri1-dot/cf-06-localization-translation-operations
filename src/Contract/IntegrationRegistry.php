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
            'file20_shell',
            'file24_assurance',
            'file25_visual',
            'file26_search',
            'domain_contracts',
        );
    }

    public static function publishManifest(): void
    {
        do_action('sabri_module_manifest_registered', Manifest::get());
    }
}
