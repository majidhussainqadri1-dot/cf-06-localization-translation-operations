<?php

declare(strict_types=1);

namespace Sabri\Localization\Application;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Sabri\Localization\Infrastructure\Database;
use Sabri\Localization\Infrastructure\Repository\AuditRepository;
use Sabri\Localization\Infrastructure\Repository\LocalizationRepository;
use Sabri\Localization\Infrastructure\Transaction;

final class ReleaseApprovalService
{
    private const ROLES = array('release_operator','independent_reviewer');
    private const APPROVAL_TTL_SECONDS = 900;

    public function __construct(private readonly LocalizationRepository $repo,private readonly AuditRepository $audit,private readonly Transaction $tx){}

    public function approve(string $bundleUuid,array $input): array
    {
        $bundle=$this->repo->find('bundles',$bundleUuid)??throw new InvalidArgumentException('Locale bundle not found.');
        if(!in_array((string)$bundle['status'],array('approved','staged','canary','superseded','rolled_back'),true)){throw new InvalidArgumentException('Release approval requires an approved/staged bundle or an eligible prior rollback bundle.');}
        $role=sanitize_key((string)($input['approval_role']??''));$evidenceRef=sanitize_text_field((string)($input['evidence_ref']??''));$evidenceHash=strtolower(trim((string)($input['evidence_hash']??'')));$stepUpAt=trim((string)($input['step_up_at']??''));
        $stepUp=$this->strictUtcTimestamp($stepUpAt);$stepUpTimestamp=null===$stepUp?false:$stepUp->getTimestamp();
        if(!in_array($role,self::ROLES,true)||''===$evidenceRef||strlen($evidenceRef)>191||1!==preg_match('/^[a-f0-9]{64}$/D',$evidenceHash)||false===$stepUpTimestamp||abs(time()-$stepUpTimestamp)>self::APPROVAL_TTL_SECONDS){throw new InvalidArgumentException('Release approval evidence or recent strict UTC step-up proof is invalid.');}
        $actor=get_current_user_id();if($actor<=0){throw new InvalidArgumentException('Release approver identity is unavailable.');}
        $existing=$this->repo->list('release_approvals',array('bundle_uuid'=>$bundleUuid,'status'=>'valid'),20,0,'approved_at DESC');$reusable=null;
        foreach($existing as $approval){
            if($this->isFreshApproval($approval)){if((int)$approval['approver_id']===$actor||(string)$approval['approval_role']===$role){throw new InvalidArgumentException('Fresh release approvals require distinct actors and distinct approval roles.');}continue;}
            if((string)$approval['approval_role']===$role){$reusable=$approval;}
        }
        $evidence=array('bundle_uuid'=>$bundleUuid,'approval_role'=>$role,'approver_id'=>$actor,'evidence_ref'=>$evidenceRef,'evidence_hash'=>$evidenceHash,'step_up_at'=>$stepUp->format('Y-m-d H:i:s'));
        if(true!==apply_filters('slto_verify_release_approval_evidence',false,$evidence,$bundle)){throw new InvalidArgumentException('Release approval evidence could not be independently verified.');}
        return $this->tx->run(function()use($evidence,$reusable):array{$values=array_merge($evidence,array('status'=>'valid','approved_at'=>Database::now()));$row=is_array($reusable)?$this->repo->updateVersioned('release_approvals',(string)$reusable['uuid'],(int)$reusable['row_version'],$values):$this->repo->insert('release_approvals',array_merge($values,array('row_version'=>1)));$this->audit->record('bundle',(string)$evidence['bundle_uuid'],'release_approval_recorded','success',array('approval_role'=>$evidence['approval_role'],'approver_id'=>$evidence['approver_id'],'evidence_ref'=>$evidence['evidence_ref'],'evidence_hash'=>$evidence['evidence_hash'],'renewed'=>is_array($reusable)));return $row;});
    }

    public function assertDualApproval(string $bundleUuid): void
    {
        $bundle=$this->repo->find('bundles',$bundleUuid)??throw new InvalidArgumentException('Locale bundle not found for release approval verification.');
        $rows=$this->repo->list('release_approvals',array('bundle_uuid'=>$bundleUuid,'status'=>'valid'),20,0,'approved_at DESC');$roles=[];$actors=[];
        foreach($rows as $row){
            if(!$this->isFreshApproval($row)){continue;}
            $role=(string)($row['approval_role']??'');$actor=(int)($row['approver_id']??0);$evidenceHash=(string)($row['evidence_hash']??'');$evidenceRef=trim((string)($row['evidence_ref']??''));
            if(!in_array($role,self::ROLES,true)||$actor<=0||''===$evidenceRef||strlen($evidenceRef)>191||1!==preg_match('/^[a-f0-9]{64}$/D',$evidenceHash)){continue;}
            $evidence=array('bundle_uuid'=>$bundleUuid,'approval_role'=>$role,'approver_id'=>$actor,'evidence_ref'=>$evidenceRef,'evidence_hash'=>$evidenceHash,'step_up_at'=>(string)$row['step_up_at']);
            if(true!==apply_filters('slto_verify_release_approval_evidence',false,$evidence,$bundle)){continue;}
            if(isset($roles[$role])||isset($actors[$actor])){continue;}
            $roles[$role]=true;$actors[$actor]=true;
        }
        foreach(self::ROLES as $role){if(empty($roles[$role])){throw new InvalidArgumentException('Fresh independently verified dual release approval is incomplete: '.$role);}}
        if(count($actors)<2){throw new InvalidArgumentException('Fresh dual release approval requires two distinct actors.');}
    }

    private function isFreshApproval(array $row): bool
    {
        $now=time();$stepUp=strtotime((string)($row['step_up_at']??''));$approved=strtotime((string)($row['approved_at']??''));
        if(false===$stepUp||false===$approved){return false;}
        $stepAge=$now-$stepUp;$approvalAge=$now-$approved;
        return $stepAge>=-60&&$approvalAge>=-60&&$stepAge<=self::APPROVAL_TTL_SECONDS&&$approvalAge<=self::APPROVAL_TTL_SECONDS;
    }

    private function strictUtcTimestamp(string $value): ?DateTimeImmutable
    {
        if(1!==preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D',$value)){return null;}
        $date=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z',$value,new DateTimeZone('UTC'));
        return $date instanceof DateTimeImmutable&&$date->format('Y-m-d\TH:i:s\Z')===$value?$date:null;
    }
}
