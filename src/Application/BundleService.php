<?php

declare(strict_types=1);

namespace Sabri\Localization\Application;

use InvalidArgumentException;
use Sabri\Localization\Contract\IntegrationRegistry;
use Sabri\Localization\Domain\Bundle\DeterministicBundle;
use Sabri\Localization\Domain\Workflow\StateMachine;
use Sabri\Localization\Infrastructure\Database;
use Sabri\Localization\Infrastructure\Outbox;
use Sabri\Localization\Infrastructure\Repository\AuditRepository;
use Sabri\Localization\Infrastructure\Repository\LocalizationRepository;
use Sabri\Localization\Infrastructure\Transaction;

final class BundleService
{
    public function __construct(private readonly LocalizationRepository $repo,private readonly TranslationService $translations,private readonly QaService $qa,private readonly AuditRepository $audit,private readonly Outbox $outbox,private readonly Transaction $tx){}

    public function build(string $locale):array
    {
        $localeRecord=$this->repo->findOne('locales','locale_tag',$locale)??throw new InvalidArgumentException('Bundle locale is not registered.');
        $rows=$this->repo->releasedItems($locale);$items=[];$sources=[];
        foreach($rows as $row){$items[(string)$row['resource_key']]=['text'=>$this->translations->targetText($row),'source_hash'=>$row['source_hash'],'source_version'=>(int)$row['source_version'],'unit_uuid'=>$row['uuid']];$sources[]=['key'=>$row['resource_key'],'source_hash'=>$row['source_hash'],'source_version'=>(int)$row['source_version'],'unit_uuid'=>$row['uuid']];}
        $coverage=$this->repo->coverage($locale);$qa=$this->qa->bundle($locale,$items,$coverage);if(!$qa['passed']){throw new InvalidArgumentException('Locale bundle failed critical coverage or QA gates.');}
        $version=$this->repo->nextBundleVersion($locale);$built=DeterministicBundle::build($locale,$items,['bundle_version'=>$version,'direction'=>$localeRecord['direction'],'fallback'=>$localeRecord['fallback_tag'],'coverage'=>$coverage,'format_data_version'=>$localeRecord['format_data_version'],'plural_rules_version'=>$localeRecord['plural_rules_version'],'signing_key_id'=>DeterministicBundle::signingKeyId()]);$signature=DeterministicBundle::sign($built['sha256']);
        return $this->tx->run(function()use($locale,$version,$built,$signature,$sources,$coverage,$qa):array{
            $bundle=$this->repo->insert('bundles',array('locale_tag'=>$locale,'bundle_version'=>$version,'manifest_json'=>wp_json_encode($built['payload']['metadata']),'payload_json'=>$built['json'],'source_list_json'=>wp_json_encode($sources),'coverage'=>$coverage['coverage'],'critical_coverage'=>$coverage['critical_coverage'],'bundle_hash'=>$built['sha256'],'signature'=>$signature,'status'=>'built','previous_bundle_uuid'=>$this->repo->activeBundle($locale)['uuid']??null,'approved_by'=>null,'activated_by'=>null,'activated_at'=>null,'row_version'=>1));
            foreach($qa['checks'] as $check){$this->repo->insert('qa_results',array('target_type'=>'bundle','target_uuid'=>$bundle['uuid'],'rule_code'=>$check['rule'],'result'=>$check['result'],'severity'=>$check['severity'],'details_json'=>wp_json_encode($check['details']),'reviewer_id'=>get_current_user_id(),'fixed_at'=>null));}
            $this->audit->record('bundle',(string)$bundle['uuid'],'locale_bundle_built','success',array('locale'=>$locale,'version'=>$version,'hash'=>$built['sha256'],'coverage'=>$coverage,'signed'=>null!==$signature));$this->outbox->enqueue('LocaleBundleBuilt','bundle',(string)$bundle['uuid'],array('locale'=>$locale,'version'=>$version,'hash'=>$built['sha256'],'coverage'=>$coverage));return $bundle;
        });
    }

    public function recordQa(string $uuid,string $rule,string $result,string $severity,array $details=[]):array
    {
        if(!$this->repo->find('bundles',$uuid)){throw new InvalidArgumentException('Locale bundle not found.');}
        $rule=sanitize_key($rule);if(!in_array($rule,['in_context_qa','accessibility','rtl_ltr','links','browser','performance','security'],true)){throw new InvalidArgumentException('Bundle QA rule is invalid.');}
        if(!in_array($result,['pass','fail'],true)||!in_array($severity,['medium','high','critical'],true)){throw new InvalidArgumentException('Bundle QA result is invalid.');}
        $record=$this->repo->insert('qa_results',['target_type'=>'bundle','target_uuid'=>$uuid,'rule_code'=>$rule,'result'=>$result,'severity'=>$severity,'details_json'=>wp_json_encode($details),'reviewer_id'=>get_current_user_id(),'fixed_at'=>null]);
        $this->audit->record('bundle',$uuid,'bundle_qa_recorded','success',['rule'=>$rule,'result'=>$result,'severity'=>$severity,'qa_uuid'=>$record['uuid']??null]);
        return $record;
    }

