<?php

declare(strict_types=1);

namespace Sabri\Localization\Domain\Future;

final class SemanticIntegrityGuard
{
    public static function apply(string $id, array $input, array $envelope): array
    {
        if (! isset($envelope['result']) || ! is_array($envelope['result'])) {
            return $envelope;
        }
        return match ($id) {
            'CF06-FUT-005' => self::semanticEquivalence($input, $envelope),
            'CF06-FUT-006' => self::riskDiff($input, $envelope),
            'CF06-FUT-009' => self::citationIntegrity($input, $envelope),
            'CF06-FUT-010' => self::protectedDomainTokens($input, $envelope),
            'CF06-FUT-036' => self::qualityEstimate($input, $envelope),
            default => $envelope,
        };
    }

    private static function semanticEquivalence(array $input, array $out): array
    {
        $source = (string)($input['source'] ?? '');
        $target = (string)($input['target'] ?? '');
        $declared = self::declaredTerms($input);
        $sourceTokens = self::protectedTokens($source, $declared);
        $targetTokens = self::protectedTokens($target, $declared);
        if ($sourceTokens !== $targetTokens) {
            $out['result']['flags'][] = 'protected-fact-drift';
        }
        $out['result']['flags'] = array_values(array_unique(array_map('strval', $out['result']['flags'] ?? [])));
        $out['result']['risk'] = [] === $out['result']['flags'] ? 'low' : 'review';
        $out['result']['protected_source_tokens'] = $sourceTokens;
        $out['result']['protected_target_tokens'] = $targetTokens;
        $out['result']['approval_authority'] = false;
        return $out;
    }

    private static function riskDiff(array $input, array $out): array
    {
        $old = (string)($input['old_source'] ?? '');
        $new = (string)($input['new_source'] ?? '');
        $oldTerms = self::normalizeTerms(is_array($input['old_protected_terms'] ?? null) ? $input['old_protected_terms'] : self::declaredTerms($input));
        $newTerms = self::normalizeTerms(is_array($input['new_protected_terms'] ?? null) ? $input['new_protected_terms'] : self::declaredTerms($input));
        $changed = self::protectedTokens($old, $oldTerms) !== self::protectedTokens($new, $newTerms) || $oldTerms !== $newTerms;
        if ($changed) {
            $out['result']['protected_token_change'] = true;
            $out['result']['risk'] = 'high-review';
        }
        return $out;
    }

    private static function citationIntegrity(array $input, array $out): array
    {
        $source = is_array($input['source_citations'] ?? null) ? $input['source_citations'] : [];
        $target = is_array($input['target_citations'] ?? null) ? $input['target_citations'] : [];
        $sourceHashes = self::citationFingerprints($source);
        $targetHashes = self::citationFingerprints($target);
        $out['result']['source_hashes'] = $sourceHashes;
        $out['result']['target_hashes'] = $targetHashes;
        $out['result']['integrity'] = $sourceHashes === $targetHashes ? 'pass' : 'fail';
        $out['result']['comparison'] = 'canonical-order-independent-multiset';
        return $out;
    }

    private static function protectedDomainTokens(array $input, array $out): array
    {
        $text = (string)($input['text'] ?? '');
        $declared = self::declaredTerms($input);
        $tokens = self::protectedTokens($text, $declared);
        $out['result']['tokens'] = $tokens;
        $out['result']['count'] = count($tokens);
        $out['result']['declared_terms'] = $declared;
        return $out;
    }

    private static function qualityEstimate(array $input, array $out): array
    {
        $source = (string)($input['source'] ?? '');
        $target = (string)($input['target'] ?? '');
        $declared = self::declaredTerms($input);
        $sourceTokens = self::protectedTokens($source, $declared);
        $targetTokens = self::protectedTokens($target, $declared);
        $flags = array_values(array_unique(array_map('strval', $out['result']['flags'] ?? [])));
        if ($sourceTokens !== $targetTokens && ! in_array('protected-token-drift', $flags, true)) {
            $flags[] = 'protected-token-drift';
        }
        $out['result']['flags'] = $flags;
        $out['result']['quality_estimate'] = max(0, 100 - count($flags) * 20);
        $out['result']['approval_authority'] = false;
        $out['result']['human_review_required'] = true;
        return $out;
    }

    private static function protectedTokens(string $text, array $declared): array
    {
        $patterns = [
            '/\{[A-Za-z_][A-Za-z0-9_.-]*\}/u',
            '/\b\d+(?:\.\d+)?\s?(?:mg|g|ml|mL|L|mcg|µg|IU|%|°C)\b/u',
            '/\b\d+(?:C|X|LM|M|CM)\b/u',
            '~https?://[^\s)]+~u',
            '/\b[A-Z]{2,}-\d+\b/u',
        ];
        $tokens = [];
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $text, $matches);
            $tokens = array_merge($tokens, array_map('strval', $matches[0] ?? []));
        }
        foreach ($declared as $term) {
            if ('' !== $term && str_contains($text, $term)) {
                $tokens[] = $term;
            }
        }
        sort($tokens, SORT_STRING);
        return array_values(array_unique($tokens));
    }

    private static function declaredTerms(array $input): array
    {
        return self::normalizeTerms(is_array($input['protected_terms'] ?? null) ? $input['protected_terms'] : []);
    }

    private static function normalizeTerms(array $terms): array
    {
        $terms = array_values(array_unique(array_filter(array_map(static fn($v): string => trim((string)$v), $terms), static fn(string $v): bool => '' !== $v)));
        sort($terms, SORT_STRING);
        return $terms;
    }

    private static function citationFingerprints(array $citations): array
    {
        $hashes = [];
        foreach ($citations as $citation) {
            $normalized = self::canonicalize($citation);
            $hashes[] = hash('sha256', json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
        }
        sort($hashes, SORT_STRING);
        return $hashes;
    }

    private static function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map([self::class, 'canonicalize'], $value);
        }
        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) {
            $value[$key] = self::canonicalize($item);
        }
        return $value;
    }
}
