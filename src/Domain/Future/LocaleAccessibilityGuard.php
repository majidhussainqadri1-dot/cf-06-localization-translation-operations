<?php

declare(strict_types=1);

namespace Sabri\Localization\Domain\Future;

use InvalidArgumentException;
use Sabri\Localization\Domain\Locale\LocaleValidator;
use Sabri\Localization\Domain\Translation\BidiValidator;

final class LocaleAccessibilityGuard
{
    private const CORE_VIEWPORTS = [320, 375, 768, 1024, 1440, 1920];

    public static function normalize(string $id, array $input): array
    {
        return match ($id) {
            'CF06-FUT-003' => self::deviceMatrix($input),
            'CF06-FUT-017' => self::accessibility($input),
            'CF06-FUT-019' => self::registerProfile($input),
            'CF06-FUT-021' => self::numerals($input),
            'CF06-FUT-023' => self::lineBreak($input),
            'CF06-FUT-024' => self::inputMethod($input),
            default => $input,
        };
    }

    public static function apply(string $id, array $input, array $out): array
    {
        if (! isset($out['result']) || ! is_array($out['result'])) { return $out; }
        return match ($id) {
            'CF06-FUT-001' => self::pseudolocalization($input, $out),
            'CF06-FUT-017' => self::accessibilityResult($input, $out),
            'CF06-FUT-021' => self::numeralResult($input, $out),
            'CF06-FUT-023' => self::lineBreakResult($input, $out),
            'CF06-FUT-024' => self::inputMethodResult($input, $out),
            default => $out,
        };
    }

    private static function deviceMatrix(array $input): array
    {
        $requested = $input['viewports'] ?? [];
        if (! is_array($requested)) { throw new InvalidArgumentException('viewports must be an array.'); }
        $viewports = self::CORE_VIEWPORTS;
        foreach ($requested as $width) {
            if (filter_var($width, FILTER_VALIDATE_INT) === false) { throw new InvalidArgumentException('Viewport width must be an integer.'); }
            $width = (int)$width;
            if ($width < 320 || $width > 2560) { throw new InvalidArgumentException('Viewport width must be within 320-2560 pixels.'); }
            $viewports[] = $width;
        }
        $viewports = array_values(array_unique($viewports)); sort($viewports, SORT_NUMERIC); $input['viewports'] = $viewports;
        return $input;
    }

    private static function accessibility(array $input): array
    {
        $locale = LocaleValidator::canonicalize((string)($input['target_locale'] ?? ''));
        if (null === $locale) { throw new InvalidArgumentException('target_locale must be a valid BCP47-style locale.'); }
        foreach (['alt_text','captions','transcript','aria_labels'] as $field) {
            $rows = $input[$field] ?? null;
            if (! is_array($rows) || [] === $rows || count($rows) > 500) { throw new InvalidArgumentException($field . ' must contain a bounded translated accessibility-text array.'); }
            $normalized = [];
            foreach ($rows as $value) {
                if (! is_scalar($value)) { throw new InvalidArgumentException($field . ' contains a non-scalar accessibility value.'); }
                $text = trim((string)$value);
                $length = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
                if ('' === $text || $length > 10000) { throw new InvalidArgumentException($field . ' contains empty or oversized accessibility text.'); }
                BidiValidator::assertSafe($text, true);
                $normalized[] = $text;
            }
            $input[$field] = $normalized;
        }
        $input['target_locale'] = $locale;
        return $input;
    }

    private static function registerProfile(array $input): array
    {
        $locale = LocaleValidator::canonicalize((string)($input['target_locale'] ?? ''));
        if (null === $locale) { throw new InvalidArgumentException('target_locale must be a valid BCP47-style locale.'); }
        $policy = strtolower(trim((string)($input['honorific_policy'] ?? 'preserve-approved')));
        if (! in_array($policy, ['preserve-approved','source-exact','locale-approved-map'], true)) { throw new InvalidArgumentException('Unsupported honorific policy.'); }
        $input['target_locale'] = $locale; $input['honorific_policy'] = $policy;
        return $input;
    }

    private static function numerals(array $input): array
    {
        $locale = LocaleValidator::canonicalize((string)($input['locale'] ?? ''));
        if (null === $locale) { throw new InvalidArgumentException('locale must be a valid BCP47-style locale.'); }
        $input['locale'] = $locale; return $input;
    }

    private static function lineBreak(array $input): array
    {
        $locale = LocaleValidator::canonicalize((string)($input['locale'] ?? ''));
        if (null === $locale) { throw new InvalidArgumentException('locale must be a valid BCP47-style locale.'); }
        $input['locale'] = $locale; return $input;
    }

