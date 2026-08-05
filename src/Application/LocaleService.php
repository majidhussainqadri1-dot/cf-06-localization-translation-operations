<?php

declare(strict_types=1);

namespace Sabri\Localization\Application;

use InvalidArgumentException;
use Sabri\Localization\Domain\Locale\FallbackChainValidator;
use Sabri\Localization\Domain\Locale\LocaleValidator;
use Sabri\Localization\Domain\Workflow\StateMachine;
use Sabri\Localization\Infrastructure\Outbox;
use Sabri\Localization\Infrastructure\Repository\AuditRepository;
use Sabri\Localization\Infrastructure\Repository\LocalizationRepository;
use Sabri\Localization\Infrastructure\Transaction;

final class LocaleService
{
    public function __construct(
        private readonly LocalizationRepository $repo,
        private readonly AuditRepository $audit,
        private readonly Outbox $outbox,
        private readonly Transaction $tx
    ) {
    }

    public function register(array $input): array
    {
        $parsed = LocaleValidator::parse((string) ($input['locale'] ?? ''));
        if (null === $parsed) {
            throw new InvalidArgumentException('Invalid BCP 47-style locale tag.');
        }
        if ($this->repo->findOne('locales', 'locale_tag', $parsed['tag'])) {
            throw new InvalidArgumentException('Locale is already registered.');
        }
        $fallback = isset($input['fallback']) ? LocaleValidator::canonicalize((string) $input['fallback']) : null;
        FallbackChainValidator::validate($parsed['tag'], $fallback, fn (string $tag): ?array => $this->repo->findOne('locales', 'locale_tag', $tag));
        $status = (string) ($input['status'] ?? 'proposed');
        if (! in_array($status, array('proposed', 'tested', 'content_ready'), true)) {
            throw new InvalidArgumentException('New locale has an invalid initial state.');
        }
        return $this->tx->run(function () use ($parsed, $fallback, $status, $input): array {
            $row = $this->repo->insert('locales', array(
                'locale_tag' => $parsed['tag'],
                'language_subtag' => $parsed['language'],
                'script_subtag' => $parsed['script'],
                'region_subtag' => $parsed['region'],
                'direction' => LocaleValidator::direction($parsed['tag']),
                'fallback_tag' => $fallback,
                'plural_rules_version' => sanitize_text_field((string) ($input['plural_version'] ?? 'CLDR-49')),
                'format_data_version' => sanitize_text_field((string) ($input['format_version'] ?? 'CLDR-49')),
                'enabled_surfaces' => wp_json_encode(array_values(array_unique(array_map('sanitize_key', is_array($input['surfaces'] ?? null) ? $input['surfaces'] : array())))),
                'status' => $status,
                'owner' => 'CF-06',
                'row_version' => 1,
            ));
            $this->audit->record('locale', $parsed['tag'], 'locale_registered', 'success', array('status' => $status, 'fallback' => $fallback));
            return $row;
        });
    }

    public function transition(string $uuid, string $to, int $version, string $reason): array
    {
        $row = $this->repo->find('locales', $uuid) ?? throw new InvalidArgumentException('Locale not found.');
        StateMachine::assert('locale', (string) $row['status'], $to);
        $tag = (string) $row['locale_tag'];
        $default = (string) get_option('slto_default_locale', 'en-US');

        if ('enabled' === $to) {
            $fallback = (string) ($row['fallback_tag'] ?? '');
            if ('' !== $fallback) {
                FallbackChainValidator::validate($tag, $fallback, fn (string $candidate): ?array => $this->repo->findOne('locales', 'locale_tag', $candidate));
            }
            if ($tag !== $default && ! $this->repo->activeBundle($tag)) {
                throw new InvalidArgumentException('A signed active locale bundle is required before enabling this locale.');
            }
        }
        if ('disabled' === $to) {
            if ($tag === $default) {
                throw new InvalidArgumentException('The default locale cannot be disabled.');
            }
            foreach ($this->repo->list('locales', array(), 500, 0, 'id ASC') as $candidate) {
                if ((string) ($candidate['fallback_tag'] ?? '') === $tag && in_array($candidate['status'], array('enabled', 'content_ready', 'degraded'), true)) {
                    throw new InvalidArgumentException('Locale is still required by an active fallback chain.');
                }
            }
        }
        $event = match ($to) {
            'enabled' => 'LocaleEnabled',
            'degraded' => 'LocaleDegraded',
            'deprecated' => 'LocaleDeprecated',
            'disabled' => 'LocaleDisabled',
            default => null,
        };
        return $this->tx->run(function () use ($row, $to, $version, $reason, $event): array {
            $updated = $this->repo->updateVersioned('locales', (string) $row['uuid'], $version, array('status' => $to));
            $this->audit->record('locale', (string) $row['locale_tag'], 'locale_transition', 'success', array(
                'from' => $row['status'], 'to' => $to, 'reason' => $reason,
            ));
            if (null !== $event) {
                $this->outbox->enqueue($event, 'locale', (string) $row['uuid'], array(
                    'locale' => $row['locale_tag'], 'from' => $row['status'], 'to' => $to, 'reason' => $reason,
                ));
            }
            return $updated;
        });
    }

    public function resolve(string $requested): array
    {
        $canonical = LocaleValidator::canonicalize($requested);
        $default = (string) get_option('slto_default_locale', 'en-US');
        if (null === $canonical) {
            $canonical = $default;
        }
        $chain = array();
        $seen = array();
        $current = $canonical;
        for ($i = 0; $i < 8; ++$i) {
            if (isset($seen[$current])) {
                break;
            }
            $seen[$current] = true;
            $chain[] = $current;
            $row = $this->repo->findOne('locales', 'locale_tag', $current);
            if (is_array($row) && in_array((string) $row['status'], array('enabled', 'degraded'), true)) {
                return array(
                    'requested' => $requested,
                    'resolved' => $current,
                    'direction' => $row['direction'],
                    'status' => $row['status'],
                    'fallback_used' => $current !== $canonical,
                    'chain' => $chain,
                );
            }
            $next = is_array($row) ? (string) ($row['fallback_tag'] ?? '') : '';
            if ('' === $next) {
                $next = $default;
            }
            if ($next === $current) {
                break;
            }
            $current = $next;
        }
        return array(
            'requested' => $requested,
            'resolved' => $default,
            'direction' => LocaleValidator::direction($default),
            'status' => 'safe_default',
            'fallback_used' => true,
            'chain' => $chain,
        );
    }

    public function list(bool $public = false): array
    {
        $rows = $this->repo->list('locales', array(), 200, 0, 'id ASC');
        if (! $public) {
            return $rows;
        }
        return array_values(array_map(static fn (array $r): array => array(
            'locale_tag' => $r['locale_tag'],
            'language' => $r['language_subtag'],
            'script' => $r['script_subtag'],
            'region' => $r['region_subtag'],
            'direction' => $r['direction'],
            'fallback' => $r['fallback_tag'],
            'status' => $r['status'],
        ), array_filter($rows, static fn (array $r): bool => in_array((string) $r['status'], array('enabled', 'degraded'), true))));
    }
}
