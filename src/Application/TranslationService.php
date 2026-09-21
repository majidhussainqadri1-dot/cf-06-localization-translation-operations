<?php

declare(strict_types=1);

namespace Sabri\Localization\Application;

use InvalidArgumentException;
use Sabri\Localization\Domain\Translation\RiskPolicy;
use Sabri\Localization\Domain\Workflow\StateMachine;
use Sabri\Localization\Infrastructure\DependencyInvalidator;
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
    ) {}

    public function submit(string $uuid,string $target,int $version,bool $machineDraft=false,?string $providerJob=null):array
    {
        $unit=$this->repo->find('units',$uuid)??throw new InvalidArgumentException('Translation unit not found.');
        $resource=$this->repo->find('resources',(string)$unit['resource_uuid'])??throw new InvalidArgumentException('Translation source is unavailable.');
        if(!in_array($unit['status'],array('assigned','translating','stale'),true)){throw new InvalidArgumentException('Translation unit is not open for submission.');}
        if($machineDraft){
            $this->assertMachineDraftProvenance($unit,$resource,$providerJob);
        }else{
            $this->assertAssignedActor($unit,'translator_id','Only the assigned translator may submit this unit.');
        }
        if('active'!==(string)($resource['status']??'')||(int)$unit['source_version']!==(int)$resource['source_version']||!hash_equals((string)$unit['source_hash'],(string)$resource['source_hash'])){throw new InvalidArgumentException('Translation source is stale, retired or inactive and must be reassigned.');}
        if(''===trim($target)||strlen($target)>500000){throw new InvalidArgumentException('Translation target is empty or exceeds the bounded limit.');}
        $result=$this->qa->unit($unit,$resource,$target);if(!$result['passed']){throw new InvalidArgumentException('Translation failed automated linguistic QA.');}
        return $this->tx->run(function()use($unit,$resource,$target,$version,$machineDraft,$providerJob,$result):array{
            $secureId=null;$stored=$target;
            if(in_array($resource['data_class'],array('C4','C5'),true)||'private'===$resource['risk_class']){$secureId=$this->repo->storeSecurePayload('translation_unit',(string)$unit['uuid'],'target_text',$target);$stored='[ENCRYPTED RESTRICTED TRANSLATION]';}
            $updated=$this->repo->updateVersioned('units',(string)$unit['uuid'],$version,array(
                'target_text'=>$stored,'secure_payload_id'=>$secureId,'status'=>$machineDraft?'translating':'linguistic_review',
                'machine_draft'=>$machineDraft?1:0,'provider_job_uuid'=>$machineDraft?$providerJob:($unit['provider_job_uuid']??null),'qa_status'=>'passed',
            ));
            if(!empty($unit['secure_payload_id'])&&(int)$unit['secure_payload_id']!==(int)$secureId){$this->repo->retireSecurePayload((int)$unit['secure_payload_id']);}
            $this->audit->record('unit',(string)$unit['uuid'],$machineDraft?'machine_draft_received':'translation_submitted','success',array('source_version'=>$unit['source_version'],'target_hash'=>hash('sha256',$target),'qa_checks'=>count($result['checks'])));
            $this->outbox->enqueue('TranslationSubmitted','translation_unit',(string)$unit['uuid'],array('unit_uuid'=>$unit['uuid'],'locale'=>$unit['target_locale'],'machine_draft'=>$machineDraft));
            return $updated;
        });
    }

    public function review(string $uuid,string $decision,int $version,string $reviewType,string $reason=''):array
    {
        $unit=$this->repo->find('units',$uuid)??throw new InvalidArgumentException('Translation unit not found.');
        $resource=$this->repo->find('resources',(string)$unit['resource_uuid'])??throw new InvalidArgumentException('Translation source is unavailable.');
        if(!in_array($decision,array('approve','request_changes','reject'),true)){throw new InvalidArgumentException('Review decision is invalid.');}
        $reason=sanitize_textarea_field($reason);if(''===trim($reason)||strlen($reason)>2000){throw new InvalidArgumentException('Every translation review transition requires a nonempty bounded reason.');}
        if((int)$unit['source_version']!==(int)$resource['source_version']||!hash_equals((string)$unit['source_hash'],(string)$resource['source_hash'])||'active'!==(string)$resource['status']){throw new InvalidArgumentException('Translation source changed or retired before review; the unit must be refreshed.');}
        $actor=get_current_user_id();$from=(string)$unit['status'];
        if('linguistic'===$reviewType){
            if('linguistic_review'!==$from){throw new InvalidArgumentException('Unit is not awaiting linguistic review.');}
            $this->assertAssignedActor($unit,'linguistic_reviewer_id','Only the assigned linguistic reviewer may review this unit.');
            $to='approve'===$decision?(RiskPolicy::requiresDomainReview((string)$resource['risk_class'],(string)$resource['domain_name'])?'domain_review':'approved'):'translating';
        }elseif('domain'===$reviewType){
            if('domain_review'!==$from){throw new InvalidArgumentException('Unit is not awaiting domain review.');}
            $this->assertAssignedActor($unit,'domain_reviewer_id','Only the assigned domain reviewer may review this unit.');
            $to='approve'===$decision?'approved':'translating';
        }else{throw new InvalidArgumentException('Review type is invalid.');}
        if($actor===(int)$unit['translator_id']){throw new InvalidArgumentException('A translator cannot review their own translation.');}
        if('domain'===$reviewType&&$actor===(int)$unit['linguistic_reviewer_id']){throw new InvalidArgumentException('High-risk domain review requires an independent reviewer.');}
        StateMachine::assert('unit',$from,$to);
        return $this->tx->run(function()use($unit,$resource,$version,$from,$to,$decision,$reviewType,$reason,$actor):array{
            $changes=array('status'=>$to,'machine_draft'=>0);$changes['linguistic'===$reviewType?'linguistic_reviewer_id':'domain_reviewer_id']=$actor;
            $updated=$this->repo->updateVersioned('units',(string)$unit['uuid'],$version,$changes);
            $this->audit->record('unit',(string)$unit['uuid'],'translation_'.$reviewType.'_review','success',array('decision'=>$decision,'from'=>$from,'to'=>$to,'reason'=>$reason));
            if('approved'===$to){
                $this->outbox->enqueue('TranslationApproved','translation_unit',(string)$unit['uuid'],array('unit_uuid'=>$unit['uuid'],'locale'=>$unit['target_locale'],'decision'=>$decision,'review_type'=>$reviewType));
                $this->addMemory($updated,$resource);
            }elseif('approve'!==$decision){
                $this->outbox->enqueue('TranslationRejected','translation_unit',(string)$unit['uuid'],array('unit_uuid'=>$unit['uuid'],'locale'=>$unit['target_locale'],'decision'=>$decision,'review_type'=>$reviewType));
            }
            return $updated;
        });
    }

    public function comment(string $unitUuid,string $text,string $audience='internal',?string $parent=null):array
    {
        $unit=$this->repo->find('units',$unitUuid)??throw new InvalidArgumentException('Translation unit not found.');
        $resource=$this->repo->find('resources',(string)$unit['resource_uuid'])??throw new InvalidArgumentException('Translation source is unavailable.');
        $actor=get_current_user_id();
        $participant=false;
        foreach(array('translator_id','linguistic_reviewer_id','domain_reviewer_id') as $column){
            if($actor>0&&$actor===(int)($unit[$column]??0)&&$this->assignedActorIsCurrent($unit,$column,$actor)){$participant=true;break;}
        }
        if(!$participant&&!Authorization::allowed('manage',array('object'=>'unit','object_uuid'=>$unitUuid,'record_version'=>(int)$unit['row_version']))){throw new InvalidArgumentException('Only currently assigned translation participants may comment.');}
        $audience=sanitize_key($audience);if(!in_array($audience,array('internal','vendor','domain_owner'),true)){throw new InvalidArgumentException('Comment audience is invalid.');}
        $text=sanitize_textarea_field($text);if(''===$text||strlen($text)>10000){throw new InvalidArgumentException('Comment is empty or too long.');}
        if('vendor'===$audience){
            if(in_array((string)$resource['data_class'],array('C3','C4','C5'),true)||RiskPolicy::requiresDomainReview((string)$resource['risk_class'],(string)$resource['domain_name'])){throw new InvalidArgumentException('Vendor comments are denied for protected or high-risk translation units.');}
            $redacted=\Sabri\Localization\Domain\Translation\Redactor::redact($text);if(array_sum(array_map('intval',$redacted['counts']))>0||!hash_equals($text,(string)$redacted['text'])){throw new InvalidArgumentException('Sensitive data is not allowed in vendor comments.');}
        }
        if(null!==$parent){$parentRow=$this->repo->find('comments',$parent);if(!is_array($parentRow)||(string)$parentRow['unit_uuid']!==$unitUuid){throw new InvalidArgumentException('Comment parent is invalid.');}}
        return $this->tx->run(function()use($unitUuid,$parent,$actor,$audience,$text):array{
            $row=$this->repo->insert('comments',array('unit_uuid'=>$unitUuid,'parent_uuid'=>$parent,'author_id'=>$actor,'audience'=>$audience,'comment_text'=>$text,'status'=>'open','row_version'=>1));
            $this->audit->record('unit',$unitUuid,'translation_comment_added','success',array('comment_uuid'=>$row['uuid'],'audience'=>$audience));return $row;
        });
    }

    public function resolveComment(string $commentUuid,int $version,string $resolution,bool $affectsContext=false): array
    {
        $comment=$this->repo->find('comments',$commentUuid)??throw new InvalidArgumentException('Translation comment or contextual query not found.');
        if('open'!==(string)$comment['status']){throw new InvalidArgumentException('Only an open translation comment or contextual query can be resolved.');}
        $unit=$this->repo->find('units',(string)$comment['unit_uuid'])??throw new InvalidArgumentException('Translation unit for contextual query is unavailable.');
        $actor=get_current_user_id();$participant=false;
        foreach(array('translator_id','linguistic_reviewer_id','domain_reviewer_id') as $column){
            if($actor>0&&$actor===(int)($unit[$column]??0)&&$this->assignedActorIsCurrent($unit,$column,$actor)){$participant=true;break;}
        }
        if(!$participant&&!Authorization::allowed('manage',array('object'=>'unit','object_uuid'=>(string)$unit['uuid'],'record_version'=>(int)$unit['row_version']))){
            throw new InvalidArgumentException('Only a current translation participant or localization manager may resolve this contextual query.');
        }
        $resolution=sanitize_textarea_field($resolution);
        if(''===trim($resolution)||strlen($resolution)>10000){throw new InvalidArgumentException('Contextual-query resolution is empty or exceeds the bounded limit.');}
        $result=$this->tx->run(function()use($comment,$unit,$version,$resolution,$affectsContext,$actor):array{
            $updated=$this->repo->updateVersioned('comments',(string)$comment['uuid'],$version,array('status'=>'resolved','resolution_text'=>$resolution));
            $propagation=array('stale_units'=>0,'stale_content_links'=>0,'invalidated_bundles'=>0);
            if($affectsContext){
                $resourceUuid=(string)$unit['resource_uuid'];
                $propagation['stale_units']=$this->repo->markDependentUnitsStale($resourceUuid,'context_query_resolved');
                $propagation['stale_content_links']=DependencyInvalidator::markContentLinksStale($resourceUuid);
                $propagation['invalidated_bundles']=DependencyInvalidator::invalidateActiveBundles($resourceUuid);
            }
            $this->audit->record('unit',(string)$unit['uuid'],'translation_context_query_resolved','success',array(
                'comment_uuid'=>$comment['uuid'],'audience'=>$comment['audience'],'resolved_by'=>$actor,
                'affects_context'=>$affectsContext,'propagation'=>$propagation,
            ));
            $this->outbox->enqueue('TranslationContextQueryResolved','translation_unit',(string)$unit['uuid'],array(
                'unit_uuid'=>$unit['uuid'],'comment_uuid'=>$comment['uuid'],'affects_context'=>$affectsContext,'propagation'=>$propagation,
            ));
            return array('comment'=>$updated,'propagation'=>$propagation);
        });
        if($affectsContext&&array_sum(array_map('intval',(array)($result['propagation']??array())))>0){wp_cache_flush();}
        return $result;
    }

    public function targetText(array $unit):string
    {
        if(!empty($unit['secure_payload_id'])){return $this->repo->readSecurePayload((int)$unit['secure_payload_id'],(string)$unit['uuid'],'target_text');}
        return (string)$unit['target_text'];
    }

    private function addMemory(array $unit,array $resource):void
    {
        if(!in_array((string)$resource['data_class'],array('C1','C2'),true)||in_array((string)$resource['risk_class'],array('high','critical','private'),true)||RiskPolicy::requiresDomainReview((string)$resource['risk_class'],(string)$resource['domain_name'])){return;}
        $reuse=json_decode((string)($resource['translatability_json']??''),true);
        $license=is_array($reuse)?sanitize_key((string)($reuse['license_code']??'')):'';
        if(!is_array($reuse)||true!==($reuse['translation_memory_reuse_allowed']??false)||''===$license||strlen($license)>80){return;}
        $source=$this->resources->text($resource);$target=$this->targetText($unit);
        $provider=null;
        if(!empty($unit['provider_job_uuid'])){
            $job=$this->repo->find('vendor_jobs',(string)$unit['provider_job_uuid']);
            if(is_array($job)){$provider=array('job_uuid'=>$job['uuid'],'provider_key'=>$job['provider_key'],'model_version'=>$job['model_version'],'region_code'=>$job['region_code'],'status'=>$job['status']);}
        }
        $provenance=array(
            'resource_uuid'=>$resource['uuid'],'unit_uuid'=>$unit['uuid'],'project_uuid'=>$unit['project_uuid'],
            'source_version'=>(int)$resource['source_version'],'source_hash'=>$resource['source_hash'],
            'translator_id'=>(int)($unit['translator_id']??0),'linguistic_reviewer_id'=>(int)($unit['linguistic_reviewer_id']??0),'domain_reviewer_id'=>(int)($unit['domain_reviewer_id']??0),
            'provider'=>$provider,'license_code'=>$license,'translation_memory_reuse_allowed'=>true,
        );
        $encoded=wp_json_encode($provenance,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if(!is_string($encoded)||strlen($encoded)>262144){return;}
        $this->repo->insert('memory',array('source_locale'=>$resource['source_locale'],'target_locale'=>$unit['target_locale'],'source_segment'=>$source,'target_segment'=>$target,'source_hash'=>hash('sha256',$source),'context_hash'=>hash('sha256',(string)$resource['context']),'domain_name'=>$resource['domain_name'],'risk_class'=>$resource['risk_class'],'provenance_json'=>$encoded,'license_code'=>$license,'status'=>'approved','created_from_unit_uuid'=>$unit['uuid']));
    }

    private function assertMachineDraftProvenance(array $unit,array $resource,?string $providerJob): void
    {
        if(null===$providerJob||1!==preg_match('/^[a-f0-9-]{36}$/D',$providerJob)
            ||!RiskPolicy::machineTranslationAllowed((string)$resource['risk_class'],(string)$resource['data_class'],(string)$resource['domain_name'])){
            throw new InvalidArgumentException('Machine draft provenance is not eligible for this translation unit.');
        }
        $job=$this->repo->find('vendor_jobs',$providerJob);
        $units=is_array($job)?json_decode((string)($job['unit_uuids']??''),true):null;
        if(!is_array($job)||'validated'!==(string)$job['status']||!is_array($units)
            ||!in_array((string)$unit['uuid'],array_map('strval',$units),true)){
            throw new InvalidArgumentException('Machine draft must be bound to a validated vendor job containing this unit.');
        }
    }

    private function assertAssignedActor(array $unit,string $column,string $message):void
    {
        $assigned=(int)($unit[$column]??0);$actor=get_current_user_id();
        if($assigned<=0||$actor!==$assigned){throw new InvalidArgumentException($message);}
        if(!$this->assignedActorIsCurrent($unit,$column,$actor)){throw new InvalidArgumentException('The assigned translation role is inactive, conflicted, expired or no longer eligible.');}
    }

    private function assignedActorIsCurrent(array $unit,string $column,int $actor):bool
    {
        if($actor<=0||!function_exists('smc_membership_assertions')){return false;}
        $assertions=smc_membership_assertions($actor);$state=is_array($assertions)?strtolower((string)($assertions['state']??'')):'';
        $actorBound=is_array($assertions)&&isset($assertions['user_id'])&&(int)$assertions['user_id']===$actor;
        $fresh=true;if(is_array($assertions)&&isset($assertions['expires_at'])){$expiry=strtotime((string)$assertions['expires_at']);$fresh=false!==$expiry&&$expiry>time();}
        if(!is_array($assertions)||!$actorBound||!$fresh||!empty($assertions['suspended'])||!in_array($state,array('approved','active','verified'),true)){return false;}
        $role=match($column){'translator_id'=>'translator','linguistic_reviewer_id'=>'linguistic_reviewer','domain_reviewer_id'=>'domain_reviewer',default=>''};
        if(''===$role){return false;}
        foreach($this->repo->list('assignments',array('unit_uuid'=>$unit['uuid'],'assignee_id'=>$actor,'assignment_role'=>$role,'status'=>'active'),10) as $assignment){
            if(!((empty($assignment['expires_at'])||strtotime((string)$assignment['expires_at'])>=time())&&in_array((string)$assignment['conflict_status'],array('clear','disclosed-cleared'),true))){continue;}
            $qualification=json_decode((string)($assignment['qualification_json']??''),true);
            if(!is_array($qualification)){continue;}
            $resource=$this->repo->find('resources',(string)($unit['resource_uuid']??''));
            if(!is_array($resource)){continue;}
            $evidence=[
                'assignee_id'=>$actor,
                'role'=>$role,
                'target_locale'=>(string)($unit['target_locale']??''),
                'domain'=>(string)($resource['domain_name']??''),
                'risk_class'=>(string)($resource['risk_class']??''),
                'qualification'=>$qualification,
                'unit_uuid'=>(string)($unit['uuid']??''),
                'project_uuid'=>(string)($unit['project_uuid']??''),
                'assignment_uuid'=>(string)($assignment['uuid']??''),
                'verification_phase'=>'action-time',
            ];
            if(true===apply_filters('slto_verify_assignment_qualification',false,$evidence)){return true;}
        }
        return false;
    }
}
