<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';

$root=dirname(__DIR__);$t=new TestHarness();
$p=(string)file_get_contents($root.'/src/Domain/Translation/PlaceholderValidator.php');
$s=(string)file_get_contents($root.'/src/Application/TerminologyService.php');

$t->test('Typed placeholder schemas are item and identifier bounded',function()use($p):void{
    foreach(['count($provided) > 500','strlen($name) > 128','Typed placeholder schema exceeds the bounded item limit.'] as $needle){
        TestHarness::assertTrue(str_contains($p,$needle),$needle);
    }
});
$t->test('Style guide rules and examples are structurally bounded before encoding',function()use($s):void{
    foreach(["assertBoundedTree($rules,'Style guide rules',5000,32)","assertBoundedTree($rawExamples,'Style guide examples',5000,32)",'bounded structural complexity','bounded nesting depth'] as $needle){
        TestHarness::assertTrue(str_contains($s,$needle),$needle);
    }
});
$t->finish();
