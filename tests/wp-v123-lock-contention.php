<?php

declare(strict_types=1);

/**
 * GitHub Actions disposable MySQL two-connection test.
 * Demonstrates that a committed CF-06 resource row blocks a simultaneous
 * SELECT ... FOR UPDATE in another connection, then unlocks after rollback.
 * This is a database locking witness, NOT a multi-worker end-to-end release test.
 */
use Sabri\Localization\Infrastructure\Database;
use Sabri\Localization\Plugin;

if (getenv('GITHUB_ACTIONS') !== 'true') {
    throw new RuntimeException('Two-connection lock probe requires isolated GitHub Actions disposable DB.');
}
if (!defined('ABSPATH') || !extension_loaded('mysqli')) {
    throw new RuntimeException('WordPress/MySQLi required for concurrency acceptance.');
}
global $wpdb;
$resourceService = Plugin::instance()->service('resource');
if (!$resourceService) {
    throw new RuntimeException('CF-06 resource service unavailable.');
}
$key = 'ci.lock'.substr(str_replace('-', '', wp_generate_uuid4()),0,12);
$created = $resourceService->register([
    'resource_key'=>$key,
    'source_locale'=>'en-US',
    'source_text'=>'Synthetic committed source for an isolated two-connection lock probe',
    'risk_class'=>'low',
    'data_class'=>'C1',
]);
$uuid = (string)$created['record']['uuid'];
$table = Database::table('resources');
$host = (string)DB_HOST;
$port = 3306;
if (preg_match('/^([^:]+):([0-9]{2,5})$/', $host, $matches)) {
    $host = $matches[1];
    $port = (int)$matches[2];
}
if ($host !== '127.0.0.1' && $host !== 'localhost') {
    throw new RuntimeException('Lock witness refuses any nonlocal database.');
}

$link = null;
$mainOpen = false;
$secondaryOpen = false;
$timedOut = false;
$unlocked = false;
$cleanupError = null;
try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $link = new mysqli($host, (string)DB_USER, (string)DB_PASSWORD, (string)DB_NAME, $port);
    $link->query('SET SESSION innodb_lock_wait_timeout=1');

    if (false === $wpdb->query('START TRANSACTION')) {
        throw new RuntimeException('Main row-lock transaction cannot begin.');
    }
    $mainOpen = true;
    $locked = $wpdb->get_var($wpdb->prepare("SELECT uuid FROM {$table} WHERE uuid=%s FOR UPDATE", $uuid));
    if ($locked !== $uuid || '' !== (string)$wpdb->last_error) {
        throw new RuntimeException('Main connection did not lock the committed resource.');
    }

    $link->begin_transaction();
    $secondaryOpen = true;
    $statement = $link->prepare("SELECT uuid FROM {$table} WHERE uuid=? FOR UPDATE");
    $statement->bind_param('s', $uuid);
    try {
        $statement->execute();
        throw new RuntimeException('Second connection acquired the same InnoDB row while locked.');
    } catch (mysqli_sql_exception $e) {
        if ((int)$e->getCode() !== 1205 && (int)$e->getCode() !== 3572) {
            throw $e;
        }
        $timedOut = true;
    } finally {
        $statement->close();
    }
    $link->rollback();
    $secondaryOpen = false;

    if (false === $wpdb->query('ROLLBACK')) {
        throw new RuntimeException('Main test lock could not roll back.');
    }
    $mainOpen = false;

    $link->begin_transaction();
    $secondaryOpen = true;
    $statement = $link->prepare("SELECT uuid FROM {$table} WHERE uuid=? FOR UPDATE");
    $statement->bind_param('s', $uuid);
    $statement->execute();
    $row = $statement->get_result()->fetch_assoc();
    if (($row['uuid'] ?? '') !== $uuid) {
        throw new RuntimeException('Second connection did not recover the unlocked resource.');
    }
    $statement->close();
    $unlocked = true;
} finally {
    if ($secondaryOpen && $link instanceof mysqli) {
        $link->rollback();
    }
    if ($link instanceof mysqli) {
        $link->close();
    }
    if ($mainOpen) {
        $wpdb->query('ROLLBACK');
    }
    // No real site can execute this script: it demands GITHUB_ACTIONS, local DB,
    // and a disposable fixture. Do not call WordPress's canonical purge API.
    $deleted = $wpdb->delete($table, ['uuid'=>$uuid]);
    if (false === $deleted) {
        $cleanupError = 'Synthetic CI resource cleanup failed.';
    }
}
if (!$timedOut || !$unlocked || $cleanupError !== null) {
    throw new RuntimeException($cleanupError ?? 'Two-connection transactional source-row proof failed.');
}
echo "V123_TWO_CONNECTION_ROW_LOCK_PASS\n";
