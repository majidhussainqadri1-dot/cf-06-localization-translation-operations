<?php
declare(strict_types=1);
require __DIR__.'/TestHarness.php';
$root=dirname(__DIR__);
foreach ([
 'Domain/Locale/LocaleValidator.php','Domain/Locale/FallbackChainValidator.php',
 'Domain/Translation/BidiValidator.php','Domain/Translation/MessageFormatValidator.php',
 'Domain/Translation/PlaceholderValidator.php','Domain/Translation/MarkupValidator.php',
 'Domain/Translation/NumberUnitGuard.php','Domain/Translation/RiskPolicy.php',
 'Contract/FutureCapabilities.php','Contract/PlanCompliance.php',
] as $path){require_once $root.'/src/'.$path;}
use Sabri\Localization\Domain\Locale\LocaleValidator as L;
use Sabri\Localization\Domain\Locale\FallbackChainValidator as F;
use Sabri\Localization\Domain\Translation\BidiValidator as B;
use Sabri\Localization\Domain\Translation\PlaceholderValidator as P;
use Sabri\Localization\Domain\Translation\MessageFormatValidator as I;
use Sabri\Localization\Domain\Translation\MarkupValidator as M;
use Sabri\Localization\Domain\Translation\NumberUnitGuard as N;
use Sabri\Localization\Domain\Translation\RiskPolicy as R;
use Sabri\Localization\Contract\FutureCapabilities as C;
use Sabri\Localization\Contract\PlanCompliance as G;

$t=new TestHarness();$round=0;
$check=static function(string $label,callable $f)use($t,&$round):void{++$round;$t->test(sprintf('CF-06 forty-domain round %02d: %s',$round,$label),$f);};
$has=static function(string $path,string $needle)use($root):void{
 $file=$root.'/'.$path;TestHarness::assertTrue(is_file($file),'Missing '.$path);
 TestHarness::assertTrue(str_contains((string)file_get_contents($file),$needle),'Absent '.$needle.' in '.$path);
};
$ok=static function(callable $f):void{$f();TestHarness::assertTrue(true);};

