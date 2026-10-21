<?php
declare(strict_types=1);

require __DIR__ . '/TestHarness.php';

$root = dirname(__DIR__);
$t = new TestHarness();
$entry = (string)file_get_contents($root . '/sabri-localization-translation-operations.php');
$plugin = (string)file_get_contents($root . '/src/Plugin.php');
$provider = (string)file_get_contents($root . '/src/Provider/HttpJsonProvider.php');
$mt = (string)file_get_contents($root . '/src/Application/MachineTranslationService.php');
$policy = (string)file_get_contents($root . '/src/Domain/Translation/RiskPolicy.php');
$compliance = (string)file_get_contents($root . '/src/Contract/PlanCompliance.php');
$contracts = (string)file_get_contents($root . '/docs/CONTRACTS.md');

$t->test('Schema/version mismatch still denies CF-06 before route registration', function () use ($plugin): void {
    $check = strpos($plugin, 'Activator::assertRuntimeSchemaParity();');
    $routes = strpos($plugin, '(new Routes($this->services))->registerHooks();');
    TestHarness::assertTrue(false !== $check && false !== $routes && $check < $routes);
    TestHarness::assertTrue(str_contains($plugin, 'exact match for this runtime'));
});

$t->test('Failed CF-06 boot cannot fatally terminate unrelated WordPress routes', function () use ($entry): void {
    TestHarness::assertTrue(str_contains($entry, 'try {'));
    TestHarness::assertTrue(str_contains($entry, 'catch (\\Throwable $failure)'));
    TestHarness::assertTrue(str_contains($entry, "update_option('slto_runtime_enabled', false, false)"));
    TestHarness::assertTrue(str_contains($entry, "add_action('admin_notices'"));
    TestHarness::assertTrue(str_contains($entry, "current_user_can('activate_plugins')"));
    TestHarness::assertTrue(str_contains($entry, 'Sabri\\Localization\\Plugin::instance()->boot()'));
});

$t->test('Outbound vendor transport has an exact allowlisted metadata envelope', function () use ($provider): void {
    TestHarness::assertTrue(1 === preg_match('/\\$providerJob\\s*=\\s*\\[(.*?)\\];/s', $provider, $match));
    preg_match_all("/'([a-z_]+)'\\s*=>/", $match[1], $fields);
    TestHarness::assertSame(['uuid','provider_key','region_code','purpose','outbound_hash'], $fields[1]);
    TestHarness::assertTrue(str_contains($provider, "'job'=>\$providerJob"));
    TestHarness::assertTrue(! str_contains($provider, "'job'=>\$job"));
});

$t->test('MT source gates remain C1 low-risk and exact outbound-hash bound', function () use ($mt, $policy): void {
    TestHarness::assertTrue(str_contains($mt, 'RiskPolicy::machineTranslationAllowed'));
    TestHarness::assertTrue(str_contains($mt, "hash_equals((string)\$job['outbound_hash'],\$requestHash)"));
    TestHarness::assertTrue(str_contains($policy, "'C1' !== strtoupper(\$dataClass)"));
    TestHarness::assertTrue(str_contains($policy, "'low' !== strtolower(\$riskClass)"));
});

$t->test('Central and companion ownership laws remain source-referenced', function () use ($compliance, $contracts): void {
    foreach (['CEN-OWN-001','CEN-SHELL-001','CEN-PRIV-001','CEN-REV-001','companion_contract_parity'] as $needle) {
        TestHarness::assertTrue(str_contains($compliance, $needle), $needle);
    }
    foreach (['File 00','File 20','File 25','File 26'] as $owner) {
        TestHarness::assertTrue(str_contains($contracts, $owner) || str_contains($compliance, $owner), $owner);
    }
});

$t->finish();
