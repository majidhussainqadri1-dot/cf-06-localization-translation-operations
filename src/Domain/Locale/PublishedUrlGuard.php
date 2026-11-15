<?php

declare(strict_types=1);

namespace Sabri\Localization\Domain\Locale;

use InvalidArgumentException;

/** V12.3: a claimed published translation must have both public URL fields. */
final class PublishedUrlGuard
{
    public static function assertValid(string $status, ?string $translated, ?string $canonical): void
    {
        if ('published' !== $status) {
            return;
        }
        foreach (['translated_url' => $translated, 'canonical_url' => $canonical] as $field => $url) {
            if (!is_string($url) || '' === trim($url) || strlen($url) > 255) {
                throw new InvalidArgumentException('Published translation requires a bounded ' . $field . '.');
            }
            $parts = parse_url($url);
            if (!is_array($parts) || 'https' !== strtolower((string)($parts['scheme'] ?? ''))
                || '' === (string)($parts['host'] ?? '') || isset($parts['user'])
                || isset($parts['pass']) || isset($parts['fragment'])) {
                throw new InvalidArgumentException('Published translation URL requires an HTTPS canonical address without credentials or fragments.');
            }
        }
    }
}
