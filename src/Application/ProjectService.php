<?php

declare(strict_types=1);

namespace Sabri\Localization\Application;

use InvalidArgumentException;
use Sabri\Localization\Domain\Locale\LocaleValidator;
use Sabri\Localization\Infrastructure\Database;
use Sabri\Localization\Infrastructure\Outbox;
use Sabri\Localization\Infrastructure\Repository\AuditRepository;
use Sabri\Localization\Infrastructure\Repository\LocalizationRepository;
use Sabri\Localization\Infrastructure\Transaction;

final class ProjectService
{
    public function __construct(private readonly LocalizationRepository $repo,private readonly AuditRepository $audit,private readonly Outbox $outbox,private readonly Transaction $tx){}

    public function create(array $input):array
    {
        $name=sanitize_text_field((string)($input['name']??''));if(''===$name){throw new InvalidArgumentException('Translation project name is required.');}
        $sourceLocale=LocaleValidator::canonicalize((string)($input['source_locale']??''));if(null===$sourceLocale){throw new InvalidArgumentException('Project source locale is invalid.');}
        $targets=array_values(array_unique(array_filter(array_map(static fn($v):?string=>LocaleValidator::canonicalize((string)$v),is_array($input['target_locales']??null)?$input['target_locales']:array()))));
        if(empty($targets)||in_array($sourceLocale,$targets,true)){throw new InvalidArgumentException('Project needs one or more distinct target locales.');}
        foreach(array_merge(array($sourceLocale),$targets) as $tag){if(!$this->repo->findOne('locales','locale_tag',$tag)){throw new InvalidArgumentException('Project locale is not registered: '.$tag);}}
        $keys=array_values(array_unique(array_filter(array_map('strval',is_array($input['resource_keys']??null)?$input['resource_keys']:array()))));if(empty($keys)){throw new InvalidArgumentException('Project scope requires translatable resources.');}
        $resources=[];foreach($keys as $key){$r=$this->repo->findOne('resources','resource_key',$key);if(!is_array($r)||'active'!==$r['status']){throw new InvalidArgumentException('Project resource is unavailable: '.$key);}$resources[]=$r;}
        $snapshot=array_map(static fn(array $r):array=>array('uuid'=>$r['uuid'],'version'=>(int)$r['source_version'],'hash'=>$r['source_hash']),$resources);usort($snapshot,static fn($a,$b)=>strcmp($a['uuid'],$b['uuid']));$snapshotHash=hash('sha256',wp_json_encode($snapshot));
        return $this->tx->run(function()use($input,$name,$sourceLocale,$targets,$resources,$snapshotHash):array{
            $project=$this->repo->insert('projects',array('name'=>$name,'description'=>sanitize_textarea_field((string)($input['description']??'')),'source_snapshot_hash'=>$snapshotHash,'source_locale'=>$sourceLocale,'target_locales'=>wp_json_encode($targets),'scope_json'=>wp_json_encode(array('resource_count'=>count($resources),'resource_keys'=>array_column($resources,'resource_key'))),'priority'=>in_array((string)($input['priority']??'normal'),array('low','normal','high','urgent'),true)?(string)$input['priority']:'normal','risk_ceiling'=>sanitize_key((string)($input['risk_ceiling']??'critical')),'owner_id'=>get_current_user_id(),'provider_key'=>sanitize_key((string)($input['provider_key']??''))?:null,'release_target'=>sanitize_text_field((string)($input['release_target']??''))?:null,'due_at'=>$this->dateOrNull($input['due_at']??null),'status'=>'draft','row_version'=>1));
            foreach($resources as $resource){
                $this->repo->insert('project_resources',array('project_uuid'=>$project['uuid'],'resource_uuid'=>$resource['uuid'],'source_version'=>$resource['source_version'],'source_hash'=>$resource['source_hash']));
                foreach($targets as $target){$this->repo->insert('units',array('project_uuid'=>$project['uuid'],'resource_uuid'=>$resource['uuid'],'target_locale'=>$target,'source_version'=>$resource['source_version'],'source_hash'=>$resource['source_hash'],'target_text'=>null,'secure_payload_id'=>null,'status'=>'new','machine_draft'=>0,'qa_status'=>'pending','row_version'=>1));}
            }
            $this->audit->record('project',(string)$project['uuid'],'translation_project_created','success',array('source_locale'=>$sourceLocale,'target_locales'=>$targets,'resources'=>count($resources),'snapshot_hash'=>$snapshotHash));
            return $project;
        });
    }

