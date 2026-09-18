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
        $reason=sanitize_key($reason);if(''===$reason||strlen($reason)>80){throw new RuntimeException('Localization privacy erasure reason is invalid or oversized.');}
        $requestId=Database::uuid();
        return $this->tx->run(function()use($userId,$reason,$requestId):string{
            $uuid=$this->jobs->enqueue('privacy_erasure','privacy-erasure-'.$userId.'-'.$requestId,['user_id'=>$userId,'reason'=>$reason,'request_id'=>$requestId]);
            $this->audit->record('privacy',(string)$userId,'privacy_erasure_queued','success',['reason'=>$reason,'job_uuid'=>$uuid,'request_id'=>$requestId],'privacy');
            return $uuid;
        });
    }

    public function processErasure(array $payload,array $job=[]): void
    {
        global $wpdb;$userId=(int)($payload['user_id']??0);if($userId<=0){return;}
        $hold=apply_filters('slto_privacy_erasure_hold',null,$userId,$payload);
        if(true===$hold){$this->audit->record('privacy',(string)$userId,'privacy_erasure_retained','success',['hold'=>true],'privacy');return;}
        $salt=wp_salt('auth');if(''===$salt){throw new RuntimeException('Privacy pseudonymization secret is unavailable.');}
        $pseudonym=$this->pseudonymId($userId,$salt);
        $this->tx->run(function()use($wpdb,$userId,$pseudonym):void{
            $now=Database::now();
            $run=function(string $sql,array $params)use($wpdb):void{
                $prepared=$wpdb->prepare($sql,...$params);
                if(!is_string($prepared)||false===$wpdb->query($prepared)){
                    throw new RuntimeException('Localization privacy erasure could not be completed atomically.');
                }
            };
            $run("UPDATE ".Database::table('comments')." SET comment_text=%s,author_id=0,row_version=row_version+1,updated_at=%s WHERE author_id=%d",['[ERASED]',$now,$userId]);
            $run("UPDATE ".Database::table('feedback')." SET suggestion_text=%s,reporter_id=0,row_version=row_version+1,updated_at=%s WHERE reporter_id=%d",['[ERASED]',$now,$userId]);
            $run("UPDATE ".Database::table('feedback')." SET assigned_to=%d,row_version=row_version+1,updated_at=%s WHERE assigned_to=%d",[$pseudonym,$now,$userId]);
            $run("UPDATE ".Database::table('assignments')." SET status='revoked',assignee_id=0,row_version=row_version+1,updated_at=%s WHERE assignee_id=%d",[$now,$userId]);
            $run("UPDATE ".Database::table('assignments')." SET created_by=%d,row_version=row_version+1,updated_at=%s WHERE created_by=%d",[$pseudonym,$now,$userId]);
            $run("UPDATE ".Database::table('units')." SET translator_id=NULL,row_version=row_version+1,updated_at=%s WHERE translator_id=%d",[$now,$userId]);
            $run("UPDATE ".Database::table('units')." SET linguistic_reviewer_id=NULL,row_version=row_version+1,updated_at=%s WHERE linguistic_reviewer_id=%d",[$now,$userId]);
            $run("UPDATE ".Database::table('units')." SET domain_reviewer_id=NULL,row_version=row_version+1,updated_at=%s WHERE domain_reviewer_id=%d",[$now,$userId]);
            $run("UPDATE ".Database::table('projects')." SET owner_id=%d,row_version=row_version+1,updated_at=%s WHERE owner_id=%d",[$pseudonym,$now,$userId]);
            $run("UPDATE ".Database::table('resources')." SET created_by=%d,row_version=row_version+1,updated_at=%s WHERE created_by=%d",[$pseudonym,$now,$userId]);
            $run("UPDATE ".Database::table('resources')." SET updated_by=%d,row_version=row_version+1,updated_at=%s WHERE updated_by=%d",[$pseudonym,$now,$userId]);
            $run("UPDATE ".Database::table('terminology')." SET created_by=%d,row_version=row_version+1,updated_at=%s WHERE created_by=%d",[$pseudonym,$now,$userId]);
            $run("UPDATE ".Database::table('terminology')." SET reviewer_id=%d,row_version=row_version+1,updated_at=%s WHERE reviewer_id=%d",[$pseudonym,$now,$userId]);
            $run("UPDATE ".Database::table('style_guides')." SET created_by=%d,row_version=row_version+1,updated_at=%s WHERE created_by=%d",[$pseudonym,$now,$userId]);
            $run("UPDATE ".Database::table('style_guides')." SET approved_by=%d,row_version=row_version+1,updated_at=%s WHERE approved_by=%d",[$pseudonym,$now,$userId]);
            $run("UPDATE ".Database::table('vendor_jobs')." SET created_by=%d,row_version=row_version+1,updated_at=%s WHERE created_by=%d",[$pseudonym,$now,$userId]);
            $run("UPDATE ".Database::table('bundles')." SET approved_by=%d,row_version=row_version+1,updated_at=%s WHERE approved_by=%d",[$pseudonym,$now,$userId]);
            $run("UPDATE ".Database::table('bundles')." SET activated_by=%d,row_version=row_version+1,updated_at=%s WHERE activated_by=%d",[$pseudonym,$now,$userId]);
            if(false===$wpdb->update(Database::table('qa_results'),['reviewer_id'=>$pseudonym],['reviewer_id'=>$userId])){
                throw new RuntimeException('Localization privacy erasure could not be completed atomically.');
            }
            $run("UPDATE ".Database::table('integration_evidence')." SET approved_by=%d,row_version=row_version+1,updated_at=%s WHERE approved_by=%d",[$pseudonym,$now,$userId]);
            $run("UPDATE ".Database::table('extraction_evidence')." SET approved_by=%d,row_version=row_version+1,updated_at=%s WHERE approved_by=%d",[$pseudonym,$now,$userId]);
            if(false===$wpdb->update(Database::table('qa_evidence'),['reviewer_id'=>$pseudonym],['reviewer_id'=>$userId])){
                throw new RuntimeException('Localization privacy erasure could not be completed atomically.');
            }
            $run("UPDATE ".Database::table('release_approvals')." SET approver_id=%d,row_version=row_version+1,updated_at=%s WHERE approver_id=%d",[$pseudonym,$now,$userId]);
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
    private function pseudonymId(int $userId,string $salt): int
    {
        // Keep operational pseudonyms in a deterministic high-ID namespace and
        // refuse any collision with a real WordPress account.
        $base=4_611_686_018_427_387_904;
        $span=1_152_921_504_606_846_975;
        for($counter=0;$counter<32;++$counter){
            $raw=(int)hexdec(substr(hash_hmac('sha256',$userId.'|'.$counter,$salt),0,15));
            $candidate=$base+($raw%$span);
            if($candidate!==$userId&&!get_userdata($candidate)){return $candidate;}
        }
        throw new RuntimeException('Privacy pseudonymization could not allocate a collision-free actor identity.');
    }
}
