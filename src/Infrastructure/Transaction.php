<?php

declare(strict_types=1);

namespace Sabri\Localization\Infrastructure;

use RuntimeException;
use Throwable;

final class Transaction
{
    /**
     * @template T
     * @param callable():T $operation
     * @return T
     */
    public function run(callable $operation): mixed
    {
        global $wpdb;

        if (false === $wpdb->query('START TRANSACTION')) {
            throw new RuntimeException('Unable to start database transaction.');
        }

        try {
            $result = $operation();
            if (false === $wpdb->query('COMMIT')) {
                throw new RuntimeException('Unable to commit database transaction.');
            }

            return $result;
        } catch (Throwable $exception) {
            $wpdb->query('ROLLBACK');
            throw $exception;
        }
    }
}
