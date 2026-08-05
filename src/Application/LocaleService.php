<?php

declare(strict_types=1);

namespace Sabri\Localization\Application;

use InvalidArgumentException;
use Sabri\Localization\Domain\Locale\FallbackChainValidator;
use Sabri\Localization\Domain\Locale\LocaleValidator;
use Sabri\Localization\Infrastructure\Repository\AuditRepository;
use Sabri\Localization\Infrastructure\Repository\LocaleRepository;
use Sabri\Localization\Infrastructure\Transaction;

final class LocaleService
{
    public function __construct(
        private readonly LocaleRepository $locales,
        private readonly AuditRepository $audit,
        private readonly Transaction $transaction
    ) {
    }

    public function all(bool $enabledOnly = false): array
    {
        return $this->locales->all($enabledOnly);
    }

    public function publicEnabled(): array
    {
        return array_map(array($this, 'publicProjection'), $this->locales->all(true));
    }

    public function register(array $input): array
    {
        $parsed = LocaleValidator::parse((string) ($input['locale_tag'] ?? ''));
        if (null === $parsed) {
            throw new InvalidArgumentException('Invalid BCP 47-style locale tag.');
        }
        $fallback = null;
        if (! empty($input['fallback_tag'])) {
            $fallback = LocaleValidator::canonicalize((string) $input['fallback_tag']);
            if (null === $fallback) {
                throw new InvalidArgumentException('Invalid fallback locale tag.');
            }
        }
        $direction = strtolower((string) ($input['direction'] ?? 'ltr'));
        if (! in_array($direction, array('ltr', 'rtl'), true)) {
            throw new InvalidArgumentException('Direction must be ltr or rtl.');
        }
        $status = strtolower((string) ($input['status'] ?? 'planned'));
        if (! in_array($status, array('planned', 'enabled', 'disabled', 'retired'), true)) {
            throw new InvalidArgumentException('Invalid locale status.');
        }
        $allLocales = $this->locales->all(false);
        $map = $this->locales->fallbackMap(false);
        $map[$parsed['tag']] = $fallback;
        FallbackChainValidator::assertValid($map);
        if ('enabled' === $status && null !== $fallback && null === $this->locales->find($fallback, true)) {
            throw new InvalidArgumentException('An enabled locale must fall back only to an enabled locale.');
        }
        if ('enabled' !== $status) {
            $default = LocaleValidator::canonicalize((string) get_option('slto_default_locale', 'en-US'));
            if ($parsed['tag'] === $default) {
                throw new InvalidArgumentException('The configured default locale cannot be disabled or retired.');
            }
            foreach ($allLocales as $locale) {
                if (1 === (int) $locale['enabled'] && $parsed['tag'] === (string) ($locale['fallback_tag'] ?? '')) {
                    throw new InvalidArgumentException('This locale is an active fallback target and cannot be disabled.');
                }
            }
        }
        $record = array(
            'locale_tag' => $parsed['tag'],
            'language_subtag' => $parsed['language'],
            'script_subtag' => $parsed['script'],
            'region_subtag' => $parsed['region'],
            'direction' => $direction,
            'fallback_tag' => $fallback,
            'plural_rules_version' => sanitize_text_field((string) ($input['plural_rules_version'] ?? 'initial')),
            'format_data_version' => sanitize_text_field((string) ($input['format_data_version'] ?? 'initial')),
            'enabled' => ('enabled' === $status) ? 1 : 0,
            'status' => $status,
            'owner' => sanitize_text_field((string) ($input['owner'] ?? 'CF-06')),
        );
        return $this->transaction->run(function () use ($record, $parsed, $status, $fallback): array {
            $this->locales->upsert($record);
            $this->audit->record('locale', $parsed['tag'], 'locale_upserted', 'success', array('status' => $status, 'fallback' => $fallback));
            return $this->locales->find($parsed['tag']) ?? $record;
        });
    }

    public function resolve(string $requested): array
    {
        $canonical = LocaleValidator::canonicalize($requested);
        $enabled = $this->locales->all(true);
        $byTag = array();
        foreach ($enabled as $locale) {
            $byTag[(string) $locale['locale_tag']] = $locale;
        }
        $resolved = null;
        $reason = 'exact';
        if (null !== $canonical && isset($byTag[$canonical])) {
            $resolved = $canonical;
        }
        if (null === $resolved && null !== $canonical) {
            $language = explode('-', $canonical)[0];
            $matches = array_values(array_filter(array_keys($byTag), static fn (string $tag): bool => $language === explode('-', $tag)[0]));
            sort($matches, SORT_STRING);
            if ($matches) {
                $resolved = $matches[0];
                $reason = 'language_match';
            }
        }
        if (null === $resolved) {
            $default = LocaleValidator::canonicalize((string) get_option('slto_default_locale', 'en-US')) ?? 'en-US';
            $resolved = isset($byTag[$default]) ? $default : (array_key_first($byTag) ?: 'en-US');
            $reason = 'default_fallback';
        }
        $fallbackMap = $this->locales->fallbackMap(true);
        $chain = isset($fallbackMap[$resolved]) ? FallbackChainValidator::chainFor($resolved, $fallbackMap) : array();
        return array(
            'requested' => $requested,
            'canonical' => $canonical,
            'resolved' => $resolved,
            'reason' => $reason,
            'fallback_chain' => $chain,
            'locale' => isset($byTag[$resolved]) ? $this->publicProjection($byTag[$resolved]) : null,
        );
    }

    private function publicProjection(array $locale): array
    {
        return array(
            'locale_tag' => (string) $locale['locale_tag'],
            'language' => (string) $locale['language_subtag'],
            'script' => (string) $locale['script_subtag'],
            'region' => (string) $locale['region_subtag'],
            'direction' => (string) $locale['direction'],
            'fallback_tag' => (string) ($locale['fallback_tag'] ?? ''),
            'plural_rules_version' => (string) $locale['plural_rules_version'],
            'format_data_version' => (string) $locale['format_data_version'],
        );
    }
}
