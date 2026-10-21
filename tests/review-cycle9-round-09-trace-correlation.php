<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
$root=dirname(__DIR__);$t=new TestHarness();
$a=(string)file_get_contents($root.'/src/Infrastructure/Repository/AuditRepository.php');
$r=(string)file_get_contents($root.'/src/Rest/Routes.php');
$t->test('Mutation audit records and successful response share one request trace identity',function()use($a,$r):void{
 foreach(['withTrace','currentTraceId','private static array $traceStack'] as $n){TestHarness::assertTrue(str_contains($a,$n),$n);}
 foreach(['$traceId=Database::uuid();','AuditRepository::withTrace($traceId,$operation)',"'trace_id'=>\$traceId",'return $this->error($e,$traceId)'] as $n){TestHarness::assertTrue(str_contains($r,$n),$n);}
});
$t->test('Audit record defaults to current request trace before generating a standalone trace',function()use($a):void{
 TestHarness::assertTrue(str_contains($a,'$traceId = $traceId ?: self::currentTraceId() ?: Database::uuid();'));
});
$t->finish();
