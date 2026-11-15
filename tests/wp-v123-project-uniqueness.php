<?php

declare(strict_types=1);

/**
 * Disposable WordPress/MySQL integration: cross-project uniqueness for one
 * canonical source/version/target. All synthetic database writes roll back.
 * This is not a Hostinger staging or true parallel-worker acceptance test.
 */

use Sabri\Localization\Infrastructure\Database;
use Sabri\Localization\Infrastructure\Transaction;
use Sabri\Localization\Plugin;

if (!defined('ABSPATH')) {
    throw new RuntimeException('WordPress bootstrap required for V12.3 job test.');
}

$service = Plugin::instance()->service('project');
$locales = Plugin::instance()->service('locale');
$resources = Plugin::instance()->service('resource');
if (!$service || !$locales || !$resources) {
    throw new RuntimeException('CF-06 project, locale or resource services unavailable.');
}
global $wpdb;
$suffix = substr(str_replace('-', '', wp_generate_uuid4()), 0, 12);
$key = 'v123.resource'.$suffix;
$sourceLocale = 'en-GB';
$targetLocale = 'fr-FR';
$alternateTarget = 'de-DE';

$fixtureEnd = 'v123_fixture_rollback_'. $suffix;
$passed = 0;

try {
    (new Transaction())->run(function () use (
        $wpdb, $service, $locales, $resources, $key, $fixtureEnd,
        $sourceLocale, $targetLocale, $alternateTarget, &$passed
    ): void {
        foreach ([$sourceLocale, $targetLocale, $alternateTarget] as $tag) {
            $locale = $locales->register(['locale'=>$tag, 'fallback'=>null]);
            $affected = $wpdb->update(
                Database::table('locales'), ['status'=>'tested'], ['uuid'=>(string)$locale['uuid']]
            );
            if (false === $affected || 1 !== $affected) {
                throw new RuntimeException('Synthetic test locale could not become tested.');
            }
        }

        $created = $resources->register([
            'resource_key'=>$key,
            'source_locale'=>$sourceLocale,
            'source_text'=>'Synthetic source one for cross-project uniqueness',
            'risk_class'=>'low',
            'data_class'=>'C1',
        ]);
        $resource = $created['record'];
        $input = [
            'name'=>'Synthetic translation job',
            'source_locale'=>$sourceLocale,
            'target_locales'=>[$targetLocale],
            'resource_keys'=>[$key],
            'risk_ceiling'=>'low',
        ];
        $first = $service->create($input);
        if ('draft' !== (string)$first['status']) {
            throw new RuntimeException('First synthetic project should be draft.');
        }
        ++$passed;

        try {
            $service->create($input);
            throw new RuntimeException('Duplicate cross-project translation job was admitted.');
        } catch (\DomainException $exception) {
            if ('translation_job_already_active' !== $exception->getMessage()) {
                throw $exception;
            }
        }
        ++$passed;

        $different = $input;
        $different['target_locales'] = [$alternateTarget];
        $allowed = $service->create($different);
        if ('draft' !== (string)$allowed['status']) {
            throw new RuntimeException('Independent target locale was incorrectly blocked.');
        }
        ++$passed;

        // A cancelled project no longer owns an active translation job.
        $service->transition((string)$first['uuid'], 'cancelled', (int)$first['row_version'], 'Synthetic cancelled project');
        $replacement = $service->create($input);
        if ((string)$replacement['uuid'] === (string)$first['uuid']) {
            throw new RuntimeException('Replacement project identity was reused.');
        }
        ++$passed;

        // A source correction creates a new version; no stale-version job may
        // keep the new version blocked. Historical units are marked stale.
        $updated = $resources->register([
            'resource_key'=>$key,
            'source_locale'=>$sourceLocale,
            'source_text'=>'Synthetic source two after correction',
            'risk_class'=>'low',
            'data_class'=>'C1',
            'row_version'=>(int)$resource['row_version'],
        ]);
        if ((int)$updated['record']['source_version'] !== 2) {
            throw new RuntimeException('Synthetic source version did not increment.');
        }
        $nextVersion = $service->create($input);
        if ('draft' !== (string)$nextVersion['status']) {
            throw new RuntimeException('New source version was incorrectly blocked.');
        }
        ++$passed;

        // All fixture records, including audit/outbox/units/projects, are
        // rolled back by the enclosing root transaction.
        throw new RuntimeException($fixtureEnd);
    });
} catch (RuntimeException $exception) {
    if ($exception->getMessage() !== $fixtureEnd) {
        throw $exception;
    }
}

if (5 !== $passed) {
    throw new RuntimeException('V12.3 WordPress/MySQL guard integration incomplete: '. $passed);
}
echo "V123_PROJECT_JOB_INTEGRATION_PASS 5\n";
