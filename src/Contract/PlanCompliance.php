<?php

declare(strict_types=1);

namespace Sabri\Localization\Contract;

/**
 * Machine-readable reconciliation map for the latest central governing plan,
 * CF-06 completion addendum and Founder-approved Future40 expansion.
 * This is source-code evidence only; staging/live acceptance remains separate.
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

    public static function futureRequirements(): array
    {
        return FutureCapabilities::ids();
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
            'cf06_future40' => self::futureRequirements(),
            'future40_traceability_dimensions' => array(
                'requirement_id',
                'source_implementation',
                'automated_test_evidence',
                'security_privacy_safety_rule',
                'canonical_owner_boundary',
                'exact_package_evidence',
                'staging_acceptance_status',
                'live_verification_status',
            ),
            'future40_activation_law' => array(
                'default_state' => 'disabled',
                'founder_change_control_required' => true,
                'companion_contract_parity_required' => true,
                'staging_acceptance_required' => true,
                'rollback_rehearsal_required' => true,
                'live_verification_required' => true,
                'high_risk_human_review_required' => true,
                'ai_or_community_auto_publish' => false,
                'preview_route_activation_authority' => false,
                'preview_route_publication_authority' => false,
            ),
            'source_language_policy' => 'american-english-technical-source-with-explicit-domain-source-locale',
            'first_class_locales' => array('ur', 'ar', 'en-US'),
            'external_mt_policy' => array(
                'status' => 'draft-only',
                'allowed_risk' => 'low',
                'allowed_data_class' => 'C1',
                'high_risk_domains' => 'deny',
                'training_reuse' => 'deny-by-default',
                'self_hosted_adapter_does_not_remove_human_review' => true,
            ),
            'critical_release_gate' => '100-percent-current-critical-resources',
            'owner_boundaries' => array(
                'file00' => 'identity-membership-suspension-capability-and-independent-qualification-authority',
                'file19' => 'localized-delivery-consumer-boundary-not-localization-source-truth',
                'file20' => 'language-preference-switcher-and-global-shell',
                'file22' => 'page-composer-authoring-and-layout-authority',
                'file23' => 'dashboard-composer-and-presentation-authority',
                'file24' => 'independent-assurance-security-privacy-and-governance-evidence',
                'file25' => 'visual-rtl-ltr-components-typography-and-layout',
                'file26' => 'transliteration-and-multilingual-search-ranking',
                'cf04' => 'canonical-media-processing-secure-delivery-and-media-artifacts',
                'unicode_cldr_icu' => 'pinned-tested-locale-formatting-plural-direction-reference-data-not-cf06-owned-truth',
                'domain_owners' => 'source-truth-domain-meaning-and-final-publication-approval',
                'cf06' => 'localization-workflow-terminology-review-bundle-and-release-governance',
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
