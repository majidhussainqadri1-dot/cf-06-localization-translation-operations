<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
$root=dirname(__DIR__);$t=new TestHarness();
$b=(string)file_get_contents($root.'/tools/build-release.py');$r=(string)file_get_contents($root.'/README.md');
$t->test('Package metadata distinguishes reproducibility epoch from build time',function()use($b):void{
 TestHarness::assertTrue(str_contains($b,'reproducible_archive_epoch'));
 TestHarness::assertTrue(str_contains($b,'deterministic-reproducibility-only-not-build-time'));
 TestHarness::assertTrue(!str_contains($b,'"build_epoch"'));
 TestHarness::assertTrue(!str_contains($b,'"timestamp": "2026-09-16T00:00:00Z"'));
});
$t->test('README local build is explicitly exact-source bound',function()use($r):void{
 TestHarness::assertTrue(str_contains($r,'SOURCE_COMMIT="$(git rev-parse HEAD)"'));
 TestHarness::assertTrue(str_contains($r,'fixed ZIP archive epoch is a reproducibility control'));
});
$t->finish();
