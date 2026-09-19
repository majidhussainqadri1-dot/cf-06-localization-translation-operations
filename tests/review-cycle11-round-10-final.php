<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
$root=dirname(__DIR__);$t=new TestHarness();
$a=(string)file_get_contents($root.'/src/Infrastructure/Activator.php');
$r=(string)file_get_contents($root.'/src/Rest/Routes.php');
$fresh=(string)file_get_contents($root.'/tests/review-fresh-round-06-bundle-coverage.php');

$t->test('Runtime schema parity validates primary unique and non-unique indexes',function()use($a):void{
    foreach(['expectedIndexes','PRIMARY KEY','required non-unique schema index is unavailable or drifted','required unique schema index is unavailable or drifted'] as $n){
        TestHarness::assertTrue(str_contains($a,$n),$n);
    }
    TestHarness::assertTrue(str_contains($a,"'unique'=>$unique"));
});
$t->test('Public feedback mutation requires normal idempotency evidence',function()use($r):void{
    TestHarness::assertTrue(str_contains($r,"mutate($r,'feedback.submit'"));
    TestHarness::assertTrue(!str_contains($r,"feedback']->submit((array)$r->get_json_params()),201,false"));
});
$t->test('Older bundle regression follows current evidence-reverification signature',function()use($fresh):void{
    TestHarness::assertTrue(str_contains($fresh,'assertCurrentHumanQa($this->latestQaResults($uuid),$bundle)'));
});
$t->finish();
