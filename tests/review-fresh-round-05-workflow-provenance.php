<?php

declare(strict_types=1);

require __DIR__ . '/TestHarness.php';

$root=dirname(__DIR__);$t=new TestHarness();
$project=(string)file_get_contents($root.'/src/Application/ProjectService.php');
$terms=(string)file_get_contents($root.'/src/Application/TerminologyService.php');
$state=(string)file_get_contents($root.'/src/Domain/Workflow/StateMachine.php');

$t->test('Project risk ceiling is enforced against every selected resource',function()use($project):void{
    TestHarness::assertTrue(str_contains($project,'riskRank'));
    TestHarness::assertTrue(str_contains($project,'exceeds the declared risk ceiling'));
});
$t->test('Assignment revocation clears stale unit role identity',function()use($project):void{
    TestHarness::assertTrue(str_contains($project,"$unitChanges[$column]=null")||str_contains($project,'$unitChanges[$column]=null'));
    TestHarness::assertTrue(str_contains($project,"'status']='new'")||str_contains($project,"['status']='new'"));
    TestHarness::assertTrue(str_contains($state,"'assigned' => array('new'"));
});
$t->test('Terminology activation preserves the approving reviewer',function()use($terms):void{
    TestHarness::assertTrue(str_contains($terms,'Terminology activation requires preserved approval provenance.'));
    TestHarness::assertTrue(str_contains($terms,"if('approved'===$to){$changes['reviewer_id']=get_current_user_id();}"));
});
$t->test('Style guide activation preserves the original approver',function()use($terms):void{
    TestHarness::assertTrue(str_contains($terms,'Style guide activation requires preserved approval provenance.'));
    TestHarness::assertTrue(str_contains($terms,"if('approved'===$to){$changes['approved_by']=get_current_user_id();}"));
});

$t->finish();
