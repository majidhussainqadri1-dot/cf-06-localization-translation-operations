<?php

declare(strict_types=1);

namespace Sabri\Localization\Application;

use InvalidArgumentException;
use Sabri\Localization\Infrastructure\Database;
use Sabri\Localization\Infrastructure\Repository\AuditRepository;
use Sabri\Localization\Infrastructure\Repository\LocalizationRepository;
use Sabri\Localization\Infrastructure\Transaction;

final class ReleaseApprovalService
{
    private const ROLES = array('release_operator','independent_reviewer');

    public function __construct(
        private readonly LocalizationRepository $repo,
        private readonly AuditRepository $audit,
        private readonly Transaction $tx
    ) {}

    public function approve(string $bundleUuid, array $input): array
    {
        $bundle = $this->repo->find('bundles', $bundleUuid) ?? throw new InvalidArgumentException('Locale bundle not found.');
        if (! in_array((string)$bundle['status'], array('approved','staged','canary'), true)) {
            throw new InvalidArgumentException('Release approval requires an approved or staged bundle.');
        }
        $role = sanitize_key((string)($input['approval_role'] ?? ''));
        $evidenceRef = sanitize_text_field((string)($input['evidence_ref'] ?? ''));
        $evidenceHash = strtolower(trim((string)($input['evidence_hash'] ?? '')));
        $stepUpAt = (string)($input['step_up_at'] ?? '');
        $stepUpTimestamp = strtotime($stepUpAt);
        if (! in_array($role, self::ROLES, true) || '' === $evidenceRef || strlen($evidenceRef) > 191
            || 1 !== preg_match('/^[a-f0-9]{64}$/D', $evidenceHash)
            || false === $stepUpTimestamp || abs(time() - $stepUpTimestamp) > 900) {
            throw new InvalidArgumentException('Release approval evidence or recent step-up proof is invalid.');
        }
        $actor = get_current_user_id();
        $existing = $this->repo->list('release_approvals', array('bundle_uuid'=>$bundleUuid,'status'=>'valid'), 20);
        foreach ($existing as $approval) {
            if ((int)$approval['approver_id'] === $actor || (string)$approval['approval_role'] === $role) {
                throw new InvalidArgumentException('Release approvals require distinct actors and distinct approval roles.');
            }
        }
        $evidence = array('bundle_uuid'=>$bundleUuid,'approval_role'=>$role,'approver_id'=>$actor,'evidence_ref'=>$evidenceRef,'evidence_hash'=>$evidenceHash,'step_up_at'=>gmdate('Y-m-d H:i:s',$stepUpTimestamp));
        if (true !== apply_filters('slto_verify_release_approval_evidence', false, $evidence, $bundle)) {
            throw new InvalidArgumentException('Release approval evidence could not be independently verified.');
        }
        return $this->tx->run(function() use ($evidence): array {
            $row = $this->repo->insert('release_approvals', array_merge($evidence, array(
                'status'=>'valid','approved_at'=>Database::now(),'row_version'=>1,
            )));
            $this->audit->record('bundle', (string)$evidence['bundle_uuid'], 'release_approval_recorded', 'success', array(
                'approval_role'=>$evidence['approval_role'],'approver_id'=>$evidence['approver_id'],
                'evidence_ref'=>$evidence['evidence_ref'],'evidence_hash'=>$evidence['evidence_hash'],
            ));
            return $row;
        });
    }

    public function assertDualApproval(string $bundleUuid): void
    {
        $rows = $this->repo->list('release_approvals', array('bundle_uuid'=>$bundleUuid,'status'=>'valid'), 20, 0, 'approved_at DESC');
        $roles = array();
        $actors = array();
        foreach ($rows as $row) {
            $roles[(string)$row['approval_role']] = true;
            $actors[(int)$row['approver_id']] = true;
        }
        foreach (self::ROLES as $role) {
            if (empty($roles[$role])) {
                throw new InvalidArgumentException('Independent dual release approval is incomplete: ' . $role);
            }
        }
        if (count($actors) < 2) {
            throw new InvalidArgumentException('Independent dual release approval requires two distinct actors.');
        }
    }
}
