<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';

$root=dirname(__DIR__);$t=new TestHarness();
$p=(string)file_get_contents($root.'/src/Application/ProjectService.php');

$t->test('Assignment security dates require exact UTC timestamps',function()use($p):void{
    foreach([
        "DateTimeImmutable::createFromFormat",
        "DateTimeZone('UTC')",
        "Project and assignment dates must be exact UTC timestamps.",
        "!Y-m-d\\\\TH:i:s\\\\Z",
        "!Y-m-d H:i:s",
    ] as $n){TestHarness::assertTrue(str_contains($p,$n),$n);}
    TestHarness::assertTrue(!str_contains($p,"\$timestamp=strtotime((string)\$value)"));
});

$t->finish();
