<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';

$root=dirname(__DIR__);
$t=new TestHarness();
$auth=(string)file_get_contents($root.'/src/Security/Authorization.php');
$contracts=(string)file_get_contents($root.'/docs/CONTRACTS.md');

$t->test('High-risk authorization requires independent recent authentication',function()use($auth):void{
    foreach([
        "array('release','provider','review_domain')",
        "apply_filters('slto_verify_recent_authentication', false",
        "if (true !== apply_filters('slto_verify_recent_authentication'",
    ] as $needle){
        TestHarness::assertTrue(str_contains($auth,$needle),$needle);
    }
});

$t->test('Recent-authentication verifier remains fail closed and separate from ordinary authorization filter',function()use($auth):void{
    $recent=strpos($auth,"apply_filters('slto_verify_recent_authentication'");
    $general=strpos($auth,"apply_filters('slto_authorize_action'");
    TestHarness::assertTrue(false!==$recent&&false!==$general&&$recent<$general);
});

$t->test('Contract documents step-up requirement without replacing specific evidence gates',function()use($contracts):void{
    TestHarness::assertTrue(str_contains($contracts,'Recent-authentication / step-up boundary'));
    TestHarness::assertTrue(str_contains($contracts,'dual-approval, provider-activation, or domain-review evidence contracts'));
});

$t->finish();
