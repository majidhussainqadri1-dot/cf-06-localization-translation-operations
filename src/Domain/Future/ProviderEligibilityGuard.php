<?php

declare(strict_types=1);

namespace Sabri\Localization\Domain\Future;

use InvalidArgumentException;

final class ProviderEligibilityGuard
{
    public static function normalize(array $input): array
    {
        $providers = $input['providers'] ?? null;
        if (! is_array($providers)) {
            throw new InvalidArgumentException('providers must be an array.');
        }
        $eligible = [];
        foreach ($providers as $provider) {
            if (! is_array($provider) || true !== ($provider['approved'] ?? false)) {
                continue;
            }
            $id = trim((string)($provider['id'] ?? ''));
            $quality = $provider['quality'] ?? null;
            if ('' === $id || ! is_numeric($quality)) {
                continue;
            }
            $quality = (float)$quality;
            if ($quality < 0 || $quality > 100) {
                continue;
            }
            foreach (['data_classes','locales','regions'] as $field) {
                if (! is_array($provider[$field] ?? null) || [] === $provider[$field]) {
                    continue 2;
                }
            }
            $provider['quality'] = $quality;
            $eligible[] = $provider;
        }
        $input['providers'] = $eligible;
        return $input;
    }
}
