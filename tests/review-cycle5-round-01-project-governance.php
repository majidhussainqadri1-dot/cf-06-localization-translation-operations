<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
$root=dirname(__DIR__);$t=new TestHarness();$p=(string)file_get_contents($root.'/src/Application/ProjectService.php');
$t->test('Project metadata variables are defined and bounded before transactional use',function()use($p):void{
 foreach(['$description=sanitize_textarea_field','$releaseTarget=sanitize_text_field','$projectProvider=sanitize_key','strlen($description)>65535','strlen($releaseTarget)>191','strlen($projectProvider)>80'] as $n){TestHarness::assertTrue(str_contains($p,$n),$n);}
});
$t->test('Assignment qualification is independently verified before persistence',function()use($p):void{
 $verify=strpos($p,"apply_filters('slto_verify_assignment_qualification'");
 $tx=strpos($p,'return $this->tx->run(function()use($unit,$role,$assignee');
 TestHarness::assertTrue(false!==$verify&&false!==$tx&&$verify<$tx);
 TestHarness::assertTrue(str_contains($p,'Assignment qualification evidence could not be independently verified.'));
});
$t->finish();
