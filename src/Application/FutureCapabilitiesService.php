<?php

declare(strict_types=1);

namespace Sabri\Localization\Application;

use InvalidArgumentException;
use Sabri\Localization\Contract\FutureCapabilities;

/**
 * Design-time/runtime-safe implementation of CF-06 Future40 capabilities.
 *
 * The service is intentionally pure and fail-closed. It does not publish,
 * deploy, contact providers, mutate native-owner content, or bypass qualified
 * human approval. It produces deterministic validation/planning/evidence
 * envelopes that higher-level approved workflows may consume after activation.
 */
final class FutureCapabilitiesService
{
    public function catalogue(): array
    {
        return FutureCapabilities::all();
    }

    public function evaluate(string $id, array $input): array
    {
        $id = strtoupper(trim($id));
        if (null === FutureCapabilities::get($id)) {
            throw new InvalidArgumentException('Unknown CF-06 future capability.');
        }

        $method = 'f' . substr($id, -3);
        if (! method_exists($this, $method)) {
            throw new InvalidArgumentException('Capability handler is unavailable.');
        }

        $result = $this->{$method}($input);
        return array(
            'capability' => $id,
            'default_state' => 'disabled',
            'mode' => 'evidence-preview',
            'requires_founder_activation' => true,
            'activation_gates' => FutureCapabilities::activationGates(),
            'activation_ready' => false,
            'result' => $result,
        );
    }

    private function f001(array $in): array
    {
        $text = $this->requiredString($in, 'text');
        $expanded = preg_replace_callback('/[A-Za-z]+/', static function (array $m): string {
            return '[' . $m[0] . str_repeat('~', max(1, (int) ceil(strlen($m[0]) * 0.35))) . ']';
        }, $text);
        return ['text' => '⟦' . ($expanded ?? $text) . '⟧', 'expansion_target' => '35-percent', 'rtl_probe' => (bool)($in['rtl_probe'] ?? false)];
    }

    private function f002(array $in): array
    {
        $key = $this->requiredString($in, 'resource_key');
        $route = $this->requiredString($in, 'route');
        return [
            'resource_key' => $key,
            'route' => $route,
            'screenshot_ref' => $this->optionalString($in, 'screenshot_ref'),
            'component' => $this->optionalString($in, 'component'),
            'context_complete' => '' !== $route && '' !== $key,
            'required_preview_states' => ['ltr', 'rtl', 'mobile', 'desktop', 'zoom-200', 'zoom-400'],
        ];
    }

    private function f003(array $in): array
    {
        $viewports = $in['viewports'] ?? [320, 375, 768, 1024, 1440, 1920];
        if (! is_array($viewports) || [] === $viewports) {
            throw new InvalidArgumentException('viewports must be a non-empty array.');
        }
        $matrix = [];
        foreach ($viewports as $width) {
            $width = (int)$width;
            if ($width < 280 || $width > 2560) {
                throw new InvalidArgumentException('Viewport width is outside the governed test range.');
            }
            foreach (['ltr', 'rtl'] as $direction) {
                $matrix[] = ['width' => $width, 'direction' => $direction, 'zoom' => [100, 200, 400]];
            }
        }
        return ['matrix' => $matrix, 'count' => count($matrix)];
    }

    private function f004(array $in): array
    {
        $text = $this->requiredString($in, 'text');
        $issues = [];
        if (preg_match('/\b(click here|here|this|that)\b/i', $text)) {
            $issues[] = 'ambiguous-reference';
        }
        if (preg_match('/["\'][^"\']*["\']\s*\+|\+\s*["\']/', $text)) {
            $issues[] = 'concatenated-string';
        }
        if (preg_match('/\b[A-Z]{3,}\b/', $text)) {
            $issues[] = 'unexplained-abbreviation-review';
        }
        if (strlen($text) > 240) {
            $issues[] = 'long-source-string';
        }
        return ['issues' => array_values(array_unique($issues)), 'pass' => [] === $issues];
    }