    public function transition(string $uuid,string $to,int $version,string $reason=''):array
    {
        $bundle=$this->repo->find('bundles',$uuid)??throw new InvalidArgumentException('Locale bundle not found.');if(in_array($to,array('active','rolled_back','superseded'),true)){throw new InvalidArgumentException('Bundle activation and rollback require their dedicated commands.');}StateMachine::assert('bundle',(string)$bundle['status'],$to);if(in_array($to,array('staged','canary'),true)&&empty($bundle['signature'])){throw new InvalidArgumentException('Signed locale bundle is required for release.');}$results=$this->repo->list('qa_results',['target_type'=>'bundle','target_uuid'=>$uuid],500);if('automated_qa'===$to&&(empty($results)||in_array('fail',array_column($results,'result'),true))){throw new InvalidArgumentException('Automated bundle QA evidence is incomplete or failed.');}if('approved'===$to){$required=['in_context_qa','accessibility','rtl_ltr','links'];foreach($required as $rule){$passed=false;foreach($results as $result){if($result['rule_code']===$rule&&$result['result']==='pass'){$passed=true;break;}}if(!$passed){throw new InvalidArgumentException('Required in-context bundle QA evidence is missing: '.$rule);}}}
        $changes=['status'=>$to];if('approved'===$to){$changes['approved_by']=get_current_user_id();}
        $updated=$this->repo->updateVersioned('bundles',$uuid,$version,$changes);$this->audit->record('bundle',$uuid,'bundle_transition','success',array('from'=>$bundle['status'],'to'=>$to,'reason'=>$reason));return $updated;
    }

    public function activate(string $uuid,int $version):array
    {
        global $wpdb;$bundle=$this->repo->find('bundles',$uuid)??throw new InvalidArgumentException('Locale bundle not found.');if(!in_array($bundle['status'],array('staged','canary'),true)){throw new InvalidArgumentException('Bundle must complete staged or canary release before activation.');}if((float)$bundle['critical_coverage']<100.0||!DeterministicBundle::verify((string)$bundle['bundle_hash'],(string)$bundle['signature'])){throw new InvalidArgumentException('Bundle signature or critical coverage gate failed.');}
        $readiness=IntegrationRegistry::readiness();$required=['file00_membership','file20_shell','file24_assurance','file25_visual','file26_search','domain_contracts'];foreach($required as $key){if(empty($readiness[$key])){throw new InvalidArgumentException('Required localization integration is not accepted: '.$key);}}
        return $this->tx->run(function()use($wpdb,$bundle,$version):array{
            $table=Database::table('bundles');$previous=$this->repo->activeBundle((string)$bundle['locale_tag']);if(is_array($previous)){$this->repo->updateVersioned('bundles',(string)$previous['uuid'],(int)$previous['row_version'],['status'=>'superseded']);}
            $updated=$this->repo->updateVersioned('bundles',(string)$bundle['uuid'],$version,['status'=>'active','previous_bundle_uuid'=>$previous['uuid']??null,'activated_by'=>get_current_user_id(),'activated_at'=>Database::now()]);
            $units=Database::table('units');$released=$wpdb->query($wpdb->prepare("UPDATE {$units} SET status='released',released_bundle_uuid=%s,row_version=row_version+1,updated_at=%s WHERE target_locale=%s AND status='approved'",$bundle['uuid'],Database::now(),$bundle['locale_tag']));if(false===$released){throw new \RuntimeException('Approved translation units could not be released.');}
            wp_cache_flush();$this->audit->record('bundle',(string)$bundle['uuid'],'locale_bundle_activated','success',array('locale'=>$bundle['locale_tag'],'version'=>$bundle['bundle_version'],'previous'=>$previous['uuid']??null));$this->outbox->enqueue('LocaleBundleActivated','bundle',(string)$bundle['uuid'],array('locale'=>$bundle['locale_tag'],'version'=>(int)$bundle['bundle_version'],'hash'=>$bundle['bundle_hash'],'previous'=>$previous['uuid']??null));return $updated;
        });
    }

    public function rollback(string $activeUuid,string $targetUuid,int $activeVersion):array
    {
        global $wpdb;$active=$this->repo->find('bundles',$activeUuid)??throw new InvalidArgumentException('Active bundle not found.');$target=$this->repo->find('bundles',$targetUuid)??throw new InvalidArgumentException('Rollback bundle not found.');if('active'!==$active['status']||$active['locale_tag']!==$target['locale_tag']||!in_array($target['status'],array('superseded','rolled_back'),true)){throw new InvalidArgumentException('Rollback pair is invalid.');}if(!DeterministicBundle::verify((string)$target['bundle_hash'],(string)$target['signature'])){throw new InvalidArgumentException('Rollback bundle signature failed.');}
        return $this->tx->run(function()use($wpdb,$active,$target,$activeVersion):array{$rolled=$this->repo->updateVersioned('bundles',(string)$active['uuid'],$activeVersion,['status'=>'rolled_back']);$this->repo->updateVersioned('bundles',(string)$target['uuid'],(int)$target['row_version'],['status'=>'active','activated_by'=>get_current_user_id(),'activated_at'=>Database::now()]);wp_cache_flush();$this->audit->record('bundle',(string)$active['uuid'],'locale_bundle_rolled_back','success',array('target'=>$target['uuid'],'locale'=>$active['locale_tag']));$this->outbox->enqueue('LocaleBundleRolledBack','bundle',(string)$active['uuid'],array('locale'=>$active['locale_tag'],'from'=>$active['uuid'],'to'=>$target['uuid']));return ['rolled_back'=>$rolled,'active'=>$this->repo->find('bundles',(string)$target['uuid'])];});
    }

    public function publicBundle(string $locale):?array
    {
        $bundle=$this->repo->activeBundle($locale);if(!is_array($bundle)){return null;}return ['locale'=>$locale,'version'=>(int)$bundle['bundle_version'],'hash'=>$bundle['bundle_hash'],'signature'=>$bundle['signature'],'payload'=>json_decode((string)$bundle['payload_json'],true)];
    }
}
