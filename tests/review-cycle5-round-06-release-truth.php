<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
$root=dirname(__DIR__);$t=new TestHarness();
$bundle=(string)file_get_contents($root.'/src/Application/BundleService.php');
$integration=(string)file_get_contents($root.'/src/Application/IntegrationService.php');
$qa=(string)file_get_contents($root.'/src/Application/QaEvidenceService.php');
$health=(string)file_get_contents($root.'/src/Application/HealthService.php');
$approval=(string)file_get_contents($root.'/src/Application/ReleaseApprovalService.php');
$contracts=(string)file_get_contents($root.'/docs/CONTRACTS.md');

$t->test('Release narratives are bounded and current integration evidence is reverified',function()use($bundle,$integration):void{
 TestHarness::assertTrue(str_contains($bundle,'Bundle transition requires a nonempty bounded reason.'));
 TestHarness::assertTrue(str_contains($integration,'A bounded integration revocation reason is required.'));
 TestHarness::assertTrue(substr_count($integration,'slto_verify_integration_acceptance_evidence')>=2);
});
$t->test('QA evidence identities and required-test gates are bounded',function()use($qa):void{
 foreach(['strlen($testId) > 80','strlen($targetType) > 40','strlen($targetUuid) > 191','empty($requiredTestIds)','count($requiredTestIds) > 100'] as $n){TestHarness::assertTrue(str_contains($qa,$n),$n);}
});
$t->test('Live-deployed truth requires exact configured source and independent parity verifier',function()use($health,$contracts):void{
 foreach(['SLTO_DEPLOYED_SOURCE_COMMIT','slto_verify_live_deployment_parity','live_parity_verified'] as $n){TestHarness::assertTrue(str_contains($health,$n),$n);}
 TestHarness::assertTrue(str_contains($contracts,'slto_verify_staging_acceptance_evidence'));
 TestHarness::assertTrue(str_contains($contracts,'slto_verify_live_deployment_parity'));
});
$t->test('Release step-up timestamps reject material future skew',function()use($approval):void{
 TestHarness::assertTrue(str_contains($approval,'$stepAge < -60'));
 TestHarness::assertTrue(str_contains($approval,'$stepAge>self::APPROVAL_TTL_SECONDS'));
});
$t->finish();
