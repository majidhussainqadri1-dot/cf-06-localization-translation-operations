<?php

declare(strict_types=1);

namespace Sabri\Localization\Domain\Security;

use InvalidArgumentException;

final class UrlGuard
{
    public static function assertPublicHttps(string $url, array $allowedHosts): void
    {
        $parts = parse_url($url);
        if (! is_array($parts) || 'https' !== strtolower((string) ($parts['scheme'] ?? '')) || empty($parts['host'])) {
            throw new InvalidArgumentException('Provider URL must be an approved HTTPS endpoint.');
        }
        $host = strtolower((string) $parts['host']);
        if (! in_array($host, array_map('strtolower', $allowedHosts), true)) {
            throw new InvalidArgumentException('Provider host is not allowlisted.');
        }
        $ips = gethostbynamel($host) ?: array();
        if (empty($ips)) {
            throw new InvalidArgumentException('Provider hostname could not be resolved.');
        }
        foreach ($ips as $ip) {
            if (false === filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new InvalidArgumentException('Provider endpoint resolves to a private or reserved network.');
            }
        }
    }
}
