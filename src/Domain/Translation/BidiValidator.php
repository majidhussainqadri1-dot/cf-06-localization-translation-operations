<?php

declare(strict_types=1);

namespace Sabri\Localization\Domain\Translation;

use InvalidArgumentException;

final class BidiValidator
{
    private const LEGACY_FORBIDDEN = array("\u{202A}", "\u{202B}", "\u{202D}", "\u{202E}", "\u{202C}");
    private const ISOLATE_OPEN = array("\u{2066}", "\u{2067}", "\u{2068}");
    private const ISOLATE_CLOSE = "\u{2069}";

    public static function assertSafe(string $text, bool $allowIsolation = false): void
    {
        foreach (self::LEGACY_FORBIDDEN as $control) {
            if (str_contains($text, $control)) {
                throw new InvalidArgumentException('Unsafe bidirectional embedding or override control character detected.');
            }
        }

        $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if (false === $characters) {
            throw new InvalidArgumentException('Text is not valid Unicode.');
        }

        $depth = 0;
        foreach ($characters as $character) {
            if (in_array($character, self::ISOLATE_OPEN, true)) {
                if (! $allowIsolation) {
                    throw new InvalidArgumentException('Bidirectional isolation control is not allowed in this context.');
                }
                ++$depth;
                if ($depth > 8) {
                    throw new InvalidArgumentException('Bidirectional isolation nesting exceeds the safe limit.');
                }
                continue;
            }
            if (self::ISOLATE_CLOSE === $character) {
                if (! $allowIsolation || $depth <= 0) {
                    throw new InvalidArgumentException('Unpaired bidirectional isolation terminator detected.');
                }
                --$depth;
            }
        }

        if (0 !== $depth) {
            throw new InvalidArgumentException('Unclosed bidirectional isolation control detected.');
        }
    }
}
