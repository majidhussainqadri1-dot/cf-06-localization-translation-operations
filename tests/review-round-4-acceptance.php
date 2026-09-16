<?php

declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
$root=dirname(__DIR__);$t=new TestHarness();
$t->test('Staging and rollback guides exist',function()use($root):void{foreach(['docs/STAGING.md','docs/ROLLBACK.md','docs/MIGRATION.md','docs/OPERATIONS-RUNBOOK.md'] as $f){TestHarness::assertTrue(is_file($root.'/'.$f),$f);}});
$t->test('CI covers PHP 8.1 and 8.3',function()use($root):void{$s=file_get_contents($root.'/.github/workflows/ci.yml');TestHarness::assertTrue(str_contains($s,"'8.1'"));TestHarness::assertTrue(str_contains($s,"'8.3'"));});
$t->test('CI exercises the active signing key-map path',function()use($root):void{$s=file_get_contents($root.'/.github/workflows/ci.yml');TestHarness::assertTrue(str_contains($s,'SLTO_BUNDLE_SIGNING_KEYS'));TestHarness::assertTrue(str_contains($s,'SLTO_ACTIVE_BUNDLE_SIGNING_KEY_ID=ci-sign'));TestHarness::assertTrue(!str_contains($s,'SLTO_BUNDLE_SIGNING_KEY_ID=ci-sign'));});
$t->test('Package builder produces manifest checksum, SBOM and exact-source binding',function()use($root):void{$s=file_get_contents($root.'/tools/build-release.py');foreach(['MANIFEST.json','SHA256SUMS','SBOM.cdx.json','SOURCE_COMMIT','source_commit','sabri:source-commit'] as $n){TestHarness::assertTrue(str_contains($s,$n),$n);}TestHarness::assertTrue(str_contains($s,'must bind the candidate to an exact lowercase 40-character Git SHA'));TestHarness::assertTrue(!str_contains($s,'local-unbound'));});
$t->test('Package builder verifies archive bytes against manifest hashes',function()use($root):void{$s=file_get_contents($root.'/tools/build-release.py');TestHarness::assertTrue(str_contains($s,'Source/package byte parity failed'));TestHarness::assertTrue(str_contains($s,'sha256_bytes(payload)'));TestHarness::assertTrue(str_contains($s,'Embedded manifest parity failed'));TestHarness::assertTrue(str_contains($s,'Embedded SBOM parity failed'));});
$t->test('Truthful lifecycle language retained',function()use($root):void{$s=file_get_contents($root.'/README.md');foreach(['Staging-Accepted','Live-Deployed','Operational'] as $n){TestHarness::assertTrue(str_contains($s,$n),$n);}});
$t->test('Installable package includes the GPL license',function()use($root):void{$code=file_get_contents($root.'/tools/build-release.py');TestHarness::assertTrue(str_contains($code,'"LICENSE"'));});
$t->finish();
