<?php

declare(strict_types=1);

namespace Sabri\Localization\Domain\Future;

use DateTimeImmutable;
use InvalidArgumentException;
use Sabri\Localization\Domain\Locale\LocaleValidator;
use Sabri\Localization\Domain\Translation\RiskPolicy;

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
            'CF06-FUT-030' => self::offlinePack($input),
            'CF06-FUT-031' => self::lowBandwidth($input),
            'CF06-FUT-032' => self::selfHostedEndpoint($input),
            'CF06-FUT-033' => self::providerRouter($input),
            'CF06-FUT-034' => self::providerBenchmark($input),
            'CF06-FUT-035' => self::residency($input),
            'CF06-FUT-037' => self::debtForecast($input),
            default => $input,
        };
    }

    private static function ocr(array $input): array
    {
        self::boundedRatio($input, 'confidence');
        if (array_key_exists('threshold', $input)) { self::boundedRatio($input, 'threshold'); }
        return $input;
    }

    private static function regionalLocale(array $input): array
    {
        $locale = LocaleValidator::canonicalize((string)($input['locale'] ?? ''));
        $base = LocaleValidator::canonicalize((string)($input['base_locale'] ?? ''));
        if (null === $locale || null === $base) { throw new InvalidArgumentException('locale and base_locale must be valid BCP47-style tags.'); }
        if ($locale === $base) { throw new InvalidArgumentException('Regional locale must differ from base locale.'); }
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
        if (! is_array($supported) || [] === $supported) { throw new InvalidArgumentException('supported_codepoints must be a non-empty audited inventory.'); }
        foreach ($supported as $codepoint) {
            $token = strtoupper((string)$codepoint);
            if (1 !== preg_match('/^U\+([0-9A-F]{4,6})$/D', $token, $match)) { throw new InvalidArgumentException('supported_codepoints contains an invalid Unicode code point.'); }
            $value = hexdec($match[1]);
            if ($value > 0x10FFFF || ($value >= 0xD800 && $value <= 0xDFFF)) { throw new InvalidArgumentException('supported_codepoints contains a non-scalar Unicode value.'); }
        }
        return $input;
    }

    private static function seo(array $input): array
    {
        $links = $input['links'] ?? null;
        if (! is_array($links) || [] === $links) { throw new InvalidArgumentException('links must be a non-empty array.'); }
        $normalized = [];
        foreach ($links as $row) {
            if (! is_array($row)) { throw new InvalidArgumentException('Each SEO link row must be an object.'); }
            $hreflang = trim((string)($row['hreflang'] ?? ''));
            if ('x-default' === strtolower($hreflang)) {
                $row['hreflang'] = 'x-default';
            } else {
                $canonical = LocaleValidator::canonicalize($hreflang);
                if (null === $canonical) { throw new InvalidArgumentException('hreflang must be x-default or a valid BCP47-style tag.'); }
                $row['hreflang'] = $canonical;
            }
            foreach (['url', 'canonical'] as $field) {
                $url = trim((string)($row[$field] ?? ''));
                $parts = parse_url($url);
                if (false === filter_var($url, FILTER_VALIDATE_URL) || ! is_array($parts) || 'https' !== strtolower((string)($parts['scheme'] ?? '')) || '' === (string)($parts['host'] ?? '')) {
                    throw new InvalidArgumentException($field . ' must be an absolute HTTPS URL.');
                }
                if (isset($parts['user']) || isset($parts['pass'])) { throw new InvalidArgumentException($field . ' must not contain embedded credentials.'); }
                $row[$field] = $url;
            }
            $normalized[] = $row;
        }
        $input['links'] = $normalized;
        return $input;
    }

    private static function launchGate(array $input): array
    {
        foreach (['coverage_percent', 'threshold_percent'] as $field) {
            if (! array_key_exists($field, $input) || ! is_numeric($input[$field])) { throw new InvalidArgumentException($field . ' must be numeric.'); }
            $value = (float)$input[$field];
            if ($value < 0 || $value > 100) { throw new InvalidArgumentException($field . ' must be between 0 and 100.'); }
        }
        if (isset($input['critical_missing'])) {
            $critical = filter_var($input['critical_missing'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            if (false === $critical) { throw new InvalidArgumentException('critical_missing must be a non-negative integer.'); }
            $input['critical_missing'] = $critical;
        }
        return $input;
    }

    private static function offlinePack(array $input): array
    {
        $resources = $input['resources'] ?? null;
        if (! is_array($resources)) { throw new InvalidArgumentException('resources must be an array.'); }
        $eligible = [];
        $keys = [];
        foreach ($resources as $row) {
            if (! is_array($row)) { continue; }
            $dataClass = strtoupper((string)($row['data_class'] ?? ''));
            $risk = strtolower((string)($row['risk_class'] ?? ''));
            $domain = strtolower((string)($row['domain'] ?? ''));
            $public = true === ($row['public'] ?? false);
            $approved = true === ($row['approved'] ?? false);
            $current = ! array_key_exists('current', $row) || true === $row['current'];
            $stale = true === ($row['stale'] ?? false);
            if ('C1' === $dataClass && 'low' === $risk && $public && $approved && $current && ! $stale && ! RiskPolicy::requiresDomainReview($risk, $domain)) {
                $key=trim((string)($row['key']??''));
                if(1!==preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,190}$/D',$key)||isset($keys[$key])){
                    throw new InvalidArgumentException('Offline locale pack contains an invalid or duplicated resource key.');
                }
                $keys[$key]=true;
                $eligible[] = $row;
            }
        }
        $input['resources'] = $eligible;
        return $input;
    }

    private static function lowBandwidth(array $input): array
    {
        $bundle = $input['bundle'] ?? null;
        if (! is_array($bundle)) { throw new InvalidArgumentException('bundle must be an array.'); }
        foreach ($bundle as $row) {
            if (! is_array($row)) { throw new InvalidArgumentException('Low-bandwidth bundle rows must carry governance metadata.'); }
            $risk = strtolower((string)($row['risk_class'] ?? ''));
            $domain = strtolower((string)($row['domain'] ?? ''));
            if ('C1' !== strtoupper((string)($row['data_class'] ?? '')) || true !== ($row['public'] ?? false) || true !== ($row['approved'] ?? false) || 'low' !== $risk || RiskPolicy::requiresDomainReview($risk, $domain)) {
                throw new InvalidArgumentException('Low-bandwidth bundle contains material that is not approved public low-risk C1.');
            }
        }
        return $input;
    }

    private static function selfHostedEndpoint(array $input): array
    {
        $endpoint = trim((string)($input['endpoint'] ?? ''));
        $parts = parse_url($endpoint);
        if (! is_array($parts) || '' === (string)($parts['host'] ?? '') || ! in_array(strtolower((string)($parts['scheme'] ?? '')), ['https','http'], true)) {
            throw new InvalidArgumentException('Valid self-hosted MT endpoint is required.');
        }
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) { throw new InvalidArgumentException('Self-hosted MT endpoint must not contain embedded credentials or fragments.'); }
        $host = strtolower((string)$parts['host']);
        $allow = array_map('strtolower', is_array($input['approved_hosts'] ?? null) ? $input['approved_hosts'] : []);
        if ([] === $allow || ! in_array($host, $allow, true)) { throw new InvalidArgumentException('Self-hosted MT endpoint host is not explicitly approved.'); }
        $local = in_array($host, ['127.0.0.1','localhost','::1'], true) || 1 === preg_match('/\.(internal|local)$/i', $host);
        if ('http' === strtolower((string)$parts['scheme']) && (! $local || true !== ($input['allow_insecure_local'] ?? false))) {
            throw new InvalidArgumentException('Plain HTTP requires an explicitly approved local/internal endpoint.');
        }
        return $input;
    }

    private static function providerRouter(array $input): array
    {
        $locale = LocaleValidator::canonicalize((string)($input['locale'] ?? ''));
        $dataClass = strtoupper(trim((string)($input['data_class'] ?? '')));
        $risk = strtolower(trim((string)($input['risk_class'] ?? '')));
        $domain = strtolower(trim((string)($input['domain'] ?? '')));
        $region = strtoupper(trim((string)($input['region'] ?? '')));
        if (null === $locale || '' === $dataClass || '' === $risk || '' === $domain || '' === $region) { throw new InvalidArgumentException('Provider routing requires explicit locale, data_class, risk_class, domain and region.'); }
        if (! RiskPolicy::machineTranslationAllowed($risk, $dataClass, $domain, false)) { throw new InvalidArgumentException('Material is not eligible for external translation-provider routing.'); }
        if (1 !== preg_match('/^[A-Z0-9-]{2,16}$/D', $region)) { throw new InvalidArgumentException('region is invalid.'); }
        $input['locale'] = $locale;
        $input['data_class'] = $dataClass;
        $input['risk_class'] = $risk;
        $input['domain'] = $domain;
        $input['region'] = $region;
        return $input;
    }

    private static function providerBenchmark(array $input): array
    {
        $samples = $input['samples'] ?? null;
        if (! is_array($samples) || [] === $samples) { throw new InvalidArgumentException('Benchmark samples are required.'); }
        foreach ($samples as $sample) {
            if (! is_array($sample)) { throw new InvalidArgumentException('Each benchmark sample must be an object.'); }
            foreach (['accuracy','terminology','privacy','latency','cost'] as $metric) {
                if (! array_key_exists($metric, $sample) || ! is_numeric($sample[$metric])) { throw new InvalidArgumentException('Benchmark metrics must be normalized numeric utility scores.'); }
                $value = (float)$sample[$metric];
                if ($value < 0 || $value > 100) { throw new InvalidArgumentException('Benchmark utility scores must be between 0 and 100.'); }
            }
        }
        $input['score_semantics'] = '0-100-normalized-utility-higher-is-better';
        return $input;
    }

    private static function residency(array $input): array
    {
        if (true !== ($input['residency_known'] ?? false) || true === ($input['region_uncertain'] ?? false)) {
            throw new InvalidArgumentException('Data residency is uncertain; routing must fail closed.');
        }
        $target = strtoupper(trim((string)($input['target_region'] ?? '')));
        if (1 !== preg_match('/^[A-Z0-9-]{2,16}$/D', $target)) { throw new InvalidArgumentException('target_region is invalid.'); }
        return $input;
    }

    private static function debtForecast(array $input): array
    {
        $backlog=filter_var($input['backlog_units']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>10000000]]);
        $days=filter_var($input['forecast_days']??30,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>3650]]);
        foreach(['daily_new_units','daily_review_capacity'] as $field){
            if(!array_key_exists($field,$input)||!is_numeric($input[$field])){throw new InvalidArgumentException($field . ' must be a bounded numeric value.');}
            $value=(float)$input[$field];
            if(!is_finite($value)||$value<0||$value>1000000){throw new InvalidArgumentException($field . ' must be between 0 and 1000000.');}
            $input[$field]=$value;
        }
        if(false===$backlog||false===$days){throw new InvalidArgumentException('Translation-debt forecast inputs exceed governed bounds.');}
        $input['backlog_units']=$backlog;$input['forecast_days']=$days;
        return $input;
    }

    private static function boundedRatio(array $input, string $field): void
    {
        if (! array_key_exists($field, $input) || ! is_numeric($input[$field])) { throw new InvalidArgumentException($field . ' must be numeric.'); }
        $value = (float)$input[$field];
        if ($value < 0 || $value > 1) { throw new InvalidArgumentException($field . ' must be between 0 and 1.'); }
    }
}
