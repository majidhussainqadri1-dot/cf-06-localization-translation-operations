<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';

$root=dirname(__DIR__);$t=new TestHarness();
$activator=(string)file_get_contents($root.'/src/Infrastructure/Activator.php');
$cycle4=(string)file_get_contents($root.'/tests/review-cycle4-round-03-schema-parity.php');
$cycle5=(string)file_get_contents($root.'/tests/review-cycle5-round-03-schema-parity.php');

$t->test('Historical schema parity regressions follow canonical DDL derivation',function()use($activator,$cycle4,$cycle5):void{
 foreach(['expectedSchemaColumns','foreach (Database::ENTITIES as $entity=>$suffix)','array_diff($required,$found)'] as $needle){
  TestHarness::assertTrue(str_contains($activator,$needle),$needle);
  TestHarness::assertTrue(str_contains($cycle4,$needle),$needle);
  TestHarness::assertTrue(str_contains($cycle5,$needle),$needle);
 }
});
$t->test('Historical schema regressions no longer depend on removed hand-maintained entity maps',function()use($cycle4,$cycle5):void{
 foreach(["'feedback'=>array(","'secure_payloads'=>array(","'project_resources'=>array("] as $legacy){
  TestHarness::assertTrue(!str_contains($cycle4,$legacy),$legacy);
  TestHarness::assertTrue(!str_contains($cycle5,$legacy),$legacy);
 }
});
$t->finish();
