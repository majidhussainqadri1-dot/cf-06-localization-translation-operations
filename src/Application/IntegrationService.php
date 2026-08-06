<?php

declare(strict_types=1);

namespace Sabri\Localization\Application;

use InvalidArgumentException;
use Sabri\Localization\Contract\IntegrationRegistry;
use Sabri\Localization\Infrastructure\Database;
use Sabri\Localization\Infrastructure\Outbox;
use Sabri\Localization\Infrastructure\Repository\AuditRepository;
use Sabri\Localization\Infrastructure\Repository\LocalizationRepository;
use Sabri\Localization\Infrastructure\Transaction;

final class IntegrationService
{
    public function __construct(
        private readonly LocalizationRepository $repo,
        private readonly AuditRepository $audit,
        private readonly Outbox $outbox,
        private readonly Transaction $tx
    ) {}

    public function accept(array $input): array
    {
        $key = sanitize_key((string)($input['integration_key'] ?? ''));
        if (! in_array($key, IntegrationRegistry::required(), true)) {
            throw new InvalidArgumentException('Localization integration key is not recognized.');
        }
        $contractVersion = trim((string)($input['contract_version'] ?? ''));
        $manifestHash = strtolower(trim((string)($input['manifest_hash'] ?? '')));
        $evidenceHash = strtolower(trim((string)($input['evidence_hash'] ?? '')));
        $evidenceRef = sanitize_text_field((string)($input['evidence_ref'] ?? ''));
        $environment = sanitize_key((string)($input['environment_name'] ?? ''));
        $expiresAt = $this->dateOrNull($input['expires_at'] ?? null);
        if (1 !== preg_match('/^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?$/D', $contractVersion)
            || 1 !== preg_match('/^[a-f0-9]{64}$/D', $manifestHash)
            || 1 !== preg_match('/^[a-f0-9]{64}$/D', $evidenceHash)
            || '' === $evidenceRef || strlen($evidenceRef) > 191
            || ! in_array($environment, array('staging','production'), true)) {
            throw new InvalidArgumentException('Integration acceptance evidence is incomplete or invalid.');
        }
        if (null !== $expiresAt && strtotime($expiresAt) <= time()) {
            throw new InvalidArgumentException('Integration acceptance evidence is already expired.');
        }
        $evidence = array(
            'integration_key'=>$key,
            'contract_version'=>$contractVersion,
            'manifest_hash'=>$manifestHash,
            'evidence_hash'=>$evidenceHash,
            'evidence_ref'=>$evidenceRef,
            'environment_name'=>$environment,
            'approved_by'=>get_current_user_id(),
            'approved_at'=>Database::now(),
            'expires_at'=>$expiresAt,
        );
        if (true !== apply_filters('slto_verify_integration_acceptance_evidence', false, $evidence)) {
            throw new InvalidArgumentException('Integration acceptance evidence could not be independently verified.');
        }
        return $this->tx->run(function() use ($evidence, $key): array {
            $existing = $this->repo->findOne('integration_evidence', 'integration_key', $key);
            $data = array_merge($evidence, array('status'=>'accepted','row_version'=>1));
            $row = is_array($existing)
                ? $this->repo->updateVersioned('integration_evidence', (string)$existing['uuid'], (int)$existing['row_version'], array_diff_key($data, array('row_version'=>true)))
                : $this->repo->insert('integration_evidence', $data);
            $this->audit->record('integration', $key, 'integration_acceptance_recorded', 'success', array(
                'contract_version'=>$evidence['contract_version'],'manifest_hash'=>$evidence['manifest_hash'],
                'evidence_hash'=>$evidence['evidence_hash'],'environment'=>$evidence['environment_name'],
            ));
            $this->outbox->enqueue('LocalizationIntegrationAccepted', 'integration', (string)$row['uuid'], array(
                'integration_key'=>$key,'contract_version'=>$evidence['contract_version'],'environment'=>$evidence['environment_name'],
            ));
            return $row;
        });
    }

    public function revoke(string $uuid, int $version, string $reason): array
    {
        $row = $this->repo->find('integration_evidence', $uuid) ?? throw new InvalidArgumentException('Integration acceptance evidence not found.');
        $reason = sanitize_textarea_field($reason);
        if ('' === $reason) {
            throw new InvalidArgumentException('Integration revocation reason is required.');
        }
        return $this->tx->run(function() use ($row, $version, $reason): array {
            $updated = $this->repo->updateVersioned('integration_evidence', (string)$row['uuid'], $version, array('status'=>'revoked'));
            $this->audit->record('integration', (string)$row['integration_key'], 'integration_acceptance_revoked', 'success', array('reason'=>$reason));
            return $updated;
        });
    }

    public function readiness(): array
    {
        $result = array();
        foreach (IntegrationRegistry::required() as $key) {
            $row = $this->repo->findOne('integration_evidence', 'integration_key', $key);
            $valid = is_array($row)
                && 'accepted' === (string)$row['status']
                && in_array((string)$row['environment_name'], array('staging','production'), true)
                && (empty($row['expires_at']) || strtotime((string)$row['expires_at']) > time())
                && 1 === preg_match('/^[a-f0-9]{64}$/D', (string)$row['manifest_hash'])
                && 1 === preg_match('/^[a-f0-9]{64}$/D', (string)$row['evidence_hash']);
            $result[$key] = $valid;
        }
        return $result;
    }

    public function assertReady(): void
    {
        foreach ($this->readiness() as $key => $ready) {
            if (! $ready) {
                throw new InvalidArgumentException('Required localization integration is not accepted: ' . $key);
            }
        }
    }

    private function dateOrNull(mixed $value): ?string
    {
        if (null === $value || '' === $value) { return null; }
        $timestamp = strtotime((string)$value);
        if (false === $timestamp) { throw new InvalidArgumentException('Integration evidence expiry is invalid.'); }
        return gmdate('Y-m-d H:i:s', $timestamp);
    }
}
