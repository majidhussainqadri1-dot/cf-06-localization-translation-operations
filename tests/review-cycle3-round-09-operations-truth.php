<?php

declare(strict_types=1);

require __DIR__ . '/TestHarness.php';

$root=dirname(__DIR__);$t=new TestHarness();
$metrics=(string)file_get_contents($root.'/src/Application/MetricsService.php');
$health=(string)file_get_contents($root.'/src/Application/HealthService.php');

$t->test('Critical feedback metric excludes untriaged self-reported severity',function()use($metrics):void{
    TestHarness::assertTrue(str_contains($metrics,"severity='critical' AND status IN ('triaged','investigating','fixed','released')"));
    TestHarness::assertTrue(!str_contains($metrics,"severity='critical' AND status<>'closed'"));
});

$t->test('Health lifecycle truth derives live and operational state from runtime evidence',function()use($health):void{
    foreach(["'live_deployed'=>\$liveDeployed","'operational'=>\$operational","'staging_accepted'=>\$stagingAccepted",'source-candidate-under-current-verification'] as $needle){TestHarness::assertTrue(str_contains($health,$needle),$needle);}
    TestHarness::assertTrue(str_contains($health,"'production'===\$environment&&\$productionEvidenceReady"));
});

$t->test('Production truth requires explicit staging rollback package security accessibility and performance evidence',function()use($health):void{
    foreach(['staging_acceptance','rollback_restore_rehearsal','exact_package_parity','security_privacy_acceptance','accessibility_acceptance','performance_acceptance'] as $needle){TestHarness::assertTrue(str_contains($health,$needle),$needle);}
});

$t->finish();
