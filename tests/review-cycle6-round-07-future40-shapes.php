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

$t->test('Future40 string helpers reject structured values instead of Array coercion',function()use($s):void{
 TestHarness::assertThrows(fn()=>$s->evaluate('CF06-FUT-002',['resource_key'=>['bad'],'route'=>'/x']),InvalidArgumentException::class);
});
$t->test('Terminology mining and concept graph reject malformed nested evidence',function()use($s):void{
 TestHarness::assertThrows(fn()=>$s->evaluate('CF06-FUT-007',['texts'=>[['nested']]]),InvalidArgumentException::class);
 TestHarness::assertThrows(fn()=>$s->evaluate('CF06-FUT-008',['concept_id'=>'c','variants'=>['ur-PK'=>[['nested']]]]),InvalidArgumentException::class);
 TestHarness::assertThrows(fn()=>$s->evaluate('CF06-FUT-008',['concept_id'=>'c','variants'=>['../bad'=>['term']]]),InvalidArgumentException::class);
});
$t->test('Subtitle pronunciation document and calibration rows fail closed on malformed shapes',function()use($s):void{
 TestHarness::assertThrows(fn()=>$s->evaluate('CF06-FUT-012',['cues'=>['bad']]),InvalidArgumentException::class);
 TestHarness::assertThrows(fn()=>$s->evaluate('CF06-FUT-014',['entries'=>['bad']]),InvalidArgumentException::class);
 TestHarness::assertThrows(fn()=>$s->evaluate('CF06-FUT-015',['structure'=>['bad']]),InvalidArgumentException::class);
 TestHarness::assertThrows(fn()=>$s->evaluate('CF06-FUT-038',['decisions'=>['bad']]),InvalidArgumentException::class);
});
$t->test('Founder command center rejects malformed or negative queue counts',function()use($s):void{
 TestHarness::assertThrows(fn()=>$s->evaluate('CF06-FUT-040',['queues'=>['x']]),InvalidArgumentException::class);
 TestHarness::assertThrows(fn()=>$s->evaluate('CF06-FUT-040',['queues'=>[-1]]),InvalidArgumentException::class);
});
$t->test('Self-hosted endpoint evidence rejects URL fragments',function()use($s):void{
 TestHarness::assertThrows(fn()=>$s->evaluate('CF06-FUT-032',['endpoint'=>'http://localhost:8080/x#frag','approved_hosts'=>['localhost'],'allow_insecure_local'=>true]),InvalidArgumentException::class);
});
$t->finish();
