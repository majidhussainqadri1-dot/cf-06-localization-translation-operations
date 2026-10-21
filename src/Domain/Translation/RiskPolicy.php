<?php

declare(strict_types=1);

namespace Sabri\Localization\Domain\Translation;

final class RiskPolicy
{
    public const HIGH_RISK_DOMAINS = array(
        'medical', 'homeopathy', 'clinical', 'shariah', 'islamic', 'legal', 'financial', 'payment',
        'privacy', 'security', 'consent', 'identity', 'message', 'communication', 'credential', 'secret',
        'support_evidence',
    );

    public static function requiresDomainReview(string $riskClass, string $domain): bool
    {
        return in_array(strtolower($riskClass), array('high', 'critical', 'private'), true)
            || in_array(strtolower($domain), self::HIGH_RISK_DOMAINS, true);
    }

    /**
     * External MT is intentionally stricter than human translation: the current
     * governing completion addendum permits only approved low-risk draft material.
     * C2-C5 and every high-risk domain remain external-provider denied even when a
     * caller supplies an explicit approval flag. The flag remains in the signature
     * for contract compatibility but can never weaken these privacy/safety gates.
     */
    public static function machineTranslationAllowed(string $riskClass, string $dataClass, string $domain, bool $explicitlyApproved = false): bool
    {
        unset($explicitlyApproved);
        if ('C1' !== strtoupper($dataClass)) {
            return false;
        }
        if ('low' !== strtolower($riskClass)) {
            return false;
        }
        if (in_array(strtolower($domain), self::HIGH_RISK_DOMAINS, true)) {
            return false;
        }
        return true;
    }

    public static function criticalResource(string $riskClass, string $domain): bool
    {
        return 'critical' === strtolower($riskClass)
            || in_array(strtolower($domain), array('authentication', 'consent', 'payment', 'clinical', 'security', 'legal', 'privacy'), true);
    }
}
