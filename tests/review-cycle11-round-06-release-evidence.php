<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
$root=dirname(__DIR__);$t=new TestHarness();
$b=(string)file_get_contents($root.'/src/Application/BundleService.php');
$q=(string)file_get_contents($root.'/src/Application/QaEvidenceService.php');

$t->test('Release gates reverify stored human bundle QA at consumption time',function()use($b):void{
  TestHarness::assertTrue(str_contains($b,"'verification_phase'=>'consumption-time'"));
  TestHarness::assertTrue(str_contains($b,'Current in-context bundle QA evidence could not be independently reverified'));
  TestHarness::assertTrue(str_contains($b,'assertCurrentHumanQa($latest,$bundle)'));
});

$t->test('QA evidence passed() rechecks independent verifier',function()use($q):void{
  TestHarness::assertTrue(substr_count($q,"apply_filters('slto_verify_qa_evidence'")>=2);
  TestHarness::assertTrue(str_contains($q,"'verification_phase'=>'consumption-time'"));
});
$t->finish();
