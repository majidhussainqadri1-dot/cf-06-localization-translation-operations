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
$t->test('Canonical Future40 facade enforces byte and node bounds outside REST',function()use($s):void{
 TestHarness::assertThrows(fn()=>$s->evaluate('CF06-FUT-001',['text'=>str_repeat('x',262200)]),InvalidArgumentException::class);
 $nested=['text'=>'x'];for($i=0;$i<5100;$i++){$nested['n'.$i]=[$i];}
 TestHarness::assertThrows(fn()=>$s->evaluate('CF06-FUT-001',$nested),InvalidArgumentException::class);
});
$t->test('Hotfix timestamps must be real calendar instants and evidence is bounded',function()use($s):void{
 $bad=[['role'=>'linguistic','actor_id'=>'a','approved'=>true,'approved_at'=>'2026-02-30T10:00:00Z'],['role'=>'domain','actor_id'=>'b','approved'=>true,'approved_at'=>'2026-02-30T10:00:00Z']];
 TestHarness::assertThrows(fn()=>$s->evaluate('CF06-FUT-028',['approvals'=>$bad,'expires_in_minutes'=>10,'evidence_ref'=>'x']),InvalidArgumentException::class);
 TestHarness::assertThrows(fn()=>$s->evaluate('CF06-FUT-027',['scope'=>'resource:x','kill'=>true,'reason'=>str_repeat('r',1001),'actor_ref'=>'a','evidence_ref'=>'e']),InvalidArgumentException::class);
});
$t->test('Offline packs reject duplicate resource identities',function()use($s):void{
 $row=['key'=>'dup','text'=>'A','data_class'=>'C1','risk_class'=>'low','domain'=>'platform','public'=>true,'approved'=>true,'current'=>true,'stale'=>false];
 TestHarness::assertThrows(fn()=>$s->evaluate('CF06-FUT-030',['resources'=>[$row,$row]]),InvalidArgumentException::class);
});
$t->test('Translation debt forecasts reject unbounded or malformed numerics',function()use($s):void{
 TestHarness::assertThrows(fn()=>$s->evaluate('CF06-FUT-037',['backlog_units'=>1,'daily_new_units'=>'x','daily_review_capacity'=>2,'forecast_days'=>30]),InvalidArgumentException::class);
 TestHarness::assertThrows(fn()=>$s->evaluate('CF06-FUT-037',['backlog_units'=>1,'daily_new_units'=>1,'daily_review_capacity'=>2,'forecast_days'=>99999]),InvalidArgumentException::class);
});
$t->finish();
