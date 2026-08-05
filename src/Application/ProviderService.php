<?php

declare(strict_types=1);

namespace Sabri\Localization\Application;

use InvalidArgumentException;
use Sabri\Localization\Domain\Security\UrlGuard;
use Sabri\Localization\Infrastructure\Repository\AuditRepository;
use Sabri\Localization\Infrastructure\Repository\LocalizationRepository;

final class ProviderService
{
    public function __construct(private readonly LocalizationRepository $repo, private readonly AuditRepository $audit)
    {
    }

    public function register(array $input): array
    {
        $key = sanitize_key((string) ($input['provider_key'] ?? ''));
        $type = sanitize_key((string) ($input['provider_type'] ?? 'mt'));
        $url = esc_url_raw((string) ($input['base_url'] ?? ''));
        $hosts = array_values(array_unique(array_filter(array_map('sanitize_text_field', is_array($input['allowed_hosts'] ?? null) ? $input['allowed_hosts'] : array()))));
        if ('' === $key || '' === $type) {
            throw new InvalidArgumentException('Provider identity is required.');
        }
        if ('' !== $url) {
            UrlGuard::assertPublicHttps($url, $hosts);
        }
        if (! empty($input['training_allowed'])) {
            throw new InvalidArgumentException('Provider training is denied by default.');
        }
        $credentialRef = sanitize_text_field((string) ($input['credential_reference'] ?? ''));
        if ('' !== $credentialRef && ! str_starts_with($credentialRef, 'env:')) {
            throw new InvalidArgumentException('Provider credentials must use an environment/private-manager reference.');
        }

        $existing = $this->repo->findOne('providers', 'provider_key', $key);
        $data = array(
            'provider_key' => $key,
            'provider_type' => $type,
            'base_url' => $url ?: null,
            'allowed_hosts' => wp_json_encode($hosts),
            'region_code' => sanitize_text_field((string) ($input['region'] ?? '')) ?: null,
            'retention_days' => max(0, min(365, (int) ($input['retention_days'] ?? 0))),
            'training_allowed' => 0,
            'subprocessors_json' => wp_json_encode(array_slice(is_array($input['subprocessors'] ?? null) ? $input['subprocessors'] : array(), 0, 100)),
            'credential_reference' => $credentialRef ?: null,
            'contract_version' => sanitize_text_field((string) ($input['contract_version'] ?? '1.0')),
        );
        if (is_array($existing)) {
            // Configuration updates never smuggle a lifecycle transition.
            $data['status'] = $existing['status'];
            $row = $this->repo->updateVersioned('providers', (string) $existing['uuid'], (int) ($input['row_version'] ?? $existing['row_version']), $data);
        } else {
            $data['status'] = 'disabled';
            $row = $this->repo->insert('providers', array_merge($data, array('row_version' => 1)));
        }
        $this->audit->record('provider', $key, 'localization_provider_registered', 'success', array(
            'type' => $type, 'region' => $data['region_code'], 'retention_days' => $data['retention_days'], 'status' => $row['status'],
        ));
        return $row;
    }

    public function transition(string $uuid, string $to, int $version, string $reason = ''): array
    {
        $row = $this->repo->find('providers', $uuid) ?? throw new InvalidArgumentException('Localization provider not found.');
        $map = array(
            'disabled' => array('approved'),
            'approved' => array('active', 'disabled', 'deprecated'),
            'active' => array('disabled', 'deprecated'),
            'deprecated' => array('disabled'),
        );
        if (! in_array($to, $map[(string) $row['status']] ?? array(), true)) {
            throw new InvalidArgumentException('Invalid provider transition.');
        }
        if ('active' === $to) {
            if (! defined('SLTO_PROVIDER_ACTIVATION_APPROVED') || true !== SLTO_PROVIDER_ACTIVATION_APPROVED) {
                throw new InvalidArgumentException('Provider activation has not received Founder approval.');
            }
            if (empty($row['base_url']) || empty($row['allowed_hosts']) || empty($row['credential_reference']) || empty($row['contract_version'])) {
                throw new InvalidArgumentException('Provider activation prerequisites are incomplete.');
            }
        }
        if ('deprecated' === $to) {
            $open = $this->repo->count('vendor_jobs', array('provider_key' => $row['provider_key']), array('purged', 'rejected', 'failed'));
            if ($open > 0) {
                throw new InvalidArgumentException('Provider has unresolved jobs and cannot be deprecated.');
            }
        }
        $updated = $this->repo->updateVersioned('providers', $uuid, $version, array('status' => $to));
        $this->audit->record('provider', (string) $row['provider_key'], 'localization_provider_transition', 'success', array(
            'from' => $row['status'], 'to' => $to, 'reason' => $reason,
        ));
        return $updated;
    }
}
