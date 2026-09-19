<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';

$root=dirname(__DIR__);$t=new TestHarness();
$bundle=(string)file_get_contents($root.'/src/Application/BundleService.php');
$terminology=(string)file_get_contents($root.'/src/Application/TerminologyService.php');
$provider=(string)file_get_contents($root.'/src/Application/ProviderService.php');
$feedback=(string)file_get_contents($root.'/src/Application/FeedbackService.php');
$translation=(string)file_get_contents($root.'/src/Application/TranslationService.php');
$project=(string)file_get_contents($root.'/src/Application/ProjectService.php');
$resource=(string)file_get_contents($root.'/src/Application/ResourceService.php');
$events=(string)file_get_contents($root.'/src/Contract/Events.php');
$quality=(string)file_get_contents($root.'/tools/quality-check.sh');
$contracts=(string)file_get_contents($root.'/docs/CONTRACTS.md');

$t->test('Universal governed transitions require explicit bounded reasons',function()use($bundle,$terminology,$provider,$feedback,$translation,$project):void{
    foreach([
        [$bundle,'Bundle transition requires a nonempty bounded reason.'],
        [$terminology,'Terminology transition requires a nonempty bounded reason.'],
        [$terminology,'Style guide transition requires a nonempty bounded reason.'],
        [$provider,'Provider transition requires a nonempty bounded reason.'],
        [$feedback,'Feedback transition requires a nonempty bounded outcome/reason.'],
        [$translation,'Every translation review transition requires a nonempty bounded reason.'],
        [$project,'Project transition requires a nonempty bounded reason.'],
    ] as [$source,$needle]){TestHarness::assertTrue(str_contains($source,$needle),$needle);}
});

$t->test('Provider deprecation is fail closed on credential revocation and exit evidence',function()use($provider,$contracts):void{
    foreach(['slto_verify_provider_deprecation_evidence','credential_revoked','exit_reconciled','rollback_window_documented'] as $n){
        TestHarness::assertTrue(str_contains($provider,$n),$n);
    }
    TestHarness::assertTrue(str_contains($contracts,'slto_verify_provider_deprecation_evidence'));
});

$t->test('Automatic source staleness notifies search reconciliation consumers',function()use($resource,$events):void{
    TestHarness::assertTrue(substr_count($resource,'ContentTranslationReconciliationRequired')>=2);
    TestHarness::assertTrue(str_contains($resource,"'search_reconciliation_required'=>true"));
    TestHarness::assertTrue(str_contains($events,'ContentTranslationReconciliationRequired'));
});

$t->test('Exact-source quality gate executes this review cycle',function()use($quality):void{
    TestHarness::assertTrue(str_contains($quality,'for test in tests/review-cycle*-round-*.php'));
});

$t->finish();
