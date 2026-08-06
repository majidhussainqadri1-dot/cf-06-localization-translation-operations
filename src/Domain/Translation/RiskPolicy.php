<?php

declare(strict_types=1);

namespace Sabri\Localization\Domain\Translation;

final class RiskPolicy
{
    public const HIGH_RISK_DOMAINS = array('medical', 'homeopathy', 'clinical', 'shariah', 'islamic', 'legal', 'financial', 'payment', 'privacy', 'security', 'consent');

    public static function requiresDomainReview(string $riskClass, string $domain): bool
    {
        return in_array(strtolower($riskClass), array('high', 'critical', 'private'), true)
            || in_array(strtolower($domain), self::HIGH_RISK_DOMAINS, true);
    }

    public static function machineTranslationAllowed(string $riskClass, string $dataClass, string $domain, bool $explicitlyApproved = false): bool
    {
        if (in_array(strtoupper($dataClass), array('C4', 'C5'), true) || in_array(strtolower($riskClass), array('critical', 'private'), true)) {
            return false;
        }
        if (self::requiresDomainReview($riskClass, $domain) && ! $explicitlyApproved) {
            return false;
        }
        return true;
    }

    public static function criticalResource(string $riskClass, string $domain): bool
    {
        return 'critical' === strtolower($riskClass) || in_array(strtolower($domain), array('authentication', 'consent', 'payment', 'clinical', 'security', 'legal', 'privacy'), true);
    }
}
