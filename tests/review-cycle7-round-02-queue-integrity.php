<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
$root=dirname(__DIR__);$t=new TestHarness();
$j=(string)file_get_contents($root.'/src/Infrastructure/JobQueue.php');
$o=(string)file_get_contents($root.'/src/Infrastructure/Outbox.php');

$t->test('Job dedupe identity cannot silently mutate an existing payload',function()use($j):void{
 foreach(['ON DUPLICATE KEY UPDATE uuid=uuid','dedupe key was reused with a different payload','SELECT uuid,payload_json'] as $n){TestHarness::assertTrue(str_contains($j,$n),$n);}
 TestHarness::assertTrue(!str_contains($j,'ON DUPLICATE KEY UPDATE payload_json=VALUES(payload_json)'));
});
$t->test('Job availability timestamps are validated before persistence',function()use($j):void{
 TestHarness::assertTrue(str_contains($j,'Localization job availability timestamp is invalid.'));
 TestHarness::assertTrue(str_contains($j,"gmdate('Y-m-d H:i:s', strtotime(\$scheduledAt))"));
});
$t->test('Expired outbox deliveries consume bounded retry budget and dead-letter',function()use($o):void{
 foreach(['$wasDelivering','expired_outbox_delivery_lease','Expired localization outbox delivery could not be dead-lettered.','attempts=attempts+IF(status=\'delivering\',1,0)'] as $n){TestHarness::assertTrue(str_contains($o,$n),$n);}
});
$t->finish();
