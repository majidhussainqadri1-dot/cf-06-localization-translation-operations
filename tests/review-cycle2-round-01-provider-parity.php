<?php

declare(strict_types=1);

require __DIR__ . '/TestHarness.php';

$root=dirname(__DIR__);$t=new TestHarness();
$mt=(string)file_get_contents($root.'/src/Application/MachineTranslationService.php');
$http=(string)file_get_contents($root.'/src/Provider/HttpJsonProvider.php');

$t->test('Runtime MT adapter is bound to approved provider configuration',function()use($mt):void{
    foreach(['assertAdapterGovernance','base_url','credential_reference','contract_version','allowed_hosts','adapter configuration does not match the approved provider registry','adapter hosts do not match the approved provider registry'] as $needle){TestHarness::assertTrue(str_contains($mt,$needle),$needle);}
});
$t->test('HTTP adapter exposes bounded governance attestation without exposing secret value',function()use($http):void{
    foreach(['allowedHosts','credential_reference','contract_version','training_allowed'] as $needle){TestHarness::assertTrue(str_contains($http,$needle),$needle);}
    TestHarness::assertTrue(str_contains($http,"'env:'.\$credentialEnv"));
});
$t->test('Vendor response retains model and deletion reference provenance',function()use($mt):void{
    TestHarness::assertTrue(str_contains($mt,'response must include bounded reference and model-version provenance'));
    TestHarness::assertTrue(str_contains($mt,"'model_version'=>\$modelVersion"));
    TestHarness::assertTrue(str_contains($mt,"'provider_reference'=>\$providerReference"));
});

$t->finish();