    public function assign(array $input):array
    {
        $unit=$this->repo->find('units',(string)($input['unit_uuid']??''))??throw new InvalidArgumentException('Translation unit not found.');
        $role=sanitize_key((string)($input['role']??''));if(!in_array($role,array('translator','linguistic_reviewer','domain_reviewer'),true)){throw new InvalidArgumentException('Assignment role is invalid.');}
        $assignee=(int)($input['assignee_id']??0);if($assignee<=0||!get_userdata($assignee)){throw new InvalidArgumentException('Assignment user is invalid.');}
        $project=$this->repo->find('projects',(string)$unit['project_uuid'])??throw new InvalidArgumentException('Assignment project is unavailable.');if('active'!==$project['status']){throw new InvalidArgumentException('Assignments require an active translation project.');}$resource=$this->repo->find('resources',(string)$unit['resource_uuid'])??throw new InvalidArgumentException('Assignment resource is unavailable.');
        $existing=$this->repo->list('assignments',array('unit_uuid'=>$unit['uuid']),20);
        foreach($existing as $assignment){if((int)$assignment['assignee_id']===$assignee && $assignment['assignment_role']!==$role){throw new InvalidArgumentException('Separation of duties prevents one person holding multiple roles on the same unit.');}}
        $qualification=is_array($input['qualification']??null)?$input['qualification']:array();
        if(in_array($role,array('linguistic_reviewer','domain_reviewer'),true)&&empty($qualification['locale_competency'])){throw new InvalidArgumentException('Reviewer locale competency evidence is required.');}
        if('domain_reviewer'===$role&&empty($qualification['domains'])&&\Sabri\Localization\Domain\Translation\RiskPolicy::requiresDomainReview((string)$resource['risk_class'],(string)$resource['domain_name'])){throw new InvalidArgumentException('Qualified domain reviewer evidence is required.');}
        return $this->tx->run(function()use($unit,$role,$assignee,$qualification,$input):array{
            $assignment=$this->repo->insert('assignments',array('project_uuid'=>$unit['project_uuid'],'unit_uuid'=>$unit['uuid'],'assignee_id'=>$assignee,'assignment_role'=>$role,'locale_tag'=>$unit['target_locale'],'qualification_json'=>wp_json_encode($qualification),'conflict_status'=>'clear','status'=>'active','due_at'=>$this->dateOrNull($input['due_at']??null),'expires_at'=>$this->dateOrNull($input['expires_at']??null),'row_version'=>1,'created_by'=>get_current_user_id()));
            $column=match($role){'translator'=>'translator_id','linguistic_reviewer'=>'linguistic_reviewer_id','domain_reviewer'=>'domain_reviewer_id'};
            $changes=[$column=>$assignee];if('translator'===$role&&in_array($unit['status'],array('new','changed','stale'),true)){$changes['status']='assigned';}
            $this->repo->updateVersioned('units',(string)$unit['uuid'],(int)$unit['row_version'],$changes);
            $this->audit->record('unit',(string)$unit['uuid'],'assignment_created','success',array('role'=>$role,'assignee_id'=>$assignee,'assignment_uuid'=>$assignment['uuid']));
            return $assignment;
        });
    }

    public function transition(string $uuid,string $to,int $version,string $reason=''):array
    {
        $project=$this->repo->find('projects',$uuid)??throw new InvalidArgumentException('Translation project not found.');
        \Sabri\Localization\Domain\Workflow\StateMachine::assert('project',(string)$project['status'],$to);if('completed'===$to){global $wpdb;$units=\Sabri\Localization\Infrastructure\Database::table('units');$open=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$units} WHERE project_uuid=%s AND status NOT IN ('released','retired')",$uuid));if($open>0){throw new InvalidArgumentException('Project has unfinished translation units.');}}
        return $this->tx->run(function()use($project,$to,$version,$reason):array{
            $updated=$this->repo->updateVersioned('projects',(string)$project['uuid'],$version,['status'=>$to]);
            $this->audit->record('project',(string)$project['uuid'],'translation_project_transition','success',['from'=>$project['status'],'to'=>$to,'reason'=>$reason]);
            return $updated;
        });
    }

    public function queue(int $assigneeId,string $role='',int $limit=100):array
    {
        $rows=$this->repo->list('assignments',array('assignee_id'=>$assigneeId,'status'=>'active'),$limit,0,'due_at ASC');
        return ''===$role?$rows:array_values(array_filter($rows,static fn(array $r):bool=>$r['assignment_role']===$role));
    }

    private function dateOrNull(mixed $value):?string
    {
        if(null===$value||''===$value){return null;}$timestamp=strtotime((string)$value);if(false===$timestamp){throw new InvalidArgumentException('Invalid project date.');}return gmdate('Y-m-d H:i:s',$timestamp);
    }
}
