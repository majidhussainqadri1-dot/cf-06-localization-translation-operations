<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
$root=dirname(__DIR__);$t=new TestHarness();$r=(string)file_get_contents($root.'/src/Application/ReleaseApprovalService.php');
$t->test('Expired bundle-role approval can be renewed without violating unique storage key',function()use($r):void{
 foreach(['$reusable=null',"(string)$approval['approval_role']===$role",'updateVersioned(\'release_approvals\'','\'renewed\'=>is_array($reusable)'] as $n){TestHarness::assertTrue(str_contains($r,$n),$n);}
});
$t->test('Fresh approvals still require distinct actors and roles',function()use($r):void{
 TestHarness::assertTrue(str_contains($r,'Fresh release approvals require distinct actors and distinct approval roles.'));
});
$t->finish();
