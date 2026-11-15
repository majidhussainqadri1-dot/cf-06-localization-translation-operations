<?php

declare(strict_types=1);

/**
 * Disposable WordPress/MySQL registry tests. All policy rows/audit events are
 * rolled back; synthetic approval callbacks never escape this CI execution.
 */
use Sabri\Localization\Infrastructure\Transaction;
use Sabri\Localization\Plugin;
use Sabri\Localization\Application\EditionPolicyRegistry;

if (!defined('ABSPATH')) {
    throw new RuntimeException('WordPress required for V12.3 edition policy registry test.');
}
$registry = Plugin::instance()->service('editionPolicy');
$localeService = Plugin::instance()->service('locale');
if (!$registry instanceof EditionPolicyRegistry || !$localeService) {
    throw new RuntimeException('CF-06 edition policy registry service missing.');
}
$fixtureId = 'ci-v123-'.substr(str_replace('-', '', wp_generate_uuid4()), 0, 12);
$policy = [
    'policy_id'=>$fixtureId,
    'revision'=>'1.0.0',
    'founder_approval_ref'=>'CI-only-false-in-production',
    'effective_date'=>'2026-11-15',
    // Deliberately synthetic test-only locales; never actual Founder policy.
    'target_locales'=>['ur','en-US','ar','zh-Hans','bn','fa','hi','tr','id'],
    'reserve_locales'=>['de','fr'],
    'aliases'=>['ur-PK'=>'ur'],
];
$passes = 0;
$sentinel = 'ci_edition_policy_rollback_'.$fixtureId;
$recent = static fn (): bool => true;
$founder = static fn (mixed $original, array $identity): bool =>
    ($identity['founder_approval_ref'] ?? '') === 'CI-only-false-in-production'
    && ($identity['policy_id'] ?? '') !== '';

try {
    (new Transaction())->run(function () use (
        $registry, $localeService, $policy, $recent, $founder, $sentinel, &$passes
    ): void {
        try {
            $registry->register($policy);
            throw new RuntimeException('Release step-up bypassed.');
        } catch (InvalidArgumentException $expected) {
            if (!str_contains($expected->getMessage(), 'recent authentication')) {
                throw $expected;
            }
        }
        ++$passes;

        add_filter('slto_verify_recent_authentication', $recent, 10, 3);
        try {
            $registry->register($policy);
            throw new RuntimeException('Founder approval bypassed.');
        } catch (InvalidArgumentException $expected) {
            if (!str_contains($expected->getMessage(), 'Founder')) {
                throw $expected;
            }
        }
        ++$passes;

        add_filter('slto_verify_founder_edition_policy', $founder, 10, 2);
        $first = $registry->register($policy);
        if (true !== $first['created'] || 'registered' !== $first['record']['status']
            || false !== $first['record']['activation_authorized']
            || array_key_exists('registered_by', $first['record'])) {
            throw new RuntimeException('New immutable candidate was incorrectly activated or not registered.');
        }
        ++$passes;

        $again = $registry->register($policy);
        if (false !== $again['created']
            || !hash_equals($first['record']['policy_hash'], $again['record']['policy_hash'])) {
            throw new RuntimeException('Idempotent same-revision registration created a second record.');
        }
        ++$passes;

        $found = $registry->get($policy['policy_id'], $policy['revision']);
        if (!is_array($found) || !hash_equals($found['policy_hash'], $first['record']['policy_hash'])) {
            throw new RuntimeException('Registered policy cannot be retrieved by exact version.');
        }
        ++$passes;

        $changed = $policy;
        $changed['target_locales'][4] = 'es';
        try {
            $registry->register($changed);
            throw new RuntimeException('Different content reused the same immutable revision.');
        } catch (DomainException $expected) {
            if ('edition_policy_revision_conflict' !== $expected->getMessage()) {
                throw $expected;
            }
        }
        ++$passes;

        $projection = $localeService->previewPublicEditionPolicy($found['policy'],[
            'locale'=>'ur','version'=>'v1','hash'=>hash('sha256','synthetic source'),
        ]);
        if ('planning_ready' !== $projection['status']
            || 9 !== $projection['selected_target_count']
            || 0 !== $projection['published']['verified_published_count']
            || true === $projection['publication_authorized']) {
            throw new RuntimeException('Registered policy preview bypassed publication requirements.');
        }
        ++$passes;

        remove_filter('slto_verify_founder_edition_policy', $founder, 10);
        $blocked = $localeService->previewPublicEditionPolicy($found['policy'],[
            'locale'=>'ur','version'=>'v1','hash'=>hash('sha256','synthetic source'),
        ]);
        if ('blocked' !== $blocked['status'] || 0 !== $blocked['published']['verified_published_count']) {
            throw new RuntimeException('Unverified registered policy preview became authorized.');
        }
        ++$passes;

        remove_filter('slto_verify_recent_authentication', $recent, 10);
        throw new RuntimeException($sentinel);
    });
} catch (RuntimeException $exception) {
    remove_filter('slto_verify_founder_edition_policy', $founder, 10);
    remove_filter('slto_verify_recent_authentication', $recent, 10);
    if ($exception->getMessage() !== $sentinel) {
        throw $exception;
    }
}

if (8 !== $passes) {
    throw new RuntimeException('Edition policy registry suite incomplete: '. $passes);
}
echo "V123_EDITION_POLICY_REGISTRY_PASS 8\n";
