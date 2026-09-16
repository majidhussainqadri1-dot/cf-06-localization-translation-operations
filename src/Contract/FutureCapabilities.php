<?php

declare(strict_types=1);

namespace Sabri\Localization\Contract;

/**
 * Founder-approved Future40 capability catalogue for CF-06.
 *
 * Every capability is code-present but disabled by default. Activation is
 * governed by the normal CF-06 change-control, privacy, security, domain-review,
 * staging, rollback and live-verification gates.
 */
final class FutureCapabilities
{
    public static function ids(): array
    {
        return array_map(static fn (int $i): string => sprintf('CF06-FUT-%03d', $i), range(1, 40));
    }

    public static function all(): array
    {
        $rows = array(
            1 => ['pseudolocalization_lab', 'Pseudolocalization Lab', 'qa'],
            2 => ['visual_context_translation_editor', 'Visual Context Translation Editor', 'authoring'],
            3 => ['device_preview_matrix', 'Device Preview Matrix', 'qa'],
            4 => ['source_authoring_linter', 'Source Authoring Linter', 'authoring'],
            5 => ['semantic_equivalence_checker', 'Semantic Equivalence Checker', 'qa'],
            6 => ['translation_risk_diff', 'Translation Risk Diff', 'qa'],
            7 => ['automatic_terminology_mining', 'Automatic Terminology Mining', 'terminology'],
            8 => ['terminology_concept_graph', 'Terminology Concept Graph', 'terminology'],
            9 => ['citation_integrity_lock', 'Citation Integrity Lock', 'safety'],
            10 => ['protected_domain_tokens', 'Protected Domain Tokens', 'safety'],
            11 => ['transcript_localization', 'Transcript Localization', 'media'],
            12 => ['subtitle_localization_timing_qa', 'Subtitle Localization & Timing QA', 'media'],
            13 => ['human_reviewed_ai_dubbing', 'Human-Reviewed AI Dubbing', 'media'],
            14 => ['pronunciation_lexicon', 'Pronunciation Lexicon', 'media'],
            15 => ['pdf_ebook_localization_workflow', 'PDF/eBook Localization Workflow', 'publication'],
            16 => ['ocr_intake_review', 'OCR Intake Review', 'publication'],
            17 => ['multilingual_accessibility_text', 'Multilingual Accessibility Text', 'accessibility'],
            18 => ['regional_dialect_locale_packs', 'Regional/Dialect Locale Packs', 'locale'],
            19 => ['register_honorific_profiles', 'Register & Honorific Profiles', 'locale'],
            20 => ['hijri_gregorian_calendar_layer', 'Hijri/Gregorian Calendar Layer', 'locale'],
            21 => ['numeral_system_support', 'Numeral-System Support', 'locale'],
            22 => ['font_glyph_coverage_scanner', 'Font/Glyph Coverage Scanner', 'qa'],
            23 => ['locale_line_break_engine', 'Locale Line-Break Engine', 'qa'],
            24 => ['input_method_compatibility', 'Input Method Compatibility', 'qa'],
            25 => ['international_seo_auditor', 'International SEO Auditor', 'seo'],
            26 => ['locale_launch_gate_per_feature', 'Locale Launch Gate per Feature', 'release'],
            27 => ['critical_copy_kill_switch', 'Critical Copy Kill Switch', 'release'],
            28 => ['emergency_translation_hotfix_lane', 'Emergency Translation Hotfix Lane', 'release'],
            29 => ['delta_locale_bundles', 'Delta Locale Bundles', 'delivery'],
            30 => ['offline_locale_packs', 'Offline Locale Packs', 'delivery'],
            31 => ['low_bandwidth_localization_mode', 'Low-Bandwidth Localization Mode', 'delivery'],
            32 => ['private_self_hosted_mt_adapter', 'Private/Self-Hosted MT Adapter', 'provider'],
            33 => ['multi_provider_translation_router', 'Multi-Provider Translation Router', 'provider'],
            34 => ['provider_benchmark_sandbox', 'Provider Benchmark Sandbox', 'provider'],
            35 => ['data_residency_routing', 'Data-Residency Routing', 'privacy'],
            36 => ['ai_quality_estimation', 'AI Quality Estimation', 'qa'],
            37 => ['translation_debt_forecasting', 'Translation Debt Forecasting', 'analytics'],
            38 => ['reviewer_calibration_adjudication', 'Reviewer Calibration & Adjudication', 'governance'],
            39 => ['community_translation_suggestions', 'Community Translation Suggestions', 'community'],
            40 => ['founder_localization_command_center', 'Founder Localization Command Center', 'operations'],
        );

        $out = array();
        foreach ($rows as $i => [$slug, $name, $group]) {
            $id = sprintf('CF06-FUT-%03d', $i);
            $out[$id] = array(
                'id' => $id,
                'slug' => $slug,
                'name' => $name,
                'group' => $group,
                'default_state' => 'disabled',
                'activation' => 'founder-change-control-plus-staging',
                'public_auto_publish' => false,
                'high_risk_human_review_required' => true,
            );
        }
        return $out;
    }

    public static function get(string $id): ?array
    {
        $all = self::all();
        return $all[strtoupper($id)] ?? null;
    }
}
