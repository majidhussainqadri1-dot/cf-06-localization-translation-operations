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
require dirname(__DIR__) . '/src/Application/FutureCapabilitiesService.php';
require dirname(__DIR__) . '/src/Application/FutureCapabilitiesFacade.php';

use Sabri\Localization\Application\FutureCapabilitiesFacade;

$t = new TestHarness();
$s = new FutureCapabilitiesFacade();

$t->test('Pseudolocalization preserves placeholders and URLs while adding probes', function () use ($s): void {
    $out = $s->evaluate('CF06-FUT-001', ['text'=>'Hello {name} https://example.test/path','rtl_probe'=>true]);
    TestHarness::assertTrue(str_contains($out['result']['text'], '{name}'));
    TestHarness::assertTrue(str_contains($out['result']['text'], 'https://example.test/path'));
    TestHarness::assertSame(true, $out['result']['protected_tokens_preserved']);
    TestHarness::assertTrue(null !== $out['result']['rtl_probe_text']);
});
$t->test('Device matrix always includes governed 320-1920 core and rejects sub-320 widths', function () use ($s): void {
    $out = $s->evaluate('CF06-FUT-003', ['viewports'=>[2048]]);
    $widths = array_values(array_unique(array_column($out['result']['matrix'], 'width')));
    foreach ([320,375,768,1024,1440,1920,2048] as $required) {
        TestHarness::assertTrue(in_array($required, $widths, true));
    }
    TestHarness::assertThrows(fn () => $s->evaluate('CF06-FUT-003', ['viewports'=>[300]]), InvalidArgumentException::class);
});
$t->test('Multilingual accessibility requires canonical target locale and all text groups', function () use ($s): void {
    TestHarness::assertThrows(fn () => $s->evaluate('CF06-FUT-017', ['target_locale'=>'../ur','alt_text'=>['a'],'captions'=>['c'],'transcript'=>['t'],'aria_labels'=>['l']]), InvalidArgumentException::class);
    TestHarness::assertThrows(fn () => $s->evaluate('CF06-FUT-017', ['target_locale'=>'ur-PK','alt_text'=>[],'captions'=>['c'],'transcript'=>['t'],'aria_labels'=>['l']]), InvalidArgumentException::class);
    $out = $s->evaluate('CF06-FUT-017', ['target_locale'=>'UR_pk','alt_text'=>['a'],'captions'=>['c'],'transcript'=>['t'],'aria_labels'=>['l']]);
    TestHarness::assertSame('ur-PK', $out['result']['target_locale']);
    TestHarness::assertSame(true, $out['result']['screen_reader_acceptance_required']);
});
$t->test('Register profile requires canonical locale and governed honorific policy', function () use ($s): void {
    TestHarness::assertThrows(fn () => $s->evaluate('CF06-FUT-019', ['target_locale'=>'ur-PK','register'=>'academic','honorific_policy'=>'invent']), InvalidArgumentException::class);
});
$t->test('Numeral transformation is display-only and preserves dose URL and placeholder digits', function () use ($s): void {
    $source = 'Page 123; dose 30C; https://example.test/123; {item2}';
    $out = $s->evaluate('CF06-FUT-021', ['locale'=>'ur-PK','text'=>$source,'system'=>'persian']);
    TestHarness::assertSame($source, $out['result']['canonical_text']);
    TestHarness::assertTrue(str_contains($out['result']['display_text'], '۱۲۳'));
    TestHarness::assertTrue(str_contains($out['result']['display_text'], '30C'));
    TestHarness::assertTrue(str_contains($out['result']['display_text'], 'https://example.test/123'));
    TestHarness::assertTrue(str_contains($out['result']['display_text'], '{item2}'));
    TestHarness::assertSame(false, $out['result']['canonical_numeric_value_mutated']);
});
$t->test('Line-break evidence uses canonical locale direction beyond Urdu and Arabic', function () use ($s): void {
    $out = $s->evaluate('CF06-FUT-023', ['text'=>'متن فارسی','locale'=>'fa-IR']);
    TestHarness::assertSame('rtl', $out['result']['direction']);
    TestHarness::assertSame('Arab', $out['result']['script']);
});
$t->test('Input method rejects legacy bidi overrides and unpaired isolates', function () use ($s): void {
    TestHarness::assertThrows(fn () => $s->evaluate('CF06-FUT-024', ['locale'=>'ur-PK','text'=>"safe\u{202E}evil"]), InvalidArgumentException::class);
    TestHarness::assertThrows(fn () => $s->evaluate('CF06-FUT-024', ['locale'=>'ur-PK','text'=>"لنک \u{2066}abc"]), InvalidArgumentException::class);
    $out = $s->evaluate('CF06-FUT-024', ['locale'=>'ur-PK','text'=>"لنک \u{2066}abc\u{2069}"]);
    TestHarness::assertSame('validated', $out['result']['bidi_safety']);
});

$t->finish();
