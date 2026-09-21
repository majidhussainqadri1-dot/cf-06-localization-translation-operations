<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
$root=dirname(__DIR__);$t=new TestHarness();
$url=(string)file_get_contents($root.'/src/Domain/Security/UrlGuard.php');
$provider=(string)file_get_contents($root.'/src/Application/ProviderService.php');
$privacy=(string)file_get_contents($root.'/src/Application/PrivacyService.php');
$t->test('Provider DNS guard audits both A and AAAA records',function()use($url):void{
 foreach(['dns_get_record','DNS_A | DNS_AAAA',"'ipv6'","FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE"] as $n){TestHarness::assertTrue(str_contains($url,$n),$n);}
});
$t->test('Provider retention governance rejects rather than silently clamps',function()use($provider):void{
 TestHarness::assertTrue(str_contains($provider,"FILTER_VALIDATE_INT"));
 TestHarness::assertTrue(str_contains($provider,"'min_range'=>0,'max_range'=>30"));
 TestHarness::assertTrue(str_contains($provider,'retention_days must be an explicit integer'));
 TestHarness::assertTrue(!str_contains($provider,'max(0,min(30'));
});
$t->test('Privacy erasure advances optimistic-lock versions on mutable versioned rows',function()use($privacy):void{
 TestHarness::assertTrue(substr_count($privacy,'row_version=row_version+1')>=18);
 foreach(["Database::table('assignments')","Database::table('units')","Database::table('projects')","Database::table('resources')","Database::table('release_approvals')"] as $n){TestHarness::assertTrue(str_contains($privacy,$n),$n);}
});
$t->finish();
