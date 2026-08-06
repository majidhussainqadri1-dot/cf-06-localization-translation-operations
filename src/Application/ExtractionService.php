<?php

declare(strict_types=1);

namespace Sabri\Localization\Application;

use InvalidArgumentException;
use Sabri\Localization\Infrastructure\Database;
use Sabri\Localization\Infrastructure\Repository\AuditRepository;
use Sabri\Localization\Infrastructure\Repository\LocalizationRepository;
use Sabri\Localization\Infrastructure\Transaction;

final class ExtractionService
{
    public function __construct(
        private readonly LocalizationRepository $repo,
        private readonly AuditRepository $audit,
        private readonly Transaction $tx
    ) {}

    public function record(array $input): array
    {
        $ownerModule = sanitize_key((string)($input['owner_module'] ?? ''));
        $repositoryRef = sanitize_text_field((string)($input['repository_ref'] ?? ''));
        $sourceCommit = strtolower(trim((string)($input['source_commit'] ?? '')));
        $inventoryHash = strtolower(trim((string)($input['inventory_hash'] ?? '')));
        $extractionHash = strtolower(trim((string)($input['extraction_hash'] ?? '')));
        $evidenceRef = sanitize_text_field((string)($input['evidence_ref'] ?? ''));
        $resourceCount = (int)($input['resource_count'] ?? -1);
        if ('' === $ownerModule || '' === $repositoryRef || strlen($repositoryRef) > 191
            || 1 !== preg_match('/^[a-f0-9]{7,64}$/D', $sourceCommit)
            || 1 !== preg_match('/^[a-f0-9]{64}$/D', $inventoryHash)
            || 1 !== preg_match('/^[a-f0-9]{64}$/D', $extractionHash)
            || '' === $evidenceRef || strlen($evidenceRef) > 191
            || $resourceCount < 0 || $resourceCount > 1000000) {
            throw new InvalidArgumentException('Extraction acceptance evidence is incomplete or invalid.');
        }
        $evidence = array(
            'owner_module'=>$ownerModule,'repository_ref'=>$repositoryRef,'source_commit'=>$sourceCommit,
            'inventory_hash'=>$inventoryHash,'extraction_hash'=>$extractionHash,'evidence_ref'=>$evidenceRef,
            'resource_count'=>$resourceCount,'environment_name'=>sanitize_key((string)($input['environment_name'] ?? 'staging')),
        );
        if ('staging' !== $evidence['environment_name'] || true !== apply_filters('slto_verify_extraction_evidence', false, $evidence)) {
            throw new InvalidArgumentException('Extraction evidence could not be independently verified in staging.');
        }
        return $this->tx->run(function() use ($evidence): array {
            $row = $this->repo->insert('extraction_evidence', array_merge($evidence, array(
                'approved_by'=>get_current_user_id(),'approved_at'=>Database::now(),'status'=>'accepted','row_version'=>1,
            )));
            $this->audit->record('extraction', (string)$row['uuid'], 'extraction_evidence_recorded', 'success', array(
                'owner_module'=>$evidence['owner_module'],'source_commit'=>$evidence['source_commit'],
                'inventory_hash'=>$evidence['inventory_hash'],'extraction_hash'=>$evidence['extraction_hash'],
                'resource_count'=>$evidence['resource_count'],
            ));
            return $row;
        });
    }
}
