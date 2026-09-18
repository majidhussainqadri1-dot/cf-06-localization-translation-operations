<?php

declare(strict_types=1);

namespace Sabri\Localization\Application;

use InvalidArgumentException;
use Sabri\Localization\Infrastructure\Repository\AuditRepository;
use Sabri\Localization\Infrastructure\Repository\LocalizationRepository;
use Sabri\Localization\Infrastructure\Transaction;

final class QaEvidenceService
{
    public function __construct(
        private readonly LocalizationRepository $repo,
        private readonly AuditRepository $audit,
        private readonly Transaction $tx
    ) {}

    public function record(array $input): array
    {
        $environmentName = sanitize_key((string)($input['environment_name'] ?? ''));
        $pluginVersion = sanitize_text_field((string)($input['plugin_version'] ?? ''));
        $buildSha = strtolower(trim((string)($input['build_sha'] ?? '')));
        $testId = sanitize_key((string)($input['test_id'] ?? ''));
        $result = sanitize_key((string)($input['result'] ?? ''));
        $expectedHash = strtolower(trim((string)($input['expected_hash'] ?? '')));
        $actualHash = strtolower(trim((string)($input['actual_hash'] ?? '')));
        $artifactRef = sanitize_text_field((string)($input['artifact_ref'] ?? ''));
        $artifactHash = strtolower(trim((string)($input['artifact_hash'] ?? '')));
        $targetType = sanitize_key((string)($input['target_type'] ?? 'module'));
        $targetUuid = sanitize_text_field((string)($input['target_uuid'] ?? 'CF-06'));
        $reviewerId = get_current_user_id();
        if (! in_array($environmentName, array('ci','staging','production'), true)
            || '' === $pluginVersion || strlen($pluginVersion) > 40 || '' === $testId || strlen($testId) > 80
            || '' === $targetType || strlen($targetType) > 40 || '' === $targetUuid || strlen($targetUuid) > 191 || $reviewerId <= 0
            || ! in_array($result, array('pass','fail','blocked'), true)
            || 1 !== preg_match('/^[a-f0-9]{7,64}$/D', $buildSha)
            || 1 !== preg_match('/^[a-f0-9]{64}$/D', $expectedHash)
            || 1 !== preg_match('/^[a-f0-9]{64}$/D', $actualHash)
            || '' === $artifactRef || strlen($artifactRef) > 191
            || 1 !== preg_match('/^[a-f0-9]{64}$/D', $artifactHash)) {
            throw new InvalidArgumentException('QA evidence is incomplete or invalid.');
        }
        if ('pass' === $result && ! hash_equals($expectedHash, $actualHash)) {
            throw new InvalidArgumentException('QA evidence cannot pass when expected and actual hashes differ.');
        }
        return $this->tx->run(function() use ($input, $environmentName, $pluginVersion, $buildSha, $testId, $result, $expectedHash, $actualHash, $artifactRef, $artifactHash, $targetType, $targetUuid, $reviewerId): array {
            $row = $this->repo->insert('qa_evidence', array(
                'target_type'=>$targetType,
                'target_uuid'=>$targetUuid,
                'environment_name'=>$environmentName,'plugin_version'=>$pluginVersion,'build_sha'=>$buildSha,'test_id'=>$testId,
                'expected_hash'=>$expectedHash,'actual_hash'=>$actualHash,'result'=>$result,
                'artifact_ref'=>$artifactRef,'artifact_hash'=>$artifactHash,'reviewer_id'=>$reviewerId,
                'details_json'=>$this->encodeBounded(is_array($input['details'] ?? null) ? $input['details'] : array()),
            ));
            $this->audit->record('qa_evidence', (string)$row['uuid'], 'qa_evidence_recorded', 'success', array(
                'environment_name'=>$environmentName,'plugin_version'=>$pluginVersion,'build_sha'=>$buildSha,
                'test_id'=>$testId,'result'=>$result,'artifact_hash'=>$artifactHash,
            ));
            return $row;
        });
    }

    public function passed(
        string $targetType,
        string $targetUuid,
        array $requiredTestIds,
        string $environment = 'staging',
        ?string $pluginVersion = null,
        ?string $buildSha = null
    ): bool {
        $environment = sanitize_key($environment);
        if (! in_array($environment, array('ci','staging','production'), true)) {
            return false;
        }
        $pluginVersion = null === $pluginVersion ? null : sanitize_text_field($pluginVersion);
        $buildSha = null === $buildSha ? null : strtolower(trim($buildSha));
        if (empty($requiredTestIds) || count($requiredTestIds) > 100
            || (null !== $pluginVersion && ('' === $pluginVersion || strlen($pluginVersion) > 40))
            || (null !== $buildSha && 1 !== preg_match('/^[a-f0-9]{7,64}$/D', $buildSha))) {
            return false;
        }
        $rows = $this->repo->list('qa_evidence', array(
            'target_type'=>sanitize_key($targetType),'target_uuid'=>$targetUuid,'environment_name'=>$environment,
        ), 500, 0, 'created_at DESC');
        $latest = array();
        foreach ($rows as $row) {
            $testId = (string)($row['test_id'] ?? '');
            if ('' === $testId || isset($latest[$testId])) {
                continue;
            }
            if (null !== $pluginVersion && $pluginVersion !== (string)$row['plugin_version']) {
                continue;
            }
            if (null !== $buildSha && ! hash_equals($buildSha, strtolower((string)$row['build_sha']))) {
                continue;
            }
            $latest[$testId] = $row;
        }
        foreach ($requiredTestIds as $id) {
            $id = sanitize_key((string)$id);
            if ('' === $id || strlen($id) > 80) { return false; }
            $row = $latest[$id] ?? null;
            if (! is_array($row) || 'pass' !== (string)$row['result']
                || ! hash_equals((string)$row['expected_hash'], (string)$row['actual_hash'])) {
                return false;
            }
        }
        return true;
    }

    private function encodeBounded(array $value): string
    {
        $json = wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (! is_string($json) || strlen($json) > 262144) {
            throw new InvalidArgumentException('QA evidence details exceed the bounded limit.');
        }
        return $json;
    }
}