    private function f005(array $in): array
    {
        $source = $this->requiredString($in, 'source');
        $target = $this->requiredString($in, 'target');
        $sourceNumbers = $this->tokens($source, '/\b\d+(?:\.\d+)?\b/u');
        $targetNumbers = $this->tokens($target, '/\b\d+(?:\.\d+)?\b/u');
        $sourceUrls = $this->tokens($source, '~https?://[^\s)]+~u');
        $targetUrls = $this->tokens($target, '~https?://[^\s)]+~u');
        $flags = [];
        if ($sourceNumbers !== $targetNumbers) { $flags[] = 'numeric-drift'; }
        if ($sourceUrls !== $targetUrls) { $flags[] = 'url-drift'; }
        if (trim($target) === '') { $flags[] = 'empty-target'; }
        $ratio = strlen($source) > 0 ? strlen($target) / strlen($source) : 1.0;
        if ($ratio < 0.25 || $ratio > 4.0) { $flags[] = 'length-anomaly'; }
        return ['flags' => $flags, 'risk' => [] === $flags ? 'low' : 'review', 'human_approval_required' => true];
    }

    private function f006(array $in): array
    {
        $old = $this->requiredString($in, 'old_source');
        $new = $this->requiredString($in, 'new_source');
        $oldProtected = $this->protectedTokens($old);
        $newProtected = $this->protectedTokens($new);
        $changedProtected = $oldProtected !== $newProtected;
        $distance = $this->normalizedDistance($old, $new);
        return [
            'normalized_change' => $distance,
            'protected_token_change' => $changedProtected,
            'risk' => $changedProtected || $distance > 0.35 ? 'high-review' : ($distance > 0.10 ? 'medium-review' : 'low-review'),
        ];
    }

