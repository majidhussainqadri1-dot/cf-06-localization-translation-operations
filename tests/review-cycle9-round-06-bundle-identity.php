<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
$root=dirname(__DIR__);$t=new TestHarness();
$b=(string)file_get_contents($root.'/src/Application/BundleService.php');
$t->test('Bundle build rejects duplicate resource and unit identities before signing',function()use($b):void{
 foreach(['$seenUnits=[]','isset($items[$resourceKey])','isset($seenUnits[$unitUuid])','Locale bundle build found duplicated or invalid resource/unit identity.'] as $n){TestHarness::assertTrue(str_contains($b,$n),$n);}
 $dup=strpos($b,'Locale bundle build found duplicated or invalid resource/unit identity.');
 $sign=strpos($b,'DeterministicBundle::sign(');
 TestHarness::assertTrue(false!==$dup&&false!==$sign&&$dup<$sign);
});
$t->finish();
