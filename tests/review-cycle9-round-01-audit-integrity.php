<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
$root=dirname(__DIR__);$t=new TestHarness();
$a=(string)file_get_contents($root.'/src/Infrastructure/Repository/AuditRepository.php');
$t->test('Audit chain hashes canonical stored identity fields',function()use($a):void{
 foreach([
  '$objectType = sanitize_key($objectType);',
  '$action = sanitize_key($action);',
  '$purpose = sanitize_key($purpose);',
  '$result = sanitize_key($result);',
  "'object_type'=>\$objectType",
  "'action_name'=>\$action",
  "'purpose'=>\$purpose",
  "'result'=>\$result",
 ] as $n){TestHarness::assertTrue(str_contains($a,$n),$n);}
});
$t->test('Audit identity and trace fields are bounded before persistence',function()use($a):void{
 foreach(['strlen($objectType) > 40','strlen($objectKey) > 191','strlen($action) > 80','strlen($purpose) > 80','strlen($result) > 20',"preg_match('/^[a-f0-9-]{36}$/D', \$traceId)"] as $n){TestHarness::assertTrue(str_contains($a,$n),$n);}
});
$t->test('Audit minimization covers compound credential and session keys',function()use($a):void{
 foreach(["'authorization'","'api_key'","'access_token'","'refresh_token'","'credential'","'cookie'","'session'",'str_contains($normalized, $needle)'] as $n){TestHarness::assertTrue(str_contains($a,$n),$n);}
});
$t->finish();
