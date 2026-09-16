<?php

declare(strict_types=1);

namespace Sabri\Localization\Domain\Future;

use DateTimeImmutable;
use InvalidArgumentException;
use Sabri\Localization\Domain\Locale\LocaleValidator;

final class ReleaseLifecycleGuard
{
    public static function normalize(string $id, array $input): array
    {
        return match ($id) {
            'CF06-FUT-026' => self::launchGate($input),
            'CF06-FUT-027' => self::killSwitch($input),
            'CF06-FUT-028' => self::hotfix($input),
            'CF06-FUT-029' => self::delta($input),
            default => $input,
        };
    }

    public static function apply(string $id, array $input, array $out): array
    {
        if (! isset($out['result']) || ! is_array($out['result'])) {
            return $out;
        }
        return match ($id) {
            'CF06-FUT-026' => self::launchResult($input, $out),
            'CF06-FUT-027' => self::killResult($input, $out),
            'CF06-FUT-028' => self::hotfixResult($input, $out),
            'CF06-FUT-029' => self::deltaResult($input, $out),
            'CF06-FUT-030' => self::offlineResult($input, $out),
            'CF06-FUT-031' => self::lowBandwidthResult($input, $out),
            default => $out,
        };
    }

    private static function launchGate(array $input): array
    {
        $locale = LocaleValidator::canonicalize((string)($input['locale'] ?? ''));
        if (null === $locale) {
            throw new InvalidArgumentException('Locale launch gate requires a valid locale.');
        }
        $feature = trim((string)($input['feature_id'] ?? ''));
        $domain = trim((string)($input['domain'] ?? ''));
        if ('' === $feature || '' === $domain || 1 !== preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{1,127}$/D', $feature) || 1 !== preg_match('/^[a-z0-9][a-z0-9_-]{1,63}$/D', strtolower($domain))) {
            throw new InvalidArgumentException('Locale launch gate requires bounded feature_id and domain scope.');
        }
        $input['locale'] = $locale;
        $input['domain'] = strtolower($domain);
        return $input;
    }

    private static function killSwitch(array $input): array
    {
        $scope = trim((string)($input['scope'] ?? ''));
        if (1 !== preg_match('/^(locale|domain|resource):[A-Za-z0-9][A-Za-z0-9_.:\/-]{0,191}$/D', $scope)) {
            throw new InvalidArgumentException('Kill-switch scope must be locale:, domain: or resource: scoped.');
        }
        if (true === ($input['kill'] ?? false)) {
            foreach (['reason','actor_ref','evidence_ref'] as $field) {
                if ('' === trim((string)($input[$field] ?? ''))) {
                    throw new InvalidArgumentException('Kill-switch activation requires reason, actor_ref and evidence_ref.');
                }
            }
        }
        return $input;
    }

    private static function hotfix(array $input): array
    {
        $approvals = $input['approvals'] ?? null;
        if (! is_array($approvals) || count($approvals) < 2) {
            throw new InvalidArgumentException('Emergency hotfix requires two approval records.');
        }
        $approvedByRole = [];
        $actors = [];
        foreach ($approvals as $approval) {
            if (! is_array($approval)) { continue; }
            $role = strtolower(trim((string)($approval['role'] ?? '')));
            $actor = trim((string)($approval['actor_id'] ?? ''));
            $approved = true === ($approval['approved'] ?? false);
            $at = trim((string)($approval['approved_at'] ?? ''));
            if (! in_array($role, ['linguistic','domain'], true) || '' === $actor || ! $approved || ! self::validIsoTime($at)) {
                continue;
            }
            $approvedByRole[$role] = $actor;
            $actors[] = $actor;
        }
        if (! isset($approvedByRole['linguistic'], $approvedByRole['domain'])) {
            throw new InvalidArgumentException('Emergency hotfix requires approved linguistic and domain records.');
        }
        if ($approvedByRole['linguistic'] === $approvedByRole['domain'] || count(array_unique($actors)) < 2) {
            throw new InvalidArgumentException('Emergency hotfix approvals must be independent actors.');
        }
        if ('' === trim((string)($input['evidence_ref'] ?? ''))) {
            throw new InvalidArgumentException('Emergency hotfix requires evidence_ref.');
        }
        return $input;
    }

