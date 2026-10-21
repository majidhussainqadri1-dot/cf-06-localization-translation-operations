<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
$root=dirname(__DIR__);$t=new TestHarness();
$q=(string)file_get_contents($root.'/tools/quality-check.sh');
$t->test('Quality gate automatically runs present and future review-cycle regressions',function()use($q):void{
    TestHarness::assertTrue(str_contains($q,'for test in tests/review-cycle*-round-*.php'));
    TestHarness::assertTrue(str_contains($q,'php "$test"'));
    TestHarness::assertTrue(!str_contains($q,'tests/review-cycle10-round-*.php; do'));
});
$t->finish();
