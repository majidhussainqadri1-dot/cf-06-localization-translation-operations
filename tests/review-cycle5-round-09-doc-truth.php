<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
$root=dirname(__DIR__);$t=new TestHarness();
$a=(string)file_get_contents($root.'/docs/ARCHITECTURE.md');
$f=(string)file_get_contents($root.'/docs/FUTURE40.md');
$e=(string)file_get_contents($root.'/docs/FUTURE40-TRACEABILITY-EVIDENCE.md');
$k=(string)file_get_contents($root.'/docs/KNOWN-LIMITATIONS.md');
$c=(string)file_get_contents($root.'/docs/CODING-COMPLETION-REPORT.md');
$t->test('Architecture documents every current companion ownership boundary',function()use($a):void{
 foreach(['File 19','Files 22/23','File 24','File 25','File 26','CF-04'] as $n){TestHarness::assertTrue(str_contains($a,$n),$n);}
});
$t->test('Future40 docs match actual canonical guard order and facade bounds',function()use($f,$e):void{
 foreach(['global byte/node bound','HotfixApprovalGuard','SemanticIntegrityGuard input normalization','ProviderEligibilityGuard for FUT-033'] as $n){TestHarness::assertTrue(str_contains($f,$n),$n);TestHarness::assertTrue(str_contains($e,$n),$n);}
});
$t->test('Lifecycle documentation cannot turn repository evidence into live truth',function()use($k,$c):void{
 TestHarness::assertTrue(str_contains($k,'exact deployed-source/DB/migration/runtime parity'));
 TestHarness::assertTrue(str_contains($c,'deployed-code/DB/migration/runtime parity'));
});
$t->finish();
