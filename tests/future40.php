<?php

declare(strict_types=1);

require __DIR__ . '/TestHarness.php';
require dirname(__DIR__) . '/src/Contract/FutureCapabilities.php';
require dirname(__DIR__) . '/src/Application/FutureCapabilitiesService.php';

use Sabri\Localization\Application\FutureCapabilitiesService;
use Sabri\Localization\Contract\FutureCapabilities;

$t = new TestHarness();
$s = new FutureCapabilitiesService();

$t->test('Future40 registry contains exact 40 capabilities', function (): void {
    TestHarness::assertSame(40, count(FutureCapabilities::all()));
    TestHarness::assertSame('CF06-FUT-001', FutureCapabilities::ids()[0]);
    TestHarness::assertSame('CF06-FUT-040', FutureCapabilities::ids()[39]);
});

$t->test('Future40 activation contract exposes every governing gate', function (): void {
    $expected = [
        'founder_change_control',
        'privacy_security_domain_review',
        'companion_contract_parity',
        'staging_acceptance',
        'rollback_restore_rehearsal',
        'live_deployment_verification',
    ];
    TestHarness::assertSame($expected, FutureCapabilities::activationGates());
    foreach (FutureCapabilities::all() as $capability) {
        TestHarness::assertSame('disabled', $capability['default_state']);
        TestHarness::assertSame('all-governing-gates-required', $capability['activation']);
        TestHarness::assertSame($expected, $capability['activation_gates']);
    }
});

