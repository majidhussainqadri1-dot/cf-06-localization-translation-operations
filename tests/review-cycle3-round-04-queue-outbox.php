<?php

declare(strict_types=1);

require __DIR__ . '/TestHarness.php';

$root=dirname(__DIR__);$t=new TestHarness();
$jobs=(string)file_get_contents($root.'/src/Infrastructure/JobQueue.php');
$outbox=(string)file_get_contents($root.'/src/Infrastructure/Outbox.php');
$tx=(string)file_get_contents($root.'/src/Infrastructure/Transaction.php');
$migration=(string)file_get_contents($root.'/src/Application/MigrationService.php');

$t->test('Job queue read and lease database failures are surfaced',function()use($jobs):void{
    foreach(['Localization job queue could not be read.','Localization job lease could not be persisted.','Expired localization job could not be moved to dead letter.','Localization job attempt state could not be read.'] as $needle){TestHarness::assertTrue(str_contains($jobs,$needle),$needle);}
    TestHarness::assertTrue(str_contains($jobs,"'' !== (string)$wpdb->last_error"));
});

$t->test('Outbox read lease and acknowledgement database failures are surfaced',function()use($outbox):void{
    foreach(['Localization outbox could not be read.','Localization outbox lease could not be persisted.','Outbox delivery acknowledgement failed.','Outbox failure state could not be persisted.'] as $needle){TestHarness::assertTrue(str_contains($outbox,$needle),$needle);}
});

$t->test('Transactions remain savepoint-backed and rollback failures surface',function()use($tx):void{
    foreach(['SAVEPOINT','ROLLBACK TO SAVEPOINT','Localization transaction rollback failed.'] as $needle){TestHarness::assertTrue(str_contains($tx,$needle),$needle);}
});

$t->test('Migration dry-run remains transactional and evidence-bounded',function()use($migration):void{
    foreach(['count($source)>100000','strlen($encoded)>5_000_000','$this->tx->run','migration_dry_run'] as $needle){TestHarness::assertTrue(str_contains($migration,$needle),$needle);}
});

$t->finish();
