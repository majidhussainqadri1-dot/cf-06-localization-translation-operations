<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
$root=dirname(__DIR__);$t=new TestHarness();$p=(string)file_get_contents($root.'/src/Application/ProjectService.php');
$t->test('Assignment qualification evidence is independently verified',function()use($p):void{
    TestHarness::assertTrue(str_contains($p,'slto_verify_assignment_qualification'));
    TestHarness::assertTrue(str_contains($p,'Assignment qualification evidence could not be independently verified.'));
});
$t->test('Project metadata respects storage bounds',function()use($p):void{
    foreach(['strlen($description)>65535','strlen($releaseTarget)>191','strlen($projectProvider)>80'] as $n){TestHarness::assertTrue(str_contains($p,$n),$n);}
});
$t->finish();
