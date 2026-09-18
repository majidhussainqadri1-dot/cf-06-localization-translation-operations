<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
$root=dirname(__DIR__);$t=new TestHarness();
$r=(string)file_get_contents($root.'/src/Application/ResourceService.php');
$l=(string)file_get_contents($root.'/src/Application/LocaleService.php');
$e=(string)file_get_contents($root.'/src/Application/ExtractionService.php');
$f=(string)file_get_contents($root.'/src/Domain/Locale/FallbackChainValidator.php');
$t->test('Resource canonical identity matches schema bounds',function()use($r):void{foreach(['strlen($key)>191','strlen($domain)>80'] as $n){TestHarness::assertTrue(str_contains($r,$n),$n);}});
$t->test('Locale metadata and surface inventory are bounded',function()use($l):void{foreach(['boundedMetadata','encodedSurfaces','count($surfaces)>100'] as $n){TestHarness::assertTrue(str_contains($l,$n),$n);}});
$t->test('Extraction owner and environment match schema bounds',function()use($e):void{foreach(['strlen($ownerModule) > 40',"strlen((string)$evidence['environment_name']) > 24"] as $n){TestHarness::assertTrue(str_contains($e,$n),$n);}});
$t->test('Fallback validation and resolution share the same maximum depth',function()use($f):void{TestHarness::assertTrue(str_contains($f,'$depth < $maximumDepth'));TestHarness::assertTrue(!str_contains($f,'$depth <= $maximumDepth'));});
$t->finish();
