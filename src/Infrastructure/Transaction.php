<?php

declare(strict_types=1);

namespace Sabri\Localization\Infrastructure;

use RuntimeException;
use Throwable;

/**
 * Savepoint-backed transaction coordinator.
 *
 * Depth is changed exactly once per scope. Commit and rollback failures are
 * treated as integrity failures and the coordinator is reset at the root so a
 * failed operation cannot poison later requests in the same PHP process.
 */
final class Transaction
{
    private static int $depth = 0;

    public function run(callable $callback): mixed
    {
        global $wpdb;

        $isRoot = 0 === self::$depth;
        $level = self::$depth;
        $savepoint = 'slto_sp_' . $level;
        $begin = $isRoot ? 'START TRANSACTION' : 'SAVEPOINT ' . $savepoint;

        if (false === $wpdb->query($begin)) {
            throw new RuntimeException('Localization transaction could not be started.');
        }

        self::$depth = $level + 1;
        $completed = false;

        try {
            $result = $callback();
            $commit = $isRoot ? 'COMMIT' : 'RELEASE SAVEPOINT ' . $savepoint;
            if (false === $wpdb->query($commit)) {
                throw new RuntimeException('Localization transaction could not be committed.');
            }
            $completed = true;
            return $result;
        } catch (Throwable $throwable) {
            $rollback = $isRoot ? 'ROLLBACK' : 'ROLLBACK TO SAVEPOINT ' . $savepoint;
            $rolledBack = $wpdb->query($rollback);
            if (false === $rolledBack) {
                throw new RuntimeException('Localization transaction rollback failed.', 0, $throwable);
            }
            throw $throwable;
        } finally {
            self::$depth = $isRoot ? 0 : $level;
            if (! $completed && self::$depth < 0) {
                self::$depth = 0;
            }
        }
    }
}
