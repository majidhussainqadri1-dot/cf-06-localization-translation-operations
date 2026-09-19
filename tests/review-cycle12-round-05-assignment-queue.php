<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';

$root=dirname(__DIR__);
$t=new TestHarness();
$project=(string)file_get_contents($root.'/src/Application/ProjectService.php');

$t->test('Role-specific assignment queues filter before the bounded repository query',function()use($project):void{
    TestHarness::assertTrue(str_contains($project,"\$filters['assignment_role']=\$role"));
    TestHarness::assertTrue(str_contains($project,"list('assignments',\$filters,\$limit,0,'due_at ASC')"));
    TestHarness::assertTrue(!str_contains($project,"return ''===\$role?\$rows:array_values(array_filter"));
});

$t->test('Assignment queue rejects unknown role selectors',function()use($project):void{
    TestHarness::assertTrue(str_contains($project,"Assignment queue role is invalid."));
    foreach(['translator','linguistic_reviewer','domain_reviewer'] as $role){
        TestHarness::assertTrue(str_contains($project,"'".$role."'"));
    }
});

$t->finish();
