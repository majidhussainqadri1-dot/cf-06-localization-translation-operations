<?php

declare(strict_types=1);

namespace Sabri\Localization\Contract;

/**
 * Machine-readable reconciliation map for the latest central governing plan and
 * CF-06 completion addendum. This is source-code evidence only; staging/live
 * acceptance remains separate evidence.
 */
final class PlanCompliance
{
    public static function completionRequirements(): array
    {
        return array_map(static fn (int $i): string => sprintf('CF06-CEN-%02d', $i), range(1, 10));
    }

    public static function nativeJourneys(): array
    {
        return array_map(static fn (int $i): string => sprintf('CF06-NJ-%02d', $i), range(1, 6));
    }

    public static function centralLaws(): array
    {
        return array(
            'CEN-GOV-001', 'CEN-OWN-001', 'CEN-BIZ-001', 'CEN-DON-001', 'CEN-BRAND-001',
            'CEN-SHELL-001', 'CEN-NUM-001', 'CEN-SAFE-001', 'CEN-PRIV-001', 'CEN-REV-001',
        );
    }

    public static function get(): array
    {
        return array(
            'central_governing_laws' => self::centralLaws(),
            'central_plan' => array(
                'shared_localization_capability',
                'localization_and_internationalization_section_43',
                'versioned_api_event_contracts',
                'accessibility_constitution',
                'observability_incident_response',
                'backup_disaster_recovery',
            ),
            'cf06_completion_requirements' => self::completionRequirements(),
            'cf06_native_journeys' => self::nativeJourneys(),
            'source_language_policy' => 'american-english-technical-source-with-explicit-domain-source-locale',
            'first_class_locales' => array('ur', 'ar', 'en-US'),
            'external_mt_policy' => array(
                'status' => 'draft-only',
                'allowed_risk' => 'low',
                'allowed_data_class' => 'C1',
                'high_risk_domains' => 'deny',
                'training_reuse' => 'deny-by-default',
            ),
            'critical_release_gate' => '100-percent-current-critical-resources',
            'owner_boundaries' => array(
                'file20' => 'language-preference-and-shell',
                'file25' => 'visual-rtl-ltr-implementation',
                'file26' => 'transliteration-and-search-ranking',
                'domain_owners' => 'source-truth-and-final-publication-approval',
                'cf06' => 'localization-workflow-and-bundle-governance',
            ),
            'performance_targets' => array(
                'locale_resolution_p95_ms' => 20,
                'admin_queue_p95_ms' => 1000,
                'critical_staleness_target_seconds' => 60,
                'general_staleness_target_seconds' => 300,
                'critical_rollback_target_seconds' => 300,
                'availability_percent' => 99.9,
                'rpo_seconds' => 3600,
                'rto_seconds' => 28800,
            ),
        );
    }
}
