<?php

declare(strict_types=1);

namespace Sabri\Localization\Domain\Bundle;

final class DeterministicBundle
{
    public static function build(string $locale, array $items, array $metadata = array()): array
    {
        ksort($items, SORT_STRING);
        $normalized = array();
        foreach ($items as $key => $item) {
            if (is_array($item)) {
                ksort($item, SORT_STRING);
            }
            $normalized[(string) $key] = $item;
        }
        ksort($metadata, SORT_STRING);
        $payload = array(
            'contract_version' => defined('SABRI_SLTO_CONTRACT_VERSION') ? SABRI_SLTO_CONTRACT_VERSION : '1.0.0',
            'locale' => $locale,
            'metadata' => $metadata,
            'items' => $normalized,
        );
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        return array('payload' => $payload, 'json' => $json, 'sha256' => hash('sha256', $json));
    }

    public static function sign(string $hash): ?string
    {
        $active = self::activeSigningKey();
        if (null === $active) {
            return null;
        }
        [$keyId, $key] = $active;
        return $keyId . ':' . base64_encode(hash_hmac('sha256', $hash, $key, true));
    }

    public static function verify(string $hash, string $signature): bool
    {
        if (! str_contains($signature, ':')) {
            return false;
        }
        [$keyId, $encoded] = explode(':', $signature, 2);
        $keys = self::signingKeys();
        if (! isset($keys[$keyId])) {
            return false;
        }
        $expected = base64_encode(hash_hmac('sha256', $hash, $keys[$keyId], true));
        return hash_equals($expected, $encoded);
    }

    public static function signingKeyId(): ?string
    {
        $active = self::activeSigningKey();
        return $active[0] ?? null;
    }

    private static function activeSigningKey(): ?array
    {
        $keys = self::signingKeys();
        $activeId = defined('SLTO_ACTIVE_BUNDLE_SIGNING_KEY_ID')
            ? (string) SLTO_ACTIVE_BUNDLE_SIGNING_KEY_ID
            : (string) getenv('SLTO_ACTIVE_BUNDLE_SIGNING_KEY_ID');
        if ('' === $activeId && isset($keys['legacy'])) {
            $activeId = 'legacy';
        }
        return '' !== $activeId && isset($keys[$activeId]) ? array($activeId, $keys[$activeId]) : null;
    }

    private static function signingKeys(): array
    {
        $keys = array();
        $raw = defined('SLTO_BUNDLE_SIGNING_KEYS')
            ? (string) SLTO_BUNDLE_SIGNING_KEYS
            : (string) getenv('SLTO_BUNDLE_SIGNING_KEYS');
        $decoded = '' === $raw ? null : json_decode($raw, true);
        if (is_array($decoded)) {
            foreach ($decoded as $id => $encoded) {
                $key = base64_decode((string) $encoded, true);
                if (false !== $key && strlen($key) >= 32 && preg_match('/^[A-Za-z0-9._-]{1,64}$/D', (string) $id)) {
                    $keys[(string) $id] = $key;
                }
            }
        }
        // Backward-compatible single-key configuration for staging migration.
        $legacy = defined('SLTO_BUNDLE_SIGNING_KEY') ? (string) SLTO_BUNDLE_SIGNING_KEY : (string) getenv('SLTO_BUNDLE_SIGNING_KEY');
        $legacyDecoded = base64_decode($legacy, true);
        if (false !== $legacyDecoded && strlen($legacyDecoded) >= 32) {
            $keys['legacy'] = $legacyDecoded;
        }
        return $keys;
    }
}
