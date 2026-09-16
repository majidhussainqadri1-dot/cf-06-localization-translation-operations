<?php

declare(strict_types=1);

namespace Sabri\Localization\Application;

use InvalidArgumentException;
use RuntimeException;
use Sabri\Localization\Infrastructure\Database;
use Sabri\Localization\Infrastructure\Repository\AuditRepository;
use Sabri\Localization\Infrastructure\Transaction;

final class MigrationService
{
    public function __construct(private readonly AuditRepository $audit, private readonly Transaction $tx){}

    public function inventory(): array
    {
        global $wpdb;$items=[];
        foreach(Database::ENTITIES as $entity=>$suffix){
            $table=$wpdb->prefix.$suffix;$found=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table));
            if(''!==(string)$wpdb->last_error){throw new RuntimeException('Migration inventory table lookup failed.');}
            $exists=$found===$table;$rows=null;
            if($exists){$rows=$wpdb->get_var("SELECT COUNT(*) FROM {$table}");if(''!==(string)$wpdb->last_error){throw new RuntimeException('Migration inventory count failed.');}$rows=(int)$rows;}
            $items[$entity]=['table'=>$table,'exists'=>$exists,'rows'=>$rows];
        }
        return ['schema_version'=>(string)get_option('slto_schema_version',''),'contract_version'=>(string)get_option('slto_contract_version',''),'tables'=>$items,'options'=>['runtime_enabled'=>(bool)get_option('slto_runtime_enabled',false),'default_locale'=>(string)get_option('slto_default_locale','en-US')]];
    }

    public function dryRun(string $key,array $source): array
    {
        global $wpdb;
        $key=trim($key);
        if(''===$key||strlen($key)>191||1!==preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,190}$/D',$key)||count($source)>100000){throw new InvalidArgumentException('Migration key or source inventory is invalid.');}
        $report=['migration_key'=>$key,'migration_version'=>SABRI_SLTO_SCHEMA_VERSION,'mode'=>'dry-run','source_count'=>count($source),'create'=>0,'update'=>0,'skip'=>0,'quarantine'=>0,'conflicts'=>[]];
        foreach($source as $item){
            if(!is_array($item)){$report['quarantine']++;continue;}
            $resourceKey=trim((string)($item['resource_key']??''));$sourceHash=(string)($item['source_hash']??'');
            if(''===$resourceKey||strlen($resourceKey)>191||1!==preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,190}$/D',$resourceKey)||1!==preg_match('/^[a-f0-9]{64}$/D',$sourceHash)){$report['quarantine']++;continue;}
            $existing=$wpdb->get_row($wpdb->prepare('SELECT source_hash FROM '.Database::table('resources').' WHERE resource_key=%s',$resourceKey),ARRAY_A);
            if(''!==(string)$wpdb->last_error){throw new RuntimeException('Migration dry-run source comparison failed.');}
            if(!is_array($existing)){$report['create']++;}elseif(hash_equals((string)$existing['source_hash'],$sourceHash)){$report['skip']++;}else{$report['update']++;if(count($report['conflicts'])<1000){$report['conflicts'][]=$resourceKey;}}
        }
        $report['conflicts_truncated']=$report['update']>count($report['conflicts']);
        $checkpoint=wp_json_encode(['offset'=>0,'source_count'=>count($source),'schema_version'=>SABRI_SLTO_SCHEMA_VERSION],JSON_UNESCAPED_SLASHES);
        $encoded=wp_json_encode($report,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if(!is_string($checkpoint)||!is_string($encoded)||strlen($encoded)>5_000_000){throw new RuntimeException('Migration dry-run evidence could not be encoded within the governed limit.');}

        return $this->tx->run(function() use ($wpdb,$key,$checkpoint,$encoded,$report): array {
            $written=$wpdb->query($wpdb->prepare(
                "INSERT INTO ".Database::table('migrations')." (migration_key,migration_version,status,checkpoint_json,dry_run_report,updated_at) VALUES (%s,%s,'dry_run',%s,%s,%s) ON DUPLICATE KEY UPDATE migration_version=VALUES(migration_version),status='dry_run',checkpoint_json=VALUES(checkpoint_json),dry_run_report=VALUES(dry_run_report),started_at=NULL,completed_at=NULL,updated_at=VALUES(updated_at)",
                $key,SABRI_SLTO_SCHEMA_VERSION,$checkpoint,$encoded,Database::now()
            ));
            if(false===$written){throw new RuntimeException('Migration dry-run evidence could not be persisted.');}
            $this->audit->record('migration',$key,'migration_dry_run','success',['report_hash'=>hash('sha256',$encoded),'schema_version'=>SABRI_SLTO_SCHEMA_VERSION]);
            return $report;
        });
    }
}
