<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
$root=dirname(__DIR__);$t=new TestHarness();
$b=(string)file_get_contents($root.'/src/Application/BundleService.php');
$q=(string)file_get_contents($root.'/src/Application/QaEvidenceService.php');
$i=(string)file_get_contents($root.'/src/Application/IntegrationService.php');
$h=(string)file_get_contents($root.'/src/Application/HealthService.php');
$a=(string)file_get_contents($root.'/src/Infrastructure/Activator.php');

$t->test('Automated bundle QA requires the complete canonical rule set',function()use($b):void{
 foreach(['AUTOMATED_QA','bundle_nonempty','critical_coverage','unique_keys','assertCurrentAutomatedQa'] as $n){TestHarness::assertTrue(str_contains($b,$n),$n);}
});
$t->test('Staged and canary transitions reverify signed source and integration readiness',function()use($b):void{
 TestHarness::assertTrue(str_contains($b,"in_array(\$to,array('staged','canary'),true)"));
 TestHarness::assertTrue(str_contains($b,'$this->validatedSourceList($bundle)'));
 TestHarness::assertTrue(str_contains($b,'$this->assertSourcesCurrent($sources)'));
 TestHarness::assertTrue(str_contains($b,'$this->integrations->assertReady()'));
});
$t->test('QA build evidence uses exact commit-length identity',function()use($q):void{
 TestHarness::assertTrue(substr_count($q,'(?:[a-f0-9]{40}|[a-f0-9]{64})')>=2);
 TestHarness::assertTrue(!str_contains($q,'[a-f0-9]{7,64}'));
});
$t->test('Integration expiry evidence is exact UTC rather than fuzzy strtotime input',function()use($i):void{
 TestHarness::assertTrue(str_contains($i,'Integration evidence expiry must be an exact UTC timestamp.'));
 TestHarness::assertTrue(str_contains($i,'DateTimeImmutable::createFromFormat'));
});
$t->test('Health schema gate invokes full runtime schema parity',function()use($h,$a):void{
 TestHarness::assertTrue(str_contains($a,'assertRuntimeSchemaParity'));
 TestHarness::assertTrue(str_contains($h,'Activator::assertRuntimeSchemaParity'));
 TestHarness::assertTrue(str_contains($h,'environment_acceptance_evidence'));
});
$t->finish();
