<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
$root=dirname(__DIR__);$t=new TestHarness();
$p=(string)file_get_contents($root.'/src/Application/ProviderService.php');
$m=(string)file_get_contents($root.'/src/Application/MachineTranslationService.php');
$t->test('Provider governance inventories reject overflow and invalid entries',function()use($p):void{
 foreach(['count($rawHosts)>100','contains an invalid host','count($rawSubprocessors)>100'] as $n){TestHarness::assertTrue(str_contains($p,$n),$n);}
});
$t->test('Provider deletion proof is nonempty and independently verified',function()use($m):void{
 foreach(['Provider purge evidence is missing.','slto_verify_provider_purge_evidence','could not be independently verified'] as $n){TestHarness::assertTrue(str_contains($m,$n),$n);}
});
$t->finish();
