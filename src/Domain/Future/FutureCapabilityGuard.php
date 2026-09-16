<?php

declare(strict_types=1);

namespace Sabri\Localization\Domain\Future;

use DateTimeImmutable;
use InvalidArgumentException;
use Sabri\Localization\Domain\Locale\LocaleValidator;

/**
 * Cross-capability input validation for Future40 evidence-preview handlers.
 * Validation is deliberately stricter than PHP scalar coercion so malformed
 * planning evidence cannot look release-ready.
 */
final class FutureCapabilityGuard
{
    public static function normalize(string $id, array $input): array
    {
        return match ($id) {
            'CF06-FUT-016' => self::ocr($input),
            'CF06-FUT-018' => self::regionalLocale($input),
            'CF06-FUT-020' => self::calendar($input),
            'CF06-FUT-022' => self::glyphInventory($input),
            'CF06-FUT-025' => self::seo($input),
            'CF06-FUT-026' => self::launchGate($input),
            default => $input,
        };
    }

    private static function ocr(array $input): array
    {
        self::boundedRatio($input, 'confidence');
        if (array_key_exists('threshold', $input)) {
            self::boundedRatio($input, 'threshold');
        }
        return $input;
    }

    private static function regionalLocale(array $input): array
    {
        $locale = LocaleValidator::canonicalize((string)($input['locale'] ?? ''));
        $base = LocaleValidator::canonicalize((string)($input['base_locale'] ?? ''));
        if (null === $locale || null === $base) {
            throw new InvalidArgumentException('locale and base_locale must be valid BCP47-style tags.');
        }
        if ($locale === $base) {
            throw new InvalidArgumentException('Regional locale must differ from base locale.');
        }
        $input['locale'] = $locale;
        $input['base_locale'] = $base;
        return $input;
    }

    private static function calendar(array $input): array
    {
        $iso = trim((string)($input['canonical_iso_date'] ?? ''));
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $iso);
        $errors = DateTimeImmutable::getLastErrors();
        if (false === $date || (is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)) || $date->format('Y-m-d') !== $iso) {
            throw new InvalidArgumentException('canonical_iso_date must be a real Gregorian date in YYYY-MM-DD format.');
        }
        return $input;
    }

    private static function glyphInventory(array $input): array
    {
        $supported = $input['supported_codepoints'] ?? null;
        if (! is_array($supported) || [] === $supported) {
            throw new InvalidArgumentException('supported_codepoints must be a non-empty audited inventory.');
        }
        foreach ($supported as $codepoint) {
            if (1 !== preg_match('/^U\+[0-9A-F]{4,6}$/D', strtoupper((string)$codepoint))) {
                throw new InvalidArgumentException('supported_codepoints contains an invalid Unicode code point.');
            }
        }
        return $input;
    }

    private static function seo(array $input): array
    {
        $links = $input['links'] ?? null;
        if (! is_array($links) || [] === $links) {
            throw new InvalidArgumentException('links must be a non-empty array.');
        }
        foreach ($links as $row) {
            if (! is_array($row)) {
                throw new InvalidArgumentException('Each SEO link row must be an object.');
            }
            $hreflang = trim((string)($row['hreflang'] ?? ''));
            if ('x-default' !== strtolower($hreflang) && null === LocaleValidator::canonicalize($hreflang)) {
                throw new InvalidArgumentException('hreflang must be x-default or a valid BCP47-style tag.');
            }
            foreach (['url', 'canonical'] as $field) {
                $url = trim((string)($row[$field] ?? ''));
                $parts = parse_url($url);
                if (false === filter_var($url, FILTER_VALIDATE_URL) || ! is_array($parts) || 'https' !== strtolower((string)($parts['scheme'] ?? '')) || '' === (string)($parts['host'] ?? '')) {
                    throw new InvalidArgumentException($field . ' must be an absolute HTTPS URL.');
                }
                if (isset($parts['user']) || isset($parts['pass'])) {
                    throw new InvalidArgumentException($field . ' must not contain embedded credentials.');
                }
            }
        }
        return $input;
    }

    private static function launchGate(array $input): array
    {
        foreach (['coverage_percent', 'threshold_percent'] as $field) {
            if (! array_key_exists($field, $input) || ! is_numeric($input[$field])) {
                throw new InvalidArgumentException($field . ' must be numeric.');
            }
            $value = (float)$input[$field];
            if ($value < 0 || $value > 100) {
                throw new InvalidArgumentException($field . ' must be between 0 and 100.');
            }
        }
        if (isset($input['critical_missing']) && (! is_numeric($input['critical_missing']) || (int)$input['critical_missing'] < 0)) {
            throw new InvalidArgumentException('critical_missing must be a non-negative integer.');
        }
        return $input;
    }

    private static function boundedRatio(array $input, string $field): void
    {
        if (! array_key_exists($field, $input) || ! is_numeric($input[$field])) {
            throw new InvalidArgumentException($field . ' must be numeric.');
        }
        $value = (float)$input[$field];
        if ($value < 0 || $value > 1) {
            throw new InvalidArgumentException($field . ' must be between 0 and 1.');
        }
    }
}
