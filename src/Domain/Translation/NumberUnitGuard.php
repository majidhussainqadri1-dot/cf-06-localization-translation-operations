<?php

declare(strict_types=1);

namespace Sabri\Localization\Domain\Translation;

use InvalidArgumentException;

final class NumberUnitGuard
{
    /**
     * CF06-FR-017/023: keep the ORDERED protected numeric sequence.
     * Sorting matched tokens would mask swapped medical doses, amounts
     * or potency assignments. This conservative set is not a UCUM/CLDR
     * replacement or qualified clinical translation review.
     */
    private const VALUE_PATTERN = <<<'REGEX'
~(?<![\p{L}\p{N}])
(?:
  (?:PKR|USD|EUR|GBP|AED|SAR|INR)\s*[-+−]?\d+(?:[.,]\d+)?
  |
  [-+−]?\d+(?:[.,]\d+)?\s*
  (?:
      mcg/kg/min|µg/kg/min|μg/kg/min|mcg/kg|µg/kg|μg/kg
    | mg/dL|mmol/L|mEq/L|mEq|mmol|mmHg|kPa|mcg|µg|μg
    | mg|kg|g|mL|ml|L|l|IU|U|units|drops|tablets
    | mm|cm|m|°C|°F|PKR|USD|EUR|GBP|AED|SAR|INR
    | 1M|10M|50M|CM|MM|30C|200C|6X|12X|LM|X|C|M|Q|%
  )?
)
(?![\p{L}\p{N}])~ixuD
REGEX;

    public static function assertImmutable(string $source, string $target, bool $strict = true): void
    {
        if (! $strict) {
            return;
        }
        if (self::tokens($source) !== self::tokens($target)) {
            throw new InvalidArgumentException('Protected numeric, potency, dosage, currency or unit values changed in translation.');
        }
    }

    private static function tokens(string $text): array
    {
        $matched = preg_match_all(self::VALUE_PATTERN, $text, $found);
        if ($matched === false) {
            throw new InvalidArgumentException('Numeric/unit QA could not parse protected values.');
        }
        // Keep natural encounter order. 5 mg then 10 mg is not equivalent
        // to 10 mg then 5 mg, even if both amounts still appear.
        return array_map(
            static fn (string $value): string => preg_replace('/\s+/u', '', $value) ?? $value,
            $found[0] ?? array()
        );
    }
}
