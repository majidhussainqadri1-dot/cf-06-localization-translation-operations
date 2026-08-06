<?php

declare(strict_types=1);

namespace Sabri\Localization\Application;

use InvalidArgumentException;
use Sabri\Localization\Domain\Translation\RiskPolicy;
use Sabri\Localization\Domain\Workflow\StateMachine;
use Sabri\Localization\Infrastructure\Outbox;
use Sabri\Localization\Infrastructure\Repository\AuditRepository;
use Sabri\Localization\Infrastructure\Repository\LocalizationRepository;
use Sabri\Localization\Infrastructure\Transaction;
use Sabri\Localization\Security\Authorization;

final class TranslationService
{
    public function __construct(
        private readonly LocalizationRepository $repo,
        private readonly ResourceService $resources,
        private readonly QaService $qa,
        private readonly AuditRepository $audit,
        private readonly Outbox $outbox,
        private readonly Transaction $tx
    ) {
    }

    public function submit(string $uuid, string $target, int $version, bool $machineDraft = false, ?string $providerJob = null): array
    {
        $unit = $this->repo->find('units', $uuid) ?? throw new InvalidArgumentException('Translation unit not found.');
        $resource = $this->repo->find('resources', (string) $unit['resource_uuid']) ?? throw new InvalidArgumentException('Translation source is unavailable.');
        if (! in_array($unit['status'], array('assigned', 'translating', 'stale'), true)) {
            throw new InvalidArgumentException('Translation unit is not open for submission.');
        }
        if (! $machineDraft) {
            $this->assertAssignedActor($unit, 'translator_id', 'Only the assigned translator may submit this unit.');
        }
        if ((int) $unit['source_version'] !== (int) $resource['source_version'] || ! hash_equals((string) $unit['source_hash'], (string) $resource['source_hash'])) {
            throw new InvalidArgumentException('Translation source is stale and must be reassigned.');
        }
        if ('' === trim($target) || strlen($target) > 500000) {
            throw new InvalidArgumentException('Translation target is empty or exceeds the bounded limit.');
        }

        $result = $this->qa->unit($unit, $resource, $target);
        if (! $result['passed']) {
            throw new InvalidArgumentException('Translation failed automated linguistic QA.');
        }

        return $this->tx->run(function () use ($unit, $resource, $target, $version, $machineDraft, $providerJob, $result): array {
            $secureId = null;
            $stored = $target;
            if (in_array($resource['data_class'], array('C4', 'C5'), true) || 'private' === $resource['risk_class']) {
                $secureId = $this->repo->storeSecurePayload('translation_unit', (string) $unit['uuid'], 'target_text', $target);
                $stored = '[ENCRYPTED RESTRICTED TRANSLATION]';
            }

            $updated = $this->repo->updateVersioned('units', (string) $unit['uuid'], $version, array(
                'target_text' => $stored,
                'secure_payload_id' => $secureId,
                'status' => $machineDraft ? 'translating' : 'linguistic_review',
                'machine_draft' => $machineDraft ? 1 : 0,
                'provider_job_uuid' => $machineDraft ? $providerJob : ($unit['provider_job_uuid'] ?? null),
                'qa_status' => 'passed',
            ));
            if (! empty($unit['secure_payload_id']) && (int) $unit['secure_payload_id'] !== (int) $secureId) {
                $this->repo->retireSecurePayload((int) $unit['secure_payload_id']);
            }

            $this->audit->record('unit', (string) $unit['uuid'], $machineDraft ? 'machine_draft_received' : 'translation_submitted', 'success', array(
                'source_version' => $unit['source_version'],
                'target_hash' => hash('sha256', $target),
                'qa_checks' => count($result['checks']),
            ));
            $this->outbox->enqueue('TranslationSubmitted', 'translation_unit', (string) $unit['uuid'], array(
                'unit_uuid' => $unit['uuid'],
                'locale' => $unit['target_locale'],
                'machine_draft' => $machineDraft,
            ));
            return $updated;
        });
    }

