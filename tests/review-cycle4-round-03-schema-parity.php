<?php

declare(strict_types=1);

require __DIR__ . '/TestHarness.php';

$root=dirname(__DIR__);$t=new TestHarness();
$activator=(string)file_get_contents($root.'/src/Infrastructure/Activator.php');

$t->test('Recorded schema versions never bypass actual schema verification',function()use($activator):void{
    $current=strpos($activator,"version_compare(\$installedSchema, SABRI_SLTO_SCHEMA_VERSION, '>=')");
    $verify=strpos($activator,'self::verifySchema();',$current===false?0:$current);
    TestHarness::assertTrue(false!==$current&&false!==$verify&&$verify>$current);
    TestHarness::assertTrue(str_contains($activator,'Version options are not schema truth.'));
});
$t->test('Runtime schema parity derives every canonical entity rather than a stale hand-maintained subset',function()use($activator):void{
    foreach(['expectedSchemaColumns','foreach (Database::ENTITIES as $entity=>$suffix)','array_diff($required,$found)','canonical schema definition is missing'] as $needle){
        TestHarness::assertTrue(str_contains($activator,$needle),$needle);
    }
});
$t->finish();
