<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/TestHarness.php';
require_once dirname(__DIR__) . '/src/Domain/Locale/PublicEditionGraphAuditor.php';

use Sabri\Localization\Domain\Locale\PublicEditionGraphAuditor as Auditor;

$t = new TestHarness();
$hash = hash('sha256','synthetic canonical public post');
$tags = ['ur','en-US','ar','zh-Hans','bn','fa','hi','tr','id','de'];
$urls = array_combine($tags,array_map(static fn (string $tag): string => 'https://example.test/lesson/'.$tag,$tags));
$version='v3';
$record=static fn (string $tag): array => [
    'locale'=>$tag, 'url'=>$urls[$tag], 'canonical_url'=>$urls[$tag],
    'source_version'=>$version, 'source_hash'=>$hash,
    'publication_status'=>'published', 'public'=>true, 'hreflang'=>$urls,
];
$sample=static fn (): array => array_map($record,$tags);
$audit=static fn (array $rows, callable $original, callable $translation, callable $search): array =>
    Auditor::audit($rows[0],array_slice($rows,1),'https://example.test/', $original,$translation,$search);
$yes=static fn ():bool=>true;
$no=static fn ():bool=>false;

$t->test('Ten independently attested synthetic reciprocal editions pass graph review only',function()use($sample,$audit,$yes):void{
    $r=$audit($sample(),$yes,$yes,$yes);
    TestHarness::assertSame(10,$r['independently_owner_verified_count']);
    TestHarness::assertSame(0,$r['missing_verified_versions']);
    TestHarness::assertSame(true,$r['graph_review_ready']);
    TestHarness::assertSame(false,$r['publication_authorized']);
    TestHarness::assertSame(false,$r['indexing_authorized']);
});
$t->test('File 26 evidence is independent and fail closed',function()use($sample,$audit,$yes,$no):void{
    $r=$audit($sample(),$yes,$yes,$no);
    TestHarness::assertSame(false,$r['graph_review_ready']);
    TestHarness::assertSame(false,$r['file26_independently_verified']);
});
$t->test('A translated edition without native owner proof is not counted',function()use($sample,$audit,$yes,$no):void{
    $r=$audit($sample(),$yes,$no,$yes);
    TestHarness::assertSame(1,$r['independently_owner_verified_count']);
    TestHarness::assertSame(false,$r['graph_review_ready']);
});
$t->test('Original source without native ownership proof is not counted',function()use($sample,$audit,$yes,$no):void{
    $r=$audit($sample(),$no,$yes,$yes);
    TestHarness::assertSame(9,$r['independently_owner_verified_count']);
    TestHarness::assertSame(false,$r['graph_review_ready']);
});
$t->test('Missing language never turns into an imaginary tenth version',function()use($sample,$audit,$yes):void{
    $rows=$sample();array_pop($rows);
    $r=$audit($rows,$yes,$yes,$yes);
    TestHarness::assertSame(9,$r['independently_owner_verified_count']);
    TestHarness::assertSame(1,$r['missing_verified_versions']);
    TestHarness::assertSame(false,$r['graph_review_ready']);
});
$t->test('Incorrect reciprocal hreflang is rejected',function()use($sample,$audit,$yes):void{
    $rows=$sample();$rows[1]['hreflang']['ur']='https://example.test/other';
    $r=$audit($rows,$yes,$yes,$yes);
    TestHarness::assertSame(false,$r['graph_review_ready']);
    TestHarness::assertTrue(in_array('missing_or_nonreciprocal_hreflang',array_column($r['issues'],'code'),true));
});
$t->test('Stale translation source version cannot be current',function()use($sample,$audit,$yes):void{
    $rows=$sample();$rows[1]['source_version']='v2';
    $r=$audit($rows,$yes,$yes,$yes);
    TestHarness::assertSame(9,$r['independently_owner_verified_count']);
});
$t->test('Private translation must not appear as a public graph node',function()use($sample,$audit,$yes):void{
    $rows=$sample();$rows[1]['public']=false;
    $r=$audit($rows,$yes,$yes,$yes);
    TestHarness::assertSame(9,$r['independently_owner_verified_count']);
});
$t->test('Retracted translation must not appear as a public graph node',function()use($sample,$audit,$yes):void{
    $rows=$sample();$rows[1]['publication_status']='retracted';
    $r=$audit($rows,$yes,$yes,$yes);
    TestHarness::assertSame(false,$r['graph_review_ready']);
});
$t->test('Duplicate locale is rejected even with a distinct URL',function()use($sample,$audit,$yes):void{
    $rows=$sample();$rows[2]['locale']='en-US';
    $r=$audit($rows,$yes,$yes,$yes);
    TestHarness::assertSame(9,$r['independently_owner_verified_count']);
});
$t->test('Duplicate public URL cannot represent distinct editions',function()use($sample,$audit,$yes):void{
    $rows=$sample();$rows[2]['url']=$rows[1]['url'];$rows[2]['canonical_url']=$rows[1]['canonical_url'];
    $r=$audit($rows,$yes,$yes,$yes);
    TestHarness::assertSame(9,$r['independently_owner_verified_count']);
});
$t->test('HTTP/foreign-origin URLs cannot enter the graph',function()use($sample,$audit,$yes):void{
    $rows=$sample();$rows[1]['url']='http://example.test/en-US';
    $rows[2]['canonical_url']='https://foreign.test/ar';
    $r=$audit($rows,$yes,$yes,$yes);
    TestHarness::assertSame(8,$r['independently_owner_verified_count']);
});
$t->test('Canonical URL override requires separate File 26 regional authority',function()use($sample,$audit,$yes):void{
    $rows=$sample();$rows[1]['canonical_url']='https://example.test/lesson/ur';
    $r=$audit($rows,$yes,$yes,$yes);
    TestHarness::assertTrue(in_array('nonself_canonical_requires_separate_approval',array_column($r['issues'],'code'),true));
});
$t->test('Malformed or duplicate hreflang tags are rejected',function()use($sample,$audit,$yes):void{
    $rows=$sample();$rows[1]['hreflang']['ur_PK']='https://example.test/lesson/ur';
    $r=$audit($rows,$yes,$yes,$yes);
    TestHarness::assertTrue(in_array('missing_or_nonreciprocal_hreflang',array_column($r['issues'],'code'),true));
});
$t->test('Invalid/private rows never invoke owner attestor',function()use($sample,$audit,$yes):void{
    $rows=$sample();$rows[1]['public']=false;
    $calls=0;
    $owner=static function() use (&$calls):bool {++$calls;return true;};
    $audit($rows,$yes,$owner,$yes);
    TestHarness::assertSame(8,$calls);
});
$t->test('Graph input is bounded and invalid origin fails closed',function()use($sample,$yes):void{
    $rows=$sample();
    TestHarness::assertThrows(fn()=>Auditor::audit($rows[0],array_fill(0,25,$rows[1]),'https://example.test/',$yes,$yes,$yes),InvalidArgumentException::class);
    TestHarness::assertThrows(fn()=>Auditor::audit($rows[0],array_slice($rows,1),'http://example.test/',$yes,$yes,$yes),InvalidArgumentException::class);
});
$t->test('Auditor has a protected read-only REST bridge and no publication command',function():void{
    $root=dirname(__DIR__);
    $p=(string)file_get_contents($root.'/src/Plugin.php');
    $r=(string)file_get_contents($root.'/src/Rest/Routes.php');
    $s=(string)file_get_contents($root.'/src/Application/PublicationGraphService.php');
    TestHarness::assertTrue(str_contains($p,'PublicationGraphService'));
    TestHarness::assertTrue(str_contains($r,'/content-links/graph/audit'));
    TestHarness::assertTrue(str_contains($s,'slto_verify_file26_search_graph'));
    TestHarness::assertTrue(str_contains($s,'slto_verify_graph_original_owner'));
});
$t->test('Approved same-language regional canonical still requires File 26 attestation',function()use($version,$hash,$yes,$no):void{
    $tags=['ur','en-US','ar','zh-Hans','bn','fa','hi','tr','id','en-GB'];
    $urls=[];
    foreach($tags as $tag){$urls[$tag]='https://example.test/lesson/'.$tag;}
    $rows=[];
    foreach($tags as $tag){
        $rows[]=[
            'locale'=>$tag,'url'=>$urls[$tag],
            'canonical_url'=>$tag==='en-GB'?$urls['en-US']:$urls[$tag],
            'source_version'=>$version,'source_hash'=>$hash,
            'publication_status'=>'published','public'=>true,'hreflang'=>$urls,
        ];
    }
    $pass=Auditor::audit($rows[0],array_slice($rows,1),'https://example.test/',$yes,$yes,$yes);
    TestHarness::assertSame(10,$pass['independently_owner_verified_count']);
    TestHarness::assertSame(true,$pass['graph_review_ready']);
    $blocked=Auditor::audit($rows[0],array_slice($rows,1),'https://example.test/',$yes,$yes,$no);
    TestHarness::assertSame(false,$blocked['graph_review_ready']);
});
$t->finish();
