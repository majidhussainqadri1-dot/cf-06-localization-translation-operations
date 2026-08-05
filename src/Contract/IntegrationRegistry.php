<?php

declare(strict_types=1);

namespace Sabri\Localization\Contract;

final class IntegrationRegistry
{
    public static function readiness(): array
    {
        $defaults = array(
            'file00_membership' => false,
            'file20_shell' => false,
            'file24_assurance' => false,
            'file25_visual' => false,
            'file26_search' => false,
            'domain_contracts' => false,
        );
        $readiness = apply_filters('slto_integration_readiness', $defaults, Manifest::get());
        return is_array($readiness) ? array_merge($defaults, $readiness) : $defaults;
    }

    public static function publishManifest(): void
    {
        do_action('sabri_module_manifest_registered', Manifest::get());
    }
}
