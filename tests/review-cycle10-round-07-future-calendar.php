<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
require dirname(__DIR__) . '/src/Contract/FutureCapabilities.php';
require dirname(__DIR__) . '/src/Application/FutureCapabilitiesService.php';

use Sabri\Localization\Application\FutureCapabilitiesService;

$t=new TestHarness();$s=new FutureCapabilitiesService();

$t->test('Future40 calendar preview rejects impossible Gregorian dates',function()use($s):void{
    TestHarness::assertThrows(
        fn()=>$s->evaluate('CF06-FUT-020',['canonical_iso_date'=>'2026-02-30','display_mode'=>'dual']),
        InvalidArgumentException::class
    );
    $ok=$s->evaluate('CF06-FUT-020',['canonical_iso_date'=>'2026-02-28','display_mode'=>'dual']);
    TestHarness::assertSame('2026-02-28',$ok['result']['canonical_storage']);
});

$t->finish();
