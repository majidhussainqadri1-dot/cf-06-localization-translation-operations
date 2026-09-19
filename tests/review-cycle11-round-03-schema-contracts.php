<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
$root=dirname(__DIR__);$t=new TestHarness();
$a=(string)file_get_contents($root.'/src/Infrastructure/Activator.php');
$t->test('Runtime schema parity checks column type and nullability contracts',function()use($a):void{
 foreach(['expectedColumnContracts','normalizeColumnType','schema column contract is drifted',"['Type']","['Null']"] as $n){TestHarness::assertTrue(str_contains($a,$n),$n);}
});
$t->finish();
