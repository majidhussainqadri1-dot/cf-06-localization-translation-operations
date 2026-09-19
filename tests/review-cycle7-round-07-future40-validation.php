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
require dirname(__DIR__) . '/src/Domain/Future/HotfixApprovalGuard.php';
require dirname(__DIR__) . '/src/Application/FutureCapabilitiesService.php';
require dirname(__DIR__) . '/src/Application/FutureCapabilitiesFacade.php';

use Sabri\Localization\Application\FutureCapabilitiesFacade;
$t=new TestHarness();$s=new FutureCapabilitiesFacade();

$t->test('Transcript and subtitle timing reject scalar coercion and invalid order',function()use($s):void{
 TestHarness::assertThrows(fn()=>$s->evaluate('CF06-FUT-011',['segments'=>[['start'=>'1junk','end'=>2,'text'=>'x']]]),InvalidArgumentException::class);
 TestHarness::assertThrows(fn()=>$s->evaluate('CF06-FUT-011',['segments'=>[['start'=>2,'end'=>3,'text'=>'x'],['start'=>1,'end'=>4,'text'=>'y']]]),InvalidArgumentException::class);
 TestHarness::assertThrows(fn()=>$s->evaluate('CF06-FUT-012',['cues'=>[['start'=>0,'end'=>1,'text'=>['nested']]]]),InvalidArgumentException::class);
});
$t->test('Pronunciation lexicon requires bounded canonical locale evidence',function()use($s):void{
 TestHarness::assertThrows(fn()=>$s->evaluate('CF06-FUT-014',['entries'=>[['term'=>'Arnica','pronunciation'=>'AR-ni-ka','locale'=>'../bad']]]),InvalidArgumentException::class);
 $out=$s->evaluate('CF06-FUT-014',['entries'=>[['term'=>'Arnica','pronunciation'=>'AR-ni-ka','locale'=>'en-us']]]);
 TestHarness::assertSame('en-US',$out['result']['entries'][0]['locale']);
});
$t->test('Provider router drops duplicate provider identities',function()use($s):void{
 $p=['id'=>'p1','approved'=>true,'healthy'=>true,'training_allowed'=>false,'region_verified'=>true,'deletion_supported'=>true,'contract_current'=>true,'retention_days'=>0,'quality'=>90,'data_classes'=>['C1'],'locales'=>['en-US'],'regions'=>['US']];
 $out=$s->evaluate('CF06-FUT-033',['locale'=>'en-US','data_class'=>'C1','risk_class'=>'low','domain'=>'platform','region'=>'US','providers'=>[$p,$p]]);
 TestHarness::assertSame(1,$out['result']['eligible_count']);
});
$t->finish();
