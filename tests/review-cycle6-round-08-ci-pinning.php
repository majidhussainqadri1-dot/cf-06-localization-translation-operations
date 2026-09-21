<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
$root=dirname(__DIR__);$t=new TestHarness();
$ci=(string)file_get_contents($root.'/.github/workflows/ci.yml');

$t->test('CI pins Composer and WP-CLI tool versions',function()use($ci):void{
 TestHarness::assertTrue(str_contains($ci,'composer:2.10.3'));
 TestHarness::assertTrue(str_contains($ci,'wp-cli:2.12.0'));
});
$t->test('CI explicitly tests declared minimum and current WordPress lines',function()use($ci):void{
 TestHarness::assertTrue(str_contains($ci,"wordpress: ['6.0.15', '7.1.1']"));
 TestHarness::assertTrue(str_contains($ci,'--version="${{ matrix.wordpress }}"'));
 TestHarness::assertTrue(str_contains($ci,'WP ${{ matrix.wordpress }}'));
});
$t->test('CI diagnostic artifact identity includes WordPress version',function()use($ci):void{
 TestHarness::assertTrue(str_contains($ci,'quality-log-${{ matrix.php }}-wp-${{ matrix.wordpress }}'));
 TestHarness::assertTrue(str_contains($ci,'quality-${{ matrix.php }}-wp-${{ matrix.wordpress }}.log'));
});
$t->finish();
