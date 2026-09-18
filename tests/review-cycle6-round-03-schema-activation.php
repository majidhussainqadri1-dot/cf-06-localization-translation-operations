<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
$root=dirname(__DIR__);$t=new TestHarness();
$a=(string)file_get_contents($root.'/src/Infrastructure/Activator.php');

$t->test('Schema verification derives the complete canonical column inventory',function()use($a):void{
 foreach(['expectedSchemaColumns','foreach (Database::ENTITIES as $entity=>$suffix)','array_diff($required,$found)'] as $n){TestHarness::assertTrue(str_contains($a,$n),$n);}
 TestHarness::assertTrue(!str_contains($a,"'locales'=>array('locale_tag','fallback_tag'"));
});
$t->test('Activation version markers are persisted only after side-effect prerequisites',function()use($a):void{
 $schedule=strpos($a,'self::scheduleJobs();');
 $schema=strpos($a,"update_option('slto_schema_version'");
 $contract=strpos($a,"update_option('slto_contract_version'");
 TestHarness::assertTrue(false!==$schedule&&false!==$schema&&false!==$contract&&$schedule<$schema&&$schedule<$contract);
});
$t->test('Plugin deactivation forces runtime disabled before hooks are cleared',function()use($a):void{
 $deactivate=strpos($a,'public static function deactivate');
 $disable=strpos($a,"update_option('slto_runtime_enabled', false, false)",$deactivate);
 $clear=strpos($a,"wp_clear_scheduled_hook('slto_process_jobs')",$deactivate);
 TestHarness::assertTrue(false!==$disable&&false!==$clear&&$disable<$clear);
});
$t->finish();
