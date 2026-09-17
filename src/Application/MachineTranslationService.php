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
    ) {}

    public function prepare(array $unitUuids,string $purpose='draft_translation',bool $explicitHighRiskApproval=false): array
    {
        $providerRow=$this->assertActiveProvider();
        $purpose=sanitize_key($purpose);if(!in_array($purpose,array('draft_translation','terminology_draft','backfill_draft'),true)){throw new InvalidArgumentException('Machine translation purpose is invalid.');}
        $unitUuids=array_values(array_unique(array_filter(array_map('strval',$unitUuids))));if(empty($unitUuids)||count($unitUuids)>100){throw new InvalidArgumentException('Vendor job requires 1–100 units.');}
        $payload=[];$summary=[];
        foreach($unitUuids as $uuid){
            $unit=$this->repo->find('units',$uuid)??throw new InvalidArgumentException('Vendor unit is unavailable.');if(!in_array((string)$unit['status'],array('assigned','translating','stale'),true)){throw new InvalidArgumentException('Vendor unit is not open for a machine draft.');}
            $resource=$this->repo->find('resources',(string)$unit['resource_uuid'])??throw new InvalidArgumentException('Vendor source is unavailable.');
            if(!RiskPolicy::machineTranslationAllowed((string)$resource['risk_class'],(string)$resource['data_class'],(string)$resource['domain_name'],$explicitHighRiskApproval)){throw new InvalidArgumentException('Machine translation is denied for this risk/data/domain class.');}
            $source=$this->resources->text($resource);$redacted=Redactor::redact($source);$schema=json_decode((string)$resource['placeholders'],true)?:array();PlaceholderValidator::assertSource($redacted['text'],$schema);
            $payload[]=array('unit_uuid'=>$uuid,'source_locale'=>$resource['source_locale'],'target_locale'=>$unit['target_locale'],'source_text'=>$redacted['text'],'placeholders'=>$schema,'domain'=>$resource['domain_name'],'risk'=>$resource['risk_class']);$summary[$uuid]=$redacted['counts'];
        }
        $uuid=\Sabri\Localization\Infrastructure\Database::uuid();$payloadJson=wp_json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$unitsJson=wp_json_encode($unitUuids,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$summaryJson=wp_json_encode($summary,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if(!is_string($payloadJson)||!is_string($unitsJson)||!is_string($summaryJson)||strlen($payloadJson)>5_000_000){throw new InvalidArgumentException('Machine translation preparation evidence is invalid or oversized.');}
        $hash=hash('sha256',$payloadJson);
        $job=$this->tx->run(function()use($uuid,$purpose,$unitsJson,$hash,$summaryJson,$unitUuids,$providerRow):array{
            $job=$this->repo->insert('vendor_jobs',array('uuid'=>$uuid,'provider_key'=>$providerRow['provider_key'],'model_version'=>'configured-at-send','region_code'=>$providerRow['region_code'],'purpose'=>$purpose,'project_uuid'=>null,'unit_uuids'=>$unitsJson,'outbound_hash'=>$hash,'inbound_hash'=>null,'redaction_summary'=>$summaryJson,'provider_reference'=>null,'status'=>'prepared','deletion_evidence'=>null,'row_version'=>1,'created_by'=>get_current_user_id(),'purge_due_at'=>gmdate('Y-m-d H:i:s',time()+7*DAY_IN_SECONDS)));
            $this->audit->record('vendor_job',$uuid,'vendor_job_prepared','success',array('provider'=>$providerRow['provider_key'],'region'=>$providerRow['region_code'],'contract_version'=>$providerRow['contract_version'],'unit_count'=>count($unitUuids),'outbound_hash'=>$hash,'redaction_summary_hash'=>hash('sha256',$summaryJson)));return $job;
        });
        return array('job'=>$job,'payload'=>$payload);
    }

    public function send(string $jobUuid,array $payload,int $version): array
    {
        $job=$this->repo->find('vendor_jobs',$jobUuid)??throw new InvalidArgumentException('Vendor job not found.');$providerRow=$this->assertActiveProvider((string)$job['provider_key']);StateMachine::assert('vendor_job',(string)$job['status'],'sent');
        $requestJson=wp_json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if(!is_string($requestJson)||strlen($requestJson)>5_000_000){throw new InvalidArgumentException('Vendor payload is invalid or oversized.');}$requestHash=hash('sha256',$requestJson);if(!hash_equals((string)$job['outbound_hash'],$requestHash)){throw new InvalidArgumentException('Vendor payload hash mismatch.');}
        if((string)($job['region_code']??'')!==(string)$providerRow['region_code']){throw new InvalidArgumentException('Vendor job provider-region binding no longer matches the active provider.');}
        $sent=$this->tx->run(function()use($jobUuid,$version):array{$sent=$this->repo->updateVersioned('vendor_jobs',$jobUuid,$version,array('status'=>'sent'));$this->audit->record('vendor_job',$jobUuid,'vendor_job_sent','success',array('provider'=>$sent['provider_key'],'outbound_hash'=>$sent['outbound_hash']));return $sent;});
        try{
            $response=$this->provider->submit($sent,$payload);$results=is_array($response['translations']??null)?$response['translations']:array();$this->assertResponse($payload,$results);
            $responseRegion=sanitize_text_field((string)($response['region']??''));if(''===$responseRegion||!hash_equals((string)$providerRow['region_code'],$responseRegion)){throw new InvalidArgumentException('Machine translation provider response must attest the approved provider region.');}
            $providerReference=sanitize_text_field((string)($response['reference']??''));$modelVersion=sanitize_text_field((string)($response['model_version']??''));if(''===$providerReference||strlen($providerReference)>191||''===$modelVersion||strlen($modelVersion)>80){throw new InvalidArgumentException('Machine translation provider response must include bounded reference and model-version provenance.');}
            return $this->tx->run(function()use($sent,$results,$responseRegion,$providerReference,$modelVersion):array{
                StateMachine::assert('vendor_job','sent','received');$received=$this->repo->updateVersioned('vendor_jobs',(string)$sent['uuid'],(int)$sent['row_version'],array('status'=>'received','provider_reference'=>$providerReference,'model_version'=>$modelVersion,'region_code'=>$responseRegion,'inbound_hash'=>hash('sha256',wp_json_encode($results,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))));
                StateMachine::assert('vendor_job','received','validated');$validated=$this->repo->updateVersioned('vendor_jobs',(string)$sent['uuid'],(int)$received['row_version'],array('status'=>'validated'));
                foreach($results as $item){$unitUuid=(string)$item['unit_uuid'];$target=(string)$item['target_text'];$unit=$this->repo->find('units',$unitUuid)??throw new InvalidArgumentException('Vendor returned an unknown unit.');$this->translations->submit($unitUuid,$target,(int)$unit['row_version'],true,(string)$sent['uuid']);}
                $this->audit->record('vendor_job',(string)$sent['uuid'],'vendor_job_received','success',array('result_count'=>count($results),'inbound_hash'=>$validated['inbound_hash'],'model_version'=>$modelVersion,'provider_reference_hash'=>hash('sha256',$providerReference),'draft_only'=>true,'human_review_required'=>true));$this->outbox->enqueue('MachineTranslationDraftReceived','vendor_job',(string)$sent['uuid'],array('unit_count'=>count($results),'human_review_required'=>true));return $validated;
            });
        }catch(Throwable $throwable){$current=$this->repo->find('vendor_jobs',$jobUuid);if(is_array($current)&&'sent'===(string)$current['status']){try{StateMachine::assert('vendor_job','sent','failed');$this->repo->updateVersioned('vendor_jobs',$jobUuid,(int)$current['row_version'],array('status'=>'failed'));$this->audit->record('vendor_job',$jobUuid,'vendor_job_failed','failed',array('exception_class'=>get_class($throwable),'message_hash'=>hash('sha256',$throwable->getMessage())));}catch(Throwable){}}throw $throwable;}
    }

    public function review(string $jobUuid,string $decision,int $version,string $reason=''): array
    {
        $job=$this->repo->find('vendor_jobs',$jobUuid)??throw new InvalidArgumentException('Vendor job not found.');if('validated'!==(string)$job['status']){throw new InvalidArgumentException('Vendor job is not awaiting human review.');}if(!in_array($decision,array('accept','reject'),true)){throw new InvalidArgumentException('Vendor job review decision is invalid.');}if('reject'===$decision&&''===trim($reason)){throw new InvalidArgumentException('Vendor job rejection reason is required.');}
        $unitUuids=json_decode((string)$job['unit_uuids'],true);if(!is_array($unitUuids)||empty($unitUuids)){throw new InvalidArgumentException('Vendor job unit evidence is invalid.');}
        if('accept'===$decision){foreach($unitUuids as $unitUuid){$unit=$this->repo->find('units',(string)$unitUuid)??throw new InvalidArgumentException('Vendor job unit is unavailable.');if((string)($unit['provider_job_uuid']??'')!==$jobUuid||1===(int)($unit['machine_draft']??0)){throw new InvalidArgumentException('Every machine draft must be human-edited before provider-job acceptance.');}if(!in_array((string)$unit['status'],array('linguistic_review','domain_review','approved','released'),true)){throw new InvalidArgumentException('Every machine draft must enter the human review workflow before provider-job acceptance.');}}}
        return $this->tx->run(function()use($job,$decision,$version,$reason):array{if('reject'===$decision){StateMachine::assert('vendor_job','validated','rejected');$updated=$this->repo->updateVersioned('vendor_jobs',(string)$job['uuid'],$version,array('status'=>'rejected'));}else{StateMachine::assert('vendor_job','validated','human_reviewed');$reviewed=$this->repo->updateVersioned('vendor_jobs',(string)$job['uuid'],$version,array('status'=>'human_reviewed'));StateMachine::assert('vendor_job','human_reviewed','accepted');$updated=$this->repo->updateVersioned('vendor_jobs',(string)$job['uuid'],(int)$reviewed['row_version'],array('status'=>'accepted'));}$this->audit->record('vendor_job',(string)$job['uuid'],'vendor_job_human_reviewed','success',array('decision'=>$decision,'reason'=>$reason,'reviewer_id'=>get_current_user_id()));$this->outbox->enqueue('MachineTranslationJobReviewed','vendor_job',(string)$job['uuid'],array('decision'=>$decision,'human_reviewed'=>true));return $updated;});
    }

    public function purge(string $jobUuid,int $version): array
    {
        $job=$this->repo->find('vendor_jobs',$jobUuid)??throw new InvalidArgumentException('Vendor job not found.');$this->assertGovernedProviderForPurge((string)$job['provider_key']);if(!in_array($job['status'],array('accepted','rejected','failed','human_reviewed'),true)){throw new InvalidArgumentException('Vendor job is not eligible for purge.');}
        if('human_reviewed'===$job['status']){StateMachine::assert('vendor_job','human_reviewed','rejected');$job=$this->repo->updateVersioned('vendor_jobs',$jobUuid,$version,array('status'=>'rejected'));$version=(int)$job['row_version'];}
        StateMachine::assert('vendor_job',(string)$job['status'],'purged');$evidence=$this->provider->purge((string)$job['provider_reference']);$evidenceJson=wp_json_encode($evidence,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if(!is_string($evidenceJson)||strlen($evidenceJson)>262144){throw new InvalidArgumentException('Provider purge evidence is invalid or oversized.');}
        $updated=$this->repo->updateVersioned('vendor_jobs',$jobUuid,$version,array('status'=>'purged','deletion_evidence'=>$evidenceJson));$this->audit->record('vendor_job',$jobUuid,'vendor_job_purged','success',array('provider'=>$job['provider_key'],'evidence_hash'=>hash('sha256',wp_json_encode($evidence))));$this->outbox->enqueue('TranslationVendorJobPurged','vendor_job',$jobUuid,array('provider'=>$job['provider_key'],'purged_at'=>gmdate(DATE_ATOM)));return $updated;
    }

    private function assertActiveProvider(?string $expectedKey=null): array
    {
        $key=sanitize_key($this->provider->key());if(''===$key||'disabled'===$key){throw new InvalidArgumentException('Machine translation provider is disabled or ungoverned.');}if(null!==$expectedKey&&!hash_equals($expectedKey,$key)){throw new InvalidArgumentException('Vendor job is bound to a different provider.');}
        $row=$this->repo->findOne('providers','provider_key',$key);if(!is_array($row)||'active'!==(string)$row['status']||0!==(int)($row['training_allowed']??0)||empty($row['contract_version'])||empty($row['region_code'])||empty($row['base_url'])||empty($row['credential_reference'])){throw new InvalidArgumentException('Machine translation provider is not currently active under approved governance.');}
        $health=$this->provider->health();$this->assertAdapterGovernance($row,is_array($health)?$health:[],true);return $row;
    }

    private function assertGovernedProviderForPurge(string $expectedKey): array
    {
        $key=sanitize_key($this->provider->key());if(''===$key||!hash_equals($expectedKey,$key)){throw new InvalidArgumentException('Vendor purge adapter does not match the governed provider.');}
        $row=$this->repo->findOne('providers','provider_key',$key);if(!is_array($row)||!in_array((string)$row['status'],array('active','approved','disabled','deprecated'),true)||0!==(int)($row['training_allowed']??0)||empty($row['contract_version'])||empty($row['credential_reference'])){throw new InvalidArgumentException('Vendor purge provider is no longer governed by an approved deletion contract.');}
        $health=$this->provider->health();$this->assertAdapterGovernance($row,is_array($health)?$health:[],false);return $row;
    }

    private function assertAdapterGovernance(array $row,array $health,bool $requireHealthy): void
    {
        $status=strtolower(sanitize_key((string)($health['status']??'')));$region=sanitize_text_field((string)($health['region']??''));
        if($requireHealthy&&!in_array($status,array('configured','healthy','ready'),true)){throw new InvalidArgumentException('Machine translation provider runtime health is not eligible.');}
        if(''===$region||!hash_equals((string)$row['region_code'],$region)){throw new InvalidArgumentException('Machine translation provider runtime region differs from the approved provider record.');}
        if(true===($health['training_allowed']??true)){throw new InvalidArgumentException('Machine translation adapter must attest that provider training reuse is disabled.');}
        $baseUrl=(string)($health['base_url']??'');$credentialRef=(string)($health['credential_reference']??'');$contractVersion=(string)($health['contract_version']??'');
        if(''===$baseUrl||!hash_equals((string)$row['base_url'],$baseUrl)||''===$credentialRef||!hash_equals((string)$row['credential_reference'],$credentialRef)||''===$contractVersion||!hash_equals((string)$row['contract_version'],$contractVersion)){throw new InvalidArgumentException('Machine translation adapter configuration does not match the approved provider registry.');}
        $approvedHosts=json_decode((string)($row['allowed_hosts']??'[]'),true);$runtimeHosts=$health['allowed_hosts']??null;if(!is_array($approvedHosts)||!is_array($runtimeHosts)){throw new InvalidArgumentException('Machine translation adapter host governance cannot be verified.');}
        $normalize=static function(array $hosts):array{$out=[];foreach($hosts as $host){$host=strtolower(rtrim(trim((string)$host),'.'));if(''!==$host){$out[]=$host;}}$out=array_values(array_unique($out));sort($out,SORT_STRING);return $out;};
        if($normalize($approvedHosts)!==$normalize($runtimeHosts)){throw new InvalidArgumentException('Machine translation adapter hosts do not match the approved provider registry.');}
    }

    private function assertResponse(array $payload,array $results): void
    {
        if(count($results)!==count($payload)){throw new InvalidArgumentException('Vendor result cardinality mismatch.');}$expected=array_fill_keys(array_map(static fn(array $item):string=>(string)($item['unit_uuid']??''),$payload),true);$seen=[];
        foreach($results as $item){if(!is_array($item)){throw new InvalidArgumentException('Vendor result item is invalid.');}$unitUuid=(string)($item['unit_uuid']??'');$target=(string)($item['target_text']??'');if(''===$unitUuid||!isset($expected[$unitUuid])||isset($seen[$unitUuid])){throw new InvalidArgumentException('Vendor result unit mapping is invalid.');}if(''===trim($target)||strlen($target)>500000){throw new InvalidArgumentException('Vendor result text is empty or exceeds the bounded limit.');}$seen[$unitUuid]=true;}
    }
}
