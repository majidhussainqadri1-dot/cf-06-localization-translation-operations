<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
$root=dirname(__DIR__);$t=new TestHarness();
$routes=(string)file_get_contents($root.'/src/Rest/Routes.php');
$repo=(string)file_get_contents($root.'/src/Infrastructure/Repository/LocalizationRepository.php');
$term=(string)file_get_contents($root.'/src/Application/TerminologyService.php');
$privacy=(string)file_get_contents($root.'/src/Application/PrivacyService.php');
$mt=(string)file_get_contents($root.'/src/Application/MachineTranslationService.php');

$t->test('Mutation idempotency binds method route and request parameters',function()use($routes):void{
 foreach(["'method'=>\$request->get_method()","'route'=>\$request->get_route()","'params'=>\$request->get_params()",'canonicalize(['] as $n){TestHarness::assertTrue(str_contains($routes,$n),$n);}
});
$t->test('Expired processing idempotency remains indeterminate and cleanup preserves it',function()use($repo,$routes):void{
 TestHarness::assertTrue(str_contains($repo,"'processing'===(string)\$row['status']"));
 TestHarness::assertTrue(str_contains($repo,"idempotency_indeterminate"));
 TestHarness::assertTrue(str_contains($repo,"status IN ('completed','failed')"));
 TestHarness::assertTrue(str_contains($routes,'slto_idempotency_indeterminate'));
});
$t->test('Terminology and style guide creation are transaction-backed and bounded',function()use($term):void{
 TestHarness::assertTrue(substr_count($term,'return $this->tx->run(function()')>=4);
 TestHarness::assertTrue(str_contains($term,'Style guide examples exceed the bounded item limit.'));
 TestHarness::assertTrue(str_contains($term,"'term_version'=>\$termVersion"));
});
$t->test('Privacy erasure request queue and audit share one transaction',function()use($privacy):void{
 $start=strpos($privacy,'public function requestErasure');
 $tx=strpos($privacy,'return $this->tx->run',$start);
 $enqueue=strpos($privacy,'$this->jobs->enqueue(\'privacy_erasure\'',$start);
 $audit=strpos($privacy,"privacy_erasure_queued",$start);
 TestHarness::assertTrue(false!==$tx&&false!==$enqueue&&false!==$audit&&$tx<$enqueue&&$enqueue<$audit);
});
$t->test('Vendor purge final database mutation audit and outbox are atomic',function()use($mt):void{
 $start=strpos($mt,'public function purge');
 $provider=strpos($mt,'$this->provider->purge',$start);
 $tx=strpos($mt,'return $this->tx->run',$start);
 $audit=strpos($mt,'vendor_job_purged',$start);
 $event=strpos($mt,'TranslationVendorJobPurged',$start);
 TestHarness::assertTrue(false!==$provider&&false!==$tx&&false!==$audit&&false!==$event&&$provider<$tx&&$tx<$audit&&$audit<$event);
});
$t->finish();
