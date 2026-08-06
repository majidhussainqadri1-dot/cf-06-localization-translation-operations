<?php

declare(strict_types=1);

namespace Sabri\Localization\Application;

use InvalidArgumentException;
use RuntimeException;
use Sabri\Localization\Domain\Locale\LocaleValidator;
use Sabri\Localization\Domain\Translation\RiskPolicy;
use Sabri\Localization\Domain\Workflow\StateMachine;
use Sabri\Localization\Infrastructure\Database;
use Sabri\Localization\Infrastructure\Outbox;
use Sabri\Localization\Infrastructure\Repository\AuditRepository;
use Sabri\Localization\Infrastructure\Repository\LocalizationRepository;
use Sabri\Localization\Infrastructure\Transaction;

final class ProjectService
{
    public function __construct(
        private readonly LocalizationRepository $repo,
        private readonly AuditRepository $audit,
        private readonly Outbox $outbox,
        private readonly Transaction $tx
    ) {}

    public function create(array $input): array
    {
        $name=sanitize_text_field((string)($input['name']??''));
        if(''===$name||strlen($name)>191){throw new InvalidArgumentException('Translation project name is required and bounded.');}
        $sourceLocale=LocaleValidator::canonicalize((string)($input['source_locale']??''));
        if(null===$sourceLocale){throw new InvalidArgumentException('Project source locale is invalid.');}
        $targets=array_values(array_unique(array_filter(array_map(static fn($v):?string=>LocaleValidator::canonicalize((string)$v),is_array($input['target_locales']??null)?$input['target_locales']:array()))));
        if(empty($targets)||count($targets)>25||in_array($sourceLocale,$targets,true)){throw new InvalidArgumentException('Project needs 1–25 distinct target locales.');}
        foreach(array_merge([$sourceLocale],$targets) as $tag){
            $locale=$this->repo->findOne('locales','locale_tag',$tag);
            if(!is_array($locale)||!in_array((string)$locale['status'],['tested','content_ready','enabled','degraded'],true)){throw new InvalidArgumentException('Project locale is not operationally eligible: '.$tag);}
        }
        $keys=array_values(array_unique(array_filter(array_map('strval',is_array($input['resource_keys']??null)?$input['resource_keys']:array()))));
        if(empty($keys)||count($keys)>5000){throw new InvalidArgumentException('Project scope requires 1–5000 translatable resources.');}
        $resources=[];
        foreach($keys as $key){
            $r=$this->repo->findOne('resources','resource_key',$key);
            if(!is_array($r)||'active'!==$r['status']||(string)$r['source_locale']!==$sourceLocale){throw new InvalidArgumentException('Project resource is unavailable or has a different source locale: '.$key);}
            $resources[]=$r;
        }
        $snapshot=array_map(static fn(array $r):array=>['uuid'=>$r['uuid'],'version'=>(int)$r['source_version'],'hash'=>$r['source_hash']],$resources);
        usort($snapshot,static fn($a,$b)=>strcmp($a['uuid'],$b['uuid']));
        $snapshotJson=wp_json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if(!is_string($snapshotJson)){throw new RuntimeException('Project source snapshot could not be encoded.');}
        $snapshotHash=hash('sha256',$snapshotJson);
        $riskCeiling=sanitize_key((string)($input['risk_ceiling']??'critical'));
        if(!in_array($riskCeiling,['low','medium','high','critical','private'],true)){throw new InvalidArgumentException('Project risk ceiling is invalid.');}
        $targetsJson=wp_json_encode($targets);$scopeJson=wp_json_encode(['resource_count'=>count($resources),'resource_keys'=>array_column($resources,'resource_key')]);
        if(!is_string($targetsJson)||!is_string($scopeJson)){throw new RuntimeException('Project scope could not be encoded.');}
        return $this->tx->run(function()use($input,$name,$sourceLocale,$targets,$targetsJson,$scopeJson,$resources,$snapshotHash,$riskCeiling):array{
            $project=$this->repo->insert('projects',[
                'name'=>$name,'description'=>sanitize_textarea_field((string)($input['description']??'')),'source_snapshot_hash'=>$snapshotHash,
                'source_locale'=>$sourceLocale,'target_locales'=>$targetsJson,'scope_json'=>$scopeJson,
                'priority'=>in_array((string)($input['priority']??'normal'),['low','normal','high','urgent'],true)?(string)$input['priority']:'normal',
                'risk_ceiling'=>$riskCeiling,'owner_id'=>get_current_user_id(),'provider_key'=>sanitize_key((string)($input['provider_key']??''))?:null,
                'release_target'=>sanitize_text_field((string)($input['release_target']??''))?:null,'due_at'=>$this->dateOrNull($input['due_at']??null),'status'=>'draft','row_version'=>1,
            ]);
            foreach($resources as $resource){
                $this->repo->insert('project_resources',['project_uuid'=>$project['uuid'],'resource_uuid'=>$resource['uuid'],'source_version'=>$resource['source_version'],'source_hash'=>$resource['source_hash']]);
                foreach($targets as $target){$this->repo->insert('units',['project_uuid'=>$project['uuid'],'resource_uuid'=>$resource['uuid'],'target_locale'=>$target,'source_version'=>$resource['source_version'],'source_hash'=>$resource['source_hash'],'target_text'=>null,'secure_payload_id'=>null,'status'=>'new','machine_draft'=>0,'qa_status'=>'pending','row_version'=>1]);}
            }
            $this->audit->record('project',(string)$project['uuid'],'translation_project_created','success',['source_locale'=>$sourceLocale,'target_locales'=>$targets,'resources'=>count($resources),'snapshot_hash'=>$snapshotHash]);
            return $project;
        });
    }

