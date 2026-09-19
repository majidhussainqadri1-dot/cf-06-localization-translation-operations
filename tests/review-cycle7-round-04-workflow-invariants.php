<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
$root=dirname(__DIR__);$t=new TestHarness();
$tr=(string)file_get_contents($root.'/src/Application/TranslationService.php');
$f=(string)file_get_contents($root.'/src/Application/FeedbackService.php');
$term=(string)file_get_contents($root.'/src/Application/TerminologyService.php');

$t->test('Machine drafts are application-layer bound to validated vendor-job provenance',function()use($tr):void{
 foreach(['assertMachineDraftProvenance','Machine draft provenance is not eligible','validated vendor job containing this unit',"RiskPolicy::machineTranslationAllowed"] as $n){TestHarness::assertTrue(str_contains($tr,$n),$n);}
});
$t->test('Public feedback only targets registered enabled or degraded locales',function()use($f):void{
 foreach(["findOne('locales','locale_tag',\$locale)","array('enabled','degraded')",'Feedback locale is not currently available'] as $n){TestHarness::assertTrue(str_contains($f,$n),$n);}
});
$t->test('Terminology requires distinct registered locale pair',function()use($term):void{
 TestHarness::assertTrue(str_contains($term,'$sourceLocale===$targetLocale'));
 TestHarness::assertTrue(str_contains($term,"findOne('locales','locale_tag',\$localeTag)"));
 TestHarness::assertTrue(str_contains($term,'Terminology locale is not registered'));
});
$t->finish();
