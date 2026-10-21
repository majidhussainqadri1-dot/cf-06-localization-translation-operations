<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
require dirname(__DIR__) . '/src/Domain/Locale/LocaleValidator.php';
require dirname(__DIR__) . '/src/Domain/Translation/BidiValidator.php';
require dirname(__DIR__) . '/src/Domain/Translation/RiskPolicy.php';
require dirname(__DIR__) . '/src/Contract/FutureCapabilities.php';
require dirname(__DIR__) . '/src/Domain/Future/FutureCapabilityGuard.php';
require dirname(__DIR__) . '/src/Domain/Future/HotfixApprovalGuard.php';
require dirname(__DIR__) . '/src/Domain/Future/LocaleAccessibilityGuard.php';
require dirname(__DIR__) . '/src/Domain/Future/ProviderEligibilityGuard.php';
require dirname(__DIR__) . '/src/Domain/Future/ReleaseLifecycleGuard.php';
require dirname(__DIR__) . '/src/Domain/Future/SemanticIntegrityGuard.php';
require dirname(__DIR__) . '/src/Application/FutureCapabilitiesService.php';
require dirname(__DIR__) . '/src/Application/FutureCapabilitiesFacade.php';

use Sabri\Localization\Application\FutureCapabilitiesFacade;

$t=new TestHarness();$s=new FutureCapabilitiesFacade();

$t->test('Future40 canonical facade rejects deeply nested structured evidence before recursive guards',function()use($s):void{
    $value='leaf';
    for($i=0;$i<70;$i++){$value=['n'=>$value];}
    TestHarness::assertThrows(fn()=>$s->evaluate('CF06-FUT-009',[
        'source_citations'=>[$value],
        'target_citations'=>[$value],
    ]),InvalidArgumentException::class);
    $code=(string)file_get_contents(dirname(__DIR__).'/src/Application/FutureCapabilitiesFacade.php');
    TestHarness::assertTrue(str_contains($code,'MAX_EVALUATION_DEPTH = 64'));
    TestHarness::assertTrue(str_contains($code,'Future capability input exceeds the bounded nesting depth.'));
});

$t->finish();
