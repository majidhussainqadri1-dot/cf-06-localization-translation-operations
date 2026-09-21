<?php

declare(strict_types=1);

namespace Sabri\Localization\Domain\Translation;

use InvalidArgumentException;

final class MarkupValidator
{
    private const ALLOWED_TAGS = array('a', 'abbr', 'b', 'br', 'code', 'em', 'i', 'kbd', 'li', 'ol', 'p', 'small', 'span', 'strong', 'sub', 'sup', 'ul');
    private const VOID_TAGS = array('br');

    public static function assertEquivalent(string $source, string $target): void
    {
        $sourceTags = self::tokens($source);
        $targetTags = self::tokens($target);
        if ($sourceTags !== $targetTags) {
            throw new InvalidArgumentException('Translation markup structure differs from the source.');
        }
    }

    private static function tokens(string $text): array
    {
        preg_match_all('/<\s*(\/?)\s*([A-Za-z0-9]+)(?:\s+[^>]*)?(\/?)\s*>/', $text, $matches, PREG_SET_ORDER);
        $tokens = array();
        $stack = array();
        foreach ($matches as $match) {
            $tag = strtolower((string) $match[2]);
            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                throw new InvalidArgumentException('Disallowed markup tag in translatable text.');
            }
            $closing = '/' === (string) $match[1];
            $selfClosing = '/' === (string) $match[3] || in_array($tag, self::VOID_TAGS, true);
            if ($closing) {
                if ($selfClosing || array_pop($stack) !== $tag) {
                    throw new InvalidArgumentException('Malformed or unbalanced translatable markup.');
                }
                $tokens[] = '/' . $tag;
            } elseif ($selfClosing) {
                $tokens[] = $tag . '/';
            } else {
                $stack[] = $tag;
                $tokens[] = $tag;
            }
        }
        if (! empty($stack)) {
            throw new InvalidArgumentException('Malformed or unbalanced translatable markup.');
        }
        return $tokens;
    }
}
