<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';

$root=dirname(__DIR__);$t=new TestHarness();
$b=(string)file_get_contents($root.'/src/Application/BundleService.php');

$t->test('Public bundle delivery requires a publicly eligible locale state',function()use($b):void{
    $start=strpos($b,'public function publicBundle');
    $locale=strpos($b,"findOne('locales','locale_tag',\$locale)",$start);
    $gate=strpos($b,"array('enabled','degraded')",$start);
    $active=strpos($b,'activeBundle($locale)',$start);
    TestHarness::assertTrue(false!==$start&&false!==$locale&&false!==$gate&&false!==$active&&$locale<$active&&$gate<$active);
    TestHarness::assertTrue(str_contains($b,'return null;'));
});

$t->finish();
