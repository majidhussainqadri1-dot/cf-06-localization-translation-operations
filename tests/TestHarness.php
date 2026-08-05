<?php

declare(strict_types=1);

final class TestHarness
{
    private int $passed = 0;
    private int $failed = 0;

    public function test(string $name, callable $test): void
    {
        try {
            $test();
            ++$this->passed;
            fwrite(STDOUT, "PASS {$name}\n");
        } catch (Throwable $throwable) {
            ++$this->failed;
            fwrite(STDERR, "FAIL {$name}: {$throwable->getMessage()}\n");
        }
    }

    public static function assertTrue(bool $condition, string $message = 'Assertion failed.'): void
    {
        if (! $condition) {
            throw new RuntimeException($message);
        }
    }

    public static function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException($message ?: 'Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
        }
    }

    public static function assertThrows(callable $callable, string $class = Throwable::class): void
    {
        try {
            $callable();
        } catch (Throwable $throwable) {
            if ($throwable instanceof $class) {
                return;
            }
            throw new RuntimeException('Unexpected exception: ' . get_class($throwable));
        }
        throw new RuntimeException('Expected exception was not thrown.');
    }

    public function finish(): never
    {
        fwrite(STDOUT, sprintf("RESULT %d PASS, %d FAIL\n", $this->passed, $this->failed));
        exit(0 === $this->failed ? 0 : 1);
    }
}
