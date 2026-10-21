<?php

declare(strict_types=1);

namespace Sabri\Localization\Domain\Future;

use InvalidArgumentException;
use Sabri\Localization\Domain\Locale\LocaleValidator;

final class ProviderEligibilityGuard
{
    public static function normalize(array $input): array
    {
        $providers = $input['providers'] ?? null;
        if (! is_array($providers)) {
            throw new InvalidArgumentException('providers must be an array.');
        }
        $eligible = [];
        $seenIds = [];
        foreach ($providers as $provider) {
            if (! is_array($provider)
                || true !== ($provider['approved'] ?? false)
                || true !== ($provider['healthy'] ?? false)
                || true === ($provider['training_allowed'] ?? true)
                || true !== ($provider['region_verified'] ?? false)
                || true !== ($provider['deletion_supported'] ?? false)
                || true !== ($provider['contract_current'] ?? false)) {
                continue;
            }
            $id = trim((string)($provider['id'] ?? ''));
            $quality = $provider['quality'] ?? null;
            $retention = filter_var($provider['retention_days'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 30]]);
            if ('' === $id || 1 !== preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/D', $id) || isset($seenIds[$id]) || ! is_numeric($quality) || false === $retention) {
                continue;
            }
            $seenIds[$id]=true;
            $quality = (float)$quality;
            if ($quality < 0 || $quality > 100) {
                continue;
            }
            foreach (['data_classes','locales','regions'] as $field) {
                if (! is_array($provider[$field] ?? null) || [] === $provider[$field]) {
                    continue 2;
                }
            }
            $classes = array_values(array_unique(array_map(static fn($v): string => strtoupper(trim((string)$v)), $provider['data_classes'])));
            if ([] === $classes || array_filter($classes, static fn(string $v): bool => 1 !== preg_match('/^C[1-5]$/D', $v))) {
                continue;
            }
            $locales = [];
            foreach ($provider['locales'] as $locale) {
                $canonical = LocaleValidator::canonicalize((string)$locale);
                if (null === $canonical) {
                    continue 2;
                }
                $locales[] = $canonical;
            }
            $regions = array_values(array_unique(array_map(static fn($v): string => strtoupper(trim((string)$v)), $provider['regions'])));
            if ([] === $regions || array_filter($regions, static fn(string $v): bool => 1 !== preg_match('/^[A-Z0-9-]{2,16}$/D', $v))) {
                continue;
            }
            $provider['quality'] = $quality;
            $provider['retention_days'] = $retention;
            $provider['data_classes'] = $classes;
            $provider['locales'] = array_values(array_unique($locales));
            $provider['regions'] = $regions;
            $provider['training_allowed'] = false;
            $eligible[] = $provider;
        }
        $input['providers'] = $eligible;
        return $input;
    }
}
