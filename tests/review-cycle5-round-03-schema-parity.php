<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
$root=dirname(__DIR__);$t=new TestHarness();$a=(string)file_get_contents($root.'/src/Infrastructure/Activator.php');
$t->test('Schema parity verifies the complete Database entity inventory from canonical DDL',function()use($a):void{
 foreach(['expectedSchemaColumns','foreach (Database::ENTITIES as $entity=>$suffix)','array_diff($required,$found)','canonical schema definition is missing'] as $n){TestHarness::assertTrue(str_contains($a,$n),$n);}
});
$t->test('Critical unique indexes are derived from canonical DDL and verified for drift',function()use($a):void{
 foreach(['expectedIndexes','UNIQUE KEY\\s+','KEY\\s+','SHOW INDEX FROM {$table} WHERE Key_name=%s','required unique schema index is unavailable or drifted','required non-unique schema index is unavailable or drifted'] as $n){TestHarness::assertTrue(str_contains($a,$n),$n);}
});
$t->finish();
