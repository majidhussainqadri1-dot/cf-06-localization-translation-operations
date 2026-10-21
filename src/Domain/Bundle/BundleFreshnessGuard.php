<?php

declare(strict_types=1);

namespace Sabri\Localization\Domain\Bundle;

use InvalidArgumentException;

/**
 * Proves that every source snapshot bound into a locale bundle still matches the
 * current active canonical translatable resource. This prevents a signed but
 * semantically stale bundle from remaining eligible after correction/rights expiry.
 */
final class BundleFreshnessGuard
{
    public static function assertCurrent(array $sources, callable $lookupResource): void
    {
        if (empty($sources)) {
            throw new InvalidArgumentException('Locale bundle source evidence is empty.');
        }

        $seen = array();
        foreach ($sources as $source) {
            if (! is_array($source)) {
                throw new InvalidArgumentException('Locale bundle source evidence is malformed.');
            }
            $key = (string) ($source['key'] ?? '');
            $hash = strtolower((string) ($source['source_hash'] ?? ''));
            $version = (int) ($source['source_version'] ?? 0);
            if ('' === $key || isset($seen[$key]) || 1 !== preg_match('/^[a-f0-9]{64}$/D', $hash) || $version <= 0) {
                throw new InvalidArgumentException('Locale bundle source evidence is incomplete or duplicated.');
            }
            $seen[$key] = true;

            $resource = $lookupResource($key);
            if (! is_array($resource)
                || 'active' !== (string) ($resource['status'] ?? '')
                || $version !== (int) ($resource['source_version'] ?? 0)
                || ! hash_equals($hash, strtolower((string) ($resource['source_hash'] ?? '')))) {
                throw new InvalidArgumentException('Locale bundle contains a stale, retired or replaced source resource.');
            }
        }
    }
}