    private function f007(array $in): array
    {
        $texts = $in['texts'] ?? [];
        if (! is_array($texts)) { throw new InvalidArgumentException('texts must be an array.'); }
        $counts = [];
        foreach ($texts as $text) {
            foreach (preg_split('/[^\p{L}\p{N}-]+/u', (string)$text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
                if (strlen($token) < 5) { continue; }
                $key = $this->lower($token);
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }
        arsort($counts);
        $min = max(2, (int)($in['min_frequency'] ?? 2));
        $candidates = [];
        foreach ($counts as $term => $count) {
            if ($count >= $min) { $candidates[] = ['term' => $term, 'frequency' => $count, 'status' => 'candidate']; }
        }
        return ['candidates' => array_slice($candidates, 0, 100), 'auto_approved' => false];
    }

    private function f008(array $in): array
    {
        $concept = $this->requiredString($in, 'concept_id');
        $variants = $in['variants'] ?? [];
        if (! is_array($variants) || [] === $variants) { throw new InvalidArgumentException('variants are required.'); }
        $normalized = [];
        foreach ($variants as $locale => $terms) {
            $terms = is_array($terms) ? $terms : [$terms];
            $normalized[(string)$locale] = array_values(array_unique(array_filter(array_map('strval', $terms))));
        }
        ksort($normalized);
        return ['concept_id' => $concept, 'variants' => $normalized, 'canonical_status' => 'review-required'];
    }

    private function f009(array $in): array
    {
        $source = $in['source_citations'] ?? [];
        $target = $in['target_citations'] ?? [];
        if (! is_array($source) || ! is_array($target)) { throw new InvalidArgumentException('Citation arrays are required.'); }
        $sourceHashes = array_map([$this, 'citationHash'], $source);
        $targetHashes = array_map([$this, 'citationHash'], $target);
        return ['integrity' => $sourceHashes === $targetHashes ? 'pass' : 'fail', 'source_hashes' => $sourceHashes, 'target_hashes' => $targetHashes];
    }

    private function f010(array $in): array
    {
        $text = $this->requiredString($in, 'text');
        $tokens = $this->protectedTokens($text);
        return ['tokens' => $tokens, 'count' => count($tokens), 'policy' => 'exact-preservation-required'];
    }

    private function f011(array $in): array
    {
        $segments = $in['segments'] ?? [];
        if (! is_array($segments)) { throw new InvalidArgumentException('segments must be an array.'); }
        $out = [];
        $lastEnd = 0.0;
        foreach ($segments as $i => $segment) {
            if (! is_array($segment)) { throw new InvalidArgumentException('Invalid transcript segment.'); }
            $start = (float)($segment['start'] ?? 0);
            $end = (float)($segment['end'] ?? 0);
            $text = trim((string)($segment['text'] ?? ''));
            if ($start < $lastEnd || $end <= $start || '' === $text) { throw new InvalidArgumentException('Transcript segment timing/text is invalid.'); }
            $out[] = ['id' => (string)($segment['id'] ?? 'seg-' . ($i + 1)), 'start' => $start, 'end' => $end, 'text' => $text, 'status' => 'source-ready'];
            $lastEnd = $end;
        }
        return ['segments' => $out, 'translation_workflow' => 'human-governed'];
    }

    private function f012(array $in): array
    {
        $cues = $in['cues'] ?? [];
        if (! is_array($cues)) { throw new InvalidArgumentException('cues must be an array.'); }
        $issues = [];
        $lastEnd = 0.0;
        foreach ($cues as $i => $cue) {
            $start = (float)($cue['start'] ?? 0);
            $end = (float)($cue['end'] ?? 0);
            $text = trim((string)($cue['text'] ?? ''));
            $duration = max(0.001, $end - $start);
            $cps = $this->length($text) / $duration;
            if ($start < $lastEnd) { $issues[] = ['cue' => $i, 'issue' => 'overlap']; }
            if ($end <= $start) { $issues[] = ['cue' => $i, 'issue' => 'invalid-timing']; }
            if ($cps > 20) { $issues[] = ['cue' => $i, 'issue' => 'reading-speed']; }
            if ($this->length($text) > 84) { $issues[] = ['cue' => $i, 'issue' => 'line-length-review']; }
            $lastEnd = max($lastEnd, $end);
        }
        return ['issues' => $issues, 'pass' => [] === $issues];
    }

    private function f013(array $in): array
    {
        $approved = (bool)($in['transcript_human_approved'] ?? false);
        if (! $approved) { throw new InvalidArgumentException('Human-approved transcript is required before dubbing draft generation.'); }
        return [
            'status' => 'draft-request-eligible',
            'voice_profile' => $this->optionalString($in, 'voice_profile'),
            'public_release' => false,
            'required_reviews' => ['linguistic', 'pronunciation', 'domain-if-high-risk', 'final-human'],
        ];
    }

    private function f014(array $in): array
    {
        $entries = $in['entries'] ?? [];
        if (! is_array($entries)) { throw new InvalidArgumentException('entries must be an array.'); }
        $out = [];
        foreach ($entries as $entry) {
            if (! is_array($entry)) { continue; }
            $term = trim((string)($entry['term'] ?? ''));
            $pron = trim((string)($entry['pronunciation'] ?? ''));
            if ('' === $term || '' === $pron) { continue; }
            $out[] = ['term' => $term, 'pronunciation' => $pron, 'locale' => (string)($entry['locale'] ?? ''), 'status' => 'reviewed-candidate'];
        }
        return ['entries' => $out, 'count' => count($out)];
    }

    private function f015(array $in): array
    {
        $structure = $in['structure'] ?? [];
        if (! is_array($structure)) { throw new InvalidArgumentException('structure must be an array.'); }
        $allowed = ['heading', 'paragraph', 'table', 'figure', 'caption', 'footnote', 'endnote', 'citation'];
        $counts = array_fill_keys($allowed, 0);
        foreach ($structure as $item) {
            $type = (string)($item['type'] ?? '');
            if (isset($counts[$type])) { ++$counts[$type]; }
        }
        return ['structure_counts' => $counts, 'preserve_page_refs' => true, 'preserve_citations' => true, 'layout_qa_required' => true];
    }

    private function f016(array $in): array
    {
        $confidence = (float)($in['confidence'] ?? -1);
        if ($confidence < 0 || $confidence > 1) { throw new InvalidArgumentException('confidence must be between 0 and 1.'); }
        $threshold = (float)($in['threshold'] ?? 0.93);
        return [
            'confidence' => $confidence,
            'threshold' => $threshold,
            'route' => $confidence >= $threshold ? 'translation-ready' : 'human-ocr-correction',
            'auto_publish' => false,
        ];
    }

    private function f017(array $in): array
    {
        $required = ['alt_text', 'captions', 'transcript', 'aria_labels'];
        $coverage = [];
        foreach ($required as $field) {
            $items = $in[$field] ?? [];
            $coverage[$field] = is_array($items) ? count(array_filter($items, static fn($v): bool => trim((string)$v) !== '')) : 0;
        }
        return ['coverage' => $coverage, 'wcag_review_required' => true, 'missing_groups' => array_keys(array_filter($coverage, static fn(int $n): bool => 0 === $n))];
    }

    private function f018(array $in): array
    {
        $locale = $this->requiredString($in, 'locale');
        $base = $this->requiredString($in, 'base_locale');
        if ($locale === $base) { throw new InvalidArgumentException('Regional locale must differ from base locale.'); }
        return ['locale' => $locale, 'base_locale' => $base, 'fallback_chain' => [$locale, $base, 'en-US'], 'status' => 'candidate'];
    }

    private function f019(array $in): array
    {
        $register = strtolower($this->requiredString($in, 'register'));
        $allowed = ['academic', 'public', 'clinical', 'shariah', 'formal', 'conversational'];
        if (! in_array($register, $allowed, true)) { throw new InvalidArgumentException('Unsupported register.'); }
        return [
            'register' => $register,
            'honorific_policy' => (string)($in['honorific_policy'] ?? 'preserve-approved'),
            'style_profile' => ['tone' => $register, 'abbreviations' => 'controlled', 'terminology' => 'canonical'],
        ];
    }

    private function f020(array $in): array
    {
        $iso = $this->requiredString($in, 'canonical_iso_date');
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $iso)) { throw new InvalidArgumentException('canonical_iso_date must use YYYY-MM-DD.'); }
        $mode = (string)($in['display_mode'] ?? 'gregorian');
        if (! in_array($mode, ['gregorian', 'hijri', 'dual'], true)) { throw new InvalidArgumentException('Unsupported calendar display mode.'); }
        return ['canonical_storage' => $iso, 'display_mode' => $mode, 'conversion_source' => 'approved-calendar-library-required', 'canonical_date_mutated' => false];
    }

