<?php

declare(strict_types=1);

namespace Sabri\Localization\Domain\Locale;

final class LocaleValidator
{
    private const PATTERN = '/^(?<language>[A-Za-z]{2,8})(?:-(?<script>[A-Za-z]{4}))?(?:-(?<region>[A-Za-z]{2}|[0-9]{3}))?(?<variants>(?:-(?:[A-Za-z0-9]{5,8}|[0-9][A-Za-z0-9]{3}))*)$/D';

    public static function canonicalize(string $tag): ?string
    {
        $parsed = self::parse($tag);

        return $parsed['tag'] ?? null;
    }

    public static function parse(string $tag): ?array
    {
        $tag = trim(str_replace('_', '-', $tag));
        if ('' === $tag || 1 !== preg_match(self::PATTERN, $tag, $matches)) {
            return null;
        }

        $language = strtolower($matches['language']);
        $script   = isset($matches['script']) ? ucfirst(strtolower($matches['script'])) : '';
        $region   = isset($matches['region']) ? strtoupper($matches['region']) : '';
        $variants = array_values(array_filter(explode('-', ltrim((string) ($matches['variants'] ?? ''), '-')), static fn (string $value): bool => '' !== $value));
        $variants = array_map('strtolower', $variants);
        $parts    = array_filter(array_merge(array($language, $script, $region), $variants), static fn (string $value): bool => '' !== $value);

        return array(
            'tag' => implode('-', $parts),
            'language' => $language,
            'script' => $script,
            'region' => $region,
            'variants' => $variants,
        );
    }
}
