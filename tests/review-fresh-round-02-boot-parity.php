<?php

declare(strict_types=1);

require __DIR__ . '/TestHarness.php';

$root = dirname(__DIR__);
$t = new TestHarness();
$code = (string) file_get_contents($root . '/src/Plugin.php');

$t->test('Plugin boot remains retryable until complete registration succeeds', function () use ($code): void {
    $bootFlag = strrpos($code, '$this->booted = true;');
    $privacyEraser = strpos($code, "add_filter('wp_privacy_personal_data_erasers'");
    TestHarness::assertTrue(false !== $bootFlag && false !== $privacyEraser && $bootFlag > $privacyEraser);
});

$t->test('Migration and contract parity are checked before service construction', function () use ($code): void {
    $parity = strpos($code, 'schema or contract upgrade is incomplete');
    $crypto = strpos($code, '$crypto = new Crypto();');
    TestHarness::assertTrue(false !== $parity && false !== $crypto && $parity < $crypto);
});

$t->finish();