    private function f021(array $in): array
    {
        $text = $this->requiredString($in, 'text');
        $system = (string)($in['system'] ?? 'latin');
        $maps = [
            'latin' => ['0','1','2','3','4','5','6','7','8','9'],
            'arabic-indic' => ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'],
            'persian' => ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'],
        ];
        if (! isset($maps[$system])) { throw new InvalidArgumentException('Unsupported numeral system.'); }
        $latin = strtr($text, array_combine($maps['arabic-indic'], $maps['latin']) + array_combine($maps['persian'], $maps['latin']));
        return ['text' => strtr($latin, array_combine($maps['latin'], $maps[$system])), 'system' => $system];
    }

    private function f022(array $in): array
    {
        $text = $this->requiredString($in, 'text');
        $supported = $in['supported_codepoints'] ?? [];
        if (! is_array($supported)) { throw new InvalidArgumentException('supported_codepoints must be an array.'); }
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $missing = [];
        if ([] !== $supported) {
            $set = array_fill_keys(array_map('strtoupper', array_map('strval', $supported)), true);
            foreach ($chars as $ch) {
                $cp = $this->codepoint($ch);
                if (null !== $cp && ! isset($set[strtoupper(sprintf('U+%04X', $cp))])) { $missing[] = sprintf('U+%04X', $cp); }
            }
        }
        return ['missing_codepoints' => array_values(array_unique($missing)), 'pass' => [] === $missing, 'shaping_review' => (bool)preg_match('/[\x{0600}-\x{06FF}]/u', $text)];
    }

