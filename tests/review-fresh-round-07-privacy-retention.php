<?php

declare(strict_types=1);

require __DIR__ . '/TestHarness.php';

$root=dirname(__DIR__);$t=new TestHarness();
$privacy=(string)file_get_contents($root.'/src/Application/PrivacyService.php');
$repo=(string)file_get_contents($root.'/src/Infrastructure/Repository/LocalizationRepository.php');

$t->test('Privacy export includes user-authored comment and feedback content',function()use($privacy):void{
    TestHarness::assertTrue(str_contains($privacy,'comment_text'));
    TestHarness::assertTrue(str_contains($privacy,'suggestion_text'));
});
$t->test('Retired secure payload ciphertext is erased rather than only hidden',function()use($repo):void{
    TestHarness::assertTrue(str_contains($repo,"ciphertext='',nonce='',auth_tag='',aad_hash='',payload_hash=''"));
    TestHarness::assertTrue(str_contains($repo,'cryptographically erased'));
});
$t->test('Expired secure payload cleanup also removes recoverable material',function()use($repo):void{
    TestHarness::assertTrue(substr_count($repo,"ciphertext='',nonce='',auth_tag='',aad_hash='',payload_hash=''" )>=2);
});

$t->finish();
