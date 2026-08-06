<?php

declare(strict_types=1);

use Sabri\Localization\Application\IntegrationService;
use Sabri\Localization\Infrastructure\Activator;
use Sabri\Localization\Infrastructure\Database;
use Sabri\Localization\Infrastructure\Transaction;
use Sabri\Localization\Plugin;
use Sabri\Localization\Security\Authorization;

if (! defined('ABSPATH')) {
    throw new RuntimeException('WordPress bootstrap is required.');
}

if (! function_exists('smc_membership_assertions')) {
    function smc_membership_assertions(int $userId): array
    {
        return array('state' => 'active', 'approved' => true, 'suspended' => false, 'user_id' => $userId);
    }
}

$check = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException('WP_INTEGRATION_FAIL: ' . $message);
    }
};

/** @var wpdb $wpdb */
global $wpdb;
wp_set_current_user(1);

// Fresh activation and a second idempotent activation must both succeed.
Activator::activate();
Activator::activate();
$check('1.0.1' === (string) get_option('slto_schema_version'), 'schema version mismatch');
$check('1.1.0' === (string) get_option('slto_contract_version'), 'contract version mismatch');
$check(false === (bool) get_option('slto_runtime_enabled', false), 'runtime must remain disabled');

foreach (Database::ENTITIES as $entity => $suffix) {
    $table = Database::table((string) $entity);
    $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
    $check($table === $found, 'missing table ' . $entity);
}

$jobsTable = Database::table('jobs');
$quotedJobs = '`' . str_replace('`', '``', $jobsTable) . '`';
$indexRows = $wpdb->get_results("SHOW INDEX FROM {$quotedJobs} WHERE Key_name='job_dedupe'", ARRAY_A);
$indexColumns = is_array($indexRows) ? array_column($indexRows, 'Column_name') : array();
$check(array('job_type', 'dedupe_key') === array_values($indexColumns), 'compound job dedupe index mismatch');

$check(Authorization::allowed('manage'), 'approved File 00 assertion should permit administrator capability');
$deny = static fn (): bool => false;
add_filter('slto_authorize_action', $deny, 10, 5);
$check(! Authorization::allowed('manage'), 'authorization extension must be able to deny');
remove_filter('slto_authorize_action', $deny, 10);

$integrationService = Plugin::instance()->service('integrations');
$check($integrationService instanceof IntegrationService, 'integration service unavailable');
$readiness = $integrationService->readiness();
$check(count($readiness) > 0 && ! in_array(true, $readiness, true), 'unaccepted integrations must fail closed');

$transaction = new Transaction();
$bucketRollback = hash('sha256', 'rollback-' . wp_generate_uuid4());
try {
    $transaction->run(static function () use ($wpdb, $bucketRollback): void {
        $written = $wpdb->insert(Database::table('rate_limits'), array(
            'bucket_key' => $bucketRollback,
            'window_start' => time(),
            'request_count' => 1,
            'updated_at' => Database::now(),
        ));
        if (false === $written) {
            throw new RuntimeException('test insert failed');
        }
        throw new RuntimeException('forced rollback');
    });
    throw new RuntimeException('forced rollback was not surfaced');
} catch (RuntimeException $exception) {
    $check('forced rollback' === $exception->getMessage(), 'unexpected rollback exception');
}
$remaining = $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . Database::table('rate_limits') . ' WHERE bucket_key=%s', $bucketRollback));
$check(0 === (int) $remaining, 'root transaction did not roll back');

$bucketOuter = hash('sha256', 'outer-' . wp_generate_uuid4());
$bucketInner = hash('sha256', 'inner-' . wp_generate_uuid4());
$transaction->run(static function () use ($transaction, $wpdb, $bucketOuter, $bucketInner): void {
    $wpdb->insert(Database::table('rate_limits'), array(
        'bucket_key' => $bucketOuter,
        'window_start' => time(),
        'request_count' => 1,
        'updated_at' => Database::now(),
    ));
    try {
        $transaction->run(static function () use ($wpdb, $bucketInner): void {
            $wpdb->insert(Database::table('rate_limits'), array(
                'bucket_key' => $bucketInner,
                'window_start' => time(),
                'request_count' => 1,
                'updated_at' => Database::now(),
            ));
            throw new RuntimeException('forced savepoint rollback');
        });
    } catch (RuntimeException $exception) {
        if ('forced savepoint rollback' !== $exception->getMessage()) {
            throw $exception;
        }
    }
});
$outerCount = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . Database::table('rate_limits') . ' WHERE bucket_key=%s', $bucketOuter));
$innerCount = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . Database::table('rate_limits') . ' WHERE bucket_key=%s', $bucketInner));
$check(1 === $outerCount && 0 === $innerCount, 'nested savepoint behavior is incorrect');
$wpdb->query($wpdb->prepare('DELETE FROM ' . Database::table('rate_limits') . ' WHERE bucket_key IN (%s,%s)', $bucketOuter, $bucketInner));

$check(false !== wp_next_scheduled('slto_process_jobs'), 'job schedule missing');
$check(false !== wp_next_scheduled('slto_daily_reconciliation'), 'reconciliation schedule missing');

echo "WP_INTEGRATION_PASS\n";
