<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
$root=dirname(__DIR__);$t=new TestHarness();
$tr=(string)file_get_contents($root.'/src/Application/TranslationService.php');
$t->test('Translation actions reverify assignment qualification at use time',function()use($tr):void{
 foreach([
  "json_decode((string)(\$assignment['qualification_json']??''),true)",
  "'assignment_uuid'=>(string)(\$assignment['uuid']??'')",
  "'verification_phase'=>'action-time'",
  "apply_filters('slto_verify_assignment_qualification',false,\$evidence)"
 ] as $n){TestHarness::assertTrue(str_contains($tr,$n),$n);}
});
$t->finish();
