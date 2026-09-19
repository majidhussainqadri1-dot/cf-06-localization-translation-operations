<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
$root=dirname(__DIR__);$t=new TestHarness();
$a=(string)file_get_contents($root.'/src/Infrastructure/Activator.php');
$t->test('Runtime schema parity derives every declared unique index from canonical DDL',function()use($a):void{
 foreach(['expectedUniqueIndexes','UNIQUE KEY\\s+','Database::ENTITIES','required unique schema index is unavailable or drifted'] as $n){TestHarness::assertTrue(str_contains($a,$n),$n);}
});
$t->test('Schema parity is no longer limited to a hand-selected critical-index list',function()use($a):void{
 TestHarness::assertTrue(!str_contains($a,'$criticalIndexes = array('));
 TestHarness::assertTrue(str_contains($a,"$out[$table][(string)$idx[1]]=$columns"));
});
$t->finish();