    private function f023(array $in): array
    {
        $text = $this->requiredString($in, 'text');
        $locale = $this->requiredString($in, 'locale');
        $script = preg_match('/^(ur|ar)(-|$)/i', $locale) ? 'arabic-script' : 'latin-script';
        $opportunities = preg_match_all('/[\s\-،,؛;\/]/u', $text, $m);
        return ['script' => $script, 'break_opportunities' => (int)$opportunities, 'forced_hyphenation' => false, 'overflow_test_required' => true];
    }

    private function f024(array $in): array
    {
        $text = $this->requiredString($in, 'text');
        $controls = preg_match('/[\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', $text) === 1;
        $normalized = class_exists('Normalizer') ? \Normalizer::normalize($text, \Normalizer::FORM_C) : $text;
        return ['normalized_text' => $normalized ?: $text, 'contains_bidi_controls' => $controls, 'keyboard_test_required' => true, 'copy_paste_test_required' => true];
    }

    private function f025(array $in): array
    {
        $links = $in['links'] ?? [];
        if (! is_array($links)) { throw new InvalidArgumentException('links must be an array.'); }
        $issues = [];
        $seen = [];
        foreach ($links as $i => $row) {
            $lang = strtolower(trim((string)($row['hreflang'] ?? '')));
            $url = trim((string)($row['url'] ?? ''));
            $canonical = trim((string)($row['canonical'] ?? ''));
            if ('' === $lang || '' === $url || '' === $canonical) { $issues[] = ['row' => $i, 'issue' => 'missing-required-seo-field']; continue; }
            if (isset($seen[$lang])) { $issues[] = ['row' => $i, 'issue' => 'duplicate-hreflang']; }
            $seen[$lang] = true;
            if (! preg_match('~^https://~i', $url) || ! preg_match('~^https://~i', $canonical)) { $issues[] = ['row' => $i, 'issue' => 'https-required']; }
        }
        return ['issues' => $issues, 'pass' => [] === $issues, 'ranking_owner' => 'File-26'];
    }

    private function f026(array $in): array
    {
        $coverage = (float)($in['coverage_percent'] ?? 0);
        $criticalMissing = max(0, (int)($in['critical_missing'] ?? 0));
        $threshold = (float)($in['threshold_percent'] ?? 95);
        $eligible = $coverage >= $threshold && 0 === $criticalMissing;
        return ['eligible' => $eligible, 'coverage_percent' => $coverage, 'critical_missing' => $criticalMissing, 'required_threshold' => $threshold, 'critical_gate' => '100-percent'];
    }

    private function f027(array $in): array
    {
        $scope = $this->requiredString($in, 'scope');
        $enabled = (bool)($in['kill'] ?? false);
        $reason = trim((string)($in['reason'] ?? ''));
        if ($enabled && '' === $reason) { throw new InvalidArgumentException('Kill-switch activation requires a reason.'); }
        return ['scope' => $scope, 'state' => $enabled ? 'blocked' : 'allowed', 'reason' => $reason, 'rollback_required' => $enabled];
    }

    private function f028(array $in): array
    {
        $approvals = $in['approvals'] ?? [];
        if (! is_array($approvals)) { throw new InvalidArgumentException('approvals must be an array.'); }
        $roles = array_values(array_unique(array_map(static fn($a): string => (string)($a['role'] ?? ''), $approvals)));
        $required = ['linguistic', 'domain'];
        $missing = array_values(array_diff($required, $roles));
        $expires = (int)($in['expires_in_minutes'] ?? 0);
        if ($expires < 1 || $expires > 1440) { throw new InvalidArgumentException('Emergency hotfix expiry must be 1-1440 minutes.'); }
        return ['eligible' => [] === $missing, 'missing_approvals' => $missing, 'expires_in_minutes' => $expires, 'post_hotfix_full_review_required' => true];
    }

