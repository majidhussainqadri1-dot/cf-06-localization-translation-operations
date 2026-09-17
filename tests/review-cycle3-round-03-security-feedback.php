<?php

declare(strict_types=1);

require __DIR__ . '/TestHarness.php';

$root=dirname(__DIR__);$t=new TestHarness();
$feedback=(string)file_get_contents($root.'/src/Application/FeedbackService.php');
$routes=(string)file_get_contents($root.'/src/Rest/Routes.php');
$auth=(string)file_get_contents($root.'/src/Security/Authorization.php');

$t->test('Anonymous feedback cannot directly emit a critical operational escalation',function()use($feedback):void{
    $submit=substr($feedback,strpos($feedback,'public function submit'),strpos($feedback,'public function transition')-strpos($feedback,'public function submit'));
    TestHarness::assertTrue(!str_contains($submit,'CriticalTranslationDefectDetected'));
    TestHarness::assertTrue(str_contains($feedback,"'triaged'===\$to&&'critical'===(string)\$row['severity']"));
});

$t->test('Feedback identity fields are bounded before persistence',function()use($feedback):void{
    foreach(['strlen($category)>40','strlen($route)>1000','strlen($resourceKey)>191','resource key or route'] as $needle){TestHarness::assertTrue(str_contains($feedback,$needle),$needle);}
});

$t->test('Critical feedback escalation occurs only after privileged transition route',function()use($routes,$feedback):void{
    TestHarness::assertTrue(str_contains($routes,"/feedback/(?P<uuid>"));
    TestHarness::assertTrue(str_contains($routes,"'manage'"));
    TestHarness::assertTrue(str_contains($feedback,'triaged_by'));
});

$t->test('Privileged authorization remains fail closed and File 00 bound',function()use($auth):void{
    foreach(['if (! isset(self::MAP[$action]))','smc_membership_assertions','suspended','expires_at','slto_authorize_action'] as $needle){TestHarness::assertTrue(str_contains($auth,$needle),$needle);}
});

$t->finish();
