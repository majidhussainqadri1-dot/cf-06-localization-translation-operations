<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
$root=dirname(__DIR__);$t=new TestHarness();
$c=(string)file_get_contents($root.'/docs/CONTRACTS.md');
$r=(string)file_get_contents($root.'/docs/REQUIREMENTS-TRACEABILITY.md');
$f=(string)file_get_contents($root.'/docs/FUTURE40-TRACEABILITY-EVIDENCE.md');
$s=(string)file_get_contents($root.'/docs/STAGING.md');
$k=(string)file_get_contents($root.'/docs/KNOWN-LIMITATIONS.md');
$rb=(string)file_get_contents($root.'/docs/ROLLBACK.md');

$t->test('Contracts document independent QA and staging activation semantics',function()use($c):void{
 foreach(['slto_verify_bundle_qa_evidence','slto_verify_qa_evidence','Staging acceptance is an **outcome**','Runtime boot requires the persisted schema'] as $n){TestHarness::assertTrue(str_contains($c,$n),$n);}
});
$t->test('Functional QA traceability names independent bundle QA verification',function()use($r):void{
 TestHarness::assertTrue(str_contains($r,'CF06-FR-024'));
 TestHarness::assertTrue(str_contains($r,'slto_verify_bundle_qa_evidence'));
 TestHarness::assertTrue(str_contains($r,'atomic erasure-job minimization'));
});
$t->test('Future40 hardened rows reference the cycle 7 validation suite',function()use($f):void{
 TestHarness::assertTrue(substr_count($f,'review-cycle7-round-07-future40-validation.php')>=4);
});
$t->test('Staging limitations and rollback docs include complete current boundaries',function()use($s,$k,$rb):void{
 foreach(['File 19','Files 22/23','File 26','CF-04'] as $n){TestHarness::assertTrue(str_contains($s,$n),$n);TestHarness::assertTrue(str_contains($k,$n),$n);}
 TestHarness::assertTrue(str_contains($rb,'older plugin refuses activation against newer schema/contract state'));
});
$t->finish();
