<?php

declare(strict_types=1);

namespace Sabri\Localization\Infrastructure;

use RuntimeException;

final class Crypto
{
    public function available(): bool
    {
        return function_exists('openssl_encrypt')&&function_exists('openssl_decrypt')&&null !== $this->activeKey();
    }

    public function encrypt(string $plaintext, string $purpose): array
    {
        if(!function_exists('openssl_encrypt')){throw new RuntimeException('Localization encryption runtime is unavailable.');}
        $active = $this->activeKey();
        if (null === $active) {
            throw new RuntimeException('Localization encryption key is unavailable.');
        }
        [$keyId, $key] = $active;
        $iv = random_bytes(12);
        $tag = '';
        $aad = 'slto|' . $purpose . '|' . $keyId;
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $aad, 16);
        if (false === $ciphertext || 16 !== strlen($tag)) {
            throw new RuntimeException('Localization payload encryption failed.');
        }
        return array(
            'key_id' => $keyId,
            'algorithm' => 'AES-256-GCM',
            'nonce' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'ciphertext' => base64_encode($ciphertext),
            'aad_hash' => hash('sha256', $aad),
        );
    }

    public function decrypt(array $envelope, string $purpose): string
    {
        if ('AES-256-GCM' !== (string)($envelope['algorithm'] ?? '')) {
            throw new RuntimeException('Localization payload encryption algorithm is unsupported or tampered.');
        }
        $keyId = (string) ($envelope['key_id'] ?? '');
        $keys = $this->keys();
        if (! isset($keys[$keyId])) {
            throw new RuntimeException('Localization encryption key version is unavailable.');
        }
        $aad = 'slto|' . $purpose . '|' . $keyId;
        if (! hash_equals(hash('sha256', $aad), (string) ($envelope['aad_hash'] ?? ''))) {
            throw new RuntimeException('Localization payload purpose binding failed.');
        }
        if(!function_exists('openssl_decrypt')){throw new RuntimeException('Localization decryption runtime is unavailable.');}
        $ciphertext=base64_decode((string)($envelope['ciphertext']??''),true);
        $nonce=base64_decode((string)($envelope['nonce']??''),true);
        $tag=base64_decode((string)($envelope['tag']??''),true);
        if(false===$ciphertext||false===$nonce||false===$tag||12!==strlen($nonce)||16!==strlen($tag)){
            throw new RuntimeException('Localization payload encryption envelope is malformed.');
        }
        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $keys[$keyId],
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $aad
        );
        if (false === $plaintext) {
            throw new RuntimeException('Localization payload decryption failed.');
        }
        return $plaintext;
    }

    private function activeKey(): ?array
    {
        $keys = $this->keys();
        $active = defined('SLTO_ACTIVE_DATA_KEY_ID') ? (string) SLTO_ACTIVE_DATA_KEY_ID : (string) getenv('SLTO_ACTIVE_DATA_KEY_ID');
        if ('' === $active || ! isset($keys[$active])) {
            return null;
        }
        return array($active, $keys[$active]);
    }

    private function keys(): array
    {
        $raw = defined('SLTO_DATA_ENCRYPTION_KEYS') ? (string) SLTO_DATA_ENCRYPTION_KEYS : (string) getenv('SLTO_DATA_ENCRYPTION_KEYS');
        if ('' === $raw) {
            return array();
        }
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return array();
        }
        $keys = array();
        foreach ($decoded as $id => $encoded) {
            $key = base64_decode((string) $encoded, true);
            if (false !== $key && 32 === strlen($key) && preg_match('/^[A-Za-z0-9._-]{1,64}$/D', (string) $id)) {
                $keys[(string) $id] = $key;
            }
        }
        return $keys;
    }
}
