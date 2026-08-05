<?php

declare(strict_types=1);

namespace Sabri\Localization\Application;

use InvalidArgumentException;
use Sabri\Localization\Domain\Locale\LocaleValidator;
use Sabri\Localization\Infrastructure\Repository\AuditRepository;
use Sabri\Localization\Infrastructure\Repository\LocaleRepository;
use Sabri\Localization\Infrastructure\Repository\ResourceRepository;
use Sabri\Localization\Infrastructure\Transaction;

final class ResourceService
{
    private const KEY_PATTERN = '/^[a-z][a-z0-9_.-]{2,190}$/D';

    public function __construct(
        private readonly LocaleRepository $locales,
        private readonly ResourceRepository $resources,
        private readonly AuditRepository $audit,
        private readonly Transaction $transaction
    ) {
    }

    public function register(array $input): array
    {
        $key = strtolower(trim((string) ($input['resource_key'] ?? '')));
        if (1 !== preg_match(self::KEY_PATTERN, $key)) {
            throw new InvalidArgumentException('Resource key must be a stable lowercase semantic key.');
        }
        $sourceLocale = LocaleValidator::canonicalize((string) ($input['source_locale'] ?? ''));
        if (null === $sourceLocale || null === $this->locales->find($sourceLocale)) {
            throw new InvalidArgumentException('Source locale must be registered.');
        }
        $sourceText = wp_kses_post((string) ($input['source_text'] ?? ''));
        if ('' === trim($sourceText)) {
            throw new InvalidArgumentException('Source text is required.');
        }
        if (strlen($sourceText) > 200000) {
            throw new InvalidArgumentException('Source text exceeds the foundation safety limit.');
        }
        $riskClass = strtolower((string) ($input['risk_class'] ?? 'low'));
        if (! in_array($riskClass, array('low', 'medium', 'high', 'critical', 'private'), true)) {
            throw new InvalidArgumentException('Invalid risk class.');
        }
        $dataClass = strtoupper((string) ($input['data_class'] ?? 'C1'));
        if (! in_array($dataClass, array('C1', 'C2', 'C3', 'C4', 'C5'), true)) {
            throw new InvalidArgumentException('Invalid data class.');
        }
        if (in_array($dataClass, array('C4', 'C5'), true) || 'private' === $riskClass) {
            throw new InvalidArgumentException('Restricted/private resources remain blocked until the approved secure-storage contract is implemented.');
        }
        $placeholderSchema = $this->validatePlaceholders($sourceText, $input['placeholders'] ?? array());
        $references = is_array($input['references'] ?? null) ? array_slice($input['references'], 0, 50) : array();
        $context = wp_kses_post((string) ($input['context'] ?? ''));
        $description = sanitize_textarea_field((string) ($input['description'] ?? ''));
        $domain = sanitize_key((string) ($input['domain'] ?? 'platform')) ?: 'platform';
        $hashPayload = array(
            'key' => $key,
            'source_locale' => $sourceLocale,
            'source_text' => $sourceText,
            'context' => $context,
            'domain' => $domain,
            'risk_class' => $riskClass,
            'data_class' => $dataClass,
            'placeholders' => $placeholderSchema,
        );
        $encoded = wp_json_encode($hashPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $hash = hash('sha256', is_string($encoded) ? $encoded : '');
        $record = array(
            'resource_key' => $key,
            'source_locale' => $sourceLocale,
            'source_text' => $sourceText,
            'source_hash' => $hash,
            'context' => $context,
            'description' => $description,
            'domain_name' => $domain,
            'risk_class' => $riskClass,
            'data_class' => $dataClass,
            'placeholders' => wp_json_encode($placeholderSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'references_json' => wp_json_encode($references, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => 'active',
        );
        return $this->transaction->run(function () use ($record, $key, $hash, $riskClass, $dataClass): array {
            $result = $this->resources->upsert($record, get_current_user_id());
            $this->audit->record(
                'resource',
                $key,
                $result['changed'] ? 'resource_versioned' : 'resource_idempotent',
                'success',
                array('version' => $result['version'], 'hash' => $hash, 'risk' => $riskClass, 'data_class' => $dataClass)
            );
            return array_merge($result, array('resource_key' => $key, 'source_hash' => $hash));
        });
    }

    private function validatePlaceholders(string $sourceText, mixed $provided): array
    {
        preg_match_all('/(?<!\{)\{([A-Za-z][A-Za-z0-9_]*)\}(?!\})/', $sourceText, $matches);
        $found = array_values(array_unique($matches[1] ?? array()));
        sort($found, SORT_STRING);
        $schema = array();
        if (is_array($provided)) {
            foreach ($provided as $name => $type) {
                $name = (string) $name;
                $type = strtolower((string) $type);
                if (1 !== preg_match('/^[A-Za-z][A-Za-z0-9_]*$/D', $name)) {
                    throw new InvalidArgumentException('Invalid placeholder name: ' . $name);
                }
                if (! in_array($type, array('string', 'integer', 'decimal', 'date', 'time', 'datetime', 'currency', 'percent', 'url'), true)) {
                    throw new InvalidArgumentException('Invalid placeholder type for: ' . $name);
                }
                $schema[$name] = $type;
            }
        }
        $declared = array_keys($schema);
        sort($declared, SORT_STRING);
        if ($found !== $declared) {
            throw new InvalidArgumentException('Declared placeholders must exactly match named placeholders in source text.');
        }
        return $schema;
    }
}
