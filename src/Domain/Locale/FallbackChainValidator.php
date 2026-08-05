<?php

declare(strict_types=1);

namespace Sabri\Localization\Domain\Locale;

use InvalidArgumentException;

final class FallbackChainValidator
{
    private const MAX_DEPTH = 8;

    public static function assertValid(array $fallbackMap): void
    {
        foreach ($fallbackMap as $tag => $fallback) {
            if (null === $fallback || '' === $fallback) {
                continue;
            }
            if (! array_key_exists($fallback, $fallbackMap)) {
                throw new InvalidArgumentException('Fallback locale is not registered: ' . $fallback);
            }
            if ($tag === $fallback) {
                throw new InvalidArgumentException('A locale cannot fall back to itself: ' . $tag);
            }
            self::walk($tag, $fallbackMap);
        }
    }

    public static function chainFor(string $tag, array $fallbackMap): array
    {
        self::assertValid($fallbackMap);
        $chain = array();
        $current = $tag;
        while (isset($fallbackMap[$current]) && null !== $fallbackMap[$current] && '' !== $fallbackMap[$current]) {
            $current = (string) $fallbackMap[$current];
            $chain[] = $current;
        }
        return $chain;
    }

    private static function walk(string $start, array $fallbackMap): void
    {
        $seen = array($start => true);
        $current = $start;
        $depth = 0;
        while (isset($fallbackMap[$current]) && null !== $fallbackMap[$current] && '' !== $fallbackMap[$current]) {
            $current = (string) $fallbackMap[$current];
            ++$depth;
            if (isset($seen[$current])) {
                throw new InvalidArgumentException('Cyclic locale fallback detected at: ' . $current);
            }
            if ($depth > self::MAX_DEPTH) {
                throw new InvalidArgumentException('Locale fallback chain exceeds maximum depth.');
            }
            $seen[$current] = true;
        }
    }
}
