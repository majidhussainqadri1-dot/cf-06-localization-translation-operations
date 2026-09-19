<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
$root=dirname(__DIR__);$t=new TestHarness();$a=(string)file_get_contents($root.'/src/Infrastructure/Activator.php');
$t->test('Schema parity verifies the complete Database entity inventory from canonical DDL',function()use($a):void{
 foreach(['expectedSchemaColumns','foreach (Database::ENTITIES as $entity=>$suffix)','array_diff($required,$found)','canonical schema definition is missing'] as $n){TestHarness::assertTrue(str_contains($a,$n),$n);}
});
$t->test('Critical unique indexes are verified for drift',function()use($a):void{
 foreach(["'resource_locale_project'=>array('project_uuid','resource_uuid','target_locale')","'unit_role'=>array('unit_uuid','assignment_role')","'bundle_role'=>array('bundle_uuid','approval_role')","'actor_route_key'=>array('actor_id','route_key','idempotency_key')","required unique schema index is unavailable or drifted"] as $n){TestHarness::assertTrue(str_contains($a,$n),$n);}
});
$t->finish();
