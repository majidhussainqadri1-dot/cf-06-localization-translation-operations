<?php

declare(strict_types=1);

namespace Sabri\Localization\Domain\Locale;

use InvalidArgumentException;

/** V12.3 original source + nine different target editions; planning never publishes. */
final class PublicEditionPolicy
{
    public const TARGET_COUNT = 9;

    public static function validate(array $policy): array
    {
        $id = self::bounded($policy['policy_id'] ?? null, 80, 'policy ID');
        $revision = self::bounded($policy['revision'] ?? null, 40, 'policy revision');
        $approval = self::bounded($policy['founder_approval_ref'] ?? null, 160, 'Founder approval reference');
        $effective = self::bounded($policy['effective_date'] ?? null, 10, 'effective date');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $effective)
            || !checkdate((int)substr($effective, 5, 2), (int)substr($effective, 8, 2), (int)substr($effective, 0, 4))) {
            throw new InvalidArgumentException('Edition policy requires a valid effective date.');
        }
        foreach ([$id, $revision] as $value) {
            if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/D', $value)) {
                throw new InvalidArgumentException('Edition policy identity is noncanonical.');
            }
        }
        $aliasInput = $policy['aliases'] ?? [];
        if (!is_array($aliasInput) || count($aliasInput) > 32) {
            throw new InvalidArgumentException('Edition aliases are excessive or malformed.');
        }
        $aliases = [];
        foreach ($aliasInput as $from => $to) {
            if (!is_string($from) || !is_string($to)) {
                throw new InvalidArgumentException('Edition aliases must be locale-to-locale mappings.');
            }
            $key = self::locale($from);
            $destination = self::locale($to);
            if ($key === $destination || isset($aliases[$key])) {
                throw new InvalidArgumentException('Duplicate or reflexive edition alias.');
            }
            $aliases[$key] = $destination;
        }
        foreach ($aliases as $destination) {
            if (isset($aliases[$destination])) {
                throw new InvalidArgumentException('Edition alias chains or cycles are forbidden.');
            }
        }
        $targets = self::localeList($policy['target_locales'] ?? null, $aliases, self::TARGET_COUNT);
        if (count($targets) !== self::TARGET_COUNT) {
            throw new InvalidArgumentException('Exactly nine primary translation targets are mandatory.');
        }
        foreach (['ur', 'en-US', 'ar'] as $required) {
            if (!in_array($required, $targets, true)) {
                throw new InvalidArgumentException('Missing a V12.3 governing language.');
            }
        }
        $chineseScriptSelected = false;
        foreach ($targets as $tag) {
            $parsed = LocaleValidator::parse($tag);
            if ('zh' === $parsed['language'] && in_array($parsed['script'], ['Hans', 'Hant'], true)) {
                $chineseScriptSelected = true;
            }
        }
        if (!$chineseScriptSelected) {
            throw new InvalidArgumentException('Mandarin Chinese must have an explicitly approved script.');
        }
        $reserves = self::localeList($policy['reserve_locales'] ?? [], $aliases, 24);
        foreach ($reserves as $tag) {
            if (in_array($tag, $targets, true)) {
                throw new InvalidArgumentException('Reserve locale duplicates primary target.');
            }
        }
        return [
            'policy_id' => $id,
            'revision' => $revision,
            'founder_approval_ref' => $approval,
            'effective_date' => $effective,
            'target_locales' => $targets,
            'reserve_locales' => $reserves,
            'aliases' => $aliases,
        ];
    }

    public static function plan(array $policy, string $sourceLocale, string $sourceVersion, string $sourceHash, bool $policyApprovalVerified = false): array
    {
        $config = self::validate($policy);
        $source = self::normalized($sourceLocale, $config['aliases']);
        $version = self::bounded($sourceVersion, 80, 'source version');
        $hash = strtolower(trim($sourceHash));
        if (!preg_match('/^[a-f0-9]{64}$/D', $hash)) {
            throw new InvalidArgumentException('Edition source content hash must be SHA-256.');
        }
        $selected = [];
        $sourceWasTarget = false;
        foreach ($config['target_locales'] as $tag) {
            if ($tag === $source) {
                $sourceWasTarget = true;
                continue;
            }
            $selected[] = $tag;
        }
        $reservesUsed = [];
        foreach ($config['reserve_locales'] as $tag) {
            if (count($selected) >= self::TARGET_COUNT) {
                break;
            }
            if ($tag !== $source && !in_array($tag, $selected, true)) {
                $selected[] = $tag;
                $reservesUsed[] = $tag;
            }
        }
        $missing = self::TARGET_COUNT - count($selected);
        $reason = !$policyApprovalVerified ? 'founder_policy_approval_unverified'
            : ($missing > 0 ? 'approved_reserve_shortfall' : null);
        return [
            'status' => $reason === null ? 'planning_ready' : 'blocked',
            'reason' => $reason,
            'policy_id' => $config['policy_id'],
            'policy_revision' => $config['revision'],
            'source_locale' => $source,
            'source_version' => $version,
            'source_hash' => $hash,
            'selected_target_locales' => $selected,
            'reserve_locales_used' => $reservesUsed,
            'source_removed_from_primary_targets' => $sourceWasTarget,
            'required_target_count' => self::TARGET_COUNT,
            'selected_target_count' => count($selected),
            'missing_target_count' => $missing,
            'policy_approval_independently_verified' => $policyApprovalVerified,
            'publication_authorized' => false,
            'jobs_created' => 0,
            'separate_language_preferences' => ['ui', 'content', 'edition'],
        ];
    }

    /** A row is counted only with independently verified publication evidence. */
    public static function countVerifiedPublished(array $plan, array $records, callable $verify): array
    {
        if (count($records) > 100) {
            throw new InvalidArgumentException('Edition evidence batch exceeds limit.');
        }
        if (true !== ($plan['policy_approval_independently_verified'] ?? false)) {
            return [
                'verified_published_count' => 0,
                'verified_locales' => [],
                'missing_public_versions' => self::TARGET_COUNT + 1,
                'unverified_or_rejected_record_count' => count($records),
                'source_and_nine_targets_complete' => false,
            ];
        }
        $allowed = array_merge([$plan['source_locale']], $plan['selected_target_locales']);
        $seen = [];
        $rejected = 0;
        foreach ($records as $record) {
            if (!is_array($record)) {
                ++$rejected;
                continue;
            }
            $tag = LocaleValidator::canonicalize((string)($record['locale'] ?? ''));
            $url = (string)($record['public_url'] ?? '');
            $parts = parse_url($url);
            $valid = $tag !== null
                && in_array($tag, $allowed, true)
                && !isset($seen[$tag])
                && 'published' === ($record['status'] ?? '')
                && true === ($record['public'] ?? false)
                && hash_equals((string)$plan['source_version'], (string)($record['source_version'] ?? ''))
                && hash_equals((string)$plan['source_hash'], strtolower((string)($record['source_hash'] ?? '')))
                && is_array($parts)
                && 'https' === strtolower((string)($parts['scheme'] ?? ''))
                && '' !== (string)($parts['host'] ?? '')
                && !isset($parts['user']) && !isset($parts['pass']);
            if (!$valid || true !== $verify($record)) {
                ++$rejected;
                continue;
            }
            $seen[$tag] = true;
        }
        return [
            'verified_published_count' => count($seen),
            'verified_locales' => array_keys($seen),
            'missing_public_versions' => self::TARGET_COUNT + 1 - count($seen),
            'unverified_or_rejected_record_count' => $rejected,
            'source_and_nine_targets_complete' => count($seen) === self::TARGET_COUNT + 1 && 'planning_ready' === ($plan['status'] ?? ''),
        ];
    }

    private static function localeList(mixed $list, array $aliases, int $limit): array
    {
        if (!is_array($list) || count($list) > $limit) {
            throw new InvalidArgumentException('Edition locale list is malformed or excessive.');
        }
        $result = [];
        foreach ($list as $raw) {
            if (!is_string($raw)) {
                throw new InvalidArgumentException('Locale must be a string.');
            }
            $tag = self::normalized($raw, $aliases);
            if (in_array($tag, $result, true)) {
                throw new InvalidArgumentException('Edition locale repeats after alias normalization.');
            }
            $result[] = $tag;
        }
        return $result;
    }

    private static function normalized(string $locale, array $aliases): string
    {
        $tag = self::locale($locale);
        return $aliases[$tag] ?? $tag;
    }

    private static function locale(string $raw): string
    {
        $tag = LocaleValidator::canonicalize($raw);
        if ($tag === null) {
            throw new InvalidArgumentException('Invalid edition locale tag.');
        }
        $parsed = LocaleValidator::parse($tag);
        if ('zh' === $parsed['language'] && '' === $parsed['script']) {
            throw new InvalidArgumentException('Chinese edition locale requires an approved script.');
        }
        return $tag;
    }

    private static function bounded(mixed $value, int $limit, string $label): string
    {
        if (!is_string($value) || '' === trim($value) || strlen($value) > $limit || preg_match('/[\x00-\x1f\x7f]/', $value)) {
            throw new InvalidArgumentException('Missing or invalid ' . $label . '.');
        }
        return trim($value);
    }
}
