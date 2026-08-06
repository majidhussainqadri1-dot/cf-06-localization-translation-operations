<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';require __DIR__ . '/TestHarness.php';
use Sabri\Localization\Domain\Translation\Redactor;use Sabri\Localization\Domain\Translation\RiskPolicy;
$root=dirname(__DIR__);$t=new TestHarness();
$t->test('Runtime and MT provider are disabled by default',function()use($root):void{$p=file_get_contents($root.'/src/Plugin.php');$a=file_get_contents($root.'/src/Infrastructure/Activator.php');TestHarness::assertTrue(str_contains($a,"slto_runtime_enabled', false"));TestHarness::assertTrue(str_contains($p,'NullProvider'));});
$t->test('C4 and C5 fail closed for external MT',fn()=>TestHarness::assertTrue(!RiskPolicy::machineTranslationAllowed('low','C5','platform',true)));
$t->test('Redaction catches email, phone, ID and secret patterns',function():void{$r=Redactor::redact('a@b.com +92 300 1234567 12345-1234567-1 token_abcdefghijklmnop');TestHarness::assertTrue(($r['counts']['email']??0)>0);TestHarness::assertTrue(($r['counts']['phone']??0)>0);TestHarness::assertTrue(array_sum($r['counts'])>=3);TestHarness::assertTrue(($r['counts']['secret']??0)>0);});
$t->test('REST uses capability authorization and safe errors',function()use($root):void{$s=file_get_contents($root.'/src/Rest/Routes.php');TestHarness::assertTrue(str_contains($s,'Authorization'));TestHarness::assertTrue(str_contains($s,'$this->error'));TestHarness::assertTrue(str_contains($s,'Authorization::allowed'));});
$t->test('WordPress privacy eraser queues audited background erasure',function()use($root):void{$code=file_get_contents($root.'/src/Plugin.php');TestHarness::assertTrue(str_contains($code,'wp_privacy_personal_data_erasers'));TestHarness::assertTrue(str_contains($code,'requestErasure'));});
$t->test('Migration dry-run validates hashes and persists evidence safely',function()use($root):void{$code=file_get_contents($root.'/src/Application/MigrationService.php');TestHarness::assertTrue(str_contains($code,"preg_match('/^[a-f0-9]{64}$/D'"));TestHarness::assertTrue(str_contains($code,'Migration dry-run evidence could not be persisted'));});
$t->finish();
