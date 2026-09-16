<?php

declare(strict_types=1);

namespace Sabri\Localization\Domain\Translation;

use InvalidArgumentException;

/**
 * Structural validator for the ICU MessageFormat subset used by platform strings.
 *
 * It deliberately validates arguments, formatter types and plural/select branches
 * without attempting to render messages. Runtime formatting remains the shell/
 * client owner's responsibility.
 */
final class MessageFormatValidator
{
    private const SELECTOR_TYPES = array('plural', 'selectordinal', 'select');

    public static function analyze(string $message): array
    {
        $arguments = array();
        $formats = array();
        $selectors = array();
        self::scan($message, $arguments, $formats, $selectors);

        $arguments = array_values(array_unique($arguments));
        sort($arguments, SORT_STRING);
        ksort($formats, SORT_STRING);
        ksort($selectors, SORT_STRING);
        foreach ($selectors as &$selector) {
            sort($selector['categories'], SORT_STRING);
        }
        unset($selector);

        return array(
            'arguments' => $arguments,
            'formats' => $formats,
            'selectors' => $selectors,
        );
    }

    public static function assertEquivalent(string $source, string $target): void
    {
        $sourceAnalysis = self::analyze($source);
        $targetAnalysis = self::analyze($target);

        if ($sourceAnalysis['arguments'] !== $targetAnalysis['arguments']) {
            throw new InvalidArgumentException('ICU MessageFormat argument set changed in translation.');
        }
        if ($sourceAnalysis['formats'] !== $targetAnalysis['formats']) {
            throw new InvalidArgumentException('ICU MessageFormat formatter type changed in translation.');
        }
        if ($sourceAnalysis['selectors'] !== $targetAnalysis['selectors']) {
            throw new InvalidArgumentException('ICU MessageFormat plural/select branches changed in translation.');
        }
    }

    private static function scan(string $message, array &$arguments, array &$formats, array &$selectors): void
    {
        $length = strlen($message);
        for ($i = 0; $i < $length; ++$i) {
            if ('{' !== $message[$i]) {
                continue;
            }

            $parsed = self::parseArgumentAt($message, $i);
            if (null === $parsed) {
                continue;
            }

            $arguments[] = $parsed['name'];
            if (null !== $parsed['type']) {
                $formats[$parsed['name']] = $parsed['type'];
            }
            if (null !== $parsed['selector']) {
                if (isset($selectors[$parsed['name']]) && $selectors[$parsed['name']] !== $parsed['selector']) {
                    throw new InvalidArgumentException('ICU MessageFormat selector is inconsistent for the same argument.');
                }
                $selectors[$parsed['name']] = $parsed['selector'];
            }

            if ('' !== $parsed['body']) {
                self::scan($parsed['body'], $arguments, $formats, $selectors);
            }
            $i = $parsed['end'];
        }
    }

    private static function parseArgumentAt(string $message, int $open): ?array
    {
        $length = strlen($message);
        $cursor = $open + 1;
        while ($cursor < $length && ctype_space($message[$cursor])) {
            ++$cursor;
        }
        if ($cursor >= $length || 1 !== preg_match('/[A-Za-z_]/', $message[$cursor])) {
            return null;
        }

        $start = $cursor;
        while ($cursor < $length && 1 === preg_match('/[A-Za-z0-9_]/', $message[$cursor])) {
            ++$cursor;
        }
        $name = substr($message, $start, $cursor - $start);
        while ($cursor < $length && ctype_space($message[$cursor])) {
            ++$cursor;
        }

        if ($cursor >= $length) {
            throw new InvalidArgumentException('Unclosed ICU MessageFormat argument.');
        }
        if ('}' === $message[$cursor]) {
            return array('name' => $name, 'type' => null, 'selector' => null, 'body' => '', 'end' => $cursor);
        }
        if (',' !== $message[$cursor]) {
            return null;
        }

        ++$cursor;
        while ($cursor < $length && ctype_space($message[$cursor])) {
            ++$cursor;
        }
        $typeStart = $cursor;
        while ($cursor < $length && 1 === preg_match('/[A-Za-z]/', $message[$cursor])) {
            ++$cursor;
        }
        $type = strtolower(substr($message, $typeStart, $cursor - $typeStart));
        if ('' === $type) {
            throw new InvalidArgumentException('ICU MessageFormat argument type is missing.');
        }

        $end = self::findMatchingBrace($message, $open);
        if (! in_array($type, self::SELECTOR_TYPES, true)) {
            return array('name' => $name, 'type' => $type, 'selector' => null, 'body' => '', 'end' => $end);
        }

        while ($cursor < $end && ctype_space($message[$cursor])) {
            ++$cursor;
        }
        if ($cursor >= $end || ',' !== $message[$cursor]) {
            throw new InvalidArgumentException('ICU MessageFormat selector options are missing.');
        }
        ++$cursor;
        $body = substr($message, $cursor, $end - $cursor);
        $categories = self::selectorCategories($body, $type);

        return array(
            'name' => $name,
            'type' => $type,
            'selector' => array('type' => $type, 'categories' => $categories),
            'body' => $body,
            'end' => $end,
        );
    }

    private static function selectorCategories(string $body, string $type): array
    {
        $categories = array();
        $length = strlen($body);
        $i = 0;

        while ($i < $length) {
            while ($i < $length && ctype_space($body[$i])) {
                ++$i;
            }
            if ($i >= $length) {
                break;
            }

            if (in_array($type, array('plural', 'selectordinal'), true) && 1 === preg_match('/\Goffset\s*:\s*\d+/A', substr($body, $i), $offsetMatch)) {
                $i += strlen($offsetMatch[0]);
                continue;
            }

            if (1 !== preg_match('/\G(=[0-9]+|[A-Za-z][A-Za-z0-9_-]*)/A', substr($body, $i), $keyMatch)) {
                throw new InvalidArgumentException('Malformed ICU MessageFormat selector category.');
            }
            $category = $keyMatch[1];
            $i += strlen($keyMatch[0]);
            while ($i < $length && ctype_space($body[$i])) {
                ++$i;
            }
            if ($i >= $length || '{' !== $body[$i]) {
                throw new InvalidArgumentException('ICU MessageFormat selector category has no message body.');
            }
            $close = self::findMatchingBrace($body, $i);
            $categories[] = $category;
            $i = $close + 1;
        }

        $categories = array_values(array_unique($categories));
        if (! in_array('other', $categories, true)) {
            throw new InvalidArgumentException('ICU MessageFormat plural/select requires an other branch.');
        }
        sort($categories, SORT_STRING);
        return $categories;
    }

    private static function findMatchingBrace(string $text, int $open): int
    {
        $depth = 0;
        $length = strlen($text);
        for ($i = $open; $i < $length; ++$i) {
            if ('{' === $text[$i]) {
                ++$depth;
            } elseif ('}' === $text[$i]) {
                --$depth;
                if (0 === $depth) {
                    return $i;
                }
                if ($depth < 0) {
                    break;
                }
            }
        }
        throw new InvalidArgumentException('Unbalanced ICU MessageFormat braces.');
    }
}
