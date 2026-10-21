<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
$root=dirname(__DIR__);$t=new TestHarness();
$p=(string)file_get_contents($root.'/src/Plugin.php');
$a=(string)file_get_contents($root.'/src/Infrastructure/Activator.php');

$t->test('Runtime boot requires exact schema and contract parity',function()use($p):void{
 TestHarness::assertTrue(str_contains($p,'$installedSchema!==SABRI_SLTO_SCHEMA_VERSION'));
 TestHarness::assertTrue(str_contains($p,'$installedContract!==SABRI_SLTO_CONTRACT_VERSION'));
 TestHarness::assertTrue(str_contains($p,'exact match for this runtime'));
});
$t->test('Activation refuses an older runtime against newer persisted contracts',function()use($a):void{
 TestHarness::assertTrue(str_contains($a,"version_compare(\$installedSchema,SABRI_SLTO_SCHEMA_VERSION,'>')"));
 TestHarness::assertTrue(str_contains($a,"version_compare(\$installedContract,SABRI_SLTO_CONTRACT_VERSION,'>')"));
 TestHarness::assertTrue(str_contains($a,'refuses to activate older code against a newer schema or contract'));
});
$t->test('Deactivation verifies runtime disable persistence before clearing schedules',function()use($a):void{
 $verify=strpos($a,'runtime could not be disabled during plugin deactivation');
 $clear=strpos($a,"wp_clear_scheduled_hook('slto_process_jobs')");
 TestHarness::assertTrue(false!==$verify&&false!==$clear&&$verify<$clear);
});
$t->finish();
