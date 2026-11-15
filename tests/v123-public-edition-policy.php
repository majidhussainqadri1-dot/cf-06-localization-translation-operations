<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/TestHarness.php';
require_once dirname(__DIR__) . '/src/Domain/Locale/PublicEditionPolicy.php';

use Sabri\Localization\Domain\Locale\PublicEditionPolicy;

$t = new TestHarness();
$hash = hash('sha256', 'synthetic-source-version-one');
$fixture = static fn (): array => [
    'policy_id' => 'CF06-V123-SYNTHETIC',
    'revision' => '1.0.0',
    'effective_date' => '2026-11-15',
    'founder_approval_ref' => 'synthetic-test-only-not-approved',
    // Test-only choices. Remaining five languages are NOT approved by this fixture.
    'target_locales' => ['ur', 'en-US', 'ar', 'zh-Hans', 'bn', 'fa', 'hi', 'tr', 'id'],
    'reserve_locales' => ['de', 'fr'],
    'aliases' => ['ur-PK' => 'ur'],
];
$plan = static fn (array $p, string $s, bool $verified = true): array
    => PublicEditionPolicy::plan($p, $s, 'source-v1', $hash, $verified);

$t->test('Unapproved plan is blocked', function () use ($fixture, $plan): void {
    $r = $plan($fixture(), 'ur', false);
    TestHarness::assertSame('blocked', $r['status']);
    TestHarness::assertSame('founder_policy_approval_unverified', $r['reason']);
    TestHarness::assertSame(false, $r['publication_authorized']);
});
$t->test('Original Urdu is removed and first reserve selected', function () use ($fixture, $plan): void {
    $r = $plan($fixture(), 'ur');
    TestHarness::assertSame(9, $r['selected_target_count']);
    TestHarness::assertSame(['de'], $r['reserve_locales_used']);
    TestHarness::assertTrue(!in_array('ur', $r['selected_target_locales'], true));
});
$t->test('Outside source uses nine unchanged targets', function () use ($fixture, $plan): void {
    $r = $plan($fixture(), 'sw');
    TestHarness::assertSame(9, $r['selected_target_count']);
    TestHarness::assertSame([], $r['reserve_locales_used']);
});
$t->test('Approved regional alias cannot duplicate source', function () use ($fixture, $plan): void {
    $r = $plan($fixture(), 'UR_pk');
    TestHarness::assertSame('ur', $r['source_locale']);
    TestHarness::assertSame(['de'], $r['reserve_locales_used']);
});
$t->test('Reserve shortfall must block instead of invent', function () use ($fixture, $plan): void {
    $p = $fixture(); $p['reserve_locales'] = [];
    $r = $plan($p, 'ur');
    TestHarness::assertSame('blocked', $r['status']);
    TestHarness::assertSame('approved_reserve_shortfall', $r['reason']);
    TestHarness::assertSame(8, $r['selected_target_count']);
});
$t->test('Source present only in reserves is never selected', function () use ($fixture, $plan): void {
    $p = $fixture(); $p['reserve_locales'] = ['sw', 'fr'];
    TestHarness::assertSame([], $plan($p, 'sw')['reserve_locales_used']);
});
$t->test('Duplicate target after alias resolution must fail', function () use ($fixture, $plan): void {
    $p = $fixture(); $p['target_locales'][4] = 'ur-PK';
    TestHarness::assertThrows(fn () => $plan($p, 'ar'), InvalidArgumentException::class);
});
$t->test('Primary target cannot also be a reserve', function () use ($fixture, $plan): void {
    $p = $fixture(); $p['reserve_locales'][] = 'ur-PK';
    TestHarness::assertThrows(fn () => $plan($p, 'ar'), InvalidArgumentException::class);
});
$t->test('Chinese script and governing language are mandatory', function () use ($fixture, $plan): void {
    $p = $fixture(); $p['target_locales'][3] = 'zh';
    TestHarness::assertThrows(fn () => $plan($p, 'ur'), InvalidArgumentException::class);
    $p = $fixture(); $p['target_locales'][3] = 'ja';
    TestHarness::assertThrows(fn () => $plan($p, 'ur'), InvalidArgumentException::class);
});
$t->test('Alias cycles are rejected', function () use ($fixture, $plan): void {
    $p = $fixture(); $p['aliases'] = ['en-GB' => 'en-US', 'en-US' => 'en-GB'];
    TestHarness::assertThrows(fn () => $plan($p, 'ur'), InvalidArgumentException::class);
});
$t->test('Approval reference and effective date cannot be fabricated by omission', function () use ($fixture, $plan): void {
    $p = $fixture(); unset($p['founder_approval_ref']);
    TestHarness::assertThrows(fn () => $plan($p, 'ur'), InvalidArgumentException::class);
    $p = $fixture(); $p['effective_date'] = '2026-02-30';
    TestHarness::assertThrows(fn () => $plan($p, 'ur'), InvalidArgumentException::class);
});
$t->test('Approval reference rejects personal email or unbounded free text', function () use ($fixture, $plan): void {
    $p=$fixture(); $p['founder_approval_ref']='person@example.test';
    TestHarness::assertThrows(fn () => $plan($p,'ur'),InvalidArgumentException::class);
    $p=$fixture(); $p['founder_approval_ref']='Founder approved this via my private account';
    TestHarness::assertThrows(fn () => $plan($p,'ur'),InvalidArgumentException::class);
});
$t->test('Source content hash is mandatory', function () use ($fixture): void {
    TestHarness::assertThrows(
        fn () => PublicEditionPolicy::plan($fixture(), 'ur', 'source-v1', 'bad', true),
        InvalidArgumentException::class
    );
});
$t->test('Current public and independently attested records only', function () use ($fixture, $plan, $hash): void {
    $r = $plan($fixture(), 'ur');
    $valid = static fn (string $tag): array => [
        'locale'=>$tag,'status'=>'published','public'=>true,
        'public_url'=>'https://example.test/'.$tag,'canonical_url'=>'https://example.test/'.$tag,'source_version'=>'source-v1','source_hash'=>$hash
    ];
    $records = [$valid('ur'), $valid('en-US'), $valid('en-US')];
    $bad = $valid('ar'); $bad['source_hash'] = hash('sha256', 'old'); $records[] = $bad;
    $bad = $valid('zh-Hans'); $bad['public_url'] = 'http://example.test'; $records[] = $bad;
    $bad = $valid('bn'); $bad['public'] = false; $records[] = $bad;
    $bad = $valid('fa'); $bad['source_version'] = 'old'; $records[] = $bad;
    $report = PublicEditionPolicy::countVerifiedPublished($r, $records, static fn (): bool => true);
    TestHarness::assertSame(2, $report['verified_published_count']);
    TestHarness::assertSame(8, $report['missing_public_versions']);
    TestHarness::assertSame(5, $report['unverified_or_rejected_record_count']);
});
$t->test('Neither missing row attestation nor missing policy approval can count', function () use ($fixture, $plan, $hash): void {
    $record = ['locale'=>'ur','status'=>'published','public'=>true,'public_url'=>'https://example.test/ur','canonical_url'=>'https://example.test/ur',
        'source_version'=>'source-v1','source_hash'=>$hash];
    $count = PublicEditionPolicy::countVerifiedPublished($plan($fixture(), 'ur'), [$record], static fn (): bool => false);
    TestHarness::assertSame(0, $count['verified_published_count']);
    $count = PublicEditionPolicy::countVerifiedPublished($plan($fixture(), 'ur', false), [$record], static fn (): bool => true);
    TestHarness::assertSame(0, $count['verified_published_count']);
});
$t->test('Ten independently attested current editions counted exactly', function () use ($fixture, $plan, $hash): void {
    $p = $plan($fixture(), 'ur');
    $records = [];
    foreach (array_merge(['ur'], $p['selected_target_locales']) as $locale) {
        $records[] = ['locale'=>$locale,'status'=>'published','public'=>true,
            'public_url'=>'https://example.test/'.rawurlencode($locale),
            'canonical_url'=>'https://example.test/'.rawurlencode($locale),
            'source_version'=>'source-v1','source_hash'=>$hash];
    }
    $count = PublicEditionPolicy::countVerifiedPublished($p, $records, static fn (): bool => true);
    TestHarness::assertSame(10, $count['verified_published_count']);
    TestHarness::assertSame(true, $count['source_and_nine_targets_complete']);
});
$t->test('Evidence batch must remain bounded', function () use ($fixture, $plan): void {
    TestHarness::assertThrows(
        fn () => PublicEditionPolicy::countVerifiedPublished($plan($fixture(), 'ur'), array_fill(0, 101, []), static fn (): bool => false),
        InvalidArgumentException::class
    );
});
$t->test('V12.3 integration remains owner-evidence-gated and read only', function (): void {
    $root = dirname(__DIR__);
    $locale = (string) file_get_contents($root.'/src/Application/LocaleService.php');
    $compliance = (string) file_get_contents($root.'/src/Contract/PlanCompliance.php');
    TestHarness::assertTrue(str_contains($locale, 'slto_verify_founder_edition_policy'));
    TestHarness::assertTrue(str_contains($locale, 'slto_verify_current_edition_publication'));
    TestHarness::assertTrue(str_contains($compliance, 'source_plus_nine_distinct_target_versions'));
});
$t->test('One public URL cannot be counted as two different locale editions', function () use ($fixture, $plan, $hash): void {
    $record = static fn (string $tag): array => [
        'locale'=>$tag,'status'=>'published','public'=>true,
        'public_url'=>'https://example.test/shared','canonical_url'=>'https://example.test/shared',
        'source_version'=>'source-v1','source_hash'=>$hash,
    ];
    $result = PublicEditionPolicy::countVerifiedPublished(
        $plan($fixture(), 'ur'), [$record('ur'), $record('en-US')], static fn (): bool => true
    );
    TestHarness::assertSame(1, $result['verified_published_count']);
    TestHarness::assertSame(1, $result['unverified_or_rejected_record_count']);
});
$t->test('Missing, insecure, or fragment canonical URLs never count as public', function () use ($fixture, $plan, $hash): void {
    $row = ['locale'=>'en-US','status'=>'published','public'=>true,
        'public_url'=>'https://example.test/en-US','source_version'=>'source-v1','source_hash'=>$hash];
    foreach ([null,'http://example.test/en-US','https://example.test/en-US#top'] as $canonical) {
        $candidate=$row;
        if(null!==$canonical){$candidate['canonical_url']=$canonical;}
        $result=PublicEditionPolicy::countVerifiedPublished(
            $plan($fixture(), 'ur'), [$candidate], static fn (): bool => true
        );
        TestHarness::assertSame(0,$result['verified_published_count']);
    }
});
$t->finish();
