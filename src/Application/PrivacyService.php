<?php

declare(strict_types=1);

namespace Sabri\Localization\Application;

use RuntimeException;
use Sabri\Localization\Infrastructure\Database;
use Sabri\Localization\Infrastructure\JobQueue;
use Sabri\Localization\Infrastructure\Repository\AuditRepository;
use Sabri\Localization\Infrastructure\Transaction;

final class PrivacyService
{
    public function __construct(private readonly JobQueue $jobs,private readonly AuditRepository $audit,private readonly Transaction $tx){}

    public function exportUser(int $userId,int $page=1,int $perPage=100): array
    {
        global $wpdb;
        $page=max(1,$page);$perPage=max(20,min(200,$perPage));$offset=($page-1)*$perPage;
        $queries=[
            'assignments'=>['SELECT uuid,project_uuid,unit_uuid,assignment_role,locale_tag,status,due_at,expires_at,created_by,created_at,updated_at FROM '.Database::table('assignments').' WHERE assignee_id=%d OR created_by=%d ORDER BY id ASC LIMIT %d OFFSET %d',2],
            'unit_roles'=>['SELECT uuid,project_uuid,resource_uuid,target_locale,status,translator_id,linguistic_reviewer_id,domain_reviewer_id,created_at,updated_at FROM '.Database::table('units').' WHERE translator_id=%d OR linguistic_reviewer_id=%d OR domain_reviewer_id=%d ORDER BY id ASC LIMIT %d OFFSET %d',3],
            'comments'=>['SELECT uuid,unit_uuid,audience,status,comment_text,created_at,updated_at FROM '.Database::table('comments').' WHERE author_id=%d ORDER BY id ASC LIMIT %d OFFSET %d',1],
            'feedback'=>['SELECT uuid,locale_tag,resource_key,route_path,category,severity,suggestion_text,status,outcome_text,assigned_to,created_at,updated_at FROM '.Database::table('feedback').' WHERE reporter_id=%d OR assigned_to=%d ORDER BY id ASC LIMIT %d OFFSET %d',2],
            'projects'=>['SELECT uuid,name,source_locale,target_locales,status,created_at,updated_at FROM '.Database::table('projects').' WHERE owner_id=%d ORDER BY id ASC LIMIT %d OFFSET %d',1],
            'resources_actor_metadata'=>['SELECT uuid,resource_key,source_locale,source_version,status,created_by,updated_by,created_at,updated_at FROM '.Database::table('resources').' WHERE created_by=%d OR updated_by=%d ORDER BY id ASC LIMIT %d OFFSET %d',2],
            'terminology_actor_metadata'=>['SELECT uuid,concept_id,domain_name,target_locale,status,created_by,reviewer_id,created_at,updated_at FROM '.Database::table('terminology').' WHERE created_by=%d OR reviewer_id=%d ORDER BY id ASC LIMIT %d OFFSET %d',2],
            'style_guide_actor_metadata'=>['SELECT uuid,locale_tag,domain_name,guide_version,status,created_by,approved_by,created_at,updated_at FROM '.Database::table('style_guides').' WHERE created_by=%d OR approved_by=%d ORDER BY id ASC LIMIT %d OFFSET %d',2],
            'vendor_job_actor_metadata'=>['SELECT uuid,provider_key,purpose,status,created_by,created_at,updated_at,purge_due_at FROM '.Database::table('vendor_jobs').' WHERE created_by=%d ORDER BY id ASC LIMIT %d OFFSET %d',1],
            'bundle_actor_metadata'=>['SELECT uuid,locale_tag,bundle_version,status,approved_by,activated_by,activated_at,created_at,updated_at FROM '.Database::table('bundles').' WHERE approved_by=%d OR activated_by=%d ORDER BY id ASC LIMIT %d OFFSET %d',2],
            'qa_result_actor_metadata'=>['SELECT uuid,target_type,target_uuid,rule_code,result,severity,reviewer_id,created_at FROM '.Database::table('qa_results').' WHERE reviewer_id=%d ORDER BY id ASC LIMIT %d OFFSET %d',1],
            'integration_evidence'=>['SELECT uuid,integration_key,contract_version,environment_name,approved_by,approved_at,expires_at,status FROM '.Database::table('integration_evidence').' WHERE approved_by=%d ORDER BY id ASC LIMIT %d OFFSET %d',1],
            'extraction_evidence'=>['SELECT uuid,owner_module,source_commit,environment_name,approved_by,approved_at,status FROM '.Database::table('extraction_evidence').' WHERE approved_by=%d ORDER BY id ASC LIMIT %d OFFSET %d',1],
            'qa_evidence'=>['SELECT uuid,target_type,target_uuid,environment_name,plugin_version,build_sha,test_id,result,reviewer_id,created_at FROM '.Database::table('qa_evidence').' WHERE reviewer_id=%d ORDER BY id ASC LIMIT %d OFFSET %d',1],
            'release_approvals'=>['SELECT uuid,bundle_uuid,approval_role,evidence_ref,evidence_hash,approver_id,approved_at,status FROM '.Database::table('release_approvals').' WHERE approver_id=%d ORDER BY id ASC LIMIT %d OFFSET %d',1],
            'retained_audit_metadata'=>['SELECT uuid,trace_id,object_type,object_key,action_name,actor_id,purpose,result,created_at FROM '.Database::table('audit').' WHERE actor_id=%d ORDER BY id ASC LIMIT %d OFFSET %d',1],
        ];
        $data=[];$done=true;
        foreach($queries as $key=>$definition){
            [$sql,$actorArgs]=$definition;
            $params=array_fill(0,$actorArgs,$userId);$params[]=$perPage;$params[]=$offset;
            $rows=$wpdb->get_results($wpdb->prepare($sql,...$params),ARRAY_A);
            if(''!==(string)$wpdb->last_error||!is_array($rows)){throw new RuntimeException('Localization privacy export failed.');}
            $data[$key]=$rows;$done=$done&&count($rows)<$perPage;
        }
        $data['retention_notice']=[[
            'name'=>'immutable_audit_actor_metadata',
            'status'=>'retained-for-security-governance-integrity',
            'explanation'=>'Audit-chain actor metadata is retained as immutable governance evidence and is disclosed in retained_audit_metadata.',
        ]];
        return ['data'=>$data,'done'=>$done];
    }

