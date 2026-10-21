<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';

$root=dirname(__DIR__);$t=new TestHarness();
$workflow=(string)file_get_contents($root.'/.github/workflows/ci.yml');

$t->test('WordPress integration isolates plugin verification from bundled theme defects',function()use($workflow):void{
 TestHarness::assertTrue(str_contains($workflow,'wp plugin activate sabri-localization-translation-operations --path="$wpdir" --skip-themes'));
 TestHarness::assertTrue(str_contains($workflow,'wp eval-file "$GITHUB_WORKSPACE/tests/wp-integration.php" --path="$wpdir" --skip-themes'));
});
$t->finish();
