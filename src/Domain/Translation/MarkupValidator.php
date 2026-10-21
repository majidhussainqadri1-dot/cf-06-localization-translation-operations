<?php

declare(strict_types=1);

namespace Sabri\Localization\Domain\Translation;

use InvalidArgumentException;

/**
 * Enforces CF06-FR-017/023: the translation cannot change the meaning of
 * protected markup, hyperlinks or approved formatting attributes.
 *
 * Rendering components must still escape/sanitize HTML at output; this
 * structural guard is not a replacement for contextual output escaping.
 */
final class MarkupValidator
{
    private const ALLOWED_TAGS = array('a', 'abbr', 'b', 'br', 'code', 'em', 'i', 'kbd', 'li', 'ol', 'p', 'small', 'span', 'strong', 'sub', 'sup', 'ul');
    private const VOID_TAGS = array('br');
    private const ALLOWED_ATTRIBUTES = array('href', 'title', 'lang', 'dir', 'class', 'rel', 'target', 'aria-label');

    public static function assertEquivalent(string $source, string $target): void
    {
        if (self::tokens($source) !== self::tokens($target)) {
            throw new InvalidArgumentException('Translation markup, link or protected attributes differ from the source.');
        }
    }

    private static function tokens(string $text): array
    {
        preg_match_all('/<[^>]*>/', $text, $candidates);
        $tokens = array();
        $stack = array();
        foreach ($candidates[0] as $candidate) {
            if (1 !== preg_match('/^<\s*(\/?)\s*([A-Za-z][A-Za-z0-9]*)([^<>]*?)\s*(\/?)\s*>$/Ds', $candidate, $match)) {
                throw new InvalidArgumentException('Malformed or unsupported translatable markup.');
            }
            $tag = strtolower((string) $match[2]);
            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                throw new InvalidArgumentException('Disallowed markup tag in translatable text.');
            }
            $closing = '/' === $match[1];
            $selfClosing = '/' === $match[4] || in_array($tag, self::VOID_TAGS, true);
            $attributes = self::attributes((string) $match[3], $tag, $closing);

            if ($closing) {
                if ($selfClosing || array_pop($stack) !== $tag) {
                    throw new InvalidArgumentException('Malformed or unbalanced translatable markup.');
                }
                $tokens[] = '/' . $tag;
            } elseif ($selfClosing) {
                $tokens[] = $tag . '/' . json_encode($attributes, JSON_THROW_ON_ERROR);
            } else {
                $stack[] = $tag;
                $tokens[] = $tag . ':' . json_encode($attributes, JSON_THROW_ON_ERROR);
            }
        }
        if ($stack !== array()) {
            throw new InvalidArgumentException('Malformed or unbalanced translatable markup.');
        }
        return $tokens;
    }

    /** @return array<string,string> */
    private static function attributes(string $text, string $tag, bool $closing): array
    {
        $attributes = array();
        $remaining = $text;
        if ($closing && trim($remaining) !== '') {
            throw new InvalidArgumentException('A closing translation tag cannot carry attributes.');
        }
        while (trim($remaining) !== '') {
            if (1 !== preg_match('/^\s+([A-Za-z][A-Za-z0-9-]*)\s*=\s*(?:"([^"]*)"|' . "'([^']*)'" . ')/s', $remaining, $match, PREG_UNMATCHED_AS_NULL)) {
                throw new InvalidArgumentException('Markup attributes must be explicit and quoted.');
            }
            $name = strtolower($match[1]);
            $value = $match[2] ?? $match[3] ?? '';
            if (!in_array($name, self::ALLOWED_ATTRIBUTES, true) || isset($attributes[$name])) {
                throw new InvalidArgumentException('Unsafe or duplicate translation markup attribute.');
            }
            $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (preg_match('/[\x00-\x1F\x7F<>]/', $decoded)) {
                throw new InvalidArgumentException('Unsafe control or angle bracket inside markup attribute.');
            }
            if ($name === 'href') {
                if ($tag !== 'a' ||
                    !(preg_match('~^https://[A-Za-z0-9.-]+(?::443)?(?:[/?#][^\s\\]*)?$~D', $decoded) === 1 ||
                      (str_starts_with($decoded, '/') && !str_starts_with($decoded, '//') && !str_contains($decoded, '\\')) ||
                      preg_match('/^#[A-Za-z0-9_-]+$/D', $decoded) === 1)) {
                    throw new InvalidArgumentException('Translation link target is unsafe or not an approved HTTPS/root-relative URL.');
                }
            }
            if ($name === 'dir' && !in_array($decoded, array('ltr', 'rtl', 'auto'), true)) {
                throw new InvalidArgumentException('Unsupported translation direction attribute.');
            }
            $attributes[$name] = $decoded;
            $remaining = substr($remaining, strlen($match[0]));
        }
        ksort($attributes, SORT_STRING);
        return $attributes;
    }
}
