<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
$root=dirname(__DIR__);$t=new TestHarness();
$p=(string)file_get_contents($root.'/src/Provider/HttpJsonProvider.php');
$t->test('HTTP provider credentials are bounded and reject control characters',function()use($p):void{
 foreach(['strlen($value)>4096',"preg_match('/[\\x00-\\x1F\\x7F]/',$value)",'unsafe for an HTTP authorization header'] as $n){TestHarness::assertTrue(str_contains($p,$n),$n);}
});
$t->finish();
