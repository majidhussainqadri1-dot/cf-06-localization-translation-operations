<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
$root=dirname(__DIR__);$t=new TestHarness();
$r=(string)file_get_contents($root.'/src/Application/ResourceService.php');
$c=(string)file_get_contents($root.'/src/Application/ContentLinkService.php');
$e=(string)file_get_contents($root.'/src/Application/ExtractionService.php');

$t->test('Resource references are rejected rather than silently truncated',function()use($r):void{
 TestHarness::assertTrue(str_contains($r,'Resource reference evidence exceeds the bounded item limit.'));
 TestHarness::assertTrue(!str_contains($r,"array_slice($input['references'],0,100)"));
});
$t->test('Published content link source locale is bound to current resource',function()use($c):void{
 TestHarness::assertTrue(str_contains($c,"(string)$resource['source_locale']!==$source"));
});
$t->test('Extraction evidence uses exact commit-length identity',function()use($e):void{
 TestHarness::assertTrue(str_contains($e,"(?:[a-f0-9]{40}|[a-f0-9]{64})"));
 TestHarness::assertTrue(!str_contains($e,"[a-f0-9]{7,64}"));
});
$t->finish();