    public function review(string $uuid, string $decision, int $version, string $reviewType, string $reason = ''): array
    {
        $unit = $this->repo->find('units', $uuid) ?? throw new InvalidArgumentException('Translation unit not found.');
        $resource = $this->repo->find('resources', (string) $unit['resource_uuid']) ?? throw new InvalidArgumentException('Translation source is unavailable.');
        if (! in_array($decision, array('approve', 'request_changes', 'reject'), true)) {
            throw new InvalidArgumentException('Review decision is invalid.');
        }
        if ('approve' !== $decision && '' === trim($reason)) {
            throw new InvalidArgumentException('A review reason is required when changes are requested or rejected.');
        }

        $actor = get_current_user_id();
        $from = (string) $unit['status'];
        if ('linguistic' === $reviewType) {
            if ('linguistic_review' !== $from) {
                throw new InvalidArgumentException('Unit is not awaiting linguistic review.');
            }
            $this->assertAssignedActor($unit, 'linguistic_reviewer_id', 'Only the assigned linguistic reviewer may review this unit.');
            $to = 'approve' === $decision
                ? (RiskPolicy::requiresDomainReview((string) $resource['risk_class'], (string) $resource['domain_name']) ? 'domain_review' : 'approved')
                : 'translating';
        } elseif ('domain' === $reviewType) {
            if ('domain_review' !== $from) {
                throw new InvalidArgumentException('Unit is not awaiting domain review.');
            }
            $this->assertAssignedActor($unit, 'domain_reviewer_id', 'Only the assigned domain reviewer may review this unit.');
            $to = 'approve' === $decision ? 'approved' : 'translating';
        } else {
            throw new InvalidArgumentException('Review type is invalid.');
        }

        if ($actor === (int) $unit['translator_id']) {
            throw new InvalidArgumentException('A translator cannot review their own translation.');
        }
        if ('domain' === $reviewType && $actor === (int) $unit['linguistic_reviewer_id']) {
            throw new InvalidArgumentException('High-risk domain review requires an independent reviewer.');
        }
        StateMachine::assert('unit', $from, $to);

        return $this->tx->run(function () use ($unit, $resource, $version, $from, $to, $decision, $reviewType, $reason, $actor): array {
            $changes = array('status' => $to, 'machine_draft' => 0);
            $changes['linguistic' === $reviewType ? 'linguistic_reviewer_id' : 'domain_reviewer_id'] = $actor;
            $updated = $this->repo->updateVersioned('units', (string) $unit['uuid'], $version, $changes);

            $event = 'approved' === $to ? 'TranslationApproved' : 'TranslationRejected';
            $this->audit->record('unit', (string) $unit['uuid'], 'translation_' . $reviewType . '_review', 'success', array(
                'decision' => $decision,
                'from' => $from,
                'to' => $to,
                'reason' => $reason,
            ));
            $this->outbox->enqueue($event, 'translation_unit', (string) $unit['uuid'], array(
                'unit_uuid' => $unit['uuid'],
                'locale' => $unit['target_locale'],
                'decision' => $decision,
                'review_type' => $reviewType,
            ));
            if ('approved' === $to) {
                $this->addMemory($updated, $resource);
            }
            return $updated;
        });
    }

    public function comment(string $unitUuid, string $text, string $audience = 'internal', ?string $parent = null): array
    {
        $unit = $this->repo->find('units', $unitUuid) ?? throw new InvalidArgumentException('Translation unit not found.');
        $resource = $this->repo->find('resources', (string)$unit['resource_uuid']) ?? throw new InvalidArgumentException('Translation source is unavailable.');
        $actor = get_current_user_id();
        $assigned = array_map('intval', array_filter(array($unit['translator_id'], $unit['linguistic_reviewer_id'], $unit['domain_reviewer_id'])));
        if (! in_array($actor, $assigned, true) && ! Authorization::allowed('manage', array('object'=>'unit','object_uuid'=>$unitUuid,'record_version'=>(int)$unit['row_version']))) {
            throw new InvalidArgumentException('Only assigned translation participants may comment.');
        }
        $audience = sanitize_key($audience);
        if (! in_array($audience, array('internal','vendor','domain_owner'), true)) {
            throw new InvalidArgumentException('Comment audience is invalid.');
        }
        $text = sanitize_textarea_field($text);
        if ('' === $text || strlen($text) > 10000) {
            throw new InvalidArgumentException('Comment is empty or too long.');
        }
        if ('vendor' === $audience) {
            if (in_array((string)$resource['data_class'], array('C3','C4','C5'), true)
                || RiskPolicy::requiresDomainReview((string)$resource['risk_class'], (string)$resource['domain_name'])) {
                throw new InvalidArgumentException('Vendor comments are denied for protected or high-risk translation units.');
            }
            $redacted = \Sabri\Localization\Domain\Translation\Redactor::redact($text);
            if (array_sum(array_map('intval', $redacted['counts'])) > 0 || ! hash_equals($text, (string)$redacted['text'])) {
                throw new InvalidArgumentException('Sensitive data is not allowed in vendor comments.');
            }
        }
        if (null !== $parent) {
            $parentRow = $this->repo->find('comments', $parent);
            if (! is_array($parentRow) || (string)$parentRow['unit_uuid'] !== $unitUuid) {
                throw new InvalidArgumentException('Comment parent is invalid.');
            }
        }
        return $this->tx->run(function() use ($unitUuid,$parent,$actor,$audience,$text): array {
            $row = $this->repo->insert('comments', array(
                'unit_uuid'=>$unitUuid,'parent_uuid'=>$parent,'author_id'=>$actor,'audience'=>$audience,
                'comment_text'=>$text,'status'=>'open','row_version'=>1,
            ));
            $this->audit->record('unit',$unitUuid,'translation_comment_added','success',array('comment_uuid'=>$row['uuid'],'audience'=>$audience));
            return $row;
        });
    }

