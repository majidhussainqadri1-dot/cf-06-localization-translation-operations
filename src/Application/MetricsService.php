<?php

declare(strict_types=1);

namespace Sabri\Localization\Application;

use Sabri\Localization\Infrastructure\Database;
use Sabri\Localization\Infrastructure\Repository\LocalizationRepository;

final class MetricsService
{
    public function __construct(private readonly LocalizationRepository $repo)
    {
    }

    public function summary(): array
    {
        global $wpdb;
        $metrics = array();
        foreach (array('resources','projects','units','terminology','memory','vendor_jobs','bundles','feedback','jobs','outbox') as $entity) {
            $table = Database::table($entity);
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
            $metrics[$entity] = $exists ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}") : null;
        }
        if (null === $metrics['units']) {
            $metrics['stale_units'] = null;
            $metrics['critical_feedback'] = null;
            $metrics['dead_letter_jobs'] = null;
            $metrics['dead_letter_events'] = null;
            $metrics['coverage'] = array();
            return $metrics;
        }
        $metrics['stale_units'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM " . Database::table('units') . " WHERE status='stale'");
        $metrics['critical_feedback'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM " . Database::table('feedback') . " WHERE severity='critical' AND status<>'closed'");
        $metrics['dead_letter_jobs'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM " . Database::table('jobs') . " WHERE status='dead_letter'");
        $metrics['dead_letter_events'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM " . Database::table('outbox') . " WHERE status='dead_letter'");
        $coverage = array();
        foreach ($this->repo->list('locales', array(), 200, 0, 'id ASC') as $locale) {
            $coverage[$locale['locale_tag']] = $this->repo->coverage((string) $locale['locale_tag']);
        }
        $metrics['coverage'] = $coverage;
        return $metrics;
    }
}