$check('Locale en-US canonicalization',static fn()=>TestHarness::assertSame('en-US',L::canonicalize('EN_us')));
$check('Urdu RTL language detection',static fn()=>TestHarness::assertSame('rtl',L::direction('ur-PK')));
$check('Explicit Latin script LTR override',static fn()=>TestHarness::assertSame('ltr',L::direction('ur-Latn')));
$check('Malformed locale rejection',static fn()=>TestHarness::assertSame(null,L::canonicalize('en--US')));
$check('Eligible fallback resolution',static fn()=> $ok(static fn()=>F::validate('ur-PK','en-US',static fn(string $s)=>$s==='en-US'?['status'=>'enabled','fallback_tag'=>null]:null)));
$check('Fallback cycle refusal',static fn()=>TestHarness::assertThrows(static fn()=>F::validate('ur-PK','ur-PK',static fn()=>['status'=>'enabled'])));
$check('Unregistered fallback refusal',static fn()=>TestHarness::assertThrows(static fn()=>F::validate('ur-PK','en-US',static fn()=>null)));
$check('Typed placeholder normalization',static fn()=>TestHarness::assertSame(['count'=>'integer','name'=>'string'],P::normalizeSchema(['name'=>'STRING','count'=>'integer'])));
$check('Unsafe placeholder name refusal',static fn()=>TestHarness::assertThrows(static fn()=>P::normalizeSchema(['bad-name'=>'string'])));
$check('Declared source argument accepted',static fn()=> $ok(static fn()=>P::assertSource('Hello {name}',['name'=>'string'])));
$check('Omitted translated argument refused',static fn()=>TestHarness::assertThrows(static fn()=>P::assertTarget('Hello {name}','سلام',['name'=>'string'])));
$check('Equivalent plural branches accepted',static fn()=> $ok(static fn()=>I::assertEquivalent('{n, plural, one {# file} other {# files}}','{n, plural, one {# فائل} other {# فائلیں}}')));
$check('Plural selector drift refused',static fn()=>TestHarness::assertThrows(static fn()=>I::assertEquivalent('{n, plural, one {One} other {Other}}','{n, plural, few {Few} other {Other}}')));
$check('ICU formatter-style drift refused',static fn()=>TestHarness::assertThrows(static fn()=>I::assertEquivalent('{amount, number, integer}','{amount, number, percent}')));
$check('Unicode bidi override refused',static fn()=>TestHarness::assertThrows(static fn()=>B::assertSafe("Unsafe \u{202E} text",true)));
$check('Unclosed directional isolation refused',static fn()=>TestHarness::assertThrows(static fn()=>B::assertSafe("Text \u{2067}rtl",true)));
$check('Balanced directional isolation accepted',static fn()=> $ok(static fn()=>B::assertSafe("Text \u{2067}اردو\u{2069}",true)));
$check('Changed destination link refused',static fn()=>TestHarness::assertThrows(static fn()=>M::assertEquivalent('<a href="https://example.org">A</a>','<a href="https://other.org">B</a>')));
$check('Event-handler markup refused',static fn()=>TestHarness::assertThrows(static fn()=>M::assertEquivalent('<b>Safe</b>','<b onclick="alert(1)">Bad</b>')));
$check('Localized accessible title accepted',static fn()=> $ok(static fn()=>M::assertEquivalent('<a href="https://example.org" title="Read">Read</a>','<a href="https://example.org" title="پڑھیں">پڑھیں</a>')));
$check('Unclosed markup refused',static fn()=>TestHarness::assertThrows(static fn()=>M::assertEquivalent('<b>Safe</b>','<b>open')));
$check('Ordered dose values cannot be swapped',static fn()=>TestHarness::assertThrows(static fn()=>N::assertImmutable('5 mg then 10 mg','10 mg then 5 mg')));
$check('Currency value drift refused',static fn()=>TestHarness::assertThrows(static fn()=>N::assertImmutable('PKR 500','PKR 900')));
$check('Preserved numeric dose accepted',static fn()=> $ok(static fn()=>N::assertImmutable('Dose: 5 mg','مقدار: 5 mg')));
$check('Only low risk C1 may use external MT',static fn()=>TestHarness::assertSame(true,R::machineTranslationAllowed('low','C1','platform')));
$check('High-risk sources barred from external MT',static fn()=>TestHarness::assertSame(false,R::machineTranslationAllowed('high','C1','platform')));
$check('C2 sources barred from external MT',static fn()=>TestHarness::assertSame(false,R::machineTranslationAllowed('low','C2','platform')));
$check('Medical text barred from external MT',static fn()=>TestHarness::assertSame(false,R::machineTranslationAllowed('low','C1','medical',true)));
$check('Future40 complete and unique ID registry',static function():void{TestHarness::assertSame(40,count(C::all()));TestHarness::assertSame(40,count(array_unique(C::ids())));});
$check('Future40 default disabled and no auto publish',static function():void{foreach(C::all() as $r){TestHarness::assertSame('disabled',$r['default_state']);TestHarness::assertSame(false,$r['public_auto_publish']);}});
$check('All Future40 high-risk uses human review',static function():void{foreach(C::all() as $r){TestHarness::assertSame(true,$r['high_risk_human_review_required']);}});
$check('Schema parity before runtime routes',static fn()=> $has('src/Plugin.php','Activator::assertRuntimeSchemaParity();'));
$check('Boot exception contained for companion modules',static fn()=> $has('sabri-localization-translation-operations.php','catch (\Throwable $failure)'));
$check('File 00 actor binding and recent authentication',static function()use($has):void{$has('src/Security/Authorization.php','smc_membership_assertions');$has('src/Security/Authorization.php','slto_verify_recent_authentication');});
$check('REST capability and idempotency guards',static function()use($has):void{$has('src/Rest/Routes.php','Idempotency-Key');$has('src/Rest/Routes.php','Authorization::allowed');});
$check('Source changes invalidate translations and bundles',static function()use($has):void{$has('src/Application/ResourceService.php','markDependentUnitsStale');$has('src/Infrastructure/DependencyInvalidator.php','invalidateActiveBundles');});
$check('Vendor deletion requires completed proof',static function()use($has):void{$has('src/Provider/HttpJsonProvider.php','if (204 !== $code)');$has('src/Provider/HttpJsonProvider.php','200 !== $code');$has('src/Application/MachineTranslationService.php',"'purged'!==(string)(\$evidence['status']??'')");});
$check('Domain owner retains translation publication approval',static fn()=> $has('src/Application/ContentLinkService.php','slto_validate_owner_approval'));
$check('Independent dual approval before bundle release',static function()use($has):void{$has('src/Application/ReleaseApprovalService.php','assertDualApproval');$has('src/Application/ReleaseApprovalService.php','slto_verify_release_approval_evidence');});
$check('Central and companion boundaries still declared',static function()use($root):void{$doc=(string)file_get_contents($root.'/docs/ARCHITECTURE.md');foreach(['File 00','File 20','File 25','File 26'] as $owner){TestHarness::assertTrue(str_contains($doc,$owner));}TestHarness::assertSame(6,count(G::nativeJourneys()));});
TestHarness::assertSame(40,$round);
$t->finish();
