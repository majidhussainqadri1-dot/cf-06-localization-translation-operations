<?php

declare(strict_types=1);

namespace Sabri\Localization\Security;

/**
 * Privileged CF-06 authorization is intentionally fail-closed.
 *
 * WordPress capabilities are necessary but not sufficient. File 00 must
 * provide a current membership assertion for every privileged operation. The
 * extension filter is deny-only; it cannot create an alternate approval path.
 */
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
        $capability = self::MAP[$action] ?? 'manage_sabri_localization';
        if (! current_user_can($capability)) {
            return false;
        }

        $userId = get_current_user_id();
        if ($userId <= 0 || ! function_exists('smc_membership_assertions')) {
            return false;
        }

        $assertions = smc_membership_assertions($userId);
        if (! is_array($assertions)) {
            return false;
        }

        $state = strtolower((string) ($assertions['state'] ?? ''));
        $approved = true === ($assertions['approved'] ?? false)
            || in_array($state, array('approved', 'active', 'verified'), true);
        if (! $approved || ! empty($assertions['suspended']) || 'suspended' === $state) {
            return false;
        }

        if (isset($context['record_version']) && (int) $context['record_version'] <= 0) {
            return false;
        }

        $decision = apply_filters('slto_authorize_action', true, $action, $context, $userId, $assertions);
        return false !== $decision;
    }
}