$cases = [
    'CF06-FUT-001' => ['text'=>'Hello {name}','rtl_probe'=>true],
    'CF06-FUT-002' => ['resource_key'=>'home.title','route'=>'/ur/home','screenshot_ref'=>'shot-1'],
    'CF06-FUT-003' => ['viewports'=>[320,768]],
    'CF06-FUT-004' => ['text'=>'Click here for ABC'],
    'CF06-FUT-005' => ['source'=>'Pay 5 USD at https://example.test','target'=>'5 USD ادا کریں https://example.test'],
    'CF06-FUT-006' => ['old_source'=>'Take 30C daily','new_source'=>'Take 200C daily'],
    'CF06-FUT-007' => ['texts'=>['localization terminology localization','terminology localization terminology'],'min_frequency'=>2],
    'CF06-FUT-008' => ['concept_id'=>'remedy.arnica','variants'=>['en-US'=>['Arnica'],'ur-PK'=>['آرنیکا']]],
    'CF06-FUT-009' => ['source_citations'=>[['ref'=>'Q1','page'=>1]],'target_citations'=>[['ref'=>'Q1','page'=>1]]],
    'CF06-FUT-010' => ['text'=>'Use 30C and 5 ml.'],
    'CF06-FUT-011' => ['segments'=>[['start'=>0,'end'=>2,'text'=>'Hello'],['start'=>2,'end'=>4,'text'=>'World']]],
    'CF06-FUT-012' => ['cues'=>[['start'=>0,'end'=>2,'text'=>'Short subtitle']]],
    'CF06-FUT-013' => ['transcript_human_approved'=>true,'voice_profile'=>'ur-neutral'],
    'CF06-FUT-014' => ['entries'=>[['term'=>'Arnica','pronunciation'=>'AR-ni-ka','locale'=>'en-US']]],
    'CF06-FUT-015' => ['structure'=>[['type'=>'heading'],['type'=>'paragraph'],['type'=>'footnote']]],
    'CF06-FUT-016' => ['confidence'=>0.95,'threshold'=>0.93],
    'CF06-FUT-017' => ['alt_text'=>['a'],'captions'=>['c'],'transcript'=>['t'],'aria_labels'=>['l']],
    'CF06-FUT-018' => ['locale'=>'ar-SA','base_locale'=>'ar'],
    'CF06-FUT-019' => ['register'=>'academic','honorific_policy'=>'preserve-approved'],
    'CF06-FUT-020' => ['canonical_iso_date'=>'2026-09-16','display_mode'=>'dual'],
    'CF06-FUT-021' => ['text'=>'123','system'=>'persian'],
    'CF06-FUT-022' => ['text'=>'A','supported_codepoints'=>['U+0041']],
    'CF06-FUT-023' => ['text'=>'ایک لمبی سطر، دوسری سطر','locale'=>'ur-PK'],
    'CF06-FUT-024' => ['text'=>'اردو input'],
    'CF06-FUT-025' => ['links'=>[['hreflang'=>'ur-PK','url'=>'https://example.test/ur','canonical'=>'https://example.test/ur']]],
    'CF06-FUT-026' => ['coverage_percent'=>99,'critical_missing'=>0,'threshold_percent'=>95],
    'CF06-FUT-027' => ['scope'=>'resource:medical.warning','kill'=>true,'reason'=>'critical mistranslation'],
    'CF06-FUT-028' => ['approvals'=>[['role'=>'linguistic'],['role'=>'domain']],'expires_in_minutes'=>60],
    'CF06-FUT-029' => ['old'=>['a'=>'1','b'=>'2'],'new'=>['a'=>'1','b'=>'3','c'=>'4']],
    'CF06-FUT-030' => ['resources'=>[['key'=>'home.title','text'=>'Home','data_class'=>'C1','public'=>true],['key'=>'secret','text'=>'x','data_class'=>'C4','public'=>false]]],
    'CF06-FUT-031' => ['bundle'=>['a'=>['text'=>'A'],'b'=>['text'=>'B']]],
    'CF06-FUT-032' => ['endpoint'=>'http://localhost:8080/v1/translate'],
    'CF06-FUT-033' => ['locale'=>'ur-PK','data_class'=>'C1','region'=>'PK','providers'=>[['id'=>'p1','healthy'=>true,'quality'=>90,'data_classes'=>['C1'],'locales'=>['ur-PK'],'regions'=>['PK']]]],
    'CF06-FUT-034' => ['samples'=>[['accuracy'=>90,'terminology'=>90,'privacy'=>100,'latency'=>80,'cost'=>70]]],
    'CF06-FUT-035' => ['target_region'=>'PK','allowed_regions'=>['PK'],'denied_regions'=>[]],
    'CF06-FUT-036' => ['source'=>'Take 30C','target'=>'30C لیں'],
    'CF06-FUT-037' => ['backlog_units'=>100,'daily_new_units'=>10,'daily_review_capacity'=>15,'forecast_days'=>30],
    'CF06-FUT-038' => ['decisions'=>[['reviewer_a'=>'approve','reviewer_b'=>'approve'],['reviewer_a'=>'approve','reviewer_b'=>'reject']]],
    'CF06-FUT-039' => ['suggestion'=>'یہ ترجمہ زیادہ واضح ہے۔'],
    'CF06-FUT-040' => ['locales'=>['ur','ar','en-US'],'queues'=>['translation'=>10,'review'=>5],'providers'=>['p1'],'critical_issues'=>[]],
];

foreach ($cases as $id => $input) {
    $t->test($id . ' handler is coded and fail-closed by default', function () use ($s, $id, $input): void {
        $out = $s->evaluate($id, $input);
        TestHarness::assertSame($id, $out['capability']);
        TestHarness::assertSame('disabled', $out['default_state']);
        TestHarness::assertTrue($out['requires_founder_activation']);
        TestHarness::assertSame(FutureCapabilities::activationGates(), $out['activation_gates']);
        TestHarness::assertSame(false, $out['activation_ready']);
        TestHarness::assertTrue(is_array($out['result']));
    });
}

$t->test('Unknown future capability fails closed', fn () => TestHarness::assertThrows(
    fn () => $s->evaluate('CF06-FUT-999', []),
    InvalidArgumentException::class
));

$t->finish();
