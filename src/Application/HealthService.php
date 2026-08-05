<?php

declare(strict_types=1);

namespace Sabri\Localization\Application;

use Sabri\Localization\Contract\IntegrationRegistry;
use Sabri\Localization\Domain\Bundle\DeterministicBundle;
use Sabri\Localization\Infrastructure\Crypto;
use Sabri\Localization\Infrastructure\Database;

final class HealthService
{
    public function __construct(private readonly Crypto $crypto,private readonly MetricsService $metrics){}
    public function report():array
    {
        global $wpdb;$tables=[];foreach(Database::ENTITIES as $entity=>$suffix){$name=$wpdb->prefix.$suffix;$tables[$entity]=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$name))===$name;}
        $signing=DeterministicBundle::sign(str_repeat('0',64));$integrations=IntegrationRegistry::readiness();$localeReady=true;if(!in_array(false,$tables,true)){global $wpdb;$localeTable=Database::table('locales');$bundleTable=Database::table('bundles');$default=(string)get_option('slto_default_locale','en-US');$missing=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$localeTable} l WHERE l.status='enabled' AND l.locale_tag<>%s AND NOT EXISTS (SELECT 1 FROM {$bundleTable} b WHERE b.locale_tag=l.locale_tag AND b.status='active')",$default));$localeReady=0===$missing;}$gates=['schema'=>!in_array(false,$tables,true),'encryption'=>$this->crypto->available(),'bundle_signing'=>null!==$signing,'staffing_approved'=>defined('SLTO_LOCALIZATION_STAFFING_APPROVED')&&true===SLTO_LOCALIZATION_STAFFING_APPROVED,'locale_release_ready'=>$localeReady,'file00_membership'=>!empty($integrations['file00_membership']),'file20_shell'=>!empty($integrations['file20_shell']),'file24_assurance'=>!empty($integrations['file24_assurance']),'file25_visual'=>!empty($integrations['file25_visual']),'file26_search'=>!empty($integrations['file26_search']),'domain_contracts'=>!empty($integrations['domain_contracts'])];
        return ['status'=>in_array(false,$gates,true)?'degraded':'ready','runtime_enabled'=>(bool)get_option('slto_runtime_enabled',false),'gates'=>$gates,'tables'=>$tables,'integrations'=>$integrations,'metrics'=>$this->metrics->summary(),'truth_status'=>['specified'=>'complete','coded'=>'source-candidate','packaged'=>'requires-current-workflow-evidence','automated_qa'=>'requires-current-workflow-evidence','staging_accepted'=>false,'live_deployed'=>false,'operational'=>false]];
    }
    public function activationEligible():array{$report=$this->report();return ['eligible'=>!in_array(false,$report['gates'],true),'gates'=>$report['gates']];}
}
