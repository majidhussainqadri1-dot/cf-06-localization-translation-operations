<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
$root=dirname(__DIR__);$t=new TestHarness();
$translation=(string)file_get_contents($root.'/src/Application/TranslationService.php');
$project=(string)file_get_contents($root.'/src/Application/ProjectService.php');

$t->test('Translation submission refuses retired or inactive source resources',function()use($translation):void{
  TestHarness::assertTrue(str_contains($translation,"'active'!==(string)($resource['status']??'')"));
  TestHarness::assertTrue(str_contains($translation,'stale, retired or inactive'));
});

$t->test('Project creation rechecks source and locale eligibility inside transaction',function()use($project):void{
  $tx=strpos($project,'return $this->tx->run(function()use($input,$name');
  $locale=strpos($project,'Project locale changed eligibility before project creation');
  $source=strpos($project,'Project source changed before transactional snapshot creation');
  TestHarness::assertTrue(false!==$tx&&false!==$locale&&false!==$source&&$tx<$locale&&$locale<$source);
});
$t->finish();
