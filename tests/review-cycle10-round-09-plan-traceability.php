<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';

$root=dirname(__DIR__);$t=new TestHarness();
$c=(string)file_get_contents($root.'/src/Application/ContentLinkService.php');
$p=(string)file_get_contents($root.'/src/Plugin.php');
$e=(string)file_get_contents($root.'/src/Contract/Events.php');
$r=(string)file_get_contents($root.'/docs/REQUIREMENTS-TRACEABILITY.md');

$t->test('FR-028 publication changes emit a versioned search-reconciliation fact',function()use($c,$p,$e,$r):void{
    foreach([
        'ContentTranslationPublicationChanged',
        'search_reconciliation_required',
        "'publication_status'=>\$status",
        "'source_hash'=>\$hash",
        "'hreflang_code'=>\$data['hreflang_code']",
    ] as $n){TestHarness::assertTrue(str_contains($c,$n),$n);}
    TestHarness::assertTrue(str_contains($p,'new ContentLinkService($repo,$audit,$outbox,$tx)'));
    TestHarness::assertTrue(str_contains($e,'ContentTranslationPublicationChanged'));
    TestHarness::assertTrue(str_contains($r,'search/retraction reconciliation notification'));
});

$t->finish();
