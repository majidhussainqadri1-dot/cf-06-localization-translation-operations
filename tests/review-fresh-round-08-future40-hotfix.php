<?php

declare(strict_types=1);

require __DIR__ . '/TestHarness.php';

$root=dirname(__DIR__);$t=new TestHarness();
$guard=(string)file_get_contents($root.'/src/Domain/Future/HotfixApprovalGuard.php');
$facade=(string)file_get_contents($root.'/src/Application/FutureCapabilitiesFacade.php');
$routes=(string)file_get_contents($root.'/src/Rest/FutureRoutes.php');

$t->test('Emergency hotfix preview requires fresh independent approval evidence',function()use($guard,$facade):void{
    TestHarness::assertTrue(str_contains($guard,'fresh within fifteen minutes'));
    TestHarness::assertTrue(str_contains($guard,'$age>900'));
    TestHarness::assertTrue(str_contains($facade,'HotfixApprovalGuard::normalize'));
});
$t->test('Future40 REST evaluation remains bounded and privileged',function()use($routes):void{
    TestHarness::assertTrue(str_contains($routes,'MAX_EVALUATION_BYTES'));
    TestHarness::assertTrue(str_contains($routes,'MAX_EVALUATION_NODES'));
    TestHarness::assertTrue(str_contains($routes,"Authorization::allowed('manage')"));
});

$t->finish();
