<?php

declare(strict_types=1);

namespace Sabri\Localization\Application;

use RuntimeException;
use Sabri\Localization\Infrastructure\Database;
use Sabri\Localization\Infrastructure\Repository\LocalizationRepository;

final class MetricsService
{
    public function __construct(private readonly LocalizationRepository $repo) {}

    public function summary(): array
    {
        global $wpdb;
        $metrics=[];
        foreach(['resources','projects','units','terminology','memory','vendor_jobs','bundles','feedback','jobs','outbox'] as $entity){
            $table=Database::table($entity);$exists=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table))===$table;
            if(''!==(string)$wpdb->last_error){throw new RuntimeException('Localization metrics schema check failed.');}
            $metrics[$entity]=$exists?$this->scalar("SELECT COUNT(*) FROM {$table}"):null;
        }
        if(null===$metrics['units']){$metrics['stale_units']=$metrics['critical_feedback']=$metrics['dead_letter_jobs']=$metrics['dead_letter_events']=null;$metrics['coverage']=[];return $metrics;}
        $metrics['stale_units']=$this->scalar("SELECT COUNT(*) FROM ".Database::table('units')." WHERE status='stale'");
        $metrics['critical_feedback']=$this->scalar("SELECT COUNT(*) FROM ".Database::table('feedback')." WHERE severity='critical' AND status IN ('triaged','reproduced','correcting','reviewed','released','rolled_back')");
        $metrics['dead_letter_jobs']=$this->scalar("SELECT COUNT(*) FROM ".Database::table('jobs')." WHERE status='dead_letter'");
        $metrics['dead_letter_events']=$this->scalar("SELECT COUNT(*) FROM ".Database::table('outbox')." WHERE status='dead_letter'");
        $metrics['feedback_reopened']=$this->scalar("SELECT COUNT(*) FROM ".Database::table('outbox')." WHERE event_name='TranslationFeedbackReopened'");

        $unitStatus=[];$rows=$wpdb->get_results("SELECT status,COUNT(*) total FROM ".Database::table('units')." GROUP BY status ORDER BY status",ARRAY_A);
        if(''!==(string)$wpdb->last_error||!is_array($rows)){throw new RuntimeException('Localization unit-status metrics query failed.');}
        foreach($rows as $row){$unitStatus[(string)$row['status']]=(int)$row['total'];}$metrics['unit_status']=$unitStatus;

        $qaFailures=[];$rows=$wpdb->get_results("SELECT rule_code,COUNT(*) total FROM ".Database::table('qa_results')." WHERE result='fail' GROUP BY rule_code ORDER BY rule_code",ARRAY_A);
        if(''!==(string)$wpdb->last_error||!is_array($rows)){throw new RuntimeException('Localization QA-failure metrics query failed.');}
        foreach($rows as $row){$qaFailures[(string)$row['rule_code']]=(int)$row['total'];}
        $metrics['quality_issues_by_rule']=$qaFailures;

        $feedbackGroups=[];$f=Database::table('feedback');$r=Database::table('resources');
        $rows=$wpdb->get_results("SELECT f.locale_tag,COALESCE(r.domain_name,'unlinked') domain_name,COUNT(*) reports FROM {$f} f LEFT JOIN {$r} r ON r.resource_key=f.resource_key GROUP BY f.locale_tag,COALESCE(r.domain_name,'unlinked') ORDER BY reports DESC LIMIT 1001",ARRAY_A);
        if(''!==(string)$wpdb->last_error||!is_array($rows)){throw new RuntimeException('Localization feedback metrics query failed.');}
        $truncated=count($rows)>1000;if($truncated){$rows=array_slice($rows,0,1000);}
        foreach($rows as $row){$feedbackGroups[]=array('locale'=>(string)$row['locale_tag'],'domain'=>(string)$row['domain_name'],'reports'=>(int)$row['reports']);}
        $metrics['feedback_by_locale_domain']=array('groups'=>$feedbackGroups,'groups_truncated'=>$truncated);

        $turnaround=[];$u=Database::table('units');
        $rows=$wpdb->get_results("SELECT target_locale,COUNT(*) samples,AVG(TIMESTAMPDIFF(SECOND,created_at,updated_at)) avg_seconds FROM {$u} WHERE status='released' GROUP BY target_locale ORDER BY target_locale",ARRAY_A);
        if(''!==(string)$wpdb->last_error||!is_array($rows)){throw new RuntimeException('Localization turnaround metrics query failed.');}
        foreach($rows as $row){$turnaround[(string)$row['target_locale']]=array('samples'=>(int)$row['samples'],'released_unit_cycle_avg_seconds'=>null===$row['avg_seconds']?null:round((float)$row['avg_seconds'],2));}
        $metrics['turnaround']=$turnaround;

        $eligibleSourceWords=[];$eligibleTotalWords=0;$resourceRows=$wpdb->get_results("SELECT uuid,source_text FROM {$r} WHERE status='active' AND data_class IN ('C1','C2','C3') AND risk_class<>'private' ORDER BY id ASC LIMIT 100001",ARRAY_A);
        if(''!==(string)$wpdb->last_error||!is_array($resourceRows)){throw new RuntimeException('Localization word-workload metrics query failed.');}
        $wordInventoryTruncated=count($resourceRows)>100000;if($wordInventoryTruncated){$resourceRows=array_slice($resourceRows,0,100000);}
        foreach($resourceRows as $row){$words=$this->wordCount((string)$row['source_text']);$eligibleSourceWords[(string)$row['uuid']]=$words;$eligibleTotalWords+=$words;}

        $coverage=[];$wordWorkload=[];
        foreach($this->repo->list('locales',[],200,0,'id ASC') as $locale){
            $tag=(string)$locale['locale_tag'];$cov=$this->repo->coverage($tag);$cov['missing']=max(0,(int)$cov['total']-(int)$cov['translated']);$cov['critical_missing']=max(0,(int)$cov['critical_total']-(int)$cov['critical_translated']);$coverage[$tag]=$cov;
            $currentRows=$wpdb->get_col($wpdb->prepare("SELECT DISTINCT u.resource_uuid FROM {$u} u JOIN {$r} r ON r.uuid=u.resource_uuid WHERE u.target_locale=%s AND u.status IN ('approved','released') AND r.status='active' AND u.source_hash=r.source_hash AND u.source_version=r.source_version",$tag));
            if(''!==(string)$wpdb->last_error||!is_array($currentRows)){throw new RuntimeException('Localization current-word coverage query failed.');}
            $currentSet=array_fill_keys(array_map('strval',$currentRows),true);
            $staleRows=$wpdb->get_col($wpdb->prepare("SELECT DISTINCT u.resource_uuid FROM {$u} u JOIN {$r} r ON r.uuid=u.resource_uuid WHERE u.target_locale=%s AND r.status='active' AND (u.status='stale' OR u.source_hash<>r.source_hash OR u.source_version<>r.source_version)",$tag));
            if(''!==(string)$wpdb->last_error||!is_array($staleRows)){throw new RuntimeException('Localization stale-word coverage query failed.');}
            $staleSet=array_fill_keys(array_map('strval',$staleRows),true);
            $currentWords=0;$staleWords=0;
            foreach($eligibleSourceWords as $resourceUuid=>$words){
                if(isset($currentSet[$resourceUuid])){$currentWords+=$words;}
                elseif(isset($staleSet[$resourceUuid])){$staleWords+=$words;}
            }
            $wordWorkload[$tag]=array(
                'eligible_source_words'=>$eligibleTotalWords,
                'current_source_words'=>$currentWords,
                'stale_source_words'=>$staleWords,
                'missing_source_words'=>max(0,$eligibleTotalWords-$currentWords),
            );
        }
        $metrics['coverage']=$coverage;
        $metrics['word_workload']=array(
            'definition'=>'Unicode alphanumeric token estimate over active non-private C1-C3 source text; missing includes stale material until a current approved/released unit exists.',
            'restricted_material_excluded'=>true,
            'inventory_truncated'=>$wordInventoryTruncated,
            'by_locale'=>$wordWorkload,
        );
        $metrics['definitions']=array(
            'turnaround'=>'Average created_at-to-updated_at elapsed seconds for currently released units; aggregate only, not translator surveillance.',
            'quality_issues_by_rule'=>'Count of failed QA rows by rule code; terminology and placeholders remain separately visible.',
            'feedback_by_locale_domain'=>'Aggregate report counts; unlinked means no current resource_key-domain join.',
            'denominators'=>'Coverage uses active canonical resources; word workload excludes C4/C5/private source material.',
        );
        return $metrics;
    }

    private function wordCount(string $text): int
    {
        $matched=preg_match_all("/[\\p{L}\\p{N}]+(?:[’'-][\\p{L}\\p{N}]+)*/u",$text,$matches);
        return false===$matched?0:(int)$matched;
    }

    private function scalar(string $sql): int
    {
        global $wpdb;$value=$wpdb->get_var($sql);if(''!==(string)$wpdb->last_error){throw new RuntimeException('Localization metrics query failed.');}return (int)$value;
    }
}
