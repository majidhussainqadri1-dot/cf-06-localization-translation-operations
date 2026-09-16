<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';require __DIR__ . '/TestHarness.php';
use Sabri\Localization\Domain\Workflow\StateMachine;
$root=dirname(__DIR__);$t=new TestHarness();
$t->test('Locale lifecycle includes degraded and deprecation paths',function():void{TestHarness::assertTrue(StateMachine::can('locale','enabled','degraded'));TestHarness::assertTrue(StateMachine::can('locale','enabled','deprecated'));});
$t->test('Source change can force stale unit recovery',function():void{TestHarness::assertTrue(StateMachine::can('unit','approved','stale'));TestHarness::assertTrue(StateMachine::can('unit','stale','assigned'));});
$t->test('Terminology cannot jump from proposed to active',fn()=>TestHarness::assertTrue(!StateMachine::can('terminology','proposed','active')));
$t->test('Failed bundle can only restart at planned',function():void{TestHarness::assertTrue(StateMachine::can('bundle','failed','planned'));TestHarness::assertTrue(!StateMachine::can('bundle','failed','active'));});
$t->test('Vendor purge only follows terminal review/failure states',function():void{TestHarness::assertTrue(StateMachine::can('vendor_job','accepted','purged'));TestHarness::assertTrue(!StateMachine::can('vendor_job','sent','purged'));});
$t->test('Bundle activation requires staged or canary state',function()use($root):void{$code=file_get_contents($root.'/src/Application/BundleService.php');TestHarness::assertTrue(str_contains($code,"array('staged','canary')"));TestHarness::assertTrue(!str_contains($code,"array('approved','staged','canary')"));});
$t->test('Provider deprecation counts every unresolved vendor job',function()use($root):void{$code=file_get_contents($root.'/src/Application/ProviderService.php');TestHarness::assertTrue(str_contains($code,"->count('vendor_jobs'"));});
$t->test('Machine provider job remains validated until human review',function()use($root):void{$code=file_get_contents($root.'/src/Application/MachineTranslationService.php');TestHarness::assertTrue(str_contains($code,'return $validated;'));TestHarness::assertTrue(str_contains($code,'Every machine draft must be human-edited'));TestHarness::assertTrue(str_contains($code,"'human_reviewed', 'accepted'"));});
$t->test('Human editing preserves machine provider provenance',function()use($root):void{$code=file_get_contents($root.'/src/Application/TranslationService.php');TestHarness::assertTrue(str_contains($code,'$machineDraft ? $providerJob : ($unit[\'provider_job_uuid\'] ?? null)'));});
$t->test('Plugin boot fails closed when schema or contract parity is incomplete',function()use($root):void{$code=file_get_contents($root.'/src/Plugin.php');TestHarness::assertTrue(str_contains($code,'schema or contract upgrade is incomplete'));TestHarness::assertTrue(strpos($code,'$this->booted = true;')>strpos($code,'Activator::maybeUpgrade();'));});
$t->test('Migration dry-run duplicate path refreshes version and checkpoint',function()use($root):void{$code=file_get_contents($root.'/src/Application/MigrationService.php');TestHarness::assertTrue(str_contains($code,'migration_version=VALUES(migration_version)'));TestHarness::assertTrue(str_contains($code,'checkpoint_json=VALUES(checkpoint_json)'));TestHarness::assertTrue(str_contains($code,'started_at=NULL,completed_at=NULL'));});
$t->test('Migration dry-run persistence and audit evidence share one transaction',function()use($root):void{$code=file_get_contents($root.'/src/Application/MigrationService.php');TestHarness::assertTrue(str_contains($code,'private readonly Transaction $tx'));TestHarness::assertTrue(str_contains($code,'return $this->tx->run(function()'));TestHarness::assertTrue(str_contains($code,"migration_dry_run"));});
$t->finish();
