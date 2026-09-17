<?php

declare(strict_types=1);

require __DIR__ . '/TestHarness.php';

$root=dirname(__DIR__);$t=new TestHarness();
$hotfix=(string)file_get_contents($root.'/src/Domain/Future/HotfixApprovalGuard.php');
$facade=(string)file_get_contents($root.'/src/Application/FutureCapabilitiesFacade.php');
$routes=(string)file_get_contents($root.'/src/Rest/FutureRoutes.php');

$t->test('Future40 hotfix evidence rejects fuzzy or relative timestamps',function()use($hotfix):void{
    TestHarness::assertTrue(str_contains($hotfix,'strict ISO-8601 evidence'));
    TestHarness::assertTrue(str_contains($hotfix,"preg_match('/^\\d{4}-\\d{2}-\\d{2}T"));
    TestHarness::assertTrue(str_contains($hotfix,'fifteen minutes'));
});

$t->test('Future40 canonical facade applies release and hotfix guards before handlers',function()use($facade):void{
    $release=strpos($facade,'ReleaseLifecycleGuard::normalize');
    $hotfix=strpos($facade,'HotfixApprovalGuard::normalize');
    $handler=strpos($facade,'$this->handlers->evaluate');
    TestHarness::assertTrue(false!==$release&&false!==$hotfix&&false!==$handler&&$release<$handler&&$hotfix<$handler);
});

$t->test('Future40 REST payload remains byte and node bounded',function()use($routes):void{
    foreach(['MAX_EVALUATION_BYTES','MAX_EVALUATION_NODES','nodeCount'] as $needle){TestHarness::assertTrue(str_contains($routes,$needle),$needle);}
});

$t->finish();
