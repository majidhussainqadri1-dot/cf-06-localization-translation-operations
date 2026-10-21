<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
$root=dirname(__DIR__);$t=new TestHarness();
$composer=(string)file_get_contents($root.'/composer.json');
$workflow=(string)file_get_contents($root.'/.github/workflows/ci.yml');
$build=(string)file_get_contents($root.'/tools/build-release.py');
$quality=(string)file_get_contents($root.'/tools/quality-check.sh');

$t->test('Mandatory OpenSSL runtime dependency is declared tested and inventoried',function()use($composer,$workflow,$build,$quality):void{
 $json=json_decode($composer,true,512,JSON_THROW_ON_ERROR);
 TestHarness::assertSame('*',$json['require']['ext-openssl']??null);
 TestHarness::assertTrue(str_contains($workflow,'extensions: mysqli, openssl'));
 TestHarness::assertTrue(str_contains($build,'"name": "ext-openssl"'));
 TestHarness::assertTrue(str_contains($quality,'ext-openssl'));
});
$t->test('Secret-pattern scan includes regression tests rather than excluding them',function()use($quality):void{
 TestHarness::assertTrue(!str_contains($quality,'--exclude-dir=tests'));
});
$t->finish();
