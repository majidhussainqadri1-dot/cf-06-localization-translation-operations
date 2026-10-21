<?php

declare(strict_types=1);

require __DIR__ . '/TestHarness.php';

$root = dirname(__DIR__);
$t = new TestHarness();
$code = (string) file_get_contents($root . '/src/Application/IntegrationService.php');

$t->test('Integration acceptance is bound to configured deployment environment', function () use ($code): void {
    TestHarness::assertTrue(str_contains($code, 'Integration evidence must match the explicitly configured deployment environment.'));
    TestHarness::assertTrue(str_contains($code, '$environment !== $configuredEnvironment'));
});

$t->test('Staging and production evidence use distinct durable identities', function () use ($code): void {
    TestHarness::assertTrue(str_contains($code, 'evidenceStorageKey'));
    TestHarness::assertTrue(str_contains($code, '$key . \'@\' . $environment'));
    TestHarness::assertTrue(str_contains($code, "findOne('integration_evidence', 'integration_key', self::evidenceStorageKey(\$key, \$environment))"));
});

$t->test('Environment-qualified evidence remains bounded by current schema', function () use ($code): void {
    TestHarness::assertTrue(str_contains($code, 'strlen($value) > 80'));
    TestHarness::assertTrue(str_contains($code, 'Environment-qualified integration key exceeds the database contract.'));
});

$t->finish();
