<?php

declare(strict_types=1);

require __DIR__ . '/TestHarness.php';

$root=dirname(__DIR__);$t=new TestHarness();
$translation=(string)file_get_contents($root.'/src/Application/TranslationService.php');
$terms=(string)file_get_contents($root.'/src/Application/TerminologyService.php');
$events=(string)file_get_contents($root.'/src/Contract/Events.php');

$t->test('Review rechecks exact source freshness before approval',function()use($translation,$events):void{
    foreach(['Translation source changed or retired before review','source_version','source_hash'] as $needle){TestHarness::assertTrue(str_contains($translation,$needle),$needle);}
});

$t->test('Linguistic approval awaiting domain review is not mislabeled as rejected',function()use($translation,$events):void{
    TestHarness::assertTrue(str_contains($translation,"if('approved'===\$to)"));
    TestHarness::assertTrue(str_contains($translation,"elseif('approve'!==\$decision)"));
    TestHarness::assertTrue(str_contains($events,'TranslationRejected'));
});

$t->test('Human review and terminology transition narrative fields are bounded',function()use($translation,$terms):void{
    TestHarness::assertTrue(str_contains($translation,'Review reason exceeds the bounded limit.'));
    TestHarness::assertTrue(str_contains($terms,'Terminology transition reason exceeds the bounded limit.'));
    TestHarness::assertTrue(str_contains($terms,'Style guide transition reason exceeds the bounded limit.'));
});

$t->test('Translation memory similarity work is computationally bounded',function()use($terms):void{
    foreach(['strlen($source)>4000','strlen($context)>16000',"),200)",'$ratio<0.35','$candidateLength>8000'] as $needle){TestHarness::assertTrue(str_contains($terms,$needle),$needle);}
});

$t->finish();
