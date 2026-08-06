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
        global $wpdb;$page=max(1,$page);$perPage=max(20,min(200,$perPage));$offset=($page-1)*$perPage;
        $queries=[
            'assignments'=>['SELECT uuid,project_uuid,unit_uuid,assignment_role,locale_tag,status,due_at,expires_at,created_at,updated_at FROM '.Database::table('assignments').' WHERE assignee_id=%d ORDER BY id ASC LIMIT %d OFFSET %d'],
            'comments_metadata'=>['SELECT uuid,unit_uuid,audience,status,created_at,updated_at FROM '.Database::table('comments').' WHERE author_id=%d ORDER BY id ASC LIMIT %d OFFSET %d'],
            'feedback'=>['SELECT uuid,locale_tag,resource_key,route_path,category,severity,status,outcome_text,created_at,updated_at FROM '.Database::table('feedback').' WHERE reporter_id=%d ORDER BY id ASC LIMIT %d OFFSET %d'],
            'projects'=>['SELECT uuid,name,source_locale,target_locales,status,created_at,updated_at FROM '.Database::table('projects').' WHERE owner_id=%d ORDER BY id ASC LIMIT %d OFFSET %d'],
            'release_approvals'=>['SELECT uuid,bundle_uuid,approval_role,evidence_ref,evidence_hash,approved_at,status FROM '.Database::table('release_approvals').' WHERE approver_id=%d ORDER BY id ASC LIMIT %d OFFSET %d'],
        ];
        $data=[];$done=true;
        foreach($queries as $key=>$parts){$rows=$wpdb->get_results($wpdb->prepare($parts[0],$userId,$perPage,$offset),ARRAY_A);if(''!==(string)$wpdb->last_error||!is_array($rows)){throw new RuntimeException('Localization privacy export failed.');}$data[$key]=$rows;$done=$done&&count($rows)<$perPage;}
        return ['data'=>$data,'done'=>$done];
    }

    public function requestErasure(int $userId,string $reason='user_request'): string
    {
        $uuid=$this->jobs->enqueue('privacy_erasure','privacy-erasure-'.$userId,['user_id'=>$userId,'reason'=>sanitize_key($reason)]);
        $this->audit->record('privacy',(string)$userId,'privacy_erasure_queued','success',['reason'=>$reason,'job_uuid'=>$uuid],'privacy');return $uuid;
    }

    public function processErasure(array $payload): void
    {
        global $wpdb;$userId=(int)($payload['user_id']??0);if($userId<=0){return;}
        $hold=apply_filters('slto_privacy_erasure_hold',null,$userId,$payload);
        if(true===$hold){$this->audit->record('privacy',(string)$userId,'privacy_erasure_retained','success',['hold'=>true],'privacy');return;}
        $pseudonym=(int)sprintf('%u',crc32('slto|'.$userId));
        $this->tx->run(function()use($wpdb,$userId,$pseudonym):void{
            $ops=[
                $wpdb->update(Database::table('comments'),['comment_text'=>'[ERASED]','author_id'=>0],['author_id'=>$userId]),
                $wpdb->update(Database::table('feedback'),['suggestion_text'=>'[ERASED]','reporter_id'=>0],['reporter_id'=>$userId]),
                $wpdb->update(Database::table('assignments'),['status'=>'revoked','assignee_id'=>0,'updated_at'=>Database::now()],['assignee_id'=>$userId]),
                $wpdb->update(Database::table('projects'),['owner_id'=>$pseudonym,'updated_at'=>Database::now()],['owner_id'=>$userId]),
                $wpdb->update(Database::table('terminology'),['created_by'=>$pseudonym,'updated_at'=>Database::now()],['created_by'=>$userId]),
                $wpdb->update(Database::table('terminology'),['reviewer_id'=>$pseudonym,'updated_at'=>Database::now()],['reviewer_id'=>$userId]),
                $wpdb->update(Database::table('style_guides'),['created_by'=>$pseudonym,'updated_at'=>Database::now()],['created_by'=>$userId]),
                $wpdb->update(Database::table('style_guides'),['approved_by'=>$pseudonym,'updated_at'=>Database::now()],['approved_by'=>$userId]),
                $wpdb->update(Database::table('vendor_jobs'),['created_by'=>$pseudonym,'updated_at'=>Database::now()],['created_by'=>$userId]),
                $wpdb->update(Database::table('qa_evidence'),['reviewer_id'=>$pseudonym],['reviewer_id'=>$userId]),
                $wpdb->update(Database::table('release_approvals'),['approver_id'=>$pseudonym,'updated_at'=>Database::now()],['approver_id'=>$userId]),
            ];
            if(in_array(false,$ops,true)){throw new RuntimeException('Localization privacy erasure could not be completed atomically.');}
            $this->audit->record('privacy',(string)$userId,'privacy_erasure_completed','success',['pseudonymized_operational_roles'=>true],'privacy');
        });
    }
}
