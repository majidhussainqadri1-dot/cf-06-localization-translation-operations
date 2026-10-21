<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
$root=dirname(__DIR__);$t=new TestHarness();
$c=(string)file_get_contents($root.'/docs/CONTRACTS.md');
$a=(string)file_get_contents($root.'/docs/ARCHITECTURE.md');
$p=(string)file_get_contents($root.'/docs/PRIVACY-RETENTION.md');
$q=(string)file_get_contents($root.'/tools/quality-check.sh');
$t->test('New fail-closed governance hooks are contract-documented',function()use($c):void{
 foreach(['slto_verify_assignment_qualification','slto_verify_provider_purge_evidence','default result is denial'] as $n){TestHarness::assertTrue(str_contains($c,$n),$n);}
});
$t->test('Architecture and privacy docs preserve owner boundaries',function()use($a,$p):void{
 TestHarness::assertTrue(str_contains($a,'independent assignment qualification attestation'));
 TestHarness::assertTrue(str_contains($p,'slto_verify_provider_purge_evidence'));
});
$t->test('Source quality gate enforces governance contract presence',function()use($q):void{
 TestHarness::assertTrue(substr_count($q,'slto_verify_assignment_qualification')>=2);
 TestHarness::assertTrue(substr_count($q,'slto_verify_provider_purge_evidence')>=2);
});
$t->finish();
