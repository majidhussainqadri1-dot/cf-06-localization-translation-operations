<?php

declare(strict_types=1);

namespace Sabri\Localization\Application;

use InvalidArgumentException;
use Sabri\Localization\Domain\Translation\PlaceholderValidator;
use Sabri\Localization\Domain\Translation\Redactor;
use Sabri\Localization\Domain\Translation\RiskPolicy;
use Sabri\Localization\Domain\Workflow\StateMachine;
use Sabri\Localization\Infrastructure\Outbox;
use Sabri\Localization\Infrastructure\Repository\AuditRepository;
use Sabri\Localization\Infrastructure\Repository\LocalizationRepository;
use Sabri\Localization\Infrastructure\Transaction;
use Sabri\Localization\Provider\MachineTranslationProvider;
use Throwable;

final class MachineTranslationService
{
    public function __construct(
        private readonly LocalizationRepository $repo,
        private readonly ResourceService $resources,
        private readonly TranslationService $translations,
        private readonly AuditRepository $audit,
        private readonly Outbox $outbox,
        private readonly Transaction $tx,
        private readonly MachineTranslationProvider $provider
    ) {
    }

    public function prepare(array $unitUuids, string $purpose = 'draft_translation', bool $explicitHighRiskApproval = false): array
    {
        $unitUuids = array_values(array_unique(array_filter(array_map('strval', $unitUuids))));
        if (empty($unitUuids) || count($unitUuids) > 100) {
            throw new InvalidArgumentException('Vendor job requires 1–100 units.');
        }

        $payload = array();
        $summary = array();
        foreach ($unitUuids as $uuid) {
            $unit = $this->repo->find('units', $uuid) ?? throw new InvalidArgumentException('Vendor unit is unavailable.');
            if (! in_array((string) $unit['status'], array('assigned', 'translating', 'stale'), true)) {
                throw new InvalidArgumentException('Vendor unit is not open for a machine draft.');
            }

            $resource = $this->repo->find('resources', (string) $unit['resource_uuid']) ?? throw new InvalidArgumentException('Vendor source is unavailable.');
            if (! RiskPolicy::machineTranslationAllowed(
                (string) $resource['risk_class'],
                (string) $resource['data_class'],
                (string) $resource['domain_name'],
                $explicitHighRiskApproval
            )) {
                throw new InvalidArgumentException('Machine translation is denied for this risk/data/domain class.');
            }

            $source = $this->resources->text($resource);
            $redacted = Redactor::redact($source);
            $schema = json_decode((string) $resource['placeholders'], true) ?: array();
            PlaceholderValidator::assertSource($redacted['text'], $schema);

            $payload[] = array(
                'unit_uuid' => $uuid,
                'source_locale' => $resource['source_locale'],
                'target_locale' => $unit['target_locale'],
                'source_text' => $redacted['text'],
                'placeholders' => $schema,
                'domain' => $resource['domain_name'],
                'risk' => $resource['risk_class'],
            );
            $summary[$uuid] = $redacted['counts'];
        }

        $uuid = \Sabri\Localization\Infrastructure\Database::uuid();
        $hash = hash('sha256', wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $job = $this->repo->insert('vendor_jobs', array(
            'uuid' => $uuid,
            'provider_key' => $this->provider->key(),
            'model_version' => 'configured-at-send',
            'region_code' => '',
            'purpose' => $purpose,
            'project_uuid' => null,
            'unit_uuids' => wp_json_encode($unitUuids),
            'outbound_hash' => $hash,
            'inbound_hash' => null,
            'redaction_summary' => wp_json_encode($summary),
            'provider_reference' => null,
            'status' => 'prepared',
            'deletion_evidence' => null,
            'row_version' => 1,
            'created_by' => get_current_user_id(),
            'purge_due_at' => gmdate('Y-m-d H:i:s', time() + 7 * DAY_IN_SECONDS),
        ));
        $this->audit->record('vendor_job', $uuid, 'vendor_job_prepared', 'success', array(
            'provider' => $this->provider->key(),
            'unit_count' => count($unitUuids),
            'outbound_hash' => $hash,
            'redaction_summary_hash' => hash('sha256', wp_json_encode($summary)),
        ));

        return array('job' => $job, 'payload' => $payload);
    }

    public function send(string $jobUuid, array $payload, int $version): array
    {
        $job = $this->repo->find('vendor_jobs', $jobUuid) ?? throw new InvalidArgumentException('Vendor job not found.');
        StateMachine::assert('vendor_job', (string) $job['status'], 'sent');

        $requestHash = hash('sha256', wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        if (! hash_equals((string) $job['outbound_hash'], $requestHash)) {
            throw new InvalidArgumentException('Vendor payload hash mismatch.');
        }

        // Persist "sent" before external I/O so an outage leaves a truthful,
        // reconcilable state rather than an ambiguous prepared job.
        $sent = $this->repo->updateVersioned('vendor_jobs', $jobUuid, $version, array('status' => 'sent'));
        $this->audit->record('vendor_job', $jobUuid, 'vendor_job_sent', 'success', array(
            'provider' => $sent['provider_key'],
            'outbound_hash' => $sent['outbound_hash'],
        ));

        try {
            $response = $this->provider->submit($sent, $payload);
            $results = is_array($response['translations'] ?? null) ? $response['translations'] : array();
            $this->assertResponse($payload, $results);

            return $this->tx->run(function () use ($sent, $response, $results): array {
                StateMachine::assert('vendor_job', 'sent', 'received');
                $received = $this->repo->updateVersioned('vendor_jobs', (string) $sent['uuid'], (int) $sent['row_version'], array(
                    'status' => 'received',
                    'provider_reference' => sanitize_text_field((string) ($response['reference'] ?? '')),
                    'model_version' => sanitize_text_field((string) ($response['model_version'] ?? 'unknown')),
                    'region_code' => sanitize_text_field((string) ($response['region'] ?? '')),
                    'inbound_hash' => hash('sha256', wp_json_encode($results, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                ));

                StateMachine::assert('vendor_job', 'received', 'validated');
                $validated = $this->repo->updateVersioned('vendor_jobs', (string) $sent['uuid'], (int) $received['row_version'], array('status' => 'validated'));

                foreach ($results as $item) {
                    $unitUuid = (string) $item['unit_uuid'];
                    $target = (string) $item['target_text'];
                    $unit = $this->repo->find('units', $unitUuid) ?? throw new InvalidArgumentException('Vendor returned an unknown unit.');
                    $this->translations->submit($unitUuid, $target, (int) $unit['row_version'], true, (string) $sent['uuid']);
                }

                $this->audit->record('vendor_job', (string) $sent['uuid'], 'vendor_job_received', 'success', array(
                    'result_count' => count($results),
                    'inbound_hash' => $validated['inbound_hash'],
                    'draft_only' => true,
                    'human_review_required' => true,
                ));
                $this->outbox->enqueue('MachineTranslationDraftReceived', 'vendor_job', (string) $sent['uuid'], array(
                    'unit_count' => count($results),
                    'human_review_required' => true,
                ));

                return $validated;
            });
        } catch (Throwable $throwable) {
            // Import transaction rollback leaves the durable state at "sent".
            // Record a bounded failure without masking the original exception.
            $current = $this->repo->find('vendor_jobs', $jobUuid);
            if (is_array($current) && 'sent' === (string) $current['status']) {
                try {
                    StateMachine::assert('vendor_job', 'sent', 'failed');
                    $this->repo->updateVersioned('vendor_jobs', $jobUuid, (int) $current['row_version'], array('status' => 'failed'));
                    $this->audit->record('vendor_job', $jobUuid, 'vendor_job_failed', 'failed', array(
                        'exception_class' => get_class($throwable),
                        'message_hash' => hash('sha256', $throwable->getMessage()),
                    ));
                } catch (Throwable) {
                    // Preserve the primary provider/import failure.
                }
            }
            throw $throwable;
        }
    }

    public function review(string $jobUuid, string $decision, int $version, string $reason = ''): array
    {
        $job = $this->repo->find('vendor_jobs', $jobUuid) ?? throw new InvalidArgumentException('Vendor job not found.');
        if ('validated' !== (string) $job['status']) {
            throw new InvalidArgumentException('Vendor job is not awaiting human review.');
        }
        if (! in_array($decision, array('accept', 'reject'), true)) {
            throw new InvalidArgumentException('Vendor job review decision is invalid.');
        }
        $unitUuids = json_decode((string) $job['unit_uuids'], true);
        if (! is_array($unitUuids) || empty($unitUuids)) {
            throw new InvalidArgumentException('Vendor job unit evidence is invalid.');
        }
        if ('accept' === $decision) {
            foreach ($unitUuids as $unitUuid) {
                $unit = $this->repo->find('units', (string) $unitUuid) ?? throw new InvalidArgumentException('Vendor job unit is unavailable.');
                if ((string) ($unit['provider_job_uuid'] ?? '') !== $jobUuid || 1 === (int) ($unit['machine_draft'] ?? 0)) {
                    throw new InvalidArgumentException('Every machine draft must be human-edited before provider-job acceptance.');
                }
                if (! in_array((string) $unit['status'], array('linguistic_review','domain_review','approved','released'), true)) {
                    throw new InvalidArgumentException('Every machine draft must enter the human review workflow before provider-job acceptance.');
                }
            }
        }

        return $this->tx->run(function () use ($job, $decision, $version, $reason): array {
            if ('reject' === $decision) {
                StateMachine::assert('vendor_job', 'validated', 'rejected');
                $updated = $this->repo->updateVersioned('vendor_jobs', (string) $job['uuid'], $version, array('status'=>'rejected'));
            } else {
                StateMachine::assert('vendor_job', 'validated', 'human_reviewed');
                $reviewed = $this->repo->updateVersioned('vendor_jobs', (string) $job['uuid'], $version, array('status'=>'human_reviewed'));
                StateMachine::assert('vendor_job', 'human_reviewed', 'accepted');
                $updated = $this->repo->updateVersioned('vendor_jobs', (string) $job['uuid'], (int) $reviewed['row_version'], array('status'=>'accepted'));
            }
            $this->audit->record('vendor_job', (string) $job['uuid'], 'vendor_job_human_reviewed', 'success', array(
                'decision'=>$decision,
                'reason'=>$reason,
                'reviewer_id'=>get_current_user_id(),
            ));
            $this->outbox->enqueue('MachineTranslationJobReviewed', 'vendor_job', (string) $job['uuid'], array(
                'decision'=>$decision,
                'human_reviewed'=>true,
            ));
            return $updated;
        });
    }

    public function purge(string $jobUuid, int $version): array
    {
        $job = $this->repo->find('vendor_jobs', $jobUuid) ?? throw new InvalidArgumentException('Vendor job not found.');
        if (! in_array($job['status'], array('accepted', 'rejected', 'failed', 'human_reviewed'), true)) {
            throw new InvalidArgumentException('Vendor job is not eligible for purge.');
        }
        if ('human_reviewed' === $job['status']) {
            StateMachine::assert('vendor_job', 'human_reviewed', 'rejected');
            $job = $this->repo->updateVersioned('vendor_jobs', $jobUuid, $version, array('status' => 'rejected'));
            $version = (int) $job['row_version'];
        }
        StateMachine::assert('vendor_job', (string) $job['status'], 'purged');
        $evidence = $this->provider->purge((string) $job['provider_reference']);
        $updated = $this->repo->updateVersioned('vendor_jobs', $jobUuid, $version, array(
            'status' => 'purged',
            'deletion_evidence' => wp_json_encode($evidence),
        ));
        $this->audit->record('vendor_job', $jobUuid, 'vendor_job_purged', 'success', array(
            'provider' => $job['provider_key'],
            'evidence_hash' => hash('sha256', wp_json_encode($evidence)),
        ));
        $this->outbox->enqueue('TranslationVendorJobPurged', 'vendor_job', $jobUuid, array(
            'provider' => $job['provider_key'],
            'purged_at' => gmdate(DATE_ATOM),
        ));
        return $updated;
    }

    private function assertResponse(array $payload, array $results): void
    {
        if (count($results) !== count($payload)) {
            throw new InvalidArgumentException('Vendor result cardinality mismatch.');
        }

        $expected = array_fill_keys(array_map(static fn (array $item): string => (string) ($item['unit_uuid'] ?? ''), $payload), true);
        $seen = array();
        foreach ($results as $item) {
            if (! is_array($item)) {
                throw new InvalidArgumentException('Vendor result item is invalid.');
            }
            $unitUuid = (string) ($item['unit_uuid'] ?? '');
            $target = (string) ($item['target_text'] ?? '');
            if ('' === $unitUuid || ! isset($expected[$unitUuid]) || isset($seen[$unitUuid])) {
                throw new InvalidArgumentException('Vendor result unit mapping is invalid.');
            }
            if ('' === trim($target) || strlen($target) > 500000) {
                throw new InvalidArgumentException('Vendor result text is empty or exceeds the bounded limit.');
            }
            $seen[$unitUuid] = true;
        }
    }
}
