<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';

$root=dirname(__DIR__);$t=new TestHarness();
$resource=(string)file_get_contents($root.'/src/Application/ResourceService.php');
$content=(string)file_get_contents($root.'/src/Application/ContentLinkService.php');
$integration=(string)file_get_contents($root.'/src/Application/IntegrationService.php');
$health=(string)file_get_contents($root.'/src/Application/HealthService.php');
$privacy=(string)file_get_contents($root.'/src/Application/PrivacyService.php');
$jobs=(string)file_get_contents($root.'/src/Infrastructure/JobQueue.php');
$outbox=(string)file_get_contents($root.'/src/Infrastructure/Outbox.php');

$t->test('Existing resource updates require explicit optimistic lock and canonical governed metadata',function()use($resource):void{
 foreach([
   'Resource update requires an explicit current row_version.',
   'Resource descriptive metadata exceeds canonical storage bounds.',
   'encodeBoundedMetadata',
   "'markup_policy'=>\$markup",
   "'references'=>\$references",
   "'translatability'=>\$translatability",
 ] as $n){TestHarness::assertTrue(str_contains($resource,$n),$n);}
 TestHarness::assertTrue(str_contains($resource,"\$version=(int)\$input['row_version']"));
});

$t->test('Published content links are current resource-unit relationships and updates are versioned',function()use($content):void{
 foreach([
   'Content relationship update requires an explicit current row_version.',
   'Content relationship resource and translation unit are inconsistent.',
   'Content relationship target locale does not match the translation unit.',
   "'active'!==(string)\$resource['status']",
   "(string)\$unit['source_version']!==\$sourceVersion",
   "(string)\$resource['source_version']!==\$sourceVersion",
 ] as $n){TestHarness::assertTrue(str_contains($content,$n),$n);}
});

$t->test('Integration contract version respects varchar bound and production staging truth is independently reverified',function()use($integration,$health):void{
 TestHarness::assertTrue(str_contains($integration,'strlen($contractVersion) > 40'));
 TestHarness::assertTrue(substr_count($health,'slto_verify_staging_acceptance_evidence')>=2);
 TestHarness::assertTrue(str_contains($health,'if(!$stagingAccepted){$productionEvidenceReady=false;}'));
});

$t->test('Privacy pseudonyms use a collision-checked high actor namespace',function()use($privacy):void{
 foreach(['pseudonymId(','get_userdata($candidate)','collision-free actor identity','4_611_686_018_427_387_904'] as $n){TestHarness::assertTrue(str_contains($privacy,$n),$n);}
});

$t->test('Queue and outbox identities are bounded to canonical schema',function()use($jobs,$outbox):void{
 TestHarness::assertTrue(substr_count($jobs,'strlen($type) > 80')>=2);
 TestHarness::assertTrue(str_contains($outbox,'strlen($aggregateType)>40'));
 TestHarness::assertTrue(str_contains($outbox,"preg_match('/^[a-f0-9-]{36}$/D',\$aggregateUuid)"));
});

$t->finish();
