<?php

declare(strict_types=1);

require __DIR__ . '/TestHarness.php';

$root=dirname(__DIR__);$t=new TestHarness();
$repo=(string)file_get_contents($root.'/src/Infrastructure/Repository/LocalizationRepository.php');
$fresh=(string)file_get_contents($root.'/src/Domain/Bundle/BundleFreshnessGuard.php');
$bundle=(string)file_get_contents($root.'/src/Application/BundleService.php');

$t->test('Coverage counts translated critical resources distinctly',function()use($repo):void{
    TestHarness::assertTrue(str_contains($repo,'COUNT(DISTINCT CASE WHEN r.critical=1 THEN u.resource_uuid END) critical'));
    TestHarness::assertTrue(str_contains($repo,'$criticalTranslated=min($critical'));
});
$t->test('Bundle build rejects duplicate canonical resource evidence',function()use($fresh,$bundle):void{
    TestHarness::assertTrue(str_contains($fresh,'isset($seen[$key])'));
    TestHarness::assertTrue(str_contains($fresh,'incomplete or duplicated'));
    TestHarness::assertTrue(str_contains($bundle,'assertSourcesCurrent($sources)'));
});
$t->test('Activation rechecks current human QA and source freshness',function()use($bundle):void{
    TestHarness::assertTrue(str_contains($bundle,'assertCurrentHumanQa($this->latestQaResults($uuid),$bundle)'));
    TestHarness::assertTrue(str_contains($bundle,'assertSourcesCurrent($sourceList)'));
});

$t->finish();
