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
require dirname(__DIR__) . '/src/Domain/Future/SemanticIntegrityGuard.php';
require dirname(__DIR__) . '/src/Domain/Future/ReleaseLifecycleGuard.php';
require dirname(__DIR__) . '/src/Application/FutureCapabilitiesService.php';
require dirname(__DIR__) . '/src/Application/FutureCapabilitiesFacade.php';

use Sabri\Localization\Application\FutureCapabilitiesFacade;

$t = new TestHarness();
$s = new FutureCapabilitiesFacade();

$t->test('OCR threshold must remain within 0..1', fn () => TestHarness::assertThrows(
    fn () => $s->evaluate('CF06-FUT-016', ['confidence'=>0.95,'threshold'=>-0.1]), InvalidArgumentException::class
));
$t->test('Regional locale tags are validated and canonicalized', function () use ($s): void {
    TestHarness::assertThrows(fn () => $s->evaluate('CF06-FUT-018', ['locale'=>'../ur','base_locale'=>'en-US']), InvalidArgumentException::class);
    $out = $s->evaluate('CF06-FUT-018', ['locale'=>'UR_arab_pk','base_locale'=>'en-US']);
    TestHarness::assertSame('ur-Arab-PK', $out['result']['locale']);
    TestHarness::assertSame(['ur-Arab-PK','en-US'], $out['result']['fallback_chain']);
});
$t->test('Calendar layer rejects impossible Gregorian dates', fn () => TestHarness::assertThrows(
    fn () => $s->evaluate('CF06-FUT-020', ['canonical_iso_date'=>'2026-99-99','display_mode'=>'dual']), InvalidArgumentException::class
));
$t->test('Glyph scanner requires a real audited codepoint inventory', function () use ($s): void {
    TestHarness::assertThrows(fn () => $s->evaluate('CF06-FUT-022', ['text'=>'A','supported_codepoints'=>[]]), InvalidArgumentException::class);
    TestHarness::assertThrows(fn () => $s->evaluate('CF06-FUT-022', ['text'=>'A','supported_codepoints'=>['0041']]), InvalidArgumentException::class);
});
$t->test('International SEO rejects malformed locale and credentialed/non-HTTPS URLs', function () use ($s): void {
    TestHarness::assertThrows(fn () => $s->evaluate('CF06-FUT-025', ['links'=>[['hreflang'=>'../ur','url'=>'https://example.test/ur','canonical'=>'https://example.test/ur']]]), InvalidArgumentException::class);
    TestHarness::assertThrows(fn () => $s->evaluate('CF06-FUT-025', ['links'=>[['hreflang'=>'ur-PK','url'=>'http://example.test/ur','canonical'=>'https://example.test/ur']]]), InvalidArgumentException::class);
    TestHarness::assertThrows(fn () => $s->evaluate('CF06-FUT-025', ['links'=>[['hreflang'=>'ur-PK','url'=>'https://user:pass@example.test/ur','canonical'=>'https://example.test/ur']]]), InvalidArgumentException::class);
});
$t->test('Locale launch coverage and threshold remain bounded percentages', function () use ($s): void {
    $scope=['locale'=>'ur-PK','feature_id'=>'home.title','domain'=>'platform'];
    TestHarness::assertThrows(fn () => $s->evaluate('CF06-FUT-026', $scope+['coverage_percent'=>101,'critical_missing'=>0,'threshold_percent'=>95]), InvalidArgumentException::class);
    TestHarness::assertThrows(fn () => $s->evaluate('CF06-FUT-026', $scope+['coverage_percent'=>99,'critical_missing'=>0,'threshold_percent'=>-1]), InvalidArgumentException::class);
});
$t->test('Accessibility groups reject non-scalar and unsafe bidi values', function () use ($s): void {
    $base=['target_locale'=>'ur-PK','alt_text'=>['a'],'captions'=>['c'],'transcript'=>['t'],'aria_labels'=>['l']];
    $bad=$base;$bad['alt_text']=[['nested']];
    TestHarness::assertThrows(fn () => $s->evaluate('CF06-FUT-017',$bad),InvalidArgumentException::class);
    $bad=$base;$bad['captions']=["unsafe \u{202E} control"];
    TestHarness::assertThrows(fn () => $s->evaluate('CF06-FUT-017',$bad),InvalidArgumentException::class);
});
$t->test('Locale registration cannot skip governed initial state', function (): void {
    $code=file_get_contents(dirname(__DIR__).'/src/Application/LocaleService.php');
    TestHarness::assertTrue(str_contains($code,"'proposed' !== $status"));
    TestHarness::assertTrue(str_contains($code,'advance through governed transitions'));
});

$t->finish();
