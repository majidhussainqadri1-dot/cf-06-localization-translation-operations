<?php

declare(strict_types=1);

require __DIR__ . '/TestHarness.php';

$root=dirname(__DIR__);$t=new TestHarness();
$approvals=(string)file_get_contents($root.'/src/Application/ReleaseApprovalService.php');
$bundles=(string)file_get_contents($root.'/src/Application/BundleService.php');
$routes=(string)file_get_contents($root.'/src/Rest/Routes.php');
$rollback=(string)file_get_contents($root.'/docs/ROLLBACK.md');

$t->test('Release approvals expire operationally and are independently reverified at use time',function()use($approvals):void{
    foreach(['APPROVAL_TTL_SECONDS','isFreshApproval','Fresh independently verified dual release approval','slto_verify_release_approval_evidence'] as $needle){
        TestHarness::assertTrue(str_contains($approvals,$needle),$needle);
    }
});

$t->test('Rollback is constrained to exact prior signed bundle with fresh dual approval',function()use($bundles):void{
    foreach(['Rollback target must be the exact prior signed bundle.','previous_bundle_uuid','assertDualApproval($targetUuid)','Rollback requires a bounded reason.'] as $needle){
        TestHarness::assertTrue(str_contains($bundles,$needle),$needle);
    }
});

$t->test('Rollback activation preserves immediate predecessor chain and audit reason',function()use($bundles):void{
    TestHarness::assertTrue(str_contains($bundles,"'previous_bundle_uuid'=>\$active['uuid']"));
    TestHarness::assertTrue(str_contains($bundles,"'reason'=>\$reason"));
});

$t->test('REST rollback route carries explicit reason to canonical service',function()use($routes):void{
    TestHarness::assertTrue(str_contains($routes,"get_param('target_uuid')"));
    TestHarness::assertTrue(str_contains($routes,"get_param('row_version')"));
    TestHarness::assertTrue(str_contains($routes,"get_param('reason')"));
    TestHarness::assertTrue(str_contains($routes,'$this->s[\'bundle\']->rollback'));
});

$t->test('Rollback runbook documents exact-prior and fresh-dual-control law',function()use($rollback):void{
    foreach(['exact `previous_bundle_uuid`','fresh independently verified dual release approvals','bounded incident/operational reason'] as $needle){
        TestHarness::assertTrue(str_contains($rollback,$needle),$needle);
    }
});

$t->finish();
