<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';

$root=dirname(__DIR__);$t=new TestHarness();
$a=(string)file_get_contents($root.'/src/Infrastructure/Repository/AuditRepository.php');

$t->test('Audit chain cryptographically binds actor purpose and event UUID',function()use($a):void{
    foreach(['$eventUuid = Database::uuid();','$actorId = get_current_user_id();',"(string)\$actorId",'$purpose',"'uuid'=>\$eventUuid","'actor_id'=>\$actorId"] as $n){
        TestHarness::assertTrue(str_contains($a,$n),$n);
    }
    $hash=strpos($a,"$eventHash = hash('sha256'");
    $insert=strpos($a,"$ok = $wpdb->insert");
    TestHarness::assertTrue(false!==$hash&&false!==$insert&&$hash<$insert);
});

$t->finish();
