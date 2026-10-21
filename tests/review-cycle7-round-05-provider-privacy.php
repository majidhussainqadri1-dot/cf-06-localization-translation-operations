<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
$root=dirname(__DIR__);$t=new TestHarness();
$m=(string)file_get_contents($root.'/src/Application/MachineTranslationService.php');
$p=(string)file_get_contents($root.'/src/Application/PrivacyService.php');
$c=(string)file_get_contents($root.'/src/Infrastructure/Crypto.php');

$t->test('MT preparation rejects stale or retired resource-unit source bindings',function()use($m):void{
 foreach(["'active'!==(string)\$resource['status']","\$unit['source_version']!==(int)\$resource['source_version']",'Vendor unit source is stale or no longer active'] as $n){TestHarness::assertTrue(str_contains($m,$n),$n);}
});
$t->test('Privacy erasure payload minimization is inside the erasure transaction',function()use($p):void{
 $tx=strpos($p,'$this->tx->run(function()use($wpdb,$userId,$pseudonym,$jobUuid,$payload)');
 $scrub=strpos($p,'Privacy erasure job payload could not be minimized atomically.');
 $audit=strpos($p,'privacy_erasure_completed');
 TestHarness::assertTrue(false!==$tx&&false!==$scrub&&false!==$audit&&$tx<$scrub&&$scrub<$audit);
});
$t->test('Crypto availability and envelope shape fail closed',function()use($c):void{
 foreach(["function_exists('openssl_encrypt')","function_exists('openssl_decrypt')",'12!==strlen($nonce)','16!==strlen($tag)','encryption envelope is malformed'] as $n){TestHarness::assertTrue(str_contains($c,$n),$n);}
});
$t->finish();
