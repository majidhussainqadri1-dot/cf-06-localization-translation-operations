<?php

declare(strict_types=1);

require __DIR__ . '/TestHarness.php';

$t = new TestHarness();
$path = dirname(__DIR__) . '/docs/V12.3-90-REQUIREMENTS-EVIDENCE-REGISTER.json';
$ledger = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
$expected = [];
foreach (['FR'=>34,'CEN'=>10,'FUT'=>40,'NJ'=>6] as $type=>$n) {
    for ($i=1; $i<=$n; ++$i) {
        $expected[] = 'CF06-'.$type.'-'.sprintf($type==='FR'||$type==='FUT'?'%03d':'%02d',$i);
    }
}
sort($expected);
$t->test('Canonical V12.3 register has exactly ninety unique IDs', function () use ($ledger,$expected): void {
    $ids = array_column($ledger['requirements'],'id');
    TestHarness::assertSame(90,count($ids));
    TestHarness::assertSame(90,count(array_unique($ids)));
    sort($ids);
    TestHarness::assertSame($expected,$ids);
});
$t->test('Counts show claims, never operational acceptance', function () use ($ledger): void {
    TestHarness::assertSame(90,$ledger['totals']['source_traceability_claims']);
    TestHarness::assertSame(0,$ledger['totals']['independently_accepted']);
    TestHarness::assertSame(0,$ledger['totals']['staging_accepted']);
    TestHarness::assertSame(0,$ledger['totals']['live_verified']);
    TestHarness::assertTrue(!isset($ledger['totals']['coding_completion_percent']));
});
$t->test('Every requirement retains pointers and all external gates remain false', function () use ($ledger): void {
    foreach ($ledger['requirements'] as $row) {
        TestHarness::assertTrue(is_string($row['documented_implementation'])&&''!==trim($row['documented_implementation']));
        TestHarness::assertTrue(is_string($row['documented_automated_evidence'])&&''!==trim($row['documented_automated_evidence']));
        TestHarness::assertSame('repository_traceability_claim_only',$row['grade']);
        TestHarness::assertSame(false,$row['independent_semantic_accepted']);
        TestHarness::assertSame(false,$row['staging_accepted']);
        TestHarness::assertSame(false,$row['live_verified']);
    }
});
$t->test('External dependency blockers are explicitly recorded', function () use ($ledger): void {
    $gaps = array_column($ledger['priority_unclosed'],'area');
    foreach (['V12.3 target policy','Cross-project jobs','File 20','File 21','File 26','Companion contracts','Operations','Live parity'] as $name) {
        TestHarness::assertTrue(in_array($name,$gaps,true),'Missing unclosed gate '.$name);
    }
});
$t->test('Frozen predecessor is not mistaken for current deployed artifact', function () use ($ledger): void {
    TestHarness::assertSame('7bf7a206a8e5569ddeb369841bc0c6481cec05b9',$ledger['candidate_before_ledger_commit']);
    TestHarness::assertTrue(str_contains($ledger['state_boundary'],'not independent semantic/release acceptance'));
    TestHarness::assertTrue(str_contains($ledger['state_boundary'],'not staging'));
    TestHarness::assertTrue(str_contains($ledger['state_boundary'],'not live'));
});
$t->test('Ten source corrections are recorded but never misrepresented as Live fixes',function()use($ledger):void{
    TestHarness::assertSame(10,$ledger['source_review_summary']['found_source_gaps']);
    TestHarness::assertSame(10,$ledger['source_review_summary']['candidate_code_remediations']);
    TestHarness::assertSame(72,$ledger['source_review_summary']['new_test_checks_total']);
    TestHarness::assertSame(10,count($ledger['source_defect_register']));
    foreach($ledger['source_defect_register'] as $entry){
        TestHarness::assertSame(false,$entry['independent_semantic_accepted']);
        TestHarness::assertSame(false,$entry['staging_accepted']);
        TestHarness::assertSame(false,$entry['live_verified']);
    }
});
$t->finish();
