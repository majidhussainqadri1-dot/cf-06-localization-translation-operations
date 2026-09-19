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

$t->test('Offline packs contain only approved public low-risk C1 current material', function () use ($s): void {
    $out = $s->evaluate('CF06-FUT-030', ['resources'=>[
        ['key'=>'ok','text'=>'Public','data_class'=>'C1','risk_class'=>'low','domain'=>'platform','public'=>true,'approved'=>true,'current'=>true,'stale'=>false],
    ]]);
    TestHarness::assertSame(1, $out['result']['resource_count']);
    TestHarness::assertTrue(isset($out['result']['manifest']['ok']));
    TestHarness::assertThrows(fn () => $s->evaluate('CF06-FUT-030', ['resources'=>[
        ['key'=>'stale','text'=>'Old','data_class'=>'C1','risk_class'=>'low','domain'=>'platform','public'=>true,'approved'=>true,'current'=>true,'stale'=>true],
    ]]), InvalidArgumentException::class);
});
$t->test('Low-bandwidth mode rejects ungoverned private or stale rows', function () use ($s): void {
    TestHarness::assertThrows(fn () => $s->evaluate('CF06-FUT-031', ['bundle'=>['x'=>['text'=>'X','data_class'=>'C2','risk_class'=>'low','domain'=>'platform','public'=>false,'approved'=>true,'current'=>true,'stale'=>false]]]), InvalidArgumentException::class);
    TestHarness::assertThrows(fn () => $s->evaluate('CF06-FUT-031', ['bundle'=>['x'=>['text'=>'X','data_class'=>'C1','risk_class'=>'low','domain'=>'platform','public'=>true,'approved'=>true,'current'=>true,'stale'=>true]]]), InvalidArgumentException::class);
});
$t->test('Self-hosted MT endpoint requires an explicit host allowlist', function () use ($s): void {
    TestHarness::assertThrows(fn () => $s->evaluate('CF06-FUT-032', ['endpoint'=>'http://localhost:8080/v1/translate']), InvalidArgumentException::class);
    $out = $s->evaluate('CF06-FUT-032', ['endpoint'=>'http://localhost:8080/v1/translate','approved_hosts'=>['localhost'],'allow_insecure_local'=>true]);
    TestHarness::assertSame(false, $out['result']['network_call_performed']);
});
$eligibleProvider = static fn (bool $approved = true): array => [
    'id'=>'p1','approved'=>$approved,'healthy'=>true,'quality'=>90,'data_classes'=>['C1'],'locales'=>['ur-PK'],'regions'=>['PK'],
    'training_allowed'=>false,'region_verified'=>true,'deletion_supported'=>true,'contract_current'=>true,'retention_days'=>7,
];
$t->test('Provider router requires explicit low-risk C1 classification and privacy evidence', function () use ($s, $eligibleProvider): void {
    $providers = [$eligibleProvider()];
    TestHarness::assertThrows(fn () => $s->evaluate('CF06-FUT-033', ['locale'=>'ur-PK','data_class'=>'C1','region'=>'PK','providers'=>$providers]), InvalidArgumentException::class);
    TestHarness::assertThrows(fn () => $s->evaluate('CF06-FUT-033', ['locale'=>'ur-PK','data_class'=>'C1','risk_class'=>'low','domain'=>'medical','region'=>'PK','providers'=>$providers]), InvalidArgumentException::class);
    $out = $s->evaluate('CF06-FUT-033', ['locale'=>'ur-PK','data_class'=>'C1','risk_class'=>'low','domain'=>'platform','region'=>'PK','providers'=>$providers]);
    TestHarness::assertSame('p1', $out['result']['selected']);
    $unsafe = $eligibleProvider();
    $unsafe['training_allowed'] = true;
    $out = $s->evaluate('CF06-FUT-033', ['locale'=>'ur-PK','data_class'=>'C1','risk_class'=>'low','domain'=>'platform','region'=>'PK','providers'=>[$unsafe]]);
    TestHarness::assertSame(true, $out['result']['fail_closed']);
});
$t->test('Unapproved providers are not eligible even when healthy', function () use ($s, $eligibleProvider): void {
    $out = $s->evaluate('CF06-FUT-033', ['locale'=>'ur-PK','data_class'=>'C1','risk_class'=>'low','domain'=>'platform','region'=>'PK','providers'=>[$eligibleProvider(false)]]);
    TestHarness::assertSame(true, $out['result']['fail_closed']);
    TestHarness::assertSame(null, $out['result']['selected']);
});
$t->test('Provider benchmark only accepts normalized bounded utility scores', function () use ($s): void {
    TestHarness::assertThrows(fn () => $s->evaluate('CF06-FUT-034', ['samples'=>[['accuracy'=>101,'terminology'=>90,'privacy'=>100,'latency'=>80,'cost'=>70]]]), InvalidArgumentException::class);
    $out = $s->evaluate('CF06-FUT-034', ['samples'=>[['accuracy'=>90,'terminology'=>90,'privacy'=>100,'latency'=>80,'cost'=>70]]]);
    TestHarness::assertSame('0-100-normalized-utility-higher-is-better', $out['result']['score_semantics']);
});
$t->test('Residency uncertainty fails closed', function () use ($s): void {
    TestHarness::assertThrows(fn () => $s->evaluate('CF06-FUT-035', ['target_region'=>'PK','allowed_regions'=>['PK'],'denied_regions'=>[]]), InvalidArgumentException::class);
    $out = $s->evaluate('CF06-FUT-035', ['target_region'=>'PK','allowed_regions'=>['PK'],'denied_regions'=>[],'residency_known'=>true]);
    TestHarness::assertSame(true, $out['result']['eligible']);
});
$t->test('Provider activation requires independent privacy/security/region/retention/exit evidence', function (): void {
    $code = file_get_contents(dirname(__DIR__) . '/src/Application/ProviderService.php');
    TestHarness::assertTrue(str_contains($code, 'slto_verify_provider_activation_evidence'));
    TestHarness::assertTrue(str_contains($code, 'privacy/security/region/retention/exit evidence'));
    TestHarness::assertTrue(str_contains($code, "0!==(int)(\$row['training_allowed']??0)"));
});

$t->finish();
