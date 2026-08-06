<?php

declare(strict_types=1);

namespace Sabri\Localization\Domain\Locale;

use InvalidArgumentException;

final class FallbackChainValidator
{
    public static function validate(string $locale, ?string $fallback, callable $lookup, int $maximumDepth = 8): void
    {
        if (null === $fallback || '' === $fallback) {
            return;
        }
        $locale = LocaleValidator::canonicalize($locale) ?? '';
        $fallback = LocaleValidator::canonicalize($fallback) ?? '';
        if ('' === $locale || '' === $fallback) {
            throw new InvalidArgumentException('Invalid fallback locale.');
        }
        $seen = array($locale => true);
        $current = $fallback;
        for ($depth = 0; $depth <= $maximumDepth; $depth++) {
            if (isset($seen[$current])) {
                throw new InvalidArgumentException('Locale fallback cycle detected.');
            }
            $seen[$current] = true;
            $record = $lookup($current);
            if (! is_array($record)) {
                throw new InvalidArgumentException('Fallback locale is not registered.');
            }
            if (! in_array((string) ($record['status'] ?? ''), array('enabled', 'content_ready', 'degraded'), true)) {
                throw new InvalidArgumentException('Fallback locale is not eligible for resolution.');
            }
            $next = (string) ($record['fallback_tag'] ?? '');
            if ('' === $next) {
                return;
            }
            $current = LocaleValidator::canonicalize($next) ?? '';
            if ('' === $current) {
                throw new InvalidArgumentException('Invalid locale in fallback chain.');
            }
        }
        throw new InvalidArgumentException('Locale fallback chain exceeds the maximum depth.');
    }
}
