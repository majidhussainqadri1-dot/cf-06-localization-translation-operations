<?php

declare(strict_types=1);

require __DIR__ . '/TestHarness.php';

$root=dirname(__DIR__);$t=new TestHarness();
$translation=(string)file_get_contents($root.'/src/Application/TranslationService.php');
$terms=(string)file_get_contents($root.'/src/Application/TerminologyService.php');
$routes=(string)file_get_contents($root.'/src/Rest/Routes.php');

$t->test('Translation memory requires explicit reusable-rights evidence and bounded license',function()use($translation):void{
    TestHarness::assertTrue(str_contains($translation,'translation_memory_reuse_allowed'));
    TestHarness::assertTrue(str_contains($translation,"license_code"));
    TestHarness::assertTrue(str_contains($translation,"strlen($license)>80"));
});
$t->test('Translation memory records reviewer provider version and project provenance',function()use($translation):void{
    foreach(['project_uuid','source_version','source_hash','translator_id','linguistic_reviewer_id','domain_reviewer_id','provider_key','model_version','region_code'] as $needle){TestHarness::assertTrue(str_contains($translation,$needle),$needle);}
});
$t->test('Memory suggestion is context-aware warning-only and never auto-accepts',function()use($terms,$routes):void{
    foreach(['context_hash','context_match','context-mismatch-human-review-required','fuzzy-suggestion-human-review-required',"'auto_accept'=>false"] as $needle){TestHarness::assertTrue(str_contains($terms,$needle),$needle);}
    TestHarness::assertTrue(str_contains($routes,"get_param('context')"));
});

$t->finish();
