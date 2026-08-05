<?php

declare(strict_types=1);

namespace Sabri\Localization\Security;

final class Authorization
{
    private const MAP = array(
        'manage' => 'manage_sabri_localization',
        'translate' => 'translate_sabri_localization',
        'review_linguistic' => 'review_sabri_localization',
        'review_domain' => 'domain_review_sabri_localization',
        'terminology' => 'manage_sabri_terminology',
        'release' => 'release_sabri_localization',
        'provider' => 'manage_sabri_localization_providers',
        'audit' => 'audit_sabri_localization',
    );

    public static function allowed(string $action, array $context = array()): bool
    {
        $filtered = apply_filters('slto_authorize_action', null, $action, $context, get_current_user_id());
        if (is_bool($filtered)) {
            return $filtered;
        }
        $capability = self::MAP[$action] ?? 'manage_sabri_localization';
        if (! current_user_can($capability)) {
            return false;
        }
        $assertions = function_exists('smc_membership_assertions') ? smc_membership_assertions(get_current_user_id()) : null;
        if (is_array($assertions)) {
            if (! empty($assertions['suspended']) || 'suspended' === ($assertions['state'] ?? null)) {
                return false;
            }
        }
        return true;
    }
}
