<?php

declare(strict_types=1);

namespace Sabri\Localization\Domain\Locale;

final class LocaleValidator
{
    public static function parse(string $input): ?array
    {
        $input = trim(str_replace('_', '-', $input));
        if ('' === $input || strlen($input) > 35 || 1 !== preg_match('/^[A-Za-z]{2,8}(?:-[A-Za-z]{4})?(?:-(?:[A-Za-z]{2}|[0-9]{3}))?(?:-[A-Za-z0-9]{5,8})*$/D', $input)) {
            return null;
        }
        $parts = explode('-', $input);
        $language = strtolower((string) array_shift($parts));
        $script = '';
        $region = '';
        $variants = array();
        foreach ($parts as $part) {
            if ('' === $script && 4 === strlen($part) && ctype_alpha($part)) {
                $script = ucfirst(strtolower($part));
                continue;
            }
            if ('' === $region && ((2 === strlen($part) && ctype_alpha($part)) || (3 === strlen($part) && ctype_digit($part)))) {
                $region = strtoupper($part);
                continue;
            }
            $variants[] = strtolower($part);
        }
        $tag = implode('-', array_filter(array_merge(array($language, $script, $region), $variants), static fn (string $v): bool => '' !== $v));
        return array('tag' => $tag, 'language' => $language, 'script' => $script, 'region' => $region, 'variants' => $variants);
    }

    public static function canonicalize(string $input): ?string
    {
        $parsed = self::parse($input);
        return $parsed['tag'] ?? null;
    }

    public static function direction(string $tag): string
    {
        $parsed = self::parse($tag);
        if (null === $parsed) {
            return 'ltr';
        }

        // An explicit script is stronger evidence than the language default.
        // This avoids misclassifying locales such as ku-Latn or ur-Latn.
        $rtlScripts = array('Adlm', 'Arab', 'Hebr', 'Nkoo', 'Rohg', 'Syrc', 'Thaa');
        if ('' !== $parsed['script']) {
            return in_array($parsed['script'], $rtlScripts, true) ? 'rtl' : 'ltr';
        }

        $rtlLanguages = array('ar', 'fa', 'he', 'ps', 'ur', 'sd', 'ug', 'dv', 'ku');
        return in_array($parsed['language'], $rtlLanguages, true) ? 'rtl' : 'ltr';
    }
}
