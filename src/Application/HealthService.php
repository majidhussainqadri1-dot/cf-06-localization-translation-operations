<?php

declare(strict_types=1);

namespace Sabri\Localization\Application;

use RuntimeException;
use Sabri\Localization\Domain\Bundle\DeterministicBundle;
use Sabri\Localization\Infrastructure\Crypto;
use Sabri\Localization\Infrastructure\Database;

final class HealthService
{
    private const PRODUCTION_EVIDENCE = array(
        'staging_acceptance',
        'rollback_restore_rehearsal',
        'exact_package_parity',
        'security_privacy_acceptance',
        'accessibility_acceptance',
        'performance_acceptance',
    );

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
        if($schemaReady){
            try{\Sabri\Localization\Infrastructure\Activator::assertRuntimeSchemaParity();}
            catch(\Throwable){$schemaReady=false;}
        }
        $signing=DeterministicBundle::sign(str_repeat('0',64));
        $environment=IntegrationService::deploymentEnvironment();
        $environmentReady=null!==$environment;
        $integrations=$schemaReady&&$environmentReady
            ?$this->integrations->readiness($environment)
            :array_fill_keys(\Sabri\Localization\Contract\IntegrationRegistry::required(),false);
        $localeReady=false;
        if($schemaReady){
            $default=(string)get_option('slto_default_locale','en-US');
            $missing=$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM ".Database::table('locales')." l WHERE l.status='enabled' AND l.locale_tag<>%s AND NOT EXISTS (SELECT 1 FROM ".Database::table('bundles')." b WHERE b.locale_tag=l.locale_tag AND b.status='active')",$default));
            if(''!==(string)$wpdb->last_error){throw new RuntimeException('Locale release readiness could not be verified.');}
            $localeReady=0===(int)$missing;
        }
        $productionEvidenceReady=true;
        $productionEvidence=array();
        $stagingAccepted=false;
        $liveParityVerified=false;
        $installedSchema=(string)get_option('slto_schema_version','0.0.0');
        $installedContract=(string)get_option('slto_contract_version','0.0.0');
        $configuredSourceCommit=defined('SLTO_DEPLOYED_SOURCE_COMMIT')?(string)SLTO_DEPLOYED_SOURCE_COMMIT:(string)getenv('SLTO_DEPLOYED_SOURCE_COMMIT');
        $configuredSourceCommit=1===preg_match('/^[a-f0-9]{40}$/D',$configuredSourceCommit)?$configuredSourceCommit:'';
        $context=array(
            'plugin_version'=>SABRI_SLTO_VERSION,
            'schema_version'=>SABRI_SLTO_SCHEMA_VERSION,
            'contract_version'=>SABRI_SLTO_CONTRACT_VERSION,
            'installed_schema_version'=>$installedSchema,
            'installed_contract_version'=>$installedContract,
            'deployed_source_commit'=>$configuredSourceCommit,
            'required'=>self::PRODUCTION_EVIDENCE,
        );
        if('staging'===$environment){
            $stagingAccepted=true===apply_filters('slto_verify_staging_acceptance_evidence',false,$context);
        }elseif('production'===$environment){
            $stagingAccepted=true===apply_filters('slto_verify_staging_acceptance_evidence',false,$context);
            $candidate=apply_filters('slto_verify_production_activation_evidence',array(),$context);
            $productionEvidence=is_array($candidate)?$candidate:array();
            foreach(self::PRODUCTION_EVIDENCE as $required){
                if(true!==($productionEvidence[$required]??false)){$productionEvidenceReady=false;}
            }
            if(!$stagingAccepted){$productionEvidenceReady=false;}
            if($productionEvidenceReady&&''!==$configuredSourceCommit
                &&hash_equals(SABRI_SLTO_SCHEMA_VERSION,$installedSchema)
                &&hash_equals(SABRI_SLTO_CONTRACT_VERSION,$installedContract)){
                $liveParityVerified=true===apply_filters('slto_verify_live_deployment_parity',false,$context,$productionEvidence);
            }
        }elseif(null===$environment){
            $productionEvidenceReady=false;
        }
        $gates=array_merge([
            'deployment_environment_configured'=>$environmentReady,
            'schema'=>$schemaReady,
            'encryption'=>$this->crypto->available(),
            'bundle_signing'=>null!==$signing,
            'staffing_approved'=>defined('SLTO_LOCALIZATION_STAFFING_APPROVED')&&true===SLTO_LOCALIZATION_STAFFING_APPROVED,
            'locale_release_ready'=>$localeReady,
            'environment_acceptance_evidence'=>'staging'===$environment?$stagingAccepted:('production'===$environment?$productionEvidenceReady:false),
        ],$integrations);
        $runtimeEnabled=(bool)get_option('slto_runtime_enabled',false);
        $liveDeployed='production'===$environment&&$productionEvidenceReady&&$liveParityVerified;
        $operational=$liveDeployed&&$runtimeEnabled&&!in_array(false,$gates,true);
        return [
            'status'=>in_array(false,$gates,true)?'degraded':'ready',
            'runtime_enabled'=>$runtimeEnabled,
            'deployment_environment'=>$environment??'unconfigured',
            'gates'=>$gates,
            'tables'=>$tables,
            'integrations'=>$integrations,
            'production_evidence_required'=>'production'===$environment?self::PRODUCTION_EVIDENCE:array(),
            'production_evidence'=>$productionEvidence,
            'live_parity_verified'=>$liveParityVerified,
            'deployed_source_commit'=>$configuredSourceCommit?:null,
            'metrics'=>$schemaReady?$this->metrics->summary():[],
            'truth_status'=>[
                'specified'=>'complete',
                'coded'=>'source-candidate-under-current-verification',
                'packaged'=>'requires-current-workflow-evidence',
                'automated_qa'=>'requires-current-workflow-evidence',
                'staging_accepted'=>$stagingAccepted,
                'live_deployed'=>$liveDeployed,
                'operational'=>$operational,
            ],
        ];
    }

    public function activationEligible(): array
    {
        $report=$this->report();
        $gates=$report['gates'];
        // Staging acceptance is the outcome of running the real staging journeys.
        // It therefore cannot be a prerequisite for temporarily enabling the
        // runtime needed to execute those journeys. Production keeps the full
        // environment-acceptance gate.
        if('staging'===$report['deployment_environment']){
            unset($gates['environment_acceptance_evidence']);
            $gates['staging_acceptance_is_post_activation_evidence']=true;
        }
        return [
            'eligible'=>!in_array(false,$gates,true),
            'environment'=>$report['deployment_environment'],
            'gates'=>$gates,
        ];
    }
}
