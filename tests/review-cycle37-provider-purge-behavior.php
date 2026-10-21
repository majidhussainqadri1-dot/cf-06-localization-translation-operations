<?php
declare(strict_types=1);
require __DIR__.'/TestHarness.php';
// No real provider, network connection or production object is invoked.
class Cf06AuditUrlGuardStub {
    public static function assertPublicHttps(string $url,array $hosts):void {
        if (!str_starts_with($url,'https://')||$hosts===[]) {throw new RuntimeException('invalid test fixture');}
    }
}
class_alias(Cf06AuditUrlGuardStub::class,'Sabri\Localization\Domain\Security\UrlGuard');
function wp_json_encode(mixed $v,int $options=0):string|false{return json_encode($v,$options);}
function wp_remote_request(string $url,array $args):array{return $GLOBALS['cf06_http_fixture']??[];}
function wp_remote_retrieve_response_code(array $r):int{return (int)($r['code']??0);}
function wp_remote_retrieve_body(array $r):string{return (string)($r['body']??'');}
function is_wp_error(mixed $response):bool{return false;}
require dirname(__DIR__).'/src/Provider/MachineTranslationProvider.php';
require dirname(__DIR__).'/src/Provider/HttpJsonProvider.php';
putenv('CF06_AUDIT_PURGE_SECRET=fixture-token-only');
$provider=new Sabri\Localization\Provider\HttpJsonProvider([
 'key'=>'test','purge_url'=>'https://example.org/delete',
 'allowed_hosts'=>['example.org'],'credential_env'=>'CF06_AUDIT_PURGE_SECRET',
]);
$t=new TestHarness();
$t->test('Pending HTTP 202 cannot establish erasure',static function()use($provider):void{
 $GLOBALS['cf06_http_fixture']=['code'=>202,'body'=>'{"status":"accepted","reference":"abc"}'];
 TestHarness::assertThrows(static fn()=> $provider->purge('abc'),RuntimeException::class);
});
$t->test('HTTP 200 without deletion attestation cannot establish erasure',static function()use($provider):void{
 $GLOBALS['cf06_http_fixture']=['code'=>200,'body'=>'{}'];
 TestHarness::assertThrows(static fn()=> $provider->purge('abc'),RuntimeException::class);
});
$t->test('HTTP 200 with a different reference cannot establish erasure',static function()use($provider):void{
 $GLOBALS['cf06_http_fixture']=['code'=>200,'body'=>'{"status":"purged","reference":"different"}'];
 TestHarness::assertThrows(static fn()=> $provider->purge('abc'),RuntimeException::class);
});
$t->test('Bound explicit HTTP 200 deletion requires further independent verification',static function()use($provider):void{
 $GLOBALS['cf06_http_fixture']=['code'=>200,'body'=>'{"status":"deleted","reference":"abc"}'];
 $proof=$provider->purge('abc');
 TestHarness::assertSame('purged',$proof['status']);TestHarness::assertSame(200,$proof['provider_http_status']);
});
$t->test('Completed HTTP 204 deletion requires further independent verification',static function()use($provider):void{
 $GLOBALS['cf06_http_fixture']=['code'=>204,'body'=>''];
 $proof=$provider->purge('abc');
 TestHarness::assertSame('purged',$proof['status']);TestHarness::assertSame(204,$proof['provider_http_status']);
});
$t->finish();
