<?php

declare(strict_types=1);

namespace Sabri\Localization\Domain\Translation;

final class Redactor
{
    public static function redact(string $text): array
    {
        $patterns = array(
            'email' => '/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i',
            'phone' => '/(?<!\d)(?:\+?\d[\d\s().-]{7,}\d)(?!\d)/',
            'national_id' => '/(?<!\d)\d{5}-?\d{7}-?\d(?!\d)/',
            'card' => '/(?<!\d)(?:\d[ -]*?){13,19}(?!\d)/',
            'secret' => '/\b(?:sk|pk|api|token|secret|bearer)[_-]?[A-Za-z0-9._-]{12,}\b/i',
            'ipv4' => '/\b(?:\d{1,3}\.){3}\d{1,3}\b/',
        );
        $counts = array();
        foreach ($patterns as $name => $pattern) {
            $text = preg_replace_callback($pattern, static function () use (&$counts, $name): string {
                $counts[$name] = ($counts[$name] ?? 0) + 1;
                return '[REDACTED_' . strtoupper($name) . ']';
            }, $text) ?? $text;
        }
        return array('text' => $text, 'counts' => $counts, 'redacted' => array_sum($counts) > 0);
    }
}
