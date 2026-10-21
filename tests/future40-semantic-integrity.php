<?php

declare(strict_types=1);

require __DIR__ . '/TestHarness.php';
require dirname(__DIR__) . '/src/Domain/Locale/LocaleValidator.php';
require dirname(__DIR__) . '/src/Domain/Translation/BidiValidator.php';
require dirname(__DIR__) . '/src/Domain/Translation/RiskPolicy.php';
require dirname(__DIR__) . '/src/Contract/FutureCapabilities.php';
require dirname(__DIR__) . '/src/Domain/Future/FutureCapabilityGuard.php';
require dirname(__DIR__) . '/src/Domain/Future/LocaleAccessibilityGuard.php';
require dirname(__DIR__) . '/src/Domain/Future/ProviderEligibilityGuard.php';
require dirname(__DIR__) . '/src/Domain/Future/ReleaseLifecycleGuard.php';
require dirname(__DIR__) . '/src/Domain/Future/SemanticIntegrityGuard.php';
require dirname(__DIR__) . '/src/Application/FutureCapabilitiesService.php';
require dirname(__DIR__) . '/src/Application/FutureCapabilitiesFacade.php';

use Sabri\Localization\Application\FutureCapabilitiesFacade;

$t = new TestHarness();
$s = new FutureCapabilitiesFacade();

$t->test('Semantic equivalence flags declared protected fact drift', function () use ($s): void {
    $out = $s->evaluate('CF06-FUT-005', ['source'=>'Use Arnica 30C at https://example.test','target'=>'آرنیکا 30C استعمال کریں https://example.test','protected_terms'=>['Arnica']]);
    TestHarness::assertTrue(in_array('protected-fact-drift', $out['result']['flags'], true));
    TestHarness::assertSame(false, $out['result']['approval_authority']);
});
$t->test('Translation risk diff escalates protected-term changes', function () use ($s): void {
    $out = $s->evaluate('CF06-FUT-006', ['old_source'=>'Use Arnica 30C','new_source'=>'Use Belladonna 30C','old_protected_terms'=>['Arnica'],'new_protected_terms'=>['Belladonna']]);
    TestHarness::assertSame(true, $out['result']['protected_token_change']);
    TestHarness::assertSame('high-review', $out['result']['risk']);
});
$t->test('Citation integrity is canonical and independent of citation order', function () use ($s): void {
    $a=['ref'=>'Q1','details'=>['page'=>1,'edition'=>'2026']];$b=['details'=>['edition'=>'2026','page'=>1],'ref'=>'Q1'];
    $out=$s->evaluate('CF06-FUT-009',['source_citations'=>[$a,['ref'=>'Q2','page'=>2]],'target_citations'=>[['page'=>2,'ref'=>'Q2'],$b]]);
    TestHarness::assertSame('pass',$out['result']['integrity']);TestHarness::assertSame('canonical-order-independent-multiset',$out['result']['comparison']);
});
$t->test('Citation integrity still detects a missing duplicate citation', function () use ($s): void {
    $c=['ref'=>'Q1','page'=>1];$out=$s->evaluate('CF06-FUT-009',['source_citations'=>[$c,$c],'target_citations'=>[$c]]);TestHarness::assertSame('fail',$out['result']['integrity']);
});
$t->test('Protected domain token inventory includes declared exact terms and placeholders', function () use ($s): void {
    $out=$s->evaluate('CF06-FUT-010',['text'=>'Arnica {dose} 30C','protected_terms'=>['Arnica']]);TestHarness::assertTrue(in_array('Arnica',$out['result']['tokens'],true));TestHarness::assertTrue(in_array('{dose}',$out['result']['tokens'],true));TestHarness::assertTrue(in_array('30C',$out['result']['tokens'],true));
});
$t->test('AI quality estimation flags protected token drift without approval authority', function () use ($s): void {
    $out=$s->evaluate('CF06-FUT-036',['source'=>'Take Arnica 30C','target'=>'30C لیں','protected_terms'=>['Arnica']]);TestHarness::assertTrue(in_array('protected-token-drift',$out['result']['flags'],true));TestHarness::assertSame(false,$out['result']['approval_authority']);TestHarness::assertSame(true,$out['result']['human_review_required']);
});
$t->test('Protected-term evidence is bounded and scalar before handler execution', function () use ($s): void {
    TestHarness::assertThrows(fn()=> $s->evaluate('CF06-FUT-005',['source'=>'A','target'=>'A','protected_terms'=>[['nested']]]),InvalidArgumentException::class);
    TestHarness::assertThrows(fn()=> $s->evaluate('CF06-FUT-005',['source'=>'A','target'=>'A','protected_terms'=>[str_repeat('x',257)]]),InvalidArgumentException::class);
});
$t->test('Citation evidence is bounded before hashing', function () use ($s): void {
    $rows=array_fill(0,501,['ref'=>'Q']);
    TestHarness::assertThrows(fn()=> $s->evaluate('CF06-FUT-009',['source_citations'=>$rows,'target_citations'=>[]]),InvalidArgumentException::class);
});

$t->finish();
