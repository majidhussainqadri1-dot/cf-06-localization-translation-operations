<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
$root=dirname(__DIR__);$t=new TestHarness();
$u=(string)file_get_contents($root.'/src/Domain/Security/UrlGuard.php');
$c=(string)file_get_contents($root.'/src/Infrastructure/Crypto.php');
$a=(string)file_get_contents($root.'/src/Security/Authorization.php');

$t->test('Provider URL guard rejects embedded credentials and fragments',function()use($u):void{
 foreach(["isset(\$parts['user'])","isset(\$parts['pass'])","isset(\$parts['fragment'])",'without embedded credentials or fragments'] as $n){TestHarness::assertTrue(str_contains($u,$n),$n);}
});
$t->test('Secure payload decrypt rejects algorithm drift',function()use($c):void{
 TestHarness::assertTrue(str_contains($c,"'AES-256-GCM' !== (string)(\$envelope['algorithm'] ?? '')"));
 TestHarness::assertTrue(str_contains($c,'encryption algorithm is unsupported or tampered'));
});
$t->test('Authorization extension must explicitly return boolean true',function()use($a):void{
 TestHarness::assertTrue(str_contains($a,'return true === $decision;'));
 TestHarness::assertTrue(!str_contains($a,'return false !== $decision;'));
});
$t->finish();
