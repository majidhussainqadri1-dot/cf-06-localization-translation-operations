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

    public static function deploymentEnvironment(): ?string
    {
        $value = defined('SLTO_DEPLOYMENT_ENV') ? (string) SLTO_DEPLOYMENT_ENV : (string) getenv('SLTO_DEPLOYMENT_ENV');
        $value = sanitize_key($value);
        return in_array($value, array('staging','production'), true) ? $value : null;
    }

    public function accept(array $input): array
    {
        $key = sanitize_key((string)($input['integration_key'] ?? ''));
        if (! in_array($key, IntegrationRegistry::required(), true)) {
            throw new InvalidArgumentException('Localization integration key is not recognized.');
        }
        $configuredEnvironment = self::deploymentEnvironment();
        $environment = sanitize_key((string)($input['environment_name'] ?? ''));
        if (null === $configuredEnvironment || $environment !== $configuredEnvironment) {
            throw new InvalidArgumentException('Integration evidence must match the explicitly configured deployment environment.');
        }
        $contractVersion = trim((string)($input['contract_version'] ?? ''));
        $manifestHash = strtolower(trim((string)($input['manifest_hash'] ?? '')));
        $evidenceHash = strtolower(trim((string)($input['evidence_hash'] ?? '')));
        $evidenceRef = sanitize_text_field((string)($input['evidence_ref'] ?? ''));
        $expiresAt = $this->dateOrNull($input['expires_at'] ?? null);
        if (strlen($contractVersion) > 40 || 1 !== preg_match('/^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?$/D', $contractVersion)
            || 1 !== preg_match('/^[a-f0-9]{64}$/D', $manifestHash)
            || 1 !== preg_match('/^[a-f0-9]{64}$/D', $evidenceHash)
            || '' === $evidenceRef || strlen($evidenceRef) > 191) {
            throw new InvalidArgumentException('Integration acceptance evidence is incomplete or invalid.');
        }
        if (null !== $expiresAt && strtotime($expiresAt) <= time()) {
            throw new InvalidArgumentException('Integration acceptance evidence is already expired.');
        }
        $storageKey = self::evidenceStorageKey($key, $environment);
        $evidence = array(
            'integration_key'=>$key,
            'storage_key'=>$storageKey,
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
        return $this->tx->run(function() use ($evidence, $key, $storageKey): array {
            $existing = $this->repo->findOne('integration_evidence', 'integration_key', $storageKey);
            $data = array(
                'integration_key'=>$storageKey,
                'contract_version'=>$evidence['contract_version'],
                'manifest_hash'=>$evidence['manifest_hash'],
                'evidence_hash'=>$evidence['evidence_hash'],
                'evidence_ref'=>$evidence['evidence_ref'],
                'environment_name'=>$evidence['environment_name'],
                'approved_by'=>$evidence['approved_by'],
                'approved_at'=>$evidence['approved_at'],
                'expires_at'=>$evidence['expires_at'],
                'status'=>'accepted',
            );
            $row = is_array($existing)
                ? $this->repo->updateVersioned('integration_evidence', (string)$existing['uuid'], (int)$existing['row_version'], $data)
                : $this->repo->insert('integration_evidence', array_merge($data, array('row_version'=>1)));
            $this->audit->record('integration', $key, 'integration_acceptance_recorded', 'success', array(
                'contract_version'=>$evidence['contract_version'],'manifest_hash'=>$evidence['manifest_hash'],
                'evidence_hash'=>$evidence['evidence_hash'],'environment'=>$evidence['environment_name'],
            ));
            $this->outbox->enqueue('LocalizationIntegrationAccepted', 'integration', (string)$row['uuid'], array(
                'integration_key'=>$key,'contract_version'=>$evidence['contract_version'],'environment'=>$evidence['environment_name'],
            ));
            $row['canonical_integration_key'] = $key;
            return $row;
        });
    }

    public function revoke(string $uuid, int $version, string $reason): array
    {
        $row = $this->repo->find('integration_evidence', $uuid) ?? throw new InvalidArgumentException('Integration acceptance evidence not found.');
        $reason = sanitize_textarea_field($reason);
        if ('' === $reason || strlen($reason) > 2000) {
            throw new InvalidArgumentException('A bounded integration revocation reason is required.');
        }
        return $this->tx->run(function() use ($row, $version, $reason): array {
            $updated = $this->repo->updateVersioned('integration_evidence', (string)$row['uuid'], $version, array('status'=>'revoked'));
            $this->audit->record('integration', self::canonicalKey((string)$row['integration_key']), 'integration_acceptance_revoked', 'success', array(
                'reason'=>$reason,'environment'=>$row['environment_name']??'',
            ));
            return $updated;
        });
    }

    public function readiness(?string $environment = null): array
    {
        $environment = null === $environment ? self::deploymentEnvironment() : sanitize_key($environment);
        if (! in_array($environment, array('staging','production'), true)) {
            return array_fill_keys(IntegrationRegistry::required(), false);
        }
        $result = array();
        foreach (IntegrationRegistry::required() as $key) {
            $row = $this->repo->findOne('integration_evidence', 'integration_key', self::evidenceStorageKey($key, $environment));
            $valid = is_array($row)
                && 'accepted' === (string)$row['status']
                && $environment === (string)$row['environment_name']
                && (empty($row['expires_at']) || strtotime((string)$row['expires_at']) > time())
                && 1 === preg_match('/^[a-f0-9]{64}$/D', (string)$row['manifest_hash'])
                && 1 === preg_match('/^[a-f0-9]{64}$/D', (string)$row['evidence_hash']);
            if ($valid) {
                $evidence = array(
                    'integration_key'=>$key,
                    'storage_key'=>(string)$row['integration_key'],
                    'contract_version'=>(string)$row['contract_version'],
                    'manifest_hash'=>(string)$row['manifest_hash'],
                    'evidence_hash'=>(string)$row['evidence_hash'],
                    'evidence_ref'=>(string)$row['evidence_ref'],
                    'environment_name'=>(string)$row['environment_name'],
                    'approved_by'=>(int)$row['approved_by'],
                    'approved_at'=>(string)$row['approved_at'],
                    'expires_at'=>$row['expires_at']??null,
                );
                $valid = true === apply_filters('slto_verify_integration_acceptance_evidence', false, $evidence);
            }
            $result[$key] = $valid;
        }
        return $result;
    }

    public function assertReady(?string $environment = null): void
    {
        $environment = null === $environment ? self::deploymentEnvironment() : sanitize_key($environment);
        if (! in_array($environment, array('staging','production'), true)) {
            throw new InvalidArgumentException('CF-06 deployment environment is not explicitly configured.');
        }
        foreach ($this->readiness($environment) as $key => $ready) {
            if (! $ready) {
                throw new InvalidArgumentException('Required localization integration is not accepted for ' . $environment . ': ' . $key);
            }
        }
    }

    private static function evidenceStorageKey(string $key, string $environment): string
    {
        $value = $key . '@' . $environment;
        if (strlen($value) > 80) {
            throw new InvalidArgumentException('Environment-qualified integration key exceeds the database contract.');
        }
        return $value;
    }

    private static function canonicalKey(string $stored): string
    {
        $parts = explode('@', $stored, 2);
        return sanitize_key((string)$parts[0]);
    }

    private function dateOrNull(mixed $value): ?string
    {
        if (null === $value || '' === $value) { return null; }
        $raw=trim((string)$value);
        $formats=array('!Y-m-d\\TH:i:s\\Z','!Y-m-d H:i:s');
        foreach($formats as $format){
            $date=\DateTimeImmutable::createFromFormat($format,$raw,new \DateTimeZone('UTC'));
            $errors=\DateTimeImmutable::getLastErrors();
            $validErrors=false===$errors||((int)($errors['warning_count']??0)===0&&(int)($errors['error_count']??0)===0);
            if($date instanceof \DateTimeImmutable&&$validErrors){
                $canonical='!Y-m-d\\TH:i:s\\Z'===$format?$date->format('Y-m-d\\TH:i:s\\Z'):$date->format('Y-m-d H:i:s');
                if(hash_equals($raw,$canonical)){return $date->format('Y-m-d H:i:s');}
            }
        }
        throw new InvalidArgumentException('Integration evidence expiry must be an exact UTC timestamp.');
    }
}
