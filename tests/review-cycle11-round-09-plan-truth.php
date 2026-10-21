<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
$root=dirname(__DIR__);$t=new TestHarness();
$p=(string)file_get_contents($root.'/src/Contract/PlanCompliance.php');
$m=(string)file_get_contents($root.'/src/Contract/Manifest.php');
$a=(string)file_get_contents($root.'/docs/ARCHITECTURE.md');
$f=(string)file_get_contents($root.'/docs/FUTURE40-TRACEABILITY-EVIDENCE.md');

$t->test('Machine-readable ownership covers File 00 and Unicode CLDR ICU reference data',function()use($p,$m,$a):void{
    foreach(["'file00'","'unicode_cldr_icu'"] as $n){TestHarness::assertTrue(str_contains($p,$n),$n);}
    TestHarness::assertTrue(str_contains($m,'identity_membership_capability_authority'));
    TestHarness::assertTrue(str_contains($a,'Unicode/CLDR/ICU reference data'));
});
$t->test('Future40 package evidence is conditional on exact green commit',function()use($f):void{
    TestHarness::assertTrue(str_contains($f,'required evidence class'));
    TestHarness::assertTrue(str_contains($f,'requires exact-commit package/CI'));
    TestHarness::assertTrue(!str_contains($f,'| exact-commit package/CI; Staging pending; Live pending |'));
});
$t->finish();
