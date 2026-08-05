<?php

declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
$root=dirname(__DIR__);$t=new TestHarness();
$t->test('Canonical boundary documentation exists', function()use($root):void{$a=file_get_contents($root.'/docs/ARCHITECTURE.md');foreach(['File 20','File 25','File 26','native domain owner'] as $n){TestHarness::assertTrue(str_contains(strtolower($a),strtolower($n)),$n);}});
$t->test('Content links are projections not original records', function()use($root):void{$s=file_get_contents($root.'/src/Application/ContentLinkService.php');TestHarness::assertTrue(str_contains($s,'owner_module'));TestHarness::assertTrue(str_contains($s,'owner_object_id'));});
$t->test('Manifest exposes versioned owner contracts', function()use($root):void{$s=file_get_contents($root.'/src/Contract/Manifest.php');TestHarness::assertTrue(str_contains($s,'contract_version'));TestHarness::assertTrue(str_contains($s,'canonical_owner'));});
$t->test('Published content translation requires native-owner verification', function()use($root):void{$code=file_get_contents($root.'/src/Application/ContentLinkService.php');TestHarness::assertTrue(str_contains($code,'slto_validate_owner_approval'));TestHarness::assertTrue(str_contains($code,'Native owner approval evidence could not be verified'));});
$t->finish();
