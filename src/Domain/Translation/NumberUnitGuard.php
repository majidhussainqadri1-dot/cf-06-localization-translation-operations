<?php

declare(strict_types=1);

namespace Sabri\Localization\Domain\Translation;

use InvalidArgumentException;

final class NumberUnitGuard
{
    public static function assertImmutable(string $source, string $target, bool $strict = true): void
    {
        if (! $strict) {
            return;
        }
        $sourceTokens = self::tokens($source);
        $targetTokens = self::tokens($target);
        if ($sourceTokens !== $targetTokens) {
            throw new InvalidArgumentException('Protected numeric, potency, dosage, currency or unit values changed in translation.');
        }
    }

    private static function tokens(string $text): array
    {
        preg_match_all('/(?<![\p{L}\p{N}])(?:\d+(?:[.,]\d+)?\s*(?:%|mg|g|kg|mcg|ml|l|mm|cm|m|°C|°F|PKR|USD|EUR|X|C|M|LM|Q)?|\b(?:1M|10M|50M|CM|MM|30C|200C|6X|12X)\b)(?![\p{L}\p{N}])/iu', $text, $matches);
        $tokens = array_map(static fn (string $value): string => preg_replace('/\s+/u', '', $value) ?? $value, $matches[0] ?? array());
        sort($tokens, SORT_STRING);
        return $tokens;
    }
}
