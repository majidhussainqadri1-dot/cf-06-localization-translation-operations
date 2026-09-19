<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';

$root=dirname(__DIR__);$t=new TestHarness();
$p=(string)file_get_contents($root.'/src/Plugin.php');
$a=(string)file_get_contents($root.'/src/Infrastructure/Activator.php');

$t->test('Runtime boot rechecks operational schema parity after maybeUpgrade regardless of lock path',function()use($p,$a):void{
    $upgrade=strpos($p,'Activator::maybeUpgrade();');
    $parity=strpos($p,'Activator::assertRuntimeSchemaParity();');
    $hooks=strpos($p,"add_action('init'");
    TestHarness::assertTrue(false!==$upgrade&&false!==$parity&&false!==$hooks&&$upgrade<$parity&&$parity<$hooks);
    TestHarness::assertTrue(str_contains($a,'public static function assertRuntimeSchemaParity'));
    TestHarness::assertTrue(str_contains($a,'self::verifySchema();'));
});

$t->finish();