    private function f029(array $in): array
    {
        $old = $in['old'] ?? [];
        $new = $in['new'] ?? [];
        if (! is_array($old) || ! is_array($new)) { throw new InvalidArgumentException('old/new bundles must be arrays.'); }
        $changed = [];
        $deleted = [];
        foreach ($new as $key => $value) {
            if (! array_key_exists($key, $old) || $old[$key] !== $value) { $changed[$key] = $value; }
        }
        foreach ($old as $key => $_) {
            if (! array_key_exists($key, $new)) { $deleted[] = (string)$key; }
        }
        ksort($changed); sort($deleted);
        return ['changed' => $changed, 'deleted' => $deleted, 'changed_count' => count($changed), 'deleted_count' => count($deleted)];
    }

    private function f030(array $in): array
    {
        $resources = $in['resources'] ?? [];
        if (! is_array($resources)) { throw new InvalidArgumentException('resources must be an array.'); }
        $manifest = [];
        foreach ($resources as $row) {
            if (! is_array($row)) { continue; }
            if ('C1' !== strtoupper((string)($row['data_class'] ?? '')) || ! (bool)($row['public'] ?? false)) { continue; }
            $key = trim((string)($row['key'] ?? ''));
            if ('' === $key || isset($manifest[$key])) { throw new InvalidArgumentException('Offline locale pack resource key is missing or duplicated.'); }
            $manifest[$key] = ['hash' => hash('sha256', (string)($row['text'] ?? '')), 'cache' => 'offline-approved'];
        }
        ksort($manifest);
        return ['manifest' => $manifest, 'resource_count' => count($manifest), 'private_content_included' => false];
    }

