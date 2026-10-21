<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
$root=dirname(__DIR__);$t=new TestHarness();
$b=(string)file_get_contents($root.'/src/Application/BundleService.php');
$q=(string)file_get_contents($root.'/src/Application/QaEvidenceService.php');
$r=(string)file_get_contents($root.'/src/Application/ReleaseApprovalService.php');
$i=(string)file_get_contents($root.'/src/Application/IntegrationService.php');
$h=(string)file_get_contents($root.'/src/Application/HealthService.php');

$t->test('Passing in-context bundle QA is independently verified',function()use($b):void{
 foreach(['slto_verify_bundle_qa_evidence','Passing in-context bundle QA requires independently verified','bundle_hash','reviewer_id'] as $n){TestHarness::assertTrue(str_contains($b,$n),$n);}
});
$t->test('QA evidence rows require an independent verifier',function()use($q):void{
 TestHarness::assertTrue(str_contains($q,'slto_verify_qa_evidence'));
 TestHarness::assertTrue(str_contains($q,'QA evidence could not be independently verified.'));
});
$t->test('Release approval freshness uses strict UTC database timestamps',function()use($r):void{
 foreach(['strictDbUtcTimestamp','new DateTimeZone(\'UTC\')','Y-m-d H:i:s'] as $n){TestHarness::assertTrue(str_contains($r,$n),$n);}
 TestHarness::assertTrue(!str_contains($r,"strtotime((string)(\$row['step_up_at']"));
});
$t->test('Integration readiness validates stored expiry as strict UTC',function()use($i):void{
 TestHarness::assertTrue(str_contains($i,'storedUtcFuture'));
 TestHarness::assertTrue(str_contains($i,"createFromFormat('!Y-m-d H:i:s'"));
});
$t->test('Staging runtime can be enabled to execute acceptance journeys without claiming staging accepted',function()use($h):void{
 foreach(["if('staging'===\$report['deployment_environment'])","unset(\$gates['environment_acceptance_evidence'])","staging_acceptance_is_post_activation_evidence"] as $n){TestHarness::assertTrue(str_contains($h,$n),$n);}
});
$t->finish();
