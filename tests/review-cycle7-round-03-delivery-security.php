<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
$root=dirname(__DIR__);$t=new TestHarness();
$r=(string)file_get_contents($root.'/src/Rest/Routes.php');
$c=(string)file_get_contents($root.'/src/Cli/Commands.php');

$t->test('All REST mutations are byte and node bounded before idempotency/business logic',function()use($r):void{
 foreach(['MAX_MUTATION_BYTES','MAX_MUTATION_NODES','slto_payload_too_large','nodeCount($request->get_params())'] as $n){TestHarness::assertTrue(str_contains($r,$n),$n);}
 $bound=strpos($r,'slto_payload_too_large');$idemp=strpos($r,"get_header('Idempotency-Key')");
 TestHarness::assertTrue(false!==$bound&&false!==$idemp&&$bound<$idemp);
});
$t->test('WP-CLI operations require File 00-bound authorization by operation class',function()use($c):void{
 foreach(["authorize('audit')","authorize('manage')","authorize('release')","Authorization::allowed(\$action)",'--user=<authorized-user>'] as $n){TestHarness::assertTrue(str_contains($c,$n),$n);}
});
$t->finish();