    public function targetText(array $unit): string
    {
        if (! empty($unit['secure_payload_id'])) {
            return $this->repo->readSecurePayload((int) $unit['secure_payload_id'], (string) $unit['uuid'], 'target_text');
        }
        return (string) $unit['target_text'];
    }

    private function addMemory(array $unit, array $resource): void
    {
        if (! in_array((string) $resource['data_class'], array('C1', 'C2'), true)
            || in_array((string) $resource['risk_class'], array('high', 'critical', 'private'), true)
            || RiskPolicy::requiresDomainReview((string) $resource['risk_class'], (string) $resource['domain_name'])) {
            return;
        }
        $source = $this->resources->text($resource);
        $target = $this->targetText($unit);
        $this->repo->insert('memory', array(
            'source_locale' => $resource['source_locale'],
            'target_locale' => $unit['target_locale'],
            'source_segment' => $source,
            'target_segment' => $target,
            'source_hash' => hash('sha256', $source),
            'context_hash' => hash('sha256', (string) $resource['context']),
            'domain_name' => $resource['domain_name'],
            'risk_class' => $resource['risk_class'],
            'provenance_json' => wp_json_encode(array('resource_uuid' => $resource['uuid'], 'unit_uuid' => $unit['uuid'], 'source_version' => $resource['source_version'])),
            'license_code' => 'platform-approved',
            'status' => 'approved',
            'created_from_unit_uuid' => $unit['uuid'],
        ));
    }

    private function assertAssignedActor(array $unit, string $column, string $message): void
    {
        $assigned = (int) ($unit[$column] ?? 0);
        $actor = get_current_user_id();
        if ($assigned <= 0 || $actor !== $assigned) {
            throw new InvalidArgumentException($message);
        }
        if (! function_exists('smc_membership_assertions')) {
            throw new InvalidArgumentException('File 00 membership assertions are unavailable.');
        }
        $assertions = smc_membership_assertions($actor);
        $state = is_array($assertions) ? strtolower((string)($assertions['state'] ?? '')) : '';
        if (! is_array($assertions) || ! empty($assertions['suspended']) || ! in_array($state, array('approved','active','verified'), true)) {
            throw new InvalidArgumentException('Assigned translation actor is no longer eligible.');
        }
        $role = match ($column) {
            'translator_id' => 'translator',
            'linguistic_reviewer_id' => 'linguistic_reviewer',
            'domain_reviewer_id' => 'domain_reviewer',
            default => '',
        };
        $valid = false;
        foreach ($this->repo->list('assignments', array('unit_uuid'=>$unit['uuid'], 'assignee_id'=>$actor, 'assignment_role'=>$role, 'status'=>'active'), 10) as $assignment) {
            if ((empty($assignment['expires_at']) || strtotime((string)$assignment['expires_at']) >= time())
                && in_array((string)$assignment['conflict_status'], array('clear','disclosed-cleared'), true)) {
                $valid = true;
                break;
            }
        }
        if (! $valid) {
            throw new InvalidArgumentException('The assigned translation role is inactive, conflicted or expired.');
        }
    }
}
