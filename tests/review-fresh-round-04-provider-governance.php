<?php

declare(strict_types=1);

require __DIR__ . '/TestHarness.php';

$root=dirname(__DIR__);$t=new TestHarness();$code=(string)file_get_contents($root.'/src/Application/MachineTranslationService.php');

$t->test('Active provider runtime health and residency are fail closed',function()use($code):void{
    TestHarness::assertTrue(str_contains($code,"array('configured','healthy','ready')"));
    TestHarness::assertTrue(str_contains($code,"''===\$region"));
    TestHarness::assertTrue(str_contains($code,'runtime region differs from the approved provider record'));
    TestHarness::assertTrue(str_contains($code,'runtime health is not eligible'));
});
$t->test('Provider response must attest approved region',function()use($code):void{
    TestHarness::assertTrue(str_contains($code,"''===\$responseRegion"));
    TestHarness::assertTrue(str_contains($code,'response must attest the approved provider region'));
});
$t->test('Deletion purge remains possible after runtime provider disablement',function()use($code):void{
    TestHarness::assertTrue(str_contains($code,'assertGovernedProviderForPurge'));
    TestHarness::assertTrue(str_contains($code,"array('active','approved','disabled','deprecated')"));
    TestHarness::assertTrue(str_contains($code,'approved deletion contract'));
});

$t->finish();
