<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
$root=dirname(__DIR__);$t=new TestHarness();
$b=(string)file_get_contents($root.'/tools/build-release.py');
$r=(string)file_get_contents($root.'/README.md');
$t->test('Package source commit must equal checked-out Git HEAD',function()use($b):void{
 foreach(['git", "rev-parse", "HEAD"','SOURCE_COMMIT does not match the checked-out Git HEAD','Release packaging requires a clean exact-commit working tree'] as $n){TestHarness::assertTrue(str_contains($b,$n),$n);}
});
$t->test('Package cleanliness ignores only generated dist evidence',function()use($b):void{
 TestHarness::assertTrue(str_contains($b,'path.startswith("dist/")'));
 TestHarness::assertTrue(str_contains($b,'"--untracked-files=all"'));
});
$t->test('SBOM identity is deterministic per source commit rather than globally fixed',function()use($b):void{
 TestHarness::assertTrue(str_contains($b,'uuid.uuid5'));
 TestHarness::assertTrue(str_contains($b,'"cf06:" + source_commit'));
 TestHarness::assertTrue(!str_contains($b,'cf060000-0000-4000-8000-000000000001'));
});
$t->test('README documents clean exact-head package law',function()use($r):void{
 TestHarness::assertTrue(str_contains($r,'rejects a dirty working tree'));
 TestHarness::assertTrue(str_contains($r,'must exactly match HEAD'));
});
$t->finish();
