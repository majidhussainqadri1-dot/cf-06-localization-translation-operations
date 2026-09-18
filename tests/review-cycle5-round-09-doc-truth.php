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
$t->test('Future40 docs match actual canonical guard stack and facade bounds',function()use($f,$e):void{
 foreach(['global byte/node bound','HotfixApprovalGuard','SemanticIntegrityGuard','ProviderEligibilityGuard','FUT-033'] as $n){
  TestHarness::assertTrue(str_contains($f,$n),'FUTURE40.md: '.$n);
  TestHarness::assertTrue(str_contains($e,$n),'Future40 evidence matrix: '.$n);
 }
});
$t->test('Lifecycle documentation cannot turn repository evidence into live truth',function()use($k,$c):void{
 foreach(['exact deployed-source','DB/migration/runtime parity'] as $n){TestHarness::assertTrue(str_contains($k,$n),'known limitations: '.$n);}
 foreach(['exact deployed-source','deployed-code/DB/migration/runtime parity'] as $n){TestHarness::assertTrue(str_contains($c,$n),'coding completion: '.$n);}
});
$t->finish();
