<?php

declare(strict_types=1);

namespace Sabri\Localization\Application;

use InvalidArgumentException;
use RuntimeException;
use Sabri\Localization\Domain\Bundle\BundleFreshnessGuard;
use Sabri\Localization\Domain\Bundle\DeterministicBundle;
use Sabri\Localization\Domain\Workflow\StateMachine;
use Sabri\Localization\Infrastructure\Database;
use Sabri\Localization\Infrastructure\Outbox;
use Sabri\Localization\Infrastructure\Repository\AuditRepository;
use Sabri\Localization\Infrastructure\Repository\LocalizationRepository;
use Sabri\Localization\Infrastructure\Transaction;

final class BundleService
{
    private const AUTOMATED_QA = array('bundle_nonempty','critical_coverage','unique_keys');
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
        $localeRecord=$this->repo->findOne('locales','locale_tag',$locale)??throw new InvalidArgumentException('Bundle locale is not registered.');
        if(!in_array((string)$localeRecord['status'],array('content_ready','enabled','degraded'),true)){throw new InvalidArgumentException('Bundle locale has not reached content-ready status.');}
        $rows=$this->repo->releasedItems($locale);$items=[];$sources=[];$seenUnits=[];
        foreach($rows as $row){
            $resourceKey=(string)$row['resource_key'];$unitUuid=(string)$row['uuid'];
            if(''===$resourceKey||isset($items[$resourceKey])||''===$unitUuid||isset($seenUnits[$unitUuid])){
                throw new InvalidArgumentException('Locale bundle build found duplicated or invalid resource/unit identity.');
            }
            $seenUnits[$unitUuid]=true;
            $items[$resourceKey]=array('text'=>$this->translations->targetText($row),'source_hash'=>$row['source_hash'],'source_version'=>(int)$row['source_version'],'unit_uuid'=>$unitUuid);
            $sources[]=array('key'=>$resourceKey,'source_hash'=>$row['source_hash'],'source_version'=>(int)$row['source_version'],'unit_uuid'=>$unitUuid);
        }
        $coverage=$this->repo->coverage($locale);$qa=$this->qa->bundle($locale,$items,$coverage);if(!$qa['passed']){throw new InvalidArgumentException('Locale bundle failed critical coverage or QA gates.');}$this->assertSourcesCurrent($sources);
        $lockName=$wpdb->prefix.'slto_bundle_version_'.hash('sha256',$locale);$locked=$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,10)',$lockName));if(''!==(string)$wpdb->last_error||1!==(int)$locked){throw new RuntimeException('Locale bundle version lock is unavailable.');}
        $primaryError=null;
        try{
            $version=$this->repo->nextBundleVersion($locale);$built=DeterministicBundle::build($locale,$items,array('bundle_version'=>$version,'direction'=>$localeRecord['direction'],'fallback'=>$localeRecord['fallback_tag'],'coverage'=>$coverage,'format_data_version'=>$localeRecord['format_data_version'],'plural_rules_version'=>$localeRecord['plural_rules_version'],'signing_key_id'=>DeterministicBundle::signingKeyId()));$signature=DeterministicBundle::sign($built['sha256']);if(null===$signature){throw new RuntimeException('Locale bundle signing key is unavailable.');}
            return $this->tx->run(function()use($locale,$version,$built,$signature,$sources,$coverage,$qa):array{
                $metadataJson=wp_json_encode($built['payload']['metadata'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$sourcesJson=wp_json_encode($sources,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if(!is_string($metadataJson)||!is_string($sourcesJson)){throw new RuntimeException('Locale bundle evidence could not be encoded.');}
                $previous=$this->repo->activeBundle($locale);$bundle=$this->repo->insert('bundles',array('locale_tag'=>$locale,'bundle_version'=>$version,'manifest_json'=>$metadataJson,'payload_json'=>$built['json'],'source_list_json'=>$sourcesJson,'coverage'=>$coverage['coverage'],'critical_coverage'=>$coverage['critical_coverage'],'bundle_hash'=>$built['sha256'],'signature'=>$signature,'status'=>'built','previous_bundle_uuid'=>$previous['uuid']??null,'approved_by'=>null,'activated_by'=>null,'activated_at'=>null,'row_version'=>1));
                foreach($qa['checks'] as $check){$details=wp_json_encode($check['details'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if(!is_string($details)){throw new RuntimeException('Bundle QA evidence could not be encoded.');}$this->repo->insert('qa_results',array('target_type'=>'bundle','target_uuid'=>$bundle['uuid'],'rule_code'=>$check['rule'],'result'=>$check['result'],'severity'=>$check['severity'],'details_json'=>$details,'reviewer_id'=>get_current_user_id(),'fixed_at'=>null));}
                $this->audit->record('bundle',(string)$bundle['uuid'],'locale_bundle_built','success',array('locale'=>$locale,'version'=>$version,'hash'=>$built['sha256'],'coverage'=>$coverage,'signed'=>true));$this->outbox->enqueue('LocaleBundleBuilt','bundle',(string)$bundle['uuid'],array('locale'=>$locale,'version'=>$version,'hash'=>$built['sha256'],'coverage'=>$coverage));return $bundle;
            });
        }catch(\Throwable $e){$primaryError=$e;throw $e;}finally{$released=$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lockName));if(null===$primaryError&&(''!==(string)$wpdb->last_error||1!==(int)$released)){throw new RuntimeException('Locale bundle version lock could not be released.');}}
    }

    public function recordQa(string $uuid,string $rule,string $result,string $severity,array $details=[]): array
    {
        $bundle=$this->repo->find('bundles',$uuid);if(!is_array($bundle)){throw new InvalidArgumentException('Locale bundle not found.');}
        if(in_array((string)$bundle['status'],array('approved','staged','canary','active','superseded','rolled_back','invalidated'),true)){throw new InvalidArgumentException('QA evidence is frozen once a bundle is approved for release.');}
        $rule=sanitize_key($rule);if(!in_array($rule,self::HUMAN_QA,true)){throw new InvalidArgumentException('Bundle QA rule is invalid.');}if(!in_array($result,array('pass','fail'),true)||!in_array($severity,array('medium','high','critical'),true)){throw new InvalidArgumentException('Bundle QA result is invalid.');}
        $encoded=wp_json_encode($details,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if(!is_string($encoded)||strlen($encoded)>262144){throw new InvalidArgumentException('Bundle QA details are invalid or oversized.');}
        $reviewerId=get_current_user_id();if($reviewerId<=0){throw new InvalidArgumentException('Bundle QA reviewer identity is unavailable.');}
        if('pass'===$result){
            $evidence=array('bundle_uuid'=>$uuid,'bundle_hash'=>(string)$bundle['bundle_hash'],'bundle_version'=>(int)$bundle['bundle_version'],'locale'=>(string)$bundle['locale_tag'],'rule'=>$rule,'result'=>$result,'severity'=>$severity,'details'=>$details,'reviewer_id'=>$reviewerId);
            if(true!==apply_filters('slto_verify_bundle_qa_evidence',false,$evidence)){throw new InvalidArgumentException('Passing in-context bundle QA requires independently verified route/device/accessibility evidence.');}
        }
        return $this->tx->run(function()use($uuid,$rule,$result,$severity,$encoded,$reviewerId):array{$record=$this->repo->insert('qa_results',array('target_type'=>'bundle','target_uuid'=>$uuid,'rule_code'=>$rule,'result'=>$result,'severity'=>$severity,'details_json'=>$encoded,'reviewer_id'=>$reviewerId,'fixed_at'=>null));$this->audit->record('bundle',$uuid,'bundle_qa_recorded','success',array('rule'=>$rule,'result'=>$result,'severity'=>$severity,'qa_uuid'=>$record['uuid']??null,'reviewer_id'=>$reviewerId));return $record;});
    }

    public function transition(string $uuid,string $to,int $version,string $reason=''): array
    {
        $bundle=$this->repo->find('bundles',$uuid)??throw new InvalidArgumentException('Locale bundle not found.');$reason=sanitize_textarea_field($reason);if(''===trim($reason)||strlen($reason)>1000){throw new InvalidArgumentException('Bundle transition requires a nonempty bounded reason.');}if(in_array($to,array('active','rolled_back','superseded','invalidated'),true)){throw new InvalidArgumentException('Bundle activation, invalidation and rollback require dedicated controlled paths.');}StateMachine::assert('bundle',(string)$bundle['status'],$to);if(in_array($to,array('staged','canary'),true)&&empty($bundle['signature'])){throw new InvalidArgumentException('Signed locale bundle is required for release.');}
        $latest=$this->latestQaResults($uuid);
        if('automated_qa'===$to){$this->assertCurrentAutomatedQa($latest);}
        if('approved'===$to){$this->assertCurrentHumanQa($latest,$bundle);}
        if(in_array($to,array('staged','canary'),true)){
            $sources=$this->validatedSourceList($bundle);$this->assertSourcesCurrent($sources);$this->integrations->assertReady();
        }
        return $this->tx->run(function()use($bundle,$uuid,$to,$version,$reason):array{$changes=array('status'=>$to);if('approved'===$to){$changes['approved_by']=get_current_user_id();}$updated=$this->repo->updateVersioned('bundles',$uuid,$version,$changes);$this->audit->record('bundle',$uuid,'bundle_transition','success',array('from'=>$bundle['status'],'to'=>$to,'reason'=>$reason));return $updated;});
    }

    public function activate(string $uuid,int $version): array
    {
        $initial=$this->repo->find('bundles',$uuid)??throw new InvalidArgumentException('Locale bundle not found.');$locale=(string)$initial['locale_tag'];
        $updated=$this->withLocaleReleaseLock($locale,function()use($uuid,$version,$locale):array{
            global $wpdb;$bundle=$this->repo->find('bundles',$uuid)??throw new InvalidArgumentException('Locale bundle not found.');
            if($locale!==(string)$bundle['locale_tag']||!in_array((string)$bundle['status'],array('staged','canary'),true)){throw new InvalidArgumentException('Bundle must complete staged or canary release before activation.');}if((float)$bundle['critical_coverage']<100.0){throw new InvalidArgumentException('Bundle critical coverage gate failed.');}
            $this->assertCurrentHumanQa($this->latestQaResults($uuid),$bundle);
            $sourceList=$this->validatedSourceList($bundle);$this->assertSourcesCurrent($sourceList);$unitUuids=array_values(array_unique(array_column($sourceList,'unit_uuid')));if(empty($unitUuids)||count($unitUuids)!==count($sourceList)){throw new InvalidArgumentException('Bundle source-unit evidence is empty or duplicated.');}
            $this->integrations->assertReady();$this->releaseApprovals->assertDualApproval($uuid);
            return $this->tx->run(function()use($wpdb,$bundle,$version,$unitUuids):array{
                $previous=$this->repo->activeBundle((string)$bundle['locale_tag']);if(is_array($previous)){$previousSources=$this->validatedSourceList($previous);$previousUnits=array_values(array_unique(array_column($previousSources,'unit_uuid')));if(count($previousUnits)!==count($previousSources)){throw new RuntimeException('Previous active bundle unit evidence is duplicated.');}if(!empty($previousUnits)){$ph=implode(',',array_fill(0,count($previousUnits),'%s'));$reverted=$wpdb->query($wpdb->prepare("UPDATE ".Database::table('units')." SET status='approved',released_bundle_uuid=NULL,row_version=row_version+1,updated_at=%s WHERE uuid IN ({$ph}) AND released_bundle_uuid=%s AND status='released'",Database::now(),...array_merge($previousUnits,array($previous['uuid']))));if(false===$reverted||(int)$reverted!==count($previousUnits)){throw new RuntimeException('Previous active bundle units could not be reconciled exactly.');}}$this->repo->updateVersioned('bundles',(string)$previous['uuid'],(int)$previous['row_version'],array('status'=>'superseded'));}
                $newActive=$this->repo->updateVersioned('bundles',(string)$bundle['uuid'],$version,array('status'=>'active','previous_bundle_uuid'=>$previous['uuid']??null,'activated_by'=>get_current_user_id(),'activated_at'=>Database::now()));$ph=implode(',',array_fill(0,count($unitUuids),'%s'));$released=$wpdb->query($wpdb->prepare("UPDATE ".Database::table('units')." SET status='released',released_bundle_uuid=%s,row_version=row_version+1,updated_at=%s WHERE uuid IN ({$ph}) AND target_locale=%s AND status='approved' AND released_bundle_uuid IS NULL",$bundle['uuid'],Database::now(),...array_merge($unitUuids,array($bundle['locale_tag']))));if(false===$released||(int)$released!==count($unitUuids)){throw new RuntimeException('Exact approved translation set could not be released.');}
                $this->audit->record('bundle',(string)$bundle['uuid'],'locale_bundle_activated','success',array('locale'=>$bundle['locale_tag'],'version'=>$bundle['bundle_version'],'previous'=>$previous['uuid']??null,'released_units'=>count($unitUuids)));$this->outbox->enqueue('LocaleBundleActivated','bundle',(string)$bundle['uuid'],array('locale'=>$bundle['locale_tag'],'version'=>(int)$bundle['bundle_version'],'hash'=>$bundle['bundle_hash'],'previous'=>$previous['uuid']??null));return $newActive;
            });
        });wp_cache_flush();return $updated;
    }

    public function rollback(string $activeUuid,string $targetUuid,int $activeVersion,string $reason='controlled-prior-signed-bundle-rollback'): array
    {
        $reason=sanitize_textarea_field($reason);if(''===trim($reason)||strlen($reason)>1000){throw new InvalidArgumentException('Rollback requires a bounded reason.');}
        $initial=$this->repo->find('bundles',$activeUuid)??throw new InvalidArgumentException('Active bundle not found.');$locale=(string)$initial['locale_tag'];
        $result=$this->withLocaleReleaseLock($locale,function()use($activeUuid,$targetUuid,$activeVersion,$locale,$reason):array{
            global $wpdb;$active=$this->repo->find('bundles',$activeUuid)??throw new InvalidArgumentException('Active bundle not found.');$target=$this->repo->find('bundles',$targetUuid)??throw new InvalidArgumentException('Rollback bundle not found.');
            if($locale!==(string)$active['locale_tag']||'active'!==(string)$active['status']||(string)$active['locale_tag']!==(string)$target['locale_tag']||!in_array((string)$target['status'],array('superseded','rolled_back'),true)){throw new InvalidArgumentException('Rollback pair is invalid.');}
            if((string)($active['previous_bundle_uuid']??'')!==$targetUuid){throw new InvalidArgumentException('Rollback target must be the exact prior signed bundle.');}
            $this->releaseApprovals->assertDualApproval($targetUuid);
            $activeSources=$this->validatedSourceList($active);$targetSources=$this->validatedSourceList($target);$this->assertSourcesCurrent($targetSources);$activeUnits=array_values(array_unique(array_column($activeSources,'unit_uuid')));$targetUnits=array_values(array_unique(array_column($targetSources,'unit_uuid')));if(empty($targetUnits)||count($activeUnits)!==count($activeSources)||count($targetUnits)!==count($targetSources)){throw new InvalidArgumentException('Rollback bundle source evidence is empty or duplicated.');}
            return $this->tx->run(function()use($wpdb,$active,$target,$activeVersion,$activeUnits,$targetUnits,$reason):array{$rolled=$this->repo->updateVersioned('bundles',(string)$active['uuid'],$activeVersion,array('status'=>'rolled_back'));if(!empty($activeUnits)){$ph=implode(',',array_fill(0,count($activeUnits),'%s'));$reverted=$wpdb->query($wpdb->prepare("UPDATE ".Database::table('units')." SET status='approved',released_bundle_uuid=NULL,row_version=row_version+1,updated_at=%s WHERE uuid IN ({$ph}) AND released_bundle_uuid=%s AND status='released'",Database::now(),...array_merge($activeUnits,array($active['uuid']))));if(false===$reverted||(int)$reverted!==count($activeUnits)){throw new RuntimeException('Active bundle units could not be reconciled exactly during rollback.');}}$newActive=$this->repo->updateVersioned('bundles',(string)$target['uuid'],(int)$target['row_version'],array('status'=>'active','previous_bundle_uuid'=>$active['uuid'],'activated_by'=>get_current_user_id(),'activated_at'=>Database::now()));$ph=implode(',',array_fill(0,count($targetUnits),'%s'));$activated=$wpdb->query($wpdb->prepare("UPDATE ".Database::table('units')." SET status='released',released_bundle_uuid=%s,row_version=row_version+1,updated_at=%s WHERE uuid IN ({$ph}) AND target_locale=%s AND status='approved' AND released_bundle_uuid IS NULL",$target['uuid'],Database::now(),...array_merge($targetUnits,array($target['locale_tag']))));if(false===$activated||(int)$activated!==count($targetUnits)){throw new RuntimeException('Rollback bundle units could not be restored exactly.');}$this->audit->record('bundle',(string)$active['uuid'],'locale_bundle_rolled_back','success',array('target'=>$target['uuid'],'locale'=>$active['locale_tag'],'reason'=>$reason,'restored_units'=>count($targetUnits)));$this->outbox->enqueue('LocaleBundleRolledBack','bundle',(string)$active['uuid'],array('locale'=>$active['locale_tag'],'from'=>$active['uuid'],'to'=>$target['uuid'],'reason'=>$reason));return array('rolled_back'=>$rolled,'active'=>$newActive);});
        });wp_cache_flush();return $result;
    }

    public function publicBundle(string $locale): ?array
    {
        $localeRecord=$this->repo->findOne('locales','locale_tag',$locale);
        if(!is_array($localeRecord)||!in_array((string)$localeRecord['status'],array('enabled','degraded'),true)){
            return null;
        }
        $bundle=$this->repo->activeBundle($locale);if(!is_array($bundle)){return null;}
        $sources=$this->validatedSourceList($bundle);$this->assertSourcesCurrent($sources);
        $payload=json_decode((string)$bundle['payload_json'],true,128,JSON_THROW_ON_ERROR);
        return array('locale'=>$locale,'version'=>(int)$bundle['bundle_version'],'hash'=>$bundle['bundle_hash'],'signature'=>$bundle['signature'],'payload'=>$payload);
    }

    private function latestQaResults(string $uuid): array
    {
        $rows=$this->repo->list('qa_results',array('target_type'=>'bundle','target_uuid'=>$uuid),500,0,'id DESC');$latest=[];foreach($rows as $row){$rule=(string)($row['rule_code']??'');if(''!==$rule&&!isset($latest[$rule])){$latest[$rule]=$row;}}return $latest;
    }

    private function assertCurrentAutomatedQa(array $latest): void
    {
        foreach(self::AUTOMATED_QA as $rule){if(!isset($latest[$rule])||'pass'!==(string)$latest[$rule]['result']){throw new InvalidArgumentException('Current automated bundle QA evidence is missing or failed: '.$rule);}}
    }

    private function assertCurrentHumanQa(array $latest,array $bundle): void
    {
        foreach(self::HUMAN_QA as $rule){
            $row=$latest[$rule]??null;
            if(!is_array($row)||'pass'!==(string)($row['result']??'')){
                throw new InvalidArgumentException('Current in-context bundle QA evidence is missing or failed: '.$rule);
            }
            $details=json_decode((string)($row['details_json']??''),true);
            if(!is_array($details)){$details=array();}
            $evidence=array(
                'bundle_uuid'=>(string)$bundle['uuid'],
                'bundle_hash'=>(string)$bundle['bundle_hash'],
                'bundle_version'=>(int)$bundle['bundle_version'],
                'locale'=>(string)$bundle['locale_tag'],
                'rule'=>$rule,
                'result'=>'pass',
                'severity'=>(string)($row['severity']??''),
                'details'=>$details,
                'reviewer_id'=>(int)($row['reviewer_id']??0),
                'qa_uuid'=>(string)($row['uuid']??''),
                'verification_phase'=>'consumption-time',
            );
            if(true!==apply_filters('slto_verify_bundle_qa_evidence',false,$evidence)){
                throw new InvalidArgumentException('Current in-context bundle QA evidence could not be independently reverified: '.$rule);
            }
        }
    }

    private function validatedSourceList(array $bundle): array
    {
        $payloadJson=(string)($bundle['payload_json']??'');$hash=(string)($bundle['bundle_hash']??'');$signature=(string)($bundle['signature']??'');if(1!==preg_match('/^[a-f0-9]{64}$/D',$hash)||!hash_equals($hash,hash('sha256',$payloadJson))||!DeterministicBundle::verify($hash,$signature)){throw new InvalidArgumentException('Bundle payload hash or signature integrity failed.');}
        $payload=json_decode($payloadJson,true,128,JSON_THROW_ON_ERROR);if(!is_array($payload)||($payload['locale']??null)!==(string)$bundle['locale_tag']||!is_array($payload['metadata']??null)||!is_array($payload['items']??null)||(int)($payload['metadata']['bundle_version']??0)!==(int)$bundle['bundle_version']){throw new InvalidArgumentException('Bundle signed payload metadata is inconsistent.');}
        $sources=$this->decodeSourceList($bundle);$byKey=[];foreach($sources as $source){$byKey[(string)$source['key']]=$source;}if(count($byKey)!==count($payload['items'])){throw new InvalidArgumentException('Bundle source evidence does not match signed payload cardinality.');}
        foreach($payload['items'] as $key=>$item){$source=$byKey[(string)$key]??null;if(!is_array($item)||!is_array($source)||(string)($item['unit_uuid']??'')!==(string)$source['unit_uuid']||(string)($item['source_hash']??'')!==(string)$source['source_hash']||(int)($item['source_version']??0)!==(int)$source['source_version']){throw new InvalidArgumentException('Bundle source evidence is not bound to the signed payload.');}}
        return $sources;
    }

    private function decodeSourceList(array $bundle): array
    {
        $sources=json_decode((string)$bundle['source_list_json'],true,128,JSON_THROW_ON_ERROR);if(!is_array($sources)){throw new InvalidArgumentException('Bundle source evidence is invalid.');}$keys=[];$units=[];foreach($sources as $source){$key=(string)($source['key']??'');$unit=(string)($source['unit_uuid']??'');if(!is_array($source)||''===$key||isset($keys[$key])||isset($units[$unit])||1!==preg_match('/^[a-f0-9-]{36}$/D',$unit)||1!==preg_match('/^[a-f0-9]{64}$/D',(string)($source['source_hash']??''))||(int)($source['source_version']??0)<=0){throw new InvalidArgumentException('Bundle source evidence contains an invalid or duplicated unit.');}$keys[$key]=true;$units[$unit]=true;}return $sources;
    }

    private function assertSourcesCurrent(array $sources): void
    {
        BundleFreshnessGuard::assertCurrent($sources,fn(string $key):?array=>$this->repo->findOne('resources','resource_key',$key));
    }

    private function withLocaleReleaseLock(string $locale,callable $callback): mixed
    {
        global $wpdb;$lockName=$wpdb->prefix.'slto_bundle_release_'.hash('sha256',$locale);$locked=$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,10)',$lockName));if(''!==(string)$wpdb->last_error||1!==(int)$locked){throw new RuntimeException('Locale bundle release lock is unavailable.');}$primary=null;try{return $callback();}catch(\Throwable $e){$primary=$e;throw $e;}finally{$released=$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lockName));if(null===$primary&&(''!==(string)$wpdb->last_error||1!==(int)$released)){throw new RuntimeException('Locale bundle release lock could not be released.');}}
    }
}
