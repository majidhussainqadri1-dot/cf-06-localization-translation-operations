<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/TestHarness.php';

use Sabri\Localization\Contract\PlanCompliance;

$root = dirname(__DIR__);
$t = new TestHarness();

$t->test('Future40 per-ID evidence matrix contains every capability', function () use ($root): void {
    $matrix = file_get_contents($root . '/docs/FUTURE40-TRACEABILITY-EVIDENCE.md');
    TestHarness::assertTrue(is_string($matrix));
    foreach (range(1, 40) as $i) {
        $id = sprintf('CF06-FUT-%03d', $i);
        TestHarness::assertTrue(str_contains($matrix, $id), 'Missing per-ID evidence row ' . $id);
    }
    foreach (['Security/privacy/safety enforcement','Automated evidence','Canonical owner boundary','Package / staging / live evidence'] as $column) {
        TestHarness::assertTrue(str_contains($matrix, $column), 'Missing evidence dimension ' . $column);
    }
});

$t->test('Future40 documentation names the guarded facade and guard stack', function () use ($root): void {
    $doc = file_get_contents($root . '/docs/FUTURE40.md');
    foreach (['FutureCapabilitiesFacade','ReleaseLifecycleGuard','FutureCapabilityGuard','ProviderEligibilityGuard','SemanticIntegrityGuard','LocaleAccessibilityGuard','FUTURE40-TRACEABILITY-EVIDENCE.md'] as $needle) {
        TestHarness::assertTrue(str_contains($doc, $needle), 'Missing Future40 guarded-path evidence: ' . $needle);
    }
});

$t->test('Plan compliance records all latest companion ownership boundaries', function (): void {
    $plan = PlanCompliance::get();
    $owners = $plan['owner_boundaries'] ?? [];
    foreach (['file19','file20','file22','file23','file24','file25','file26','cf04','domain_owners','cf06'] as $owner) {
        TestHarness::assertTrue(isset($owners[$owner]) && '' !== trim((string)$owners[$owner]), 'Missing owner boundary ' . $owner);
    }
    TestHarness::assertSame(false, $plan['future40_activation_law']['preview_route_activation_authority'] ?? null);
    TestHarness::assertSame(false, $plan['future40_activation_law']['preview_route_publication_authority'] ?? null);
});

$t->test('Versioned contracts keep Future40 preview non-activating and non-publishing', function () use ($root): void {
    $contracts = file_get_contents($root . '/docs/CONTRACTS.md');
    foreach (['GET /future-capabilities','POST /future-capabilities/{CF06-FUT-NNN}/evaluate','no activation, publication, provider-send','activation_ready'] as $needle) {
        TestHarness::assertTrue(str_contains($contracts, $needle), 'Missing Future40 contract boundary: ' . $needle);
    }
});

$t->finish();
