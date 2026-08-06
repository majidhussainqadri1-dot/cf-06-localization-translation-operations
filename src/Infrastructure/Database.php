<?php

declare(strict_types=1);

namespace Sabri\Localization\Infrastructure;

final class Database
{
    public const ENTITIES = array(
        'locales' => 'slto_locales',
        'resources' => 'slto_resources',
        'secure_payloads' => 'slto_secure_payloads',
        'projects' => 'slto_projects',
        'project_resources' => 'slto_project_resources',
        'assignments' => 'slto_assignments',
        'units' => 'slto_translation_units',
        'comments' => 'slto_unit_comments',
        'terminology' => 'slto_terminology',
        'style_guides' => 'slto_style_guides',
        'memory' => 'slto_translation_memory',
        'providers' => 'slto_providers',
        'vendor_jobs' => 'slto_vendor_jobs',
        'bundles' => 'slto_bundles',
        'qa_results' => 'slto_qa_results',
        'feedback' => 'slto_feedback',
        'content_links' => 'slto_content_links',
        'audit' => 'slto_audit_events',
        'outbox' => 'slto_outbox',
        'jobs' => 'slto_jobs',
        'idempotency' => 'slto_idempotency',
        'rate_limits' => 'slto_rate_limits',
        'migrations' => 'slto_migrations',
        'integration_evidence' => 'slto_integration_evidence',
        'extraction_evidence' => 'slto_extraction_evidence',
        'qa_evidence' => 'slto_qa_evidence',
        'release_approvals' => 'slto_release_approvals',
    );

    public static function table(string $entity): string
    {
        global $wpdb;
        if (! isset(self::ENTITIES[$entity])) {
            throw new \InvalidArgumentException('Unknown localization entity.');
        }
        return $wpdb->prefix . self::ENTITIES[$entity];
    }

    public static function now(): string
    {
        return current_time('mysql', true);
    }

    public static function uuid(): string
    {
        return function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : self::fallbackUuid();
    }

    private static function fallbackUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }
}
