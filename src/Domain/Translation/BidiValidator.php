<?php

declare(strict_types=1);

namespace Sabri\Localization\Domain\Translation;

use InvalidArgumentException;

final class BidiValidator
{
    private const FORBIDDEN = array("\u{202A}", "\u{202B}", "\u{202D}", "\u{202E}", "\u{202C}", "\u{2066}", "\u{2067}", "\u{2068}", "\u{2069}");

    public static function assertSafe(string $text, bool $allowIsolation = false): void
    {
        foreach (self::FORBIDDEN as $control) {
            if (str_contains($text, $control)) {
                if ($allowIsolation && in_array($control, array("\u{2066}", "\u{2067}", "\u{2068}", "\u{2069}"), true)) {
                    continue;
                }
                throw new InvalidArgumentException('Unsafe bidirectional control character detected.');
            }
        }
    }
}
