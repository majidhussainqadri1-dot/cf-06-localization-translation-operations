<?php

declare(strict_types=1);

require __DIR__ . '/TestHarness.php';

$root=dirname(__DIR__);$t=new TestHarness();
$feedback=(string)file_get_contents($root.'/src/Application/FeedbackService.php');
$content=(string)file_get_contents($root.'/src/Application/ContentLinkService.php');
$provider=(string)file_get_contents($root.'/src/Application/ProviderService.php');

$t->test('Feedback route matches varchar 255 storage bound',function()use($feedback):void{
    TestHarness::assertTrue(str_contains($feedback,'strlen($route)>255'));
});
$t->test('Content-link canonical fields are bounded before persistence',function()use($content):void{
    foreach(['strlen($owner)>40','strlen($object)>191','strlen($sourceVersion)>80','strlen($url)>255','strlen($approvalRef)>191'] as $needle){TestHarness::assertTrue(str_contains($content,$needle),$needle);}
});
$t->test('Provider canonical fields match database widths',function()use($provider):void{
    foreach(['strlen($type)>32','strlen($url)>255','strlen($contractVersion)>40'] as $needle){TestHarness::assertTrue(str_contains($provider,$needle),$needle);}
});
$t->finish();
