<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/TestHarness.php';

use Sabri\Localization\Domain\Bundle\DeterministicBundle;
use Sabri\Localization\Domain\Locale\FallbackChainValidator;
use Sabri\Localization\Domain\Locale\LocaleValidator;
use Sabri\Localization\Domain\Translation\BidiValidator;
use Sabri\Localization\Domain\Translation\MarkupValidator;
use Sabri\Localization\Domain\Translation\NumberUnitGuard;
use Sabri\Localization\Domain\Translation\PlaceholderValidator;
use Sabri\Localization\Domain\Translation\Redactor;
use Sabri\Localization\Domain\Translation\RiskPolicy;
use Sabri\Localization\Domain\Workflow\StateMachine;

$t = new TestHarness();
$t->test('BCP47 canonicalization', fn () => TestHarness::assertSame('ur-Arab-PK', LocaleValidator::canonicalize('UR_arab_pk')));
$t->test('Malformed locale rejected', fn () => TestHarness::assertSame(null, LocaleValidator::canonicalize('../ur')));
$t->test('Urdu is RTL and English LTR', function (): void {
    TestHarness::assertSame('rtl', LocaleValidator::direction('ur-PK'));
    TestHarness::assertSame('ltr', LocaleValidator::direction('en-US'));
});
$t->test('Fallback chain accepts eligible terminal', function (): void {
    $records = array('en-US' => array('status' => 'enabled', 'fallback_tag' => ''));
    FallbackChainValidator::validate('ur-PK', 'en-US', fn (string $tag): ?array => $records[$tag] ?? null);
});
$t->test('Fallback cycle rejected', function (): void {
    $records = array(
        'ur-PK' => array('status' => 'enabled', 'fallback_tag' => 'en-US'),
        'en-US' => array('status' => 'enabled', 'fallback_tag' => 'ur-PK'),
    );
    TestHarness::assertThrows(fn () => FallbackChainValidator::validate('ur-PK', 'en-US', fn (string $tag): ?array => $records[$tag] ?? null), InvalidArgumentException::class);
});
$t->test('Unit workflow enforces review order', function (): void {
    TestHarness::assertTrue(StateMachine::can('unit', 'linguistic_review', 'domain_review'));
    TestHarness::assertTrue(! StateMachine::can('unit', 'translating', 'approved'));
});
$t->test('Bundle workflow enforces staged release', function (): void {
    TestHarness::assertTrue(StateMachine::can('bundle', 'staged', 'active'));
    TestHarness::assertTrue(! StateMachine::can('bundle', 'built', 'active'));
});
$t->test('Vendor workflow includes validated state', function (): void {
    TestHarness::assertTrue(StateMachine::can('vendor_job', 'received', 'validated'));
    TestHarness::assertTrue(! StateMachine::can('vendor_job', 'received', 'human_reviewed'));
});
$t->test('Typed placeholder schema matches source and target', function (): void {
    $schema = PlaceholderValidator::normalizeSchema(array('user_name' => 'string', 'amount' => 'currency'));
    PlaceholderValidator::assertTarget('Hello {user_name}, pay {amount}', 'سلام {user_name}، {amount} ادا کریں', $schema);
});
$t->test('Placeholder corruption rejected', fn () => TestHarness::assertThrows(fn () => PlaceholderValidator::assertTarget('Hello {name}', 'سلام {person}', array('name' => 'string')), InvalidArgumentException::class));
$t->test('Markup structure preserved', fn () => MarkupValidator::assertEquivalent('<strong>Hello</strong><br>', '<strong>سلام</strong><br>'));
$t->test('Unsafe markup rejected', fn () => TestHarness::assertThrows(fn () => MarkupValidator::assertEquivalent('<script>x</script>', '<script>y</script>'), InvalidArgumentException::class));
$t->test('Bidi override rejected', fn () => TestHarness::assertThrows(fn () => BidiValidator::assertSafe("safe\u{202E}evil"), InvalidArgumentException::class));
$t->test('Protected dose and potency remain exact', fn () => NumberUnitGuard::assertImmutable('Take 200C and 5 ml', '200C اور 5 ml لیں'));
$t->test('Changed dose rejected', fn () => TestHarness::assertThrows(fn () => NumberUnitGuard::assertImmutable('Take 30C', '200C لیں'), InvalidArgumentException::class));
$t->test('Redactor removes direct identifiers', function (): void {
    $r = Redactor::redact('Email user@example.com or call +92 300 1234567.');
    TestHarness::assertTrue($r['redacted']);
    TestHarness::assertTrue(! str_contains($r['text'], 'user@example.com'));
});
$t->test('C4/C5 content never enters external MT', function (): void {
    TestHarness::assertTrue(! RiskPolicy::machineTranslationAllowed('low', 'C4', 'platform', true));
    TestHarness::assertTrue(! RiskPolicy::machineTranslationAllowed('private', 'C1', 'platform', true));
});
$t->test('High-risk MT requires explicit approval and review', function (): void {
    TestHarness::assertTrue(! RiskPolicy::machineTranslationAllowed('high', 'C2', 'medical', false));
    TestHarness::assertTrue(RiskPolicy::machineTranslationAllowed('high', 'C2', 'medical', true));
    TestHarness::assertTrue(RiskPolicy::requiresDomainReview('normal', 'shariah'));
});
$t->test('Bundle build is deterministic regardless of input order', function (): void {
    $a = DeterministicBundle::build('ur-PK', array('b' => array('text' => 'ب'), 'a' => array('text' => 'ا')), array('z' => 2, 'a' => 1));
    $b = DeterministicBundle::build('ur-PK', array('a' => array('text' => 'ا'), 'b' => array('text' => 'ب')), array('a' => 1, 'z' => 2));
    TestHarness::assertSame($a['sha256'], $b['sha256']);
});
$t->test('Bundle signing and verification', function (): void {
    $signature = DeterministicBundle::sign(str_repeat('a', 64));
    TestHarness::assertTrue(is_string($signature) && DeterministicBundle::verify(str_repeat('a', 64), $signature));
});
$t->finish();
