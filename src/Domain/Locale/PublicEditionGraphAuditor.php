<?php

declare(strict_types=1);

namespace Sabri\Localization\Domain\Locale;

use InvalidArgumentException;

/**
 * V12.3 read-only reciprocal hreflang and canonical URL evidence audit.
 *
 * No HTML, sitemap, redirect, indexing or publication mutation is permitted.
 * Native source, translations and File 26 search evidence are three separate
 * independent attestation authorities. Input-provided booleans prove nothing.
 */
final class PublicEditionGraphAuditor
{
    public const EXPECTED_PUBLIC_VERSIONS = 10;
    private const MAX_NODES = 25;

    public static function audit(
        array $source,
        array $translations,
        string $trustedOrigin,
        callable $verifyOriginalOwner,
        callable $verifyTranslationOwner,
        callable $verifyFile26
    ): array {
        if (count($translations) > self::MAX_NODES - 1) {
            throw new InvalidArgumentException('Publication graph exceeds the bounded edition limit.');
        }
        $trusted = self::originParts($trustedOrigin);
        $sourceLocale = LocaleValidator::canonicalize((string)($source['locale'] ?? ''));
        $sourceVersion = (string)($source['source_version'] ?? '');
        $sourceHash = strtolower((string)($source['source_hash'] ?? ''));
        if (null === $sourceLocale || '' === $sourceVersion || strlen($sourceVersion) > 80
            || !preg_match('/^[a-f0-9]{64}$/D', $sourceHash)) {
            throw new InvalidArgumentException('Original publication source identity is invalid.');
        }

        $problems = [];
        $nodes = [];
        $urls = [];
        $regionalCanonicalCandidates = [];
        $entries = array_merge([['kind'=>'original','record'=>$source]],
            array_map(static fn (array $r): array => ['kind'=>'translation','record'=>$r], self::assertArrayEntries($translations)));

        foreach ($entries as $entry) {
            $record = $entry['record'];
            $kind = $entry['kind'];
            $locale = LocaleValidator::canonicalize((string)($record['locale'] ?? ''));
            $sameSource = hash_equals($sourceVersion, (string)($record['source_version'] ?? ''))
                && hash_equals($sourceHash, strtolower((string)($record['source_hash'] ?? '')));
            $published = 'published' === ($record['publication_status'] ?? '')
                && true === ($record['public'] ?? false);
            $url = (string)($record['url'] ?? '');
            $canonical = (string)($record['canonical_url'] ?? '');
            $urlOkay = self::sameOriginHttps($url, $trusted);
            $canonicalOkay = self::sameOriginHttps($canonical, $trusted);
            if (null === $locale || !$sameSource || !$published
                || !$urlOkay || !$canonicalOkay
                || ($kind === 'translation' && $locale === $sourceLocale)
                || ($kind === 'original' && $locale !== $sourceLocale)
                || ($locale !== null && isset($nodes[$locale]))
                || isset($urls[$url])) {
                $problems[] = ['code'=>'invalid_or_unattested_edition', 'locale'=>$locale ?? 'invalid'];
                continue;
            }
            // Never invoke an owner integration for invalid/possibly private input.
            $ownerOkay = $kind === 'original'
                ? true === $verifyOriginalOwner($record)
                : true === $verifyTranslationOwner($record);
            if (!$ownerOkay) {
                $problems[] = ['code'=>'native_owner_publication_unverified','locale'=>$locale];
                continue;
            }
            // Keep a regional canonical candidate only for later validation:
            // the target must be an independently verified same-language peer
            // and File 26 must separately attest the actual canonical page.
            if ($url !== $canonical) {
                $regionalCanonicalCandidates[$locale] = $canonical;
            }
            if (!is_array($record['hreflang'] ?? null) || count($record['hreflang']) > self::MAX_NODES) {
                $problems[] = ['code'=>'missing_or_excessive_reciprocal_links','locale'=>$locale];
                continue;
            }
            $nodes[$locale] = $record;
            $urls[$url] = true;
        }

        foreach ($regionalCanonicalCandidates as $locale=>$candidate) {
            $originLang = LocaleValidator::parse($locale)['language'];
            $sameLanguagePeer = false;
            foreach ($nodes as $peerLocale=>$peer) {
                if ($peerLocale !== $locale
                    && LocaleValidator::parse($peerLocale)['language'] === $originLang
                    && $candidate === (string)$peer['url']) {
                    $sameLanguagePeer = true;
                    break;
                }
            }
            if (!$sameLanguagePeer) {
                $problems[] = ['code'=>'nonself_canonical_requires_separate_approval', 'locale'=>$locale];
            }
        }

        $expectedLinks = [];
        foreach ($nodes as $locale=>$record) {
            $expectedLinks[$locale] = (string)$record['url'];
        }
        ksort($expectedLinks, SORT_STRING);
        foreach ($nodes as $locale=>$record) {
            $actual = [];
            foreach ($record['hreflang'] as $language => $destination) {
                if (!is_string($language) || !is_string($destination)
                    || ($tag=LocaleValidator::canonicalize($language))===null || isset($actual[$tag])) {
                    $problems[] = ['code'=>'invalid_or_duplicate_hreflang_tag','locale'=>$locale];
                    continue;
                }
                $actual[$tag] = $destination;
            }
            ksort($actual, SORT_STRING);
            if ($actual !== $expectedLinks) {
                $problems[] = ['code'=>'missing_or_nonreciprocal_hreflang','locale'=>$locale];
            }
        }

        // Only an independent File 26 receipt may attest live canonical,
        // hreflang, sitemap exclusion, search purge and re-index behavior.
        $searchVerified = [] !== $nodes && [] === $problems
            && true === $verifyFile26(array_values($nodes), $expectedLinks);
        if (!$searchVerified) {
            $problems[] = ['code'=>'file26_search_graph_evidence_unverified'];
        }
        $count = count($nodes);
        return [
            'source_locale'=>$sourceLocale,
            'source_version'=>$sourceVersion,
            'source_hash'=>$sourceHash,
            'independently_owner_verified_count'=>$count,
            'linked_locales'=>array_keys($nodes),
            'target_full_count'=>self::EXPECTED_PUBLIC_VERSIONS,
            'missing_verified_versions'=>max(0, self::EXPECTED_PUBLIC_VERSIONS-$count),
            'reciprocal_hreflang_verified'=>$count > 0 && [] === array_filter($problems,
                static fn (array $issue): bool => str_contains($issue['code'],'hreflang')),
            'file26_independently_verified'=>$searchVerified,
            'graph_review_ready'=>$count===self::EXPECTED_PUBLIC_VERSIONS && []===$problems && $searchVerified,
            'publication_authorized'=>false,
            'indexing_authorized'=>false,
            'issues'=>$problems,
        ];
    }

    private static function assertArrayEntries(array $values): array
    {
        foreach ($values as $value) {
            if (!is_array($value)) {
                throw new InvalidArgumentException('Publication graph entry is not structured.');
            }
        }
        return array_values($values);
    }

    private static function originParts(string $origin): array
    {
        $parts = parse_url($origin);
        if (!is_array($parts) || 'https' !== strtolower((string)($parts['scheme'] ?? ''))
            || '' === (string)($parts['host'] ?? '') || isset($parts['user'])
            || isset($parts['pass']) || isset($parts['fragment'])) {
            throw new InvalidArgumentException('Publication graph trusted canonical origin is invalid.');
        }
        return ['host'=>strtolower((string)$parts['host']),'port'=>$parts['port'] ?? null];
    }

    private static function sameOriginHttps(string $url, array $trusted): bool
    {
        if (''===$url || strlen($url)>255) {
            return false;
        }
        $parts = parse_url($url);
        return is_array($parts)
            && 'https'===strtolower((string)($parts['scheme'] ?? ''))
            && strtolower((string)($parts['host'] ?? '')) === $trusted['host']
            && ($parts['port'] ?? null) === $trusted['port']
            && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['fragment']);
    }
}
