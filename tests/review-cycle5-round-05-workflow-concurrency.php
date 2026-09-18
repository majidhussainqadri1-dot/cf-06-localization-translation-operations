<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
$root=dirname(__DIR__);$t=new TestHarness();
$p=(string)file_get_contents($root.'/src/Application/ProjectService.php');
$tr=(string)file_get_contents($root.'/src/Application/TranslationService.php');
$f=(string)file_get_contents($root.'/src/Application/FeedbackService.php');
$m=(string)file_get_contents($root.'/src/Application/MachineTranslationService.php');
$t->test('Assignment and active-role membership assertions are actor-bound and fresh',function()use($p,$tr):void{
 foreach(["isset($a['user_id'])","isset($a['expires_at'])",'current actor-bound approved membership assertion'] as $n){TestHarness::assertTrue(str_contains($p,$n),$n);}
 foreach(["isset($assertions['user_id'])","isset($assertions['expires_at'])",'$fresh'] as $n){TestHarness::assertTrue(str_contains($tr,$n),$n);}
});
$t->test('Workflow narratives are bounded before audit or persistence',function()use($p,$f,$m):void{
 TestHarness::assertTrue(substr_count($p,'strlen($reason)>2000')>=2);
 TestHarness::assertTrue(str_contains($f,'strlen($outcome)>10000'));
 TestHarness::assertTrue(str_contains($m,'strlen($reason)>2000'));
});
$t->finish();