    private static function inputMethod(array $input): array
    {
        $locale = LocaleValidator::canonicalize((string)($input['locale'] ?? ''));
        if (null === $locale) { throw new InvalidArgumentException('locale must be a valid BCP47-style locale.'); }
        $text = (string)($input['text'] ?? ''); BidiValidator::assertSafe($text, true); $input['locale'] = $locale;
        return $input;
    }

    private static function pseudolocalization(array $input, array $out): array
    {
        $source = trim((string)($input['text'] ?? '')); if ('' === $source) { return $out; }
        $protected = '~(https?://[^\s<>)]+|\{[^{}]*\}|</?[^>]+>|%(?:\d+\$)?[sdfoxegc])~u';
        $parts = preg_split($protected, $source, -1, PREG_SPLIT_DELIM_CAPTURE); if (false === $parts) { return $out; }
        $rendered = '';
        foreach ($parts as $part) {
            if ('' === $part) { continue; }
            if (1 === preg_match($protected, $part)) { $rendered .= $part; continue; }
            $rendered .= preg_replace_callback('/[\p{L}]+/u', static function(array $m): string {
                $word=$m[0];$length=function_exists('mb_strlen')?mb_strlen($word,'UTF-8'):strlen($word);return '['.$word.str_repeat('~',max(1,(int)ceil($length*0.35))).']';
            }, $part) ?? $part;
        }
        $out['result']['text']='⟦'.$rendered.'⟧';$out['result']['protected_tokens_preserved']=true;$out['result']['special_character_probe']='ÅßЖ中ع';
        $out['result']['rtl_probe_text']=true===($input['rtl_probe']??false)?"\u{2067}RTL عينة\u{2069}":null;$out['result']['publication_authority']=false;
        return $out;
    }

    private static function accessibilityResult(array $input, array $out): array
    {
        $out['result']['target_locale']=(string)$input['target_locale'];$out['result']['meaning_equivalence_human_review_required']=true;$out['result']['screen_reader_acceptance_required']=true;$out['result']['validated_text_groups']=['alt_text','captions','transcript','aria_labels'];return $out;
    }

    private static function numeralResult(array $input, array $out): array
    {
        $source=(string)($input['text']??'');$targetSystem=(string)($input['system']??'latin');$out['result']['canonical_text']=$source;$out['result']['locale']=(string)$input['locale'];$out['result']['display_text']=self::transformDisplayDigits($source,$targetSystem);$out['result']['text']=$out['result']['display_text'];$out['result']['display_only']=true;$out['result']['canonical_numeric_value_mutated']=false;return $out;
    }

    private static function transformDisplayDigits(string $text, string $system): string
    {
        $maps=['latin'=>['0','1','2','3','4','5','6','7','8','9'],'arabic-indic'=>['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'],'persian'=>['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹']];
        if(!isset($maps[$system])){return $text;}
        $protected='~(https?://[^\s<>)]+|\{[^{}]*\}|\b\d+(?:\.\d+)?\s?(?:mg|g|ml|mL|L|mcg|µg|IU|%|°C)\b|\b\d+(?:C|X|LM|M|CM)\b)~u';
        $parts=preg_split($protected,$text,-1,PREG_SPLIT_DELIM_CAPTURE);if(false===$parts){return $text;}$output='';
        foreach($parts as $part){if(''===$part){continue;}if(1===preg_match($protected,$part)){$output.=$part;continue;}$latin=strtr($part,array_combine($maps['arabic-indic'],$maps['latin'])+array_combine($maps['persian'],$maps['latin']));$output.=strtr($latin,array_combine($maps['latin'],$maps[$system]));}
        return $output;
    }

    private static function lineBreakResult(array $input, array $out): array
    {
        $locale=(string)$input['locale'];$parsed=LocaleValidator::parse($locale);$script=(string)($parsed['script']??'');if(''===$script){$script=match($parsed['language']??''){'ur','ar','fa','ps','sd','ug','dv','ku'=>'Arab','he'=>'Hebr',default=>'Latn',};}$out['result']['locale']=$locale;$out['result']['direction']=LocaleValidator::direction($locale);$out['result']['script']=$script;return $out;
    }

    private static function inputMethodResult(array $input, array $out): array
    {
        $out['result']['locale']=(string)$input['locale'];$out['result']['direction']=LocaleValidator::direction((string)$input['locale']);$out['result']['bidi_safety']='validated';$out['result']['legacy_override_allowed']=false;return $out;
    }
}
