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
        $metrics['critical_feedback']=$this->scalar("SELECT COUNT(*) FROM ".Database::table('feedback')." WHERE severity='critical' AND status<>'closed'");
        $metrics['dead_letter_jobs']=$this->scalar("SELECT COUNT(*) FROM ".Database::table('jobs')." WHERE status='dead_letter'");
        $metrics['dead_letter_events']=$this->scalar("SELECT COUNT(*) FROM ".Database::table('outbox')." WHERE status='dead_letter'");
        $coverage=[];foreach($this->repo->list('locales',[],200,0,'id ASC') as $locale){$coverage[$locale['locale_tag']]=$this->repo->coverage((string)$locale['locale_tag']);}$metrics['coverage']=$coverage;
        return $metrics;
    }

    private function scalar(string $sql): int
    {
        global $wpdb;$value=$wpdb->get_var($sql);if(''!==(string)$wpdb->last_error){throw new RuntimeException('Localization metrics query failed.');}return (int)$value;
    }
}
