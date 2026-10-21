<?php
declare(strict_types=1);

require __DIR__ . '/TestHarness.php';
require_once dirname(__DIR__) . '/src/Contract/FutureCapabilities.php';

use Sabri\Localization\Contract\FutureCapabilities;

$root = dirname(__DIR__);
$t = new TestHarness();
$catalogue = FutureCapabilities::all();
$handlers = (string)file_get_contents($root . '/src/Application/FutureCapabilitiesService.php');
$trace = (string)file_get_contents($root . '/docs/FUTURE40-TRACEABILITY-EVIDENCE.md');

// Forty independent requirement-ID checks: registry, executable source
// handler, default-deny activation and requirement-specific traceability.
for ($round = 1; $round <= 40; ++$round) {
    $id = sprintf('CF06-FUT-%03d', $round);
    $method = sprintf('f%03d', $round);
    $t->test(sprintf('Future40 source coverage check %02d: %s', $round, $id), static function () use ($catalogue, $handlers, $trace, $id, $method): void {
        TestHarness::assertTrue(isset($catalogue[$id]), 'Missing Future40 registry ID: ' . $id);
        TestHarness::assertSame('disabled', $catalogue[$id]['default_state'] ?? null);
        TestHarness::assertSame(false, $catalogue[$id]['public_auto_publish'] ?? null);
        TestHarness::assertSame(true, $catalogue[$id]['high_risk_human_review_required'] ?? null);
        TestHarness::assertTrue(1 === preg_match('/private function ' . preg_quote($method, '/') . '\\s*\\(/', $handlers), 'Missing executable handler: ' . $method);
        TestHarness::assertTrue(str_contains($trace, $id), 'Missing per-ID traceability: ' . $id);
    });
}

$t->finish();
