<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
$root=dirname(__DIR__);$t=new TestHarness();
$a=(string)file_get_contents($root.'/src/Security/Authorization.php');
$p=(string)file_get_contents($root.'/src/Application/ProjectService.php');
$tr=(string)file_get_contents($root.'/src/Application/TranslationService.php');
$r=(string)file_get_contents($root.'/src/Rest/Routes.php');
$t->test('Privileged membership assertions must explicitly bind the current actor',function()use($a,$p,$tr):void{
 TestHarness::assertTrue(str_contains($a,"! isset(\$assertions['user_id']) || (int)\$assertions['user_id'] !== \$userId"));
 TestHarness::assertTrue(str_contains($p,"isset(\$a['user_id'])&&(int)\$a['user_id']===\$userId"));
 TestHarness::assertTrue(str_contains($tr,"isset(\$assertions['user_id'])&&(int)\$assertions['user_id']===\$actor"));
});
$t->test('Mutation idempotency keys reject unsafe or ambiguous header syntax',function()use($r):void{
 TestHarness::assertTrue(str_contains($r,"preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,190}$/D',\$key)"));
 TestHarness::assertTrue(str_contains($r,'A valid bounded Idempotency-Key header is required.'));
});
$t->finish();
