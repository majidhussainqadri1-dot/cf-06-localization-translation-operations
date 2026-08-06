<?php

declare(strict_types=1);

namespace Sabri\Localization\Application;

use InvalidArgumentException;
use Sabri\Localization\Domain\Locale\LocaleValidator;
use Sabri\Localization\Domain\Workflow\StateMachine;
use Sabri\Localization\Infrastructure\Database;
use Sabri\Localization\Infrastructure\Outbox;
use Sabri\Localization\Infrastructure\Repository\AuditRepository;
use Sabri\Localization\Infrastructure\Repository\LocalizationRepository;
use Sabri\Localization\Infrastructure\Transaction;

final class TerminologyService
{
    public function __construct(
        private readonly LocalizationRepository $repo,
        private readonly AuditRepository $audit,
        private readonly Outbox $outbox,
        private readonly Transaction $tx
    ) {
    }

    public function create(array $input): array
    {
        $concept = sanitize_key((string) ($input['concept_id'] ?? ''));
        $domain = sanitize_key((string) ($input['domain'] ?? ''));
        $source = trim((string) ($input['source_term'] ?? ''));
        $approved = trim((string) ($input['approved_term'] ?? ''));
        $sourceLocale = LocaleValidator::canonicalize((string) ($input['source_locale'] ?? ''));
        $targetLocale = LocaleValidator::canonicalize((string) ($input['target_locale'] ?? ''));
        if ('' === $concept || '' === $domain || '' === $source || '' === $approved || null === $sourceLocale || null === $targetLocale) {
            throw new InvalidArgumentException('Terminology entry is incomplete.');
        }

        $row = $this->repo->insert('terminology', array(
            'concept_id' => $concept,
            'domain_name' => $domain,
            'source_locale' => $sourceLocale,
            'source_term' => $source,
            'target_locale' => $targetLocale,
            'approved_term' => $approved,
            'prohibited_terms' => wp_json_encode(array_values(array_unique(array_filter(array_map('strval', is_array($input['prohibited_terms'] ?? null) ? $input['prohibited_terms'] : array()))))),
            'definition_text' => sanitize_textarea_field((string) ($input['definition'] ?? '')),
            'context_text' => sanitize_textarea_field((string) ($input['context'] ?? '')),
            'grammar_notes' => sanitize_textarea_field((string) ($input['grammar_notes'] ?? '')),
            'created_by' => get_current_user_id(),
            'status' => 'proposed',
            'term_version' => 1,
            'row_version' => 1,
        ));
        $this->audit->record('terminology', (string) $row['uuid'], 'terminology_proposed', 'success', array(
            'concept_id' => $concept, 'locale' => $targetLocale, 'domain' => $domain,
        ));
        return $row;
    }

    public function transition(string $uuid, string $to, int $version, string $reason = ''): array
    {
        $row = $this->repo->find('terminology', $uuid) ?? throw new InvalidArgumentException('Terminology entry not found.');
        StateMachine::assert('terminology', (string) $row['status'], $to);
        if (in_array($to, array('approved', 'active'), true) && (int) $row['created_by'] === get_current_user_id()) {
            throw new InvalidArgumentException('Terminology proposer cannot approve their own entry.');
        }

        return $this->tx->run(function () use ($row, $to, $version, $reason): array {
            $changes = array('status' => $to);
            if (in_array($to, array('approved', 'active'), true)) {
                $changes['reviewer_id'] = get_current_user_id();
                $changes['effective_at'] = Database::now();
            }
            $updated = $this->repo->updateVersioned('terminology', (string) $row['uuid'], $version, $changes);
            $this->audit->record('terminology', (string) $row['uuid'], 'terminology_transition', 'success', array(
                'from' => $row['status'], 'to' => $to, 'reason' => $reason,
            ));
            if ('active' === $to) {
                $this->outbox->enqueue('TerminologyEntryApproved', 'terminology', (string) $row['uuid'], array(
                    'concept_id' => $row['concept_id'], 'locale' => $row['target_locale'], 'domain' => $row['domain_name'],
                ));
            }
            if ('deprecated' === $to) {
                $this->outbox->enqueue('TerminologyEntryDeprecated', 'terminology', (string) $row['uuid'], array(
                    'concept_id' => $row['concept_id'], 'locale' => $row['target_locale'],
                ));
            }
            return $updated;
        });
    }

