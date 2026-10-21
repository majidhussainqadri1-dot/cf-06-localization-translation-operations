<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';

$root=dirname(__DIR__);$t=new TestHarness();
$r=(string)file_get_contents($root.'/src/Application/ResourceService.php');

$t->test('Resource translation-affecting metadata is structurally bounded before recursive canonicalization',function()use($r):void{
    foreach([
        'assertBoundedTree($markup,\'markup policy\')',
        'assertBoundedTree($references,\'reference evidence\')',
        'assertBoundedTree($translatability,\'translatability evidence\')',
        'exceeds the bounded nesting depth',
        'exceeds the bounded structural complexity',
        'contains a non-serializable value',
    ] as $needle){TestHarness::assertTrue(str_contains($r,$needle),$needle);}
    $guard=strpos($r,'assertBoundedTree($markup');
    $encode=strpos($r,'encodeBoundedMetadata($markup');
    TestHarness::assertTrue(false!==$guard&&false!==$encode&&$guard<$encode);
});

$t->finish();