    public function requestErasure(int $userId,string $reason='user_request'): string
    {
        if($userId<=0){throw new RuntimeException('Localization privacy erasure user is invalid.');}
        $requestId=Database::uuid();
        $uuid=$this->jobs->enqueue('privacy_erasure','privacy-erasure-'.$userId.'-'.$requestId,['user_id'=>$userId,'reason'=>sanitize_key($reason),'request_id'=>$requestId]);
        $this->audit->record('privacy',(string)$userId,'privacy_erasure_queued','success',['reason'=>$reason,'job_uuid'=>$uuid,'request_id'=>$requestId],'privacy');return $uuid;
    }

    public function processErasure(array $payload,array $job=[]): void
    {
        global $wpdb;$userId=(int)($payload['user_id']??0);if($userId<=0){return;}
        $hold=apply_filters('slto_privacy_erasure_hold',null,$userId,$payload);
        if(true===$hold){$this->audit->record('privacy',(string)$userId,'privacy_erasure_retained','success',['hold'=>true],'privacy');return;}
        $pseudonym=(int)hexdec(substr(hash('sha256','slto|'.$userId),0,15));
        $this->tx->run(function()use($wpdb,$userId,$pseudonym):void{
            $ops=[
                $wpdb->update(Database::table('comments'),['comment_text'=>'[ERASED]','author_id'=>0],['author_id'=>$userId]),
                $wpdb->update(Database::table('feedback'),['suggestion_text'=>'[ERASED]','reporter_id'=>0],['reporter_id'=>$userId]),
                $wpdb->update(Database::table('feedback'),['assigned_to'=>$pseudonym,'updated_at'=>Database::now()],['assigned_to'=>$userId]),
                $wpdb->update(Database::table('assignments'),['status'=>'revoked','assignee_id'=>0,'updated_at'=>Database::now()],['assignee_id'=>$userId]),
                $wpdb->update(Database::table('assignments'),['created_by'=>$pseudonym,'updated_at'=>Database::now()],['created_by'=>$userId]),
                $wpdb->update(Database::table('units'),['translator_id'=>null,'updated_at'=>Database::now()],['translator_id'=>$userId]),
                $wpdb->update(Database::table('units'),['linguistic_reviewer_id'=>null,'updated_at'=>Database::now()],['linguistic_reviewer_id'=>$userId]),
                $wpdb->update(Database::table('units'),['domain_reviewer_id'=>null,'updated_at'=>Database::now()],['domain_reviewer_id'=>$userId]),
                $wpdb->update(Database::table('projects'),['owner_id'=>$pseudonym,'updated_at'=>Database::now()],['owner_id'=>$userId]),
                $wpdb->update(Database::table('resources'),['created_by'=>$pseudonym,'updated_at'=>Database::now()],['created_by'=>$userId]),
                $wpdb->update(Database::table('resources'),['updated_by'=>$pseudonym,'updated_at'=>Database::now()],['updated_by'=>$userId]),
                $wpdb->update(Database::table('terminology'),['created_by'=>$pseudonym,'updated_at'=>Database::now()],['created_by'=>$userId]),
                $wpdb->update(Database::table('terminology'),['reviewer_id'=>$pseudonym,'updated_at'=>Database::now()],['reviewer_id'=>$userId]),
                $wpdb->update(Database::table('style_guides'),['created_by'=>$pseudonym,'updated_at'=>Database::now()],['created_by'=>$userId]),
                $wpdb->update(Database::table('style_guides'),['approved_by'=>$pseudonym,'updated_at'=>Database::now()],['approved_by'=>$userId]),
                $wpdb->update(Database::table('vendor_jobs'),['created_by'=>$pseudonym,'updated_at'=>Database::now()],['created_by'=>$userId]),
                $wpdb->update(Database::table('bundles'),['approved_by'=>$pseudonym,'updated_at'=>Database::now()],['approved_by'=>$userId]),
                $wpdb->update(Database::table('bundles'),['activated_by'=>$pseudonym,'updated_at'=>Database::now()],['activated_by'=>$userId]),
                $wpdb->update(Database::table('qa_results'),['reviewer_id'=>$pseudonym],['reviewer_id'=>$userId]),
                $wpdb->update(Database::table('integration_evidence'),['approved_by'=>$pseudonym,'updated_at'=>Database::now()],['approved_by'=>$userId]),
                $wpdb->update(Database::table('extraction_evidence'),['approved_by'=>$pseudonym,'updated_at'=>Database::now()],['approved_by'=>$userId]),
                $wpdb->update(Database::table('qa_evidence'),['reviewer_id'=>$pseudonym],['reviewer_id'=>$userId]),
                $wpdb->update(Database::table('release_approvals'),['approver_id'=>$pseudonym,'updated_at'=>Database::now()],['approver_id'=>$userId]),
            ];
            if(in_array(false,$ops,true)){throw new RuntimeException('Localization privacy erasure could not be completed atomically.');}
            $this->audit->record('privacy',(string)$userId,'privacy_erasure_completed','success',['pseudonymized_operational_roles'=>true,'immutable_audit_metadata_retained'=>true],'privacy');
        });
        $jobUuid=(string)($job['uuid']??'');
        if(''!==$jobUuid){
            $scrubbed=wp_json_encode(['erased'=>true,'request_id_hash'=>hash('sha256',(string)($payload['request_id']??''))]);
            if(!is_string($scrubbed)||false===$wpdb->update(Database::table('jobs'),['payload_json'=>$scrubbed,'updated_at'=>Database::now()],['uuid'=>$jobUuid,'job_type'=>'privacy_erasure'])){
                throw new RuntimeException('Privacy erasure job payload could not be minimized after completion.');
            }
        }
    }
}