    public function styleGuide(array $input): array
    {
        $locale = LocaleValidator::canonicalize((string) ($input['locale'] ?? ''));
        if (null === $locale || ! $this->repo->findOne('locales', 'locale_tag', $locale)) {
            throw new InvalidArgumentException('Style guide locale is invalid or unregistered.');
        }
        $domain = sanitize_key((string) ($input['domain'] ?? 'platform')) ?: 'platform';
        $rules = is_array($input['rules'] ?? null) ? $input['rules'] : array();
        if (empty($rules)) {
            throw new InvalidArgumentException('Style guide rules are required.');
        }
        $encoded = wp_json_encode($rules, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (false === $encoded || strlen($encoded) > 250000) {
            throw new InvalidArgumentException('Style guide rules exceed the bounded limit.');
        }
        $existing = $this->repo->list('style_guides', array('locale_tag' => $locale, 'domain_name' => $domain), 1, 0, 'id DESC');
        $version = empty($existing) ? 1 : (int) $existing[0]['guide_version'] + 1;
        $row = $this->repo->insert('style_guides', array(
            'locale_tag' => $locale,
            'domain_name' => $domain,
            'guide_version' => $version,
            'rules_json' => $encoded,
            'examples_json' => wp_json_encode(array_slice(is_array($input['examples'] ?? null) ? $input['examples'] : array(), 0, 100)),
            'created_by' => get_current_user_id(),
            'approved_by' => null,
            'status' => 'draft',
            'effective_at' => null,
            'row_version' => 1,
        ));
        $this->audit->record('style_guide', (string) $row['uuid'], 'style_guide_drafted', 'success', array(
            'locale' => $locale, 'domain' => $domain, 'guide_version' => $version,
        ));
        return $row;
    }

    public function transitionStyleGuide(string $uuid, string $to, int $version, string $reason = ''): array
    {
        $row = $this->repo->find('style_guides', $uuid) ?? throw new InvalidArgumentException('Style guide not found.');
        $map = array(
            'draft' => array('approved', 'deprecated'),
            'approved' => array('active', 'deprecated'),
            'active' => array('deprecated'),
            'deprecated' => array(),
        );
        if (! in_array($to, $map[(string) $row['status']] ?? array(), true)) {
            throw new InvalidArgumentException('Invalid style guide transition.');
        }
        if (in_array($to, array('approved', 'active'), true) && (int) $row['created_by'] === get_current_user_id()) {
            throw new InvalidArgumentException('Style guide author cannot approve their own guide.');
        }
        return $this->tx->run(function () use ($row, $to, $version, $reason): array {
            $changes = array('status' => $to);
            if (in_array($to, array('approved', 'active'), true)) {
                $changes['approved_by'] = get_current_user_id();
                $changes['effective_at'] = Database::now();
            }
            $updated = $this->repo->updateVersioned('style_guides', (string) $row['uuid'], $version, $changes);
            $this->audit->record('style_guide', (string) $row['uuid'], 'style_guide_transition', 'success', array(
                'from' => $row['status'], 'to' => $to, 'reason' => $reason,
            ));
            return $updated;
        });
    }

    public function suggestMemory(string $source, string $sourceLocale, string $targetLocale, string $domain, int $limit = 10): array
    {
        $sourceLocale = LocaleValidator::canonicalize($sourceLocale) ?? '';
        $targetLocale = LocaleValidator::canonicalize($targetLocale) ?? '';
        $domain = sanitize_key($domain);
        if ('' === $sourceLocale || '' === $targetLocale || '' === $domain || '' === trim($source)) {
            throw new InvalidArgumentException('Translation memory query is incomplete.');
        }
        $rows = $this->repo->list('memory', array('source_locale' => $sourceLocale, 'target_locale' => $targetLocale, 'domain_name' => $domain, 'status' => 'approved'), 500);
        $lower = static fn (string $value): string => function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
        $suggestions = array();
        foreach ($rows as $row) {
            similar_text($lower($source), $lower((string) $row['source_segment']), $score);
            if ($score >= 55.0) {
                $suggestions[] = array(
                    'uuid' => $row['uuid'],
                    'source' => $row['source_segment'],
                    'target' => $row['target_segment'],
                    'score' => round($score, 2),
                    'risk' => $row['risk_class'],
                    'provenance' => json_decode((string) $row['provenance_json'], true),
                );
            }
        }
        usort($suggestions, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        return array_slice($suggestions, 0, max(1, min(50, $limit)));
    }
}
