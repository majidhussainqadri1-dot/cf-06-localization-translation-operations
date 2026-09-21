<?php

declare(strict_types=1);

require __DIR__ . '/TestHarness.php';

$root=dirname(__DIR__);$t=new TestHarness();
$ci=(string)file_get_contents($root.'/.github/workflows/ci.yml');
$build=(string)file_get_contents($root.'/tools/build-release.py');

$t->test('CI third-party actions and MySQL image are immutable-pinned',function()use($ci):void{
    foreach(['actions/checkout@11d5960a326750d5838078e36cf38b85af677262','shivammathur/setup-php@f3e473d116dcccaddc5834248c87452386958240','actions/upload-artifact@ea165f8d65b6e75b540449e92b4886f43607fa02','mysql:8.0@sha256:7dcddc01f13bab2f15cde676d44d01f61fc9f99fe7785e86196dfc07d358ae2b'] as $needle){TestHarness::assertTrue(str_contains($ci,$needle),$needle);}
});

$t->test('CI verifies exact PR head or push SHA before testing and packaging',function()use($ci):void{
    TestHarness::assertTrue(str_contains($ci,'EXACT_SOURCE_SHA: ${{ github.event.pull_request.head.sha || github.sha }}'));
    TestHarness::assertTrue(substr_count($ci,'ref: ${{ env.EXACT_SOURCE_SHA }}')>=2);
    TestHarness::assertTrue(substr_count($ci,'git rev-parse HEAD')>=2);
    TestHarness::assertTrue(substr_count($ci,'EXPECTED_SHA: ${{ env.EXACT_SOURCE_SHA }}')>=2);
});

$t->test('Package remains exact-commit byte-parity and deterministic bound',function()use($build):void{
    foreach(['validated_source_commit','Source/package byte parity failed','Embedded manifest parity failed','sabri:source-commit','FIXED_TIME'] as $needle){TestHarness::assertTrue(str_contains($build,$needle),$needle);}
});

$t->finish();
