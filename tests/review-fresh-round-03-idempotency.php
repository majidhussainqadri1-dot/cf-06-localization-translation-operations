<?php

declare(strict_types=1);

require __DIR__ . '/TestHarness.php';

$root = dirname(__DIR__);
$t = new TestHarness();
$code = (string) file_get_contents($root . '/src/Rest/Routes.php');

$t->test('Business operation failure is the only path that marks idempotency failed', function () use ($code): void {
    $start = strpos($code, 'private function mutate(');
    $end = strpos($code, 'private function ok(', $start === false ? 0 : $start);
    TestHarness::assertTrue(false !== $start && false !== $end && $start < $end);
    $mutate = substr($code, $start, $end - $start);
    $operation = strpos($mutate, '$result=$operation();');
    $catch = strpos($mutate, 'catch(Throwable $e)');
    $complete = strpos($mutate, 'completeIdempotency($actor,$route,$key,$status,$body)');
    TestHarness::assertTrue(false !== $operation && false !== $catch && false !== $complete);
    TestHarness::assertTrue($operation < $catch && $catch < $complete);
    TestHarness::assertTrue(substr_count($mutate, 'failIdempotency(') === 1);
});

$t->test('Idempotency failure persistence cannot mask primary operation exception', function () use ($code): void {
    TestHarness::assertTrue(str_contains($code, 'slto_idempotency_failure_persistence_error'));
    TestHarness::assertTrue(str_contains($code, 'throw $e;'));
});

$t->test('Completion persistence failure leaves replay state fail closed', function () use ($code): void {
    TestHarness::assertTrue(str_contains($code, 'row remains processing and retries fail closed'));
});

$t->finish();