    private function f031(array $in): array
    {
        $bundle = $in['bundle'] ?? [];
        if (! is_array($bundle)) { throw new InvalidArgumentException('bundle must be an array.'); }
        $compact = [];
        foreach ($bundle as $key => $row) {
            $text = is_array($row) ? (string)($row['text'] ?? '') : (string)$row;
            $compact[(string)$key] = $text;
        }
        ksort($compact);
        $json = json_encode($compact, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return ['payload' => $compact, 'estimated_bytes' => strlen((string)$json), 'media_deferred' => true, 'metadata_minimized' => true];
    }

    private function f032(array $in): array
    {
        $endpoint = $this->requiredString($in, 'endpoint');
        $scheme = parse_url($endpoint, PHP_URL_SCHEME);
        $host = (string)(parse_url($endpoint, PHP_URL_HOST) ?? '');
        if (! in_array($scheme, ['https', 'http'], true) || '' === $host) { throw new InvalidArgumentException('Valid self-hosted MT endpoint is required.'); }
        $local = in_array($host, ['127.0.0.1', 'localhost', '::1'], true) || preg_match('/\.(internal|local)$/i', $host);
        if ('http' === $scheme && ! $local) { throw new InvalidArgumentException('Plain HTTP is allowed only for explicit local/internal adapters.'); }
        return ['endpoint' => $endpoint, 'self_hosted' => true, 'network_call_performed' => false, 'high_risk_auto_publish' => false, 'human_review_required' => true];
    }

    private function f033(array $in): array
    {
        $providers = $in['providers'] ?? [];
        if (! is_array($providers)) { throw new InvalidArgumentException('providers must be an array.'); }
        $locale = $this->requiredString($in, 'locale');
        $dataClass = strtoupper((string)($in['data_class'] ?? 'C1'));
        $region = strtoupper((string)($in['region'] ?? ''));
        $eligible = [];
        foreach ($providers as $p) {
            if (! is_array($p) || ! (bool)($p['healthy'] ?? false)) { continue; }
            $classes = array_map('strtoupper', is_array($p['data_classes'] ?? null) ? $p['data_classes'] : []);
            $locales = is_array($p['locales'] ?? null) ? $p['locales'] : [];
            $regions = array_map('strtoupper', is_array($p['regions'] ?? null) ? $p['regions'] : []);
            if (! in_array($dataClass, $classes, true) || ! in_array($locale, $locales, true) || ('' !== $region && ! in_array($region, $regions, true))) { continue; }
            $eligible[] = $p;
        }
        usort($eligible, static fn(array $a, array $b): int => ((float)($b['quality'] ?? 0)) <=> ((float)($a['quality'] ?? 0)));
        return ['selected' => $eligible[0]['id'] ?? null, 'eligible_count' => count($eligible), 'fail_closed' => [] === $eligible];
    }

    private function f034(array $in): array
    {
        $samples = $in['samples'] ?? [];
        if (! is_array($samples) || [] === $samples) { throw new InvalidArgumentException('Benchmark samples are required.'); }
        $sum = ['accuracy' => 0.0, 'terminology' => 0.0, 'privacy' => 0.0, 'latency' => 0.0, 'cost' => 0.0];
        foreach ($samples as $sample) {
            foreach ($sum as $k => $_) { $sum[$k] += max(0.0, min(100.0, (float)($sample[$k] ?? 0))); }
        }
        $n = count($samples);
        foreach ($sum as $k => $v) { $sum[$k] = round($v / $n, 2); }
        $sum['weighted_total'] = round($sum['accuracy']*.35 + $sum['terminology']*.25 + $sum['privacy']*.25 + $sum['latency']*.10 + $sum['cost']*.05, 2);
        return ['scores' => $sum, 'production_authorization' => false];
    }

    private function f035(array $in): array
    {
        $target = strtoupper($this->requiredString($in, 'target_region'));
        $allowed = array_map('strtoupper', is_array($in['allowed_regions'] ?? null) ? $in['allowed_regions'] : []);
        $denied = array_map('strtoupper', is_array($in['denied_regions'] ?? null) ? $in['denied_regions'] : []);
        $eligible = in_array($target, $allowed, true) && ! in_array($target, $denied, true);
        return ['target_region' => $target, 'eligible' => $eligible, 'route' => $eligible ? 'approved-region' : 'deny', 'fail_closed' => ! $eligible];
    }

    private function f036(array $in): array
    {
        $source = $this->requiredString($in, 'source');
        $target = $this->requiredString($in, 'target');
        $flags = $this->f005(['source' => $source, 'target' => $target])['flags'];
        $protected = $this->protectedTokens($source) !== $this->protectedTokens($target);
        if ($protected) { $flags[] = 'protected-token-drift'; }
        $score = max(0, 100 - count(array_unique($flags)) * 20);
        return ['quality_estimate' => $score, 'flags' => array_values(array_unique($flags)), 'approval_authority' => false, 'human_review_required' => true];
    }

    private function f037(array $in): array
    {
        $backlog = max(0, (int)($in['backlog_units'] ?? 0));
        $dailyNew = max(0.0, (float)($in['daily_new_units'] ?? 0));
        $dailyCapacity = max(0.0, (float)($in['daily_review_capacity'] ?? 0));
        $days = max(1, (int)($in['forecast_days'] ?? 30));
        $forecast = max(0.0, $backlog + ($dailyNew - $dailyCapacity) * $days);
        return ['current_backlog' => $backlog, 'forecast_days' => $days, 'forecast_backlog' => (int)ceil($forecast), 'trend' => $forecast > $backlog ? 'growing' : ($forecast < $backlog ? 'shrinking' : 'stable')];
    }

    private function f038(array $in): array
    {
        $decisions = $in['decisions'] ?? [];
        if (! is_array($decisions) || [] === $decisions) { throw new InvalidArgumentException('decisions are required.'); }
        $agreements = 0; $comparisons = 0; $disputes = [];
        foreach ($decisions as $i => $row) {
            if (! is_array($row)) { continue; }
            $a = (string)($row['reviewer_a'] ?? '');
            $b = (string)($row['reviewer_b'] ?? '');
            if ('' === $a || '' === $b) { continue; }
            ++$comparisons;
            if ($a === $b) { ++$agreements; } else { $disputes[] = $i; }
        }
        $rate = 0 === $comparisons ? 0.0 : round($agreements / $comparisons * 100, 2);
        return ['agreement_percent' => $rate, 'disputes' => $disputes, 'adjudication_required' => [] !== $disputes, 'independent_reviewers_required' => true];
    }

    private function f039(array $in): array
    {
        $suggestion = trim($this->requiredString($in, 'suggestion'));
        if ($this->length($suggestion) > 2000) { throw new InvalidArgumentException('Suggestion is too long.'); }
        $containsUrl = preg_match('~https?://~i', $suggestion) === 1;
        return [
            'normalized_suggestion' => preg_replace('/\s+/u', ' ', $suggestion),
            'status' => 'moderation-queue',
            'direct_publish' => false,
            'requires_rate_limit' => true,
            'contains_url' => $containsUrl,
        ];
    }

    private function f040(array $in): array
    {
        $locales = is_array($in['locales'] ?? null) ? $in['locales'] : [];
        $queues = is_array($in['queues'] ?? null) ? $in['queues'] : [];
        $providers = is_array($in['providers'] ?? null) ? $in['providers'] : [];
        $critical = is_array($in['critical_issues'] ?? null) ? $in['critical_issues'] : [];
        return [
            'locale_count' => count($locales),
            'queue_total' => array_sum(array_map('intval', $queues)),
            'provider_count' => count($providers),
            'critical_issue_count' => count($critical),
            'release_blocked' => [] !== $critical,
            'views' => ['coverage', 'staleness', 'review-queues', 'provider-health', 'release-gates', 'rollback', 'incidents'],
            'founder_decision_authority' => true,
        ];
    }

    private function requiredString(array $in, string $key): string
    {
        $value = trim((string)($in[$key] ?? ''));
        if ('' === $value) { throw new InvalidArgumentException($key . ' is required.'); }
        return $value;
    }

    private function optionalString(array $in, string $key): string
    {
        return trim((string)($in[$key] ?? ''));
    }

    private function tokens(string $text, string $pattern): array
    {
        preg_match_all($pattern, $text, $m);
        $out = array_values(array_map('strval', $m[0] ?? []));
        sort($out);
        return $out;
    }

    private function protectedTokens(string $text): array
    {
        $patterns = [
            '/\b\d+(?:\.\d+)?\s?(?:mg|g|ml|mL|L|mcg|µg|IU|%|°C)\b/u',
            '/\b\d+(?:C|X|LM|M|CM)\b/u',
            '~https?://[^\s)]+~u',
            '/\b[A-Z]{2,}-\d+\b/u',
        ];
        $tokens = [];
        foreach ($patterns as $pattern) { $tokens = array_merge($tokens, $this->tokens($text, $pattern)); }
        sort($tokens);
        return array_values(array_unique($tokens));
    }

    private function normalizedDistance(string $a, string $b): float
    {
        $a = preg_replace('/\s+/u', ' ', trim($this->lower($a))) ?? $a;
        $b = preg_replace('/\s+/u', ' ', trim($this->lower($b))) ?? $b;
        if ($a === $b) { return 0.0; }
        $max = max(strlen($a), strlen($b), 1);
        return min(1.0, levenshtein(substr($a, 0, 250), substr($b, 0, 250)) / min($max, 250));
    }

    private function citationHash(mixed $citation): string
    {
        if (is_array($citation)) {
            ksort($citation);
            $citation = json_encode($citation, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return hash('sha256', trim((string)$citation));
    }

    private function lower(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    private function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }

    private function codepoint(string $char): ?int
    {
        if (function_exists('mb_ord')) { return mb_ord($char, 'UTF-8'); }
        $u = unpack('C*', $char);
        if (! is_array($u) || [] === $u) { return null; }
        $b = array_values($u);
        if ($b[0] < 128) { return $b[0]; }
        if (($b[0] & 0xE0) === 0xC0 && isset($b[1])) { return (($b[0] & 0x1F) << 6) | ($b[1] & 0x3F); }
        if (($b[0] & 0xF0) === 0xE0 && isset($b[2])) { return (($b[0] & 0x0F) << 12) | (($b[1] & 0x3F) << 6) | ($b[2] & 0x3F); }
        if (($b[0] & 0xF8) === 0xF0 && isset($b[3])) { return (($b[0] & 0x07) << 18) | (($b[1] & 0x3F) << 12) | (($b[2] & 0x3F) << 6) | ($b[3] & 0x3F); }
        return null;
    }
}