    private static function delta(array $input): array
    {
        foreach (['old','new'] as $field) {
            if (! is_array($input[$field] ?? null)) {
                throw new InvalidArgumentException('Delta bundles require old and new bundle maps.');
            }
        }
        $oldLocale = LocaleValidator::canonicalize((string)($input['old_locale'] ?? ''));
        $newLocale = LocaleValidator::canonicalize((string)($input['new_locale'] ?? ''));
        if (null === $oldLocale || null === $newLocale || $oldLocale !== $newLocale) {
            throw new InvalidArgumentException('Delta bundle old/new locale identity must match.');
        }
        foreach (['old_version','new_version'] as $field) {
            if ('' === trim((string)($input[$field] ?? ''))) {
                throw new InvalidArgumentException('Delta bundle requires old_version and new_version.');
            }
        }
        $input['old_locale'] = $oldLocale;
        $input['new_locale'] = $newLocale;
        return $input;
    }

    private static function launchResult(array $input, array $out): array
    {
        $out['result']['locale'] = $input['locale'];
        $out['result']['feature_id'] = (string)$input['feature_id'];
        $out['result']['domain'] = (string)$input['domain'];
        $out['result']['scope'] = 'locale+feature+domain';
        return $out;
    }

    private static function killResult(array $input, array $out): array
    {
        $out['result']['actor_ref'] = (string)($input['actor_ref'] ?? '');
        $out['result']['evidence_ref'] = (string)($input['evidence_ref'] ?? '');
        $out['result']['containment_only'] = true;
        $out['result']['content_deleted'] = false;
        $out['result']['recovery_path_required'] = true;
        return $out;
    }

    private static function hotfixResult(array $input, array $out): array
    {
        $out['result']['evidence_ref'] = (string)$input['evidence_ref'];
        $out['result']['independent_dual_approval'] = true;
        $out['result']['publication_authority'] = false;
        $out['result']['post_hotfix_full_review_required'] = true;
        return $out;
    }

    private static function deltaResult(array $input, array $out): array
    {
        $oldHash = hash('sha256', self::canonicalJson($input['old']));
        $newHash = hash('sha256', self::canonicalJson($input['new']));
        $patch = [
            'locale' => $input['new_locale'],
            'old_version' => (string)$input['old_version'],
            'new_version' => (string)$input['new_version'],
            'old_hash' => $oldHash,
            'new_hash' => $newHash,
            'changed' => $out['result']['changed'] ?? [],
            'deleted' => $out['result']['deleted'] ?? [],
        ];
        $out['result']['locale'] = $input['new_locale'];
        $out['result']['old_version'] = (string)$input['old_version'];
        $out['result']['new_version'] = (string)$input['new_version'];
        $out['result']['old_bundle_sha256'] = $oldHash;
        $out['result']['new_bundle_sha256'] = $newHash;
        $out['result']['delta_sha256'] = hash('sha256', self::canonicalJson($patch));
        $out['result']['deterministic'] = true;
        return $out;
    }

    private static function offlineResult(array $input, array $out): array
    {
        $out['result']['manifest_sha256'] = hash('sha256', self::canonicalJson($out['result']['manifest'] ?? []));
        $out['result']['release_gate_required'] = true;
        $out['result']['staleness_recheck_required_before_use'] = true;
        return $out;
    }

    private static function lowBandwidthResult(array $input, array $out): array
    {
        $out['result']['payload_sha256'] = hash('sha256', self::canonicalJson($out['result']['payload'] ?? []));
        $out['result']['release_gate_required'] = true;
        $out['result']['staleness_recheck_required_before_use'] = true;
        return $out;
    }

    private static function validIsoTime(string $value): bool
    {
        if ('' === $value) { return false; }
        try {
            new DateTimeImmutable($value);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function canonicalJson(array $value): string
    {
        $normalize = function(mixed $item) use (&$normalize): mixed {
            if (! is_array($item)) { return $item; }
            if (array_is_list($item)) { return array_map($normalize, $item); }
            ksort($item, SORT_STRING);
            foreach ($item as $key => $child) { $item[$key] = $normalize($child); }
            return $item;
        };
        return (string)json_encode($normalize($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }
}