    public function assign(array $input): array
    {
        $unit=$this->repo->find('units',(string)($input['unit_uuid']??''))??throw new InvalidArgumentException('Translation unit not found.');
        $role=sanitize_key((string)($input['role']??''));
        if(!in_array($role,['translator','linguistic_reviewer','domain_reviewer'],true)){throw new InvalidArgumentException('Assignment role is invalid.');}
        $assignee=(int)($input['assignee_id']??0);
        if($assignee<=0||!get_userdata($assignee)){throw new InvalidArgumentException('Assignment user is invalid.');}
        $this->assertEligibleAssignee($assignee);
        $project=$this->repo->find('projects',(string)$unit['project_uuid'])??throw new InvalidArgumentException('Assignment project is unavailable.');
        if('active'!==$project['status']){throw new InvalidArgumentException('Assignments require an active translation project.');}
        $resource=$this->repo->find('resources',(string)$unit['resource_uuid'])??throw new InvalidArgumentException('Assignment resource is unavailable.');
        $qualification=is_array($input['qualification']??null)?$input['qualification']:[];
        if(empty($qualification['locale_competency'])||!in_array((string)$unit['target_locale'],array_map('strval',(array)$qualification['locale_competency']),true)){throw new InvalidArgumentException('Target-locale competency evidence is required.');}
        if('domain_reviewer'===$role&&RiskPolicy::requiresDomainReview((string)$resource['risk_class'],(string)$resource['domain_name'])&&!in_array((string)$resource['domain_name'],array_map('sanitize_key',(array)($qualification['domains']??[])),true)){throw new InvalidArgumentException('Qualified domain reviewer evidence is required.');}
        $conflict=sanitize_key((string)($input['conflict_status']??''));
        if(!in_array($conflict,['clear','disclosed-cleared'],true)){throw new InvalidArgumentException('A cleared conflict declaration is required.');}
        $due=$this->dateOrNull($input['due_at']??null);$expires=$this->dateOrNull($input['expires_at']??null);
        if(null!==$expires&&strtotime($expires)<=time()){throw new InvalidArgumentException('Assignment expiry must be in the future.');}
        if(null!==$due&&null!==$expires&&strtotime($due)>strtotime($expires)){throw new InvalidArgumentException('Assignment due date cannot follow its expiry.');}
        $qualificationJson=wp_json_encode($qualification,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if(!is_string($qualificationJson)||strlen($qualificationJson)>131072){throw new InvalidArgumentException('Qualification evidence is invalid or oversized.');}
        $existing=$this->repo->list('assignments',['unit_uuid'=>$unit['uuid']],20);
        foreach($existing as $assignment){if('active'===$assignment['status']&&(int)$assignment['assignee_id']===$assignee&&$assignment['assignment_role']!==$role){throw new InvalidArgumentException('Separation of duties prevents one person holding multiple roles on the same unit.');}}
        return $this->tx->run(function()use($unit,$role,$assignee,$qualificationJson,$conflict,$due,$expires,$existing):array{
            $sameRole=null;foreach($existing as $candidate){if($candidate['assignment_role']===$role){$sameRole=$candidate;break;}}
            if(is_array($sameRole)){
                if('active'===$sameRole['status']){throw new InvalidArgumentException('This unit role already has an active assignment.');}
                $assignment=$this->repo->updateVersioned('assignments',(string)$sameRole['uuid'],(int)$sameRole['row_version'],['assignee_id'=>$assignee,'qualification_json'=>$qualificationJson,'conflict_status'=>$conflict,'status'=>'active','due_at'=>$due,'expires_at'=>$expires,'created_by'=>get_current_user_id()]);
                $action='assignment_transferred';
            }else{
                $assignment=$this->repo->insert('assignments',['project_uuid'=>$unit['project_uuid'],'unit_uuid'=>$unit['uuid'],'assignee_id'=>$assignee,'assignment_role'=>$role,'locale_tag'=>$unit['target_locale'],'qualification_json'=>$qualificationJson,'conflict_status'=>$conflict,'status'=>'active','due_at'=>$due,'expires_at'=>$expires,'row_version'=>1,'created_by'=>get_current_user_id()]);
                $action='assignment_created';
            }
            $column=match($role){'translator'=>'translator_id','linguistic_reviewer'=>'linguistic_reviewer_id','domain_reviewer'=>'domain_reviewer_id'};
            $changes=[$column=>$assignee];if('translator'===$role&&in_array($unit['status'],['new','changed','stale'],true)){$changes['status']='assigned';}
            $this->repo->updateVersioned('units',(string)$unit['uuid'],(int)$unit['row_version'],$changes);
            $this->audit->record('unit',(string)$unit['uuid'],$action,'success',['role'=>$role,'assignee_id'=>$assignee,'assignment_uuid'=>$assignment['uuid']]);
            return $assignment;
        });
    }

    public function revokeAssignment(string $uuid,int $version,string $reason): array
    {
        $assignment=$this->repo->find('assignments',$uuid)??throw new InvalidArgumentException('Assignment not found.');
        $reason=sanitize_textarea_field($reason);if('active'!==$assignment['status']||''===trim($reason)){throw new InvalidArgumentException('Active assignment and revocation reason are required.');}
        return $this->tx->run(function()use($assignment,$version,$reason):array{
            $updated=$this->repo->updateVersioned('assignments',(string)$assignment['uuid'],$version,['status'=>'revoked']);
            $this->audit->record('unit',(string)$assignment['unit_uuid'],'assignment_revoked','success',['assignment_uuid'=>$assignment['uuid'],'role'=>$assignment['assignment_role'],'reason'=>$reason]);
            return $updated;
        });
    }

    public function transition(string $uuid,string $to,int $version,string $reason=''): array
    {
        global $wpdb;
        $project=$this->repo->find('projects',$uuid)??throw new InvalidArgumentException('Translation project not found.');
        StateMachine::assert('project',(string)$project['status'],$to);
        if('completed'===$to){$open=$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM ".Database::table('units')." WHERE project_uuid=%s AND status NOT IN ('released','retired')",$uuid));if(''!==(string)$wpdb->last_error){throw new RuntimeException('Project completion state could not be verified.');}if((int)$open>0){throw new InvalidArgumentException('Project has unfinished translation units.');}}
        return $this->tx->run(function()use($project,$to,$version,$reason):array{$updated=$this->repo->updateVersioned('projects',(string)$project['uuid'],$version,['status'=>$to]);$this->audit->record('project',(string)$project['uuid'],'translation_project_transition','success',['from'=>$project['status'],'to'=>$to,'reason'=>$reason]);return $updated;});
    }

    public function queue(int $assigneeId,string $role='',int $limit=100): array
    {
        $rows=$this->repo->list('assignments',['assignee_id'=>$assigneeId,'status'=>'active'],$limit,0,'due_at ASC');
        $now=time();$rows=array_values(array_filter($rows,static fn(array $r):bool=>empty($r['expires_at'])||strtotime((string)$r['expires_at'])>=$now));
        return ''===$role?$rows:array_values(array_filter($rows,static fn(array $r):bool=>$r['assignment_role']===$role));
    }

    private function assertEligibleAssignee(int $userId): void
    {
        if(!function_exists('smc_membership_assertions')){throw new InvalidArgumentException('File 00 membership assertions are unavailable.');}
        $a=smc_membership_assertions($userId);$state=is_array($a)?strtolower((string)($a['state']??'')):'';
        if(!is_array($a)||!empty($a['suspended'])||!in_array($state,['approved','active','verified'],true)){throw new InvalidArgumentException('Assignee does not hold a current approved membership assertion.');}
    }

    private function dateOrNull(mixed $value):?string{if(null===$value||''===$value){return null;}$timestamp=strtotime((string)$value);if(false===$timestamp){throw new InvalidArgumentException('Invalid project date.');}return gmdate('Y-m-d H:i:s',$timestamp);}
}
