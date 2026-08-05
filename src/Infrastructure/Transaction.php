<?php

declare(strict_types=1);

namespace Sabri\Localization\Infrastructure;

use RuntimeException;
use Throwable;

/**
 * Small transaction coordinator with savepoint-backed nested transactions.
 *
 * Application services may compose other services without accidentally
 * committing an outer operation. MySQL/MariaDB savepoints preserve atomicity.
 */
final class Transaction
{
    private static int $depth = 0;

    public function run(callable $callback): mixed
    {
        global $wpdb;

        $isRoot = 0 === self::$depth;
        $savepoint = 'slto_sp_' . self::$depth;
        $statement = $isRoot ? 'START TRANSACTION' : 'SAVEPOINT ' . $savepoint;

        if (false === $wpdb->query($statement)) {
            throw new RuntimeException('Localization transaction could not be started.');
        }

        ++self::$depth;

        try {
            $result = $callback();
            --self::$depth;

            $commit = $isRoot ? 'COMMIT' : 'RELEASE SAVEPOINT ' . $savepoint;
            if (false === $wpdb->query($commit)) {
                throw new RuntimeException('Localization transaction could not be committed.');
            }

            return $result;
        } catch (Throwable $throwable) {
            --self::$depth;
            $wpdb->query($isRoot ? 'ROLLBACK' : 'ROLLBACK TO SAVEPOINT ' . $savepoint);
            throw $throwable;
        }
    }
}
