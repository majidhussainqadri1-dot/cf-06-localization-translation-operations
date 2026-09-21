<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
require dirname(__DIR__) . '/src/Domain/Locale/LocaleValidator.php';
require dirname(__DIR__) . '/src/Domain/Translation/RiskPolicy.php';
require dirname(__DIR__) . '/src/Domain/Future/FutureCapabilityGuard.php';

use Sabri\Localization\Domain\Future\FutureCapabilityGuard;

$t=new TestHarness();

$t->test('Offline locale packs reject nested or empty text evidence',function():void{
 $row=['key'=>'home.title','text'=>['nested'],'data_class'=>'C1','risk_class'=>'low','domain'=>'platform','public'=>true,'approved'=>true,'current'=>true,'stale'=>false];
 TestHarness::assertThrows(fn()=>FutureCapabilityGuard::normalize('CF06-FUT-030',['resources'=>[$row]]),InvalidArgumentException::class);
});

$t->test('Low-bandwidth bundles require bounded scalar text and canonical resource keys',function():void{
 $bad=['bad key'=>['text'=>'ok','data_class'=>'C1','risk_class'=>'low','domain'=>'platform','public'=>true,'approved'=>true]];
 TestHarness::assertThrows(fn()=>FutureCapabilityGuard::normalize('CF06-FUT-031',['bundle'=>$bad]),InvalidArgumentException::class);
 $nested=['home.title'=>['text'=>['nested'],'data_class'=>'C1','risk_class'=>'low','domain'=>'platform','public'=>true,'approved'=>true]];
 TestHarness::assertThrows(fn()=>FutureCapabilityGuard::normalize('CF06-FUT-031',['bundle'=>$nested]),InvalidArgumentException::class);
});

$t->finish();
