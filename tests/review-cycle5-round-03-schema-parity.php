<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
$root=dirname(__DIR__);$t=new TestHarness();$a=(string)file_get_contents($root.'/src/Infrastructure/Activator.php');
$t->test('Schema parity verifies all operational entity families',function()use($a):void{
 foreach(["'secure_payloads'=>array(","'project_resources'=>array(","'comments'=>array(","'qa_results'=>array(","'audit'=>array(","'outbox'=>array(","'jobs'=>array(","'rate_limits'=>array(","'migrations'=>array("] as $n){TestHarness::assertTrue(str_contains($a,$n),$n);}
});
$t->test('Critical unique indexes are verified for drift',function()use($a):void{
 foreach(["'resource_locale_project'=>array('project_uuid','resource_uuid','target_locale')","'unit_role'=>array('unit_uuid','assignment_role')","'bundle_role'=>array('bundle_uuid','approval_role')","'actor_route_key'=>array('actor_id','route_key','idempotency_key')","required unique schema index is unavailable or drifted"] as $n){TestHarness::assertTrue(str_contains($a,$n),$n);}
});
$t->finish();
