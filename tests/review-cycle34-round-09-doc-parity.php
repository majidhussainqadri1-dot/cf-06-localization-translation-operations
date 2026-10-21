<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';

$root=dirname(__DIR__);$t=new TestHarness();
$f=(string)file_get_contents($root.'/docs/FUTURE40.md');
$e=(string)file_get_contents($root.'/docs/FUTURE40-TRACEABILITY-EVIDENCE.md');
$c=(string)file_get_contents($root.'/docs/CONTRACTS.md');
$r=(string)file_get_contents($root.'/docs/REQUIREMENTS-TRACEABILITY.md');

$t->test('Future40 documentation records canonical depth protection',function()use($f,$e,$c):void{
    TestHarness::assertTrue(str_contains($f,'global byte/node/depth bound'));
    TestHarness::assertTrue(str_contains($e,'global byte/node/depth bound'));
    foreach(['byte size','node count','nesting depth'] as $needle){TestHarness::assertTrue(str_contains($c,$needle),$needle);}
});

$t->test('Resource traceability records structurally bounded metadata canonicalization',function()use($r,$c):void{
    TestHarness::assertTrue(str_contains($r,'bounded structured metadata canonicalization'));
    TestHarness::assertTrue(str_contains($r,'cycle34 round5'));
    TestHarness::assertTrue(str_contains($c,'structured metadata is bounded by byte size, node count and nesting depth'));
});

$t->finish();
