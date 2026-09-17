<?php

declare(strict_types=1);

require __DIR__ . '/TestHarness.php';

$root=dirname(__DIR__);$t=new TestHarness();
$provider=(string)file_get_contents($root.'/src/Application/ProviderService.php');
$privacy=(string)file_get_contents($root.'/src/Application/PrivacyService.php');
$mt=(string)file_get_contents($root.'/src/Application/MachineTranslationService.php');
$risk=(string)file_get_contents($root.'/src/Domain/Translation/RiskPolicy.php');

$t->test('Provider updates require caller-supplied optimistic-lock version',function()use($provider):void{
    TestHarness::assertTrue(str_contains($provider,'Provider update requires an explicit current row_version.'));
    TestHarness::assertTrue(str_contains($provider,"array_key_exists('row_version',$input)"));
});

$t->test('Provider governance metadata and transition narratives are bounded',function()use($provider):void{
    foreach(['Provider host allowlist exceeds the bounded limit.','Provider subprocessor identifier exceeds the bounded limit.','Provider transition reason exceeds the bounded limit.'] as $needle){TestHarness::assertTrue(str_contains($provider,$needle),$needle);}
});

$t->test('Privacy erasure uses secret-keyed deterministic pseudonymization',function()use($privacy):void{
    foreach(["wp_salt('auth')",'hash_hmac','Privacy pseudonymization secret is unavailable.','Localization privacy erasure reason is invalid or oversized.'] as $needle){TestHarness::assertTrue(str_contains($privacy,$needle),$needle);}
    TestHarness::assertTrue(!str_contains($privacy,"hash('sha256','slto|'"));
});

$t->test('External MT remains low-risk C1 only and runtime provider-governed',function()use($mt,$risk):void{
    foreach(['assertActiveProvider','assertAdapterGovernance','approved provider region'] as $needle){TestHarness::assertTrue(str_contains($mt,$needle),$needle);}
    TestHarness::assertTrue(str_contains($risk,"'C1' !== strtoupper($dataClass)"));
    TestHarness::assertTrue(str_contains($risk,"'low' !== strtolower($riskClass)"));
});

$t->finish();
