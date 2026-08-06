<?php

declare(strict_types=1);

namespace Sabri\Localization\Application;

use InvalidArgumentException;
use RuntimeException;
use Sabri\Localization\Domain\Bundle\DeterministicBundle;
use Sabri\Localization\Domain\Workflow\StateMachine;
use Sabri\Localization\Infrastructure\Database;
use Sabri\Localization\Infrastructure\Outbox;
use Sabri\Localization\Infrastructure\Repository\AuditRepository;
use Sabri\Localization\Infrastructure\Repository\LocalizationRepository;
use Sabri\Localization\Infrastructure\Transaction;

final class BundleService
{
    private const HUMAN_QA = array('in_context_qa','accessibility','rtl_ltr','links','browser','performance','security');

    public function __construct(
        private readonly LocalizationRepository $repo,
        private readonly TranslationService $translations,
        private readonly QaService $qa,
        private readonly AuditRepository $audit,
        private readonly Outbox $outbox,
        private readonly Transaction $tx,
        private readonly IntegrationService $integrations,
        private readonly ReleaseApprovalService $releaseApprovals
    ) {}

    public function build(string $locale): array
    {
        global $wpdb;
        $localeRecord = $this->repo->findOne('locales','locale_tag',$locale) ?? throw new InvalidArgumentException('Bundle locale is not registered.');
        if (! in_array((string)$localeRecord['status'], array('content_ready','enabled','degraded'), true)) {
            throw new InvalidArgumentException('Bundle locale has not reached content-ready status.');
        }
        $rows = $this->repo->releasedItems($locale);
        $items = array();
        $sources = array();
        foreach ($rows as $row) {
            $items[(string)$row['resource_key']] = array(
                'text'=>$this->translations->targetText($row),'source_hash'=>$row['source_hash'],
                'source_version'=>(int)$row['source_version'],'unit_uuid'=>$row['uuid'],
            );
            $sources[] = array('key'=>$row['resource_key'],'source_hash'=>$row['source_hash'],'source_version'=>(int)$row['source_version'],'unit_uuid'=>$row['uuid']);
        }
        $coverage = $this->repo->coverage($locale);
        $qa = $this->qa->bundle($locale,$items,$coverage);
        if (! $qa['passed']) {
            throw new InvalidArgumentException('Locale bundle failed critical coverage or QA gates.');
        }

        $lockName = $wpdb->prefix . 'slto_bundle_version_' . hash('sha256',$locale);
        $locked = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,10)', $lockName));
        if ('' !== (string)$wpdb->last_error || 1 !== (int)$locked) {
            throw new RuntimeException('Locale bundle version lock is unavailable.');
        }
        $primaryError = null;
        try {
            $version = $this->repo->nextBundleVersion($locale);
            $built = DeterministicBundle::build($locale,$items,array(
                'bundle_version'=>$version,'direction'=>$localeRecord['direction'],'fallback'=>$localeRecord['fallback_tag'],
                'coverage'=>$coverage,'format_data_version'=>$localeRecord['format_data_version'],
                'plural_rules_version'=>$localeRecord['plural_rules_version'],'signing_key_id'=>DeterministicBundle::signingKeyId(),
            ));
            $signature = DeterministicBundle::sign($built['sha256']);
            if (null === $signature) {
                throw new RuntimeException('Locale bundle signing key is unavailable.');
            }
            return $this->tx->run(function() use ($locale,$version,$built,$signature,$sources,$coverage,$qa): array {
                $metadataJson = wp_json_encode($built['payload']['metadata'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
                $sourcesJson = wp_json_encode($sources, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
                if (! is_string($metadataJson) || ! is_string($sourcesJson)) {
                    throw new RuntimeException('Locale bundle evidence could not be encoded.');
                }
                $previous = $this->repo->activeBundle($locale);
                $bundle = $this->repo->insert('bundles',array(
                    'locale_tag'=>$locale,'bundle_version'=>$version,'manifest_json'=>$metadataJson,'payload_json'=>$built['json'],
                    'source_list_json'=>$sourcesJson,'coverage'=>$coverage['coverage'],'critical_coverage'=>$coverage['critical_coverage'],
                    'bundle_hash'=>$built['sha256'],'signature'=>$signature,'status'=>'built','previous_bundle_uuid'=>$previous['uuid']??null,
                    'approved_by'=>null,'activated_by'=>null,'activated_at'=>null,'row_version'=>1,
                ));
                foreach ($qa['checks'] as $check) {
                    $details = wp_json_encode($check['details'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
                    if (! is_string($details)) { throw new RuntimeException('Bundle QA evidence could not be encoded.'); }
                    $this->repo->insert('qa_results',array(
                        'target_type'=>'bundle','target_uuid'=>$bundle['uuid'],'rule_code'=>$check['rule'],'result'=>$check['result'],
                        'severity'=>$check['severity'],'details_json'=>$details,'reviewer_id'=>get_current_user_id(),'fixed_at'=>null,
                    ));
                }
                $this->audit->record('bundle',(string)$bundle['uuid'],'locale_bundle_built','success',array(
                    'locale'=>$locale,'version'=>$version,'hash'=>$built['sha256'],'coverage'=>$coverage,'signed'=>true,
                ));
                $this->outbox->enqueue('LocaleBundleBuilt','bundle',(string)$bundle['uuid'],array(
                    'locale'=>$locale,'version'=>$version,'hash'=>$built['sha256'],'coverage'=>$coverage,
                ));
                return $bundle;
            });
        } catch (\Throwable $e) {
            $primaryError = $e;
            throw $e;
        } finally {
            $released = $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lockName));
            if (null === $primaryError && ('' !== (string)$wpdb->last_error || 1 !== (int)$released)) {
                throw new RuntimeException('Locale bundle version lock could not be released.');
            }
        }
    }

    public function recordQa(string $uuid,string $rule,string $result,string $severity,array $details=[]): array
    {
        if (! $this->repo->find('bundles',$uuid)) { throw new InvalidArgumentException('Locale bundle not found.'); }
        $rule = sanitize_key($rule);
        if (! in_array($rule,self::HUMAN_QA,true)) { throw new InvalidArgumentException('Bundle QA rule is invalid.'); }
        if (! in_array($result,array('pass','fail'),true) || ! in_array($severity,array('medium','high','critical'),true)) {
            throw new InvalidArgumentException('Bundle QA result is invalid.');
        }
        $encoded = wp_json_encode($details,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if (! is_string($encoded) || strlen($encoded)>262144) { throw new InvalidArgumentException('Bundle QA details are invalid or oversized.'); }
        return $this->tx->run(function() use ($uuid,$rule,$result,$severity,$encoded): array {
            $record = $this->repo->insert('qa_results',array(
                'target_type'=>'bundle','target_uuid'=>$uuid,'rule_code'=>$rule,'result'=>$result,'severity'=>$severity,
                'details_json'=>$encoded,'reviewer_id'=>get_current_user_id(),'fixed_at'=>null,
            ));
            $this->audit->record('bundle',$uuid,'bundle_qa_recorded','success',array(
                'rule'=>$rule,'result'=>$result,'severity'=>$severity,'qa_uuid'=>$record['uuid']??null,
            ));
            return $record;
        });
    }

    public function transition(string $uuid,string $to,int $version,string $reason=''): array
    {
        $bundle = $this->repo->find('bundles',$uuid) ?? throw new InvalidArgumentException('Locale bundle not found.');
        if (in_array($to,array('active','rolled_back','superseded'),true)) {
            throw new InvalidArgumentException('Bundle activation and rollback require their dedicated commands.');
        }
        StateMachine::assert('bundle',(string)$bundle['status'],$to);
        if (in_array($to,array('staged','canary'),true) && empty($bundle['signature'])) {
            throw new InvalidArgumentException('Signed locale bundle is required for release.');
        }
        $results = $this->repo->list('qa_results',array('target_type'=>'bundle','target_uuid'=>$uuid),500);
        if ('automated_qa' === $to && (empty($results) || in_array('fail',array_column($results,'result'),true))) {
            throw new InvalidArgumentException('Automated bundle QA evidence is incomplete or failed.');
        }
        if ('approved' === $to) {
            foreach (self::HUMAN_QA as $rule) {
                $passed = false;
                foreach ($results as $result) {
                    if ($result['rule_code']===$rule && $result['result']==='pass') { $passed=true; break; }
                }
                if (! $passed) { throw new InvalidArgumentException('Required in-context bundle QA evidence is missing: '.$rule); }
            }
        }
        return $this->tx->run(function() use ($bundle,$uuid,$to,$version,$reason): array {
            $changes = array('status'=>$to);
            if ('approved' === $to) { $changes['approved_by']=get_current_user_id(); }
            $updated = $this->repo->updateVersioned('bundles',$uuid,$version,$changes);
            $this->audit->record('bundle',$uuid,'bundle_transition','success',array('from'=>$bundle['status'],'to'=>$to,'reason'=>$reason));
            return $updated;
        });
    }

    public function activate(string $uuid,int $version): array
    {
        global $wpdb;
        $bundle = $this->repo->find('bundles',$uuid) ?? throw new InvalidArgumentException('Locale bundle not found.');
        if (! in_array($bundle['status'],array('staged','canary'),true)) {
            throw new InvalidArgumentException('Bundle must complete staged or canary release before activation.');
        }
        if ((float)$bundle['critical_coverage']<100.0 || ! DeterministicBundle::verify((string)$bundle['bundle_hash'],(string)$bundle['signature'])) {
            throw new InvalidArgumentException('Bundle signature or critical coverage gate failed.');
        }
        $this->integrations->assertReady();
        $this->releaseApprovals->assertDualApproval($uuid);
        $sourceList = $this->decodeSourceList($bundle);
        $unitUuids = array_column($sourceList,'unit_uuid');
        if (empty($unitUuids)) { throw new InvalidArgumentException('Bundle source list is empty.'); }

        $updated = $this->tx->run(function() use ($wpdb,$bundle,$version,$unitUuids): array {
            $previous = $this->repo->activeBundle((string)$bundle['locale_tag']);
            if (is_array($previous)) {
                $this->repo->updateVersioned('bundles',(string)$previous['uuid'],(int)$previous['row_version'],array('status'=>'superseded'));
            }
            $updated = $this->repo->updateVersioned('bundles',(string)$bundle['uuid'],$version,array(
                'status'=>'active','previous_bundle_uuid'=>$previous['uuid']??null,'activated_by'=>get_current_user_id(),'activated_at'=>Database::now(),
            ));
            $units = Database::table('units');
            $placeholders = implode(',',array_fill(0,count($unitUuids),'%s'));
            $sql = $wpdb->prepare("UPDATE {$units} SET status='released',released_bundle_uuid=%s,row_version=row_version+1,updated_at=%s WHERE uuid IN ({$placeholders}) AND target_locale=%s AND status='approved'", $bundle['uuid'],Database::now(),...array_merge($unitUuids,array($bundle['locale_tag'])));
            $released = $wpdb->query($sql);
            if (false === $released || (int)$released !== count($unitUuids)) {
                throw new RuntimeException('Exact approved translation set could not be released.');
            }
            $this->audit->record('bundle',(string)$bundle['uuid'],'locale_bundle_activated','success',array(
                'locale'=>$bundle['locale_tag'],'version'=>$bundle['bundle_version'],'previous'=>$previous['uuid']??null,'released_units'=>count($unitUuids),
            ));
            $this->outbox->enqueue('LocaleBundleActivated','bundle',(string)$bundle['uuid'],array(
                'locale'=>$bundle['locale_tag'],'version'=>(int)$bundle['bundle_version'],'hash'=>$bundle['bundle_hash'],'previous'=>$previous['uuid']??null,
            ));
            return $updated;
        });
        wp_cache_flush();
        return $updated;
    }

    public function rollback(string $activeUuid,string $targetUuid,int $activeVersion): array
    {
        global $wpdb;
        $active = $this->repo->find('bundles',$activeUuid) ?? throw new InvalidArgumentException('Active bundle not found.');
        $target = $this->repo->find('bundles',$targetUuid) ?? throw new InvalidArgumentException('Rollback bundle not found.');
        if ('active'!==$active['status'] || $active['locale_tag']!==$target['locale_tag'] || !in_array($target['status'],array('superseded','rolled_back'),true)) {
            throw new InvalidArgumentException('Rollback pair is invalid.');
        }
        if (! DeterministicBundle::verify((string)$target['bundle_hash'],(string)$target['signature'])) {
            throw new InvalidArgumentException('Rollback bundle signature failed.');
        }
        $activeUnits = array_column($this->decodeSourceList($active),'unit_uuid');
        $targetUnits = array_column($this->decodeSourceList($target),'unit_uuid');
        if (empty($targetUnits)) { throw new InvalidArgumentException('Rollback bundle source evidence is empty.'); }

        $result = $this->tx->run(function() use ($wpdb,$active,$target,$activeVersion,$activeUnits,$targetUnits): array {
            $rolled = $this->repo->updateVersioned('bundles',(string)$active['uuid'],$activeVersion,array('status'=>'rolled_back'));
            $newActive = $this->repo->updateVersioned('bundles',(string)$target['uuid'],(int)$target['row_version'],array(
                'status'=>'active','activated_by'=>get_current_user_id(),'activated_at'=>Database::now(),
            ));
            $units = Database::table('units');
            if (! empty($activeUnits)) {
                $ph = implode(',',array_fill(0,count($activeUnits),'%s'));
                $reverted = $wpdb->query($wpdb->prepare("UPDATE {$units} SET status='approved',released_bundle_uuid=NULL,row_version=row_version+1,updated_at=%s WHERE uuid IN ({$ph}) AND released_bundle_uuid=%s", Database::now(),...array_merge($activeUnits,array($active['uuid']))));
                if (false === $reverted) { throw new RuntimeException('Active bundle units could not be reconciled during rollback.'); }
            }
            $ph = implode(',',array_fill(0,count($targetUnits),'%s'));
            $activated = $wpdb->query($wpdb->prepare("UPDATE {$units} SET status='released',released_bundle_uuid=%s,row_version=row_version+1,updated_at=%s WHERE uuid IN ({$ph}) AND status IN ('approved','released')", $target['uuid'],Database::now(),...$targetUnits));
            if (false === $activated || (int)$activated !== count($targetUnits)) {
                throw new RuntimeException('Rollback bundle units could not be restored exactly.');
            }
            $this->audit->record('bundle',(string)$active['uuid'],'locale_bundle_rolled_back','success',array(
                'target'=>$target['uuid'],'locale'=>$active['locale_tag'],'restored_units'=>count($targetUnits),
            ));
            $this->outbox->enqueue('LocaleBundleRolledBack','bundle',(string)$active['uuid'],array(
                'locale'=>$active['locale_tag'],'from'=>$active['uuid'],'to'=>$target['uuid'],
            ));
            return array('rolled_back'=>$rolled,'active'=>$newActive);
        });
        wp_cache_flush();
        return $result;
    }

    public function publicBundle(string $locale): ?array
    {
        $bundle = $this->repo->activeBundle($locale);
        if (! is_array($bundle)) { return null; }
        $payload = (string)$bundle['payload_json'];
        if (! hash_equals((string)$bundle['bundle_hash'],hash('sha256',$payload))
            || ! DeterministicBundle::verify((string)$bundle['bundle_hash'],(string)$bundle['signature'])) {
            throw new RuntimeException('Active locale bundle integrity verification failed.');
        }
        $decoded = json_decode($payload,true,128,JSON_THROW_ON_ERROR);
        return array('locale'=>$locale,'version'=>(int)$bundle['bundle_version'],'hash'=>$bundle['bundle_hash'],'signature'=>$bundle['signature'],'payload'=>$decoded);
    }

    private function decodeSourceList(array $bundle): array
    {
        $sources = json_decode((string)$bundle['source_list_json'],true,128,JSON_THROW_ON_ERROR);
        if (! is_array($sources)) { throw new InvalidArgumentException('Bundle source evidence is invalid.'); }
        foreach ($sources as $source) {
            if (! is_array($source) || 1!==preg_match('/^[a-f0-9-]{36}$/D',(string)($source['unit_uuid']??''))
                || 1!==preg_match('/^[a-f0-9]{64}$/D',(string)($source['source_hash']??'')) || (int)($source['source_version']??0)<=0) {
                throw new InvalidArgumentException('Bundle source evidence contains an invalid unit.');
            }
        }
        return $sources;
    }
}
