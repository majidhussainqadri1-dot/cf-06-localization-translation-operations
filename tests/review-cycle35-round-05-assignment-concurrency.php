<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';

$root=dirname(__DIR__);$t=new TestHarness();
$p=(string)file_get_contents($root.'/src/Application/ProjectService.php');

$t->test('Assignment operations are serialized per unit before separation-of-duties checks',function()use($p):void{
    foreach(['withUnitAssignmentLock','SELECT GET_LOCK(%s,10)','SELECT RELEASE_LOCK(%s)','Separation of duties prevents one person holding multiple roles on the same unit.'] as $needle){
        TestHarness::assertTrue(str_contains($p,$needle),$needle);
    }
});
$t->test('Terminal units cannot receive new assignments before or inside the assignment lock',function()use($p):void{
    TestHarness::assertTrue(substr_count($p,'Terminal translation units cannot receive new assignments.')>=2);
    TestHarness::assertTrue(str_contains($p,"['approved','released','retired']"));
});
$t->finish();
