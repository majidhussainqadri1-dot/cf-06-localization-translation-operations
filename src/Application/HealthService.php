<?php

declare(strict_types=1);

namespace Sabri\Localization\Application;

use RuntimeException;
use Sabri\Localization\Domain\Bundle\DeterministicBundle;
use Sabri\Localization\Infrastructure\Crypto;
use Sabri\Localization\Infrastructure\Database;

final class HealthService
{
    public function __construct(
        private readonly Crypto $crypto,
        private readonly MetricsService $metrics,
        private readonly IntegrationService $integrations
    ) {}

    public function report(): array
    {
        global $wpdb;
        $tables=[];
        foreach(Database::ENTITIES as $entity=>$suffix){
            $name=$wpdb->prefix.$suffix;
            $found=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$name));
            $tables[$entity]=''===(string)$wpdb->last_error&&$found===$name;
        }
        $schemaReady=!in_array(false,$tables,true);
        $signing=DeterministicBundle::sign(str_repeat('0',64));
        $integrations=$schemaReady?$this->integrations->readiness():array_fill_keys(\Sabri\Localization\Contract\IntegrationRegistry::required(),false);
        $localeReady=false;
        if($schemaReady){
            $default=(string)get_option('slto_default_locale','en-US');
            $missing=$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM ".Database::table('locales')." l WHERE l.status='enabled' AND l.locale_tag<>%s AND NOT EXISTS (SELECT 1 FROM ".Database::table('bundles')." b WHERE b.locale_tag=l.locale_tag AND b.status='active')",$default));
            if(''!==(string)$wpdb->last_error){throw new RuntimeException('Locale release readiness could not be verified.');}
            $localeReady=0===(int)$missing;
        }
        $gates=array_merge([
            'schema'=>$schemaReady,'encryption'=>$this->crypto->available(),'bundle_signing'=>null!==$signing,
            'staffing_approved'=>defined('SLTO_LOCALIZATION_STAFFING_APPROVED')&&true===SLTO_LOCALIZATION_STAFFING_APPROVED,
            'locale_release_ready'=>$localeReady,
        ],$integrations);
        return [
            'status'=>in_array(false,$gates,true)?'degraded':'ready','runtime_enabled'=>(bool)get_option('slto_runtime_enabled',false),
            'gates'=>$gates,'tables'=>$tables,'integrations'=>$integrations,'metrics'=>$schemaReady?$this->metrics->summary():[],
            'truth_status'=>['specified'=>'complete','coded'=>'complete-source-candidate','packaged'=>'requires-current-workflow-evidence',
                'automated_qa'=>'requires-current-workflow-evidence','staging_accepted'=>false,'live_deployed'=>false,'operational'=>false],
        ];
    }

    public function activationEligible(): array
    {
        $report=$this->report();
        return ['eligible'=>!in_array(false,$report['gates'],true),'gates'=>$report['gates']];
    }
}
