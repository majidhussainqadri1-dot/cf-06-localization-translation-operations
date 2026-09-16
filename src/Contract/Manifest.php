<?php

declare(strict_types=1);

namespace Sabri\Localization\Contract;

final class Manifest
{
    public static function get(): array
    {
        return array(
            'module' => 'CF-06',
            'name' => 'Localization and Translation Operations',
            'version' => SABRI_SLTO_VERSION,
            'schema_version' => SABRI_SLTO_SCHEMA_VERSION,
            'contract_version' => SABRI_SLTO_CONTRACT_VERSION,
            'runtime_default' => 'disabled-fail-closed',
            'coding_scope_status' => 'latest-plans-reconciled-source-candidate',
            'privacy_invariants' => array(
                'external_mt' => 'low-risk-c1-draft-only',
                'c2_c5_external_mt' => 'deny',
                'private_or_high_risk_external_mt' => 'deny',
                'provider_training_reuse' => 'deny-by-default',
            ),
            'canonical_owners' => array(
                'locale_registry', 'translatable_resource_inventory', 'translation_workflow',
                'terminology', 'translation_memory', 'draft_mt_orchestration', 'linguistic_qa',
                'locale_bundle_release_rollback', 'translation_provider_privacy',
            ),
            'non_owners' => array(
                'user_language_preference', 'global_switcher', 'visual_rtl_components',
                'original_domain_content', 'domain_publication_decision', 'search_transliteration',
            ),
            'events' => Events::ALL,
            'requirements' => array_map(static fn (int $i): string => sprintf('CF06-FR-%03d', $i), range(1, 34)),
            'completion_requirements' => PlanCompliance::completionRequirements(),
            'native_journeys' => PlanCompliance::nativeJourneys(),
            'plan_compliance' => PlanCompliance::get(),
        );
    }
}
