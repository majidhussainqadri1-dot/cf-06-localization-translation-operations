<?php

declare(strict_types=1);

namespace Sabri\Localization\Application;

use InvalidArgumentException;
use RuntimeException;
use Sabri\Localization\Infrastructure\Database;
use Sabri\Localization\Infrastructure\Repository\AuditRepository;

final class MigrationService
{
    public function __construct(private readonly AuditRepository $audit){}
    public function inventory():array
    {
        global $wpdb;$items=[];foreach(Database::ENTITIES as $entity=>$suffix){$table=$wpdb->prefix.$suffix;$exists=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table))===$table;$items[$entity]=['table'=>$table,'exists'=>$exists,'rows'=>$exists?(int)$wpdb->get_var("SELECT COUNT(*) FROM {$table}"):0];}return ['schema_version'=>(string)get_option('slto_schema_version',''),'contract_version'=>(string)get_option('slto_contract_version',''),'tables'=>$items,'options'=>['runtime_enabled'=>(bool)get_option('slto_runtime_enabled',false),'default_locale'=>(string)get_option('slto_default_locale','en-US')]];
    }
    public function dryRun(string $key,array $source):array
    {
        global $wpdb;
        if ('' === $key || strlen($key) > 191) {
            throw new InvalidArgumentException('Migration key is required and must be bounded.');
        }
        $report=['migration_key'=>$key,'mode'=>'dry-run','source_count'=>count($source),'create'=>0,'update'=>0,'skip'=>0,'quarantine'=>0,'conflicts'=>[]];
        foreach($source as $item){
            if(!is_array($item)||empty($item['resource_key'])||empty($item['source_hash'])||1!==preg_match('/^[a-f0-9]{64}$/D',(string)$item['source_hash'])){$report['quarantine']++;continue;}
            $existing=$wpdb->get_row($wpdb->prepare('SELECT source_hash FROM '.Database::table('resources').' WHERE resource_key=%s',(string)$item['resource_key']),ARRAY_A);
            if(!$existing){$report['create']++;}elseif(hash_equals((string)$existing['source_hash'],(string)$item['source_hash'])){$report['skip']++;}else{$report['update']++;$report['conflicts'][]=(string)$item['resource_key'];}
        }
        $table=Database::table('migrations');
        $written=$wpdb->query($wpdb->prepare("INSERT INTO {$table} (migration_key,migration_version,status,checkpoint_json,dry_run_report,updated_at) VALUES (%s,%s,'dry_run',%s,%s,%s) ON DUPLICATE KEY UPDATE status='dry_run',dry_run_report=VALUES(dry_run_report),updated_at=VALUES(updated_at)",$key,SABRI_SLTO_SCHEMA_VERSION,wp_json_encode(['offset'=>0]),wp_json_encode($report),Database::now()));
        if(false===$written){throw new RuntimeException('Migration dry-run evidence could not be persisted.');}
        $this->audit->record('migration',$key,'migration_dry_run','success',['report_hash'=>hash('sha256',wp_json_encode($report))]);return $report;
    }
}
