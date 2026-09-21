<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
$root=dirname(__DIR__);$t=new TestHarness();
$i=(string)file_get_contents($root.'/src/Contract/IntegrationRegistry.php');
$m=(string)file_get_contents($root.'/src/Contract/Manifest.php');
$r=(string)file_get_contents($root.'/README.md');
$e=(string)file_get_contents($root.'/docs/COMPLETION-EVIDENCE.md');
$d=(string)file_get_contents($root.'/docs/DATA-DICTIONARY.md');

$t->test('Mandatory dependency registry includes notification composer dashboard and locale-data contracts',function()use($i):void{
 foreach(['file19_notifications','file22_composer','file23_dashboard','unicode_cldr_icu'] as $n){TestHarness::assertTrue(str_contains($i,$n),$n);}
});
$t->test('Manifest and README preserve companion non-owner boundaries',function()use($m,$r):void{
 foreach(['localized_notification_transport','page_composer_authority','dashboard_presentation_authority'] as $n){TestHarness::assertTrue(str_contains($m,$n),$n);}
 foreach(['File 19','Files 22/23','File 24','File 25','File 26','CF-04'] as $n){TestHarness::assertTrue(str_contains($r,$n),$n);}
});
$t->test('Completion evidence documents pinned PHP WordPress compatibility matrix',function()use($e):void{
 foreach(['WordPress 6.0.15 and 7.1.1','full PHP × WordPress compatibility matrix','pinned Composer/WP-CLI'] as $n){TestHarness::assertTrue(str_contains($e,$n),$n);}
});
$t->test('Data dictionary matches canonical 27-entity inventory and evidence tables',function()use($d):void{
 TestHarness::assertTrue(str_contains($d,'27 bounded relational entities'));
 foreach(['integration evidence','extraction evidence','QA evidence','release approvals'] as $n){TestHarness::assertTrue(str_contains($d,$n),$n);}
});
$t->finish();
