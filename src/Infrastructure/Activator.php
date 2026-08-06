<?php

declare(strict_types=1);

namespace Sabri\Localization\Infrastructure;

use Sabri\Localization\Domain\Locale\LocaleValidator;

final class Activator
{
    private const CAPABILITIES = array(
        'manage_sabri_localization',
        'translate_sabri_localization',
        'review_sabri_localization',
        'domain_review_sabri_localization',
        'manage_sabri_terminology',
        'release_sabri_localization',
        'manage_sabri_localization_providers',
        'audit_sabri_localization',
    );

    public static function maybeUpgrade(): void
    {
        $installedSchema = (string) get_option('slto_schema_version', '0.0.0');
        $installedContract = (string) get_option('slto_contract_version', '0.0.0');
        if (version_compare($installedSchema, SABRI_SLTO_SCHEMA_VERSION, '>=')
            && version_compare($installedContract, SABRI_SLTO_CONTRACT_VERSION, '>=')) {
            return;
        }
        if (get_transient('slto_schema_upgrade_lock')) {
            return;
        }
        set_transient('slto_schema_upgrade_lock', 1, 10 * MINUTE_IN_SECONDS);
        try {
            self::activate();
        } finally {
            delete_transient('slto_schema_upgrade_lock');
        }
    }

    public static function activate(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $c = $wpdb->get_charset_collate();
        foreach (self::schema($c) as $sql) {
            $result = dbDelta($sql);
            if (! is_array($result) || '' !== (string) $wpdb->last_error) {
                throw new \RuntimeException('CF-06 schema migration failed.');
            }
        }
        self::normalizeIndexes();
        self::verifySchema();
        if (false === update_option('slto_schema_version', SABRI_SLTO_SCHEMA_VERSION, false) && (string) get_option('slto_schema_version', '') !== SABRI_SLTO_SCHEMA_VERSION) {
            throw new \RuntimeException('CF-06 schema version could not be persisted.');
        }
        add_option('slto_runtime_enabled', false, '', false);
        add_option('slto_default_locale', 'en-US', '', false);
        if (false === update_option('slto_contract_version', SABRI_SLTO_CONTRACT_VERSION, false) && (string) get_option('slto_contract_version', '') !== SABRI_SLTO_CONTRACT_VERSION) {
            throw new \RuntimeException('CF-06 contract version could not be persisted.');
        }
        add_option('slto_last_release_status', 'source-candidate', '', false);
        self::seedLocales();
        self::grantCapabilities();
        self::scheduleJobs();
    }

    public static function deactivate(): void
    {
        wp_clear_scheduled_hook('slto_process_jobs');
        wp_clear_scheduled_hook('slto_daily_reconciliation');
    }

    private static function schema(string $c): array
    {
        $t = static fn (string $e): string => Database::table($e);
        return array(
            "CREATE TABLE {$t('locales')} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                uuid char(36) NOT NULL,
                locale_tag varchar(35) NOT NULL,
                language_subtag varchar(8) NOT NULL,
                script_subtag varchar(8) NOT NULL DEFAULT '',
                region_subtag varchar(8) NOT NULL DEFAULT '',
                direction varchar(3) NOT NULL DEFAULT 'ltr',
                fallback_tag varchar(35) NULL,
                plural_rules_version varchar(40) NOT NULL DEFAULT '',
                format_data_version varchar(40) NOT NULL DEFAULT '',
                enabled_surfaces longtext NULL,
                status varchar(24) NOT NULL DEFAULT 'proposed',
                owner varchar(191) NOT NULL DEFAULT 'CF-06',
                row_version bigint(20) unsigned NOT NULL DEFAULT 1,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                UNIQUE KEY locale_tag (locale_tag),
                KEY status (status)
            ) {$c};",
            "CREATE TABLE {$t('resources')} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                uuid char(36) NOT NULL,
                resource_key varchar(191) NOT NULL,
                source_locale varchar(35) NOT NULL,
                source_text longtext NOT NULL,
                secure_payload_id bigint(20) unsigned NULL,
                source_version bigint(20) unsigned NOT NULL DEFAULT 1,
                source_hash char(64) NOT NULL,
                context longtext NULL,
                description text NULL,
                domain_name varchar(80) NOT NULL DEFAULT 'platform',
                risk_class varchar(20) NOT NULL DEFAULT 'low',
                data_class varchar(3) NOT NULL DEFAULT 'C1',
                placeholders longtext NULL,
                markup_policy longtext NULL,
                references_json longtext NULL,
                translatability_json longtext NULL,
                critical tinyint(1) NOT NULL DEFAULT 0,
                status varchar(24) NOT NULL DEFAULT 'active',
                created_by bigint(20) unsigned NOT NULL DEFAULT 0,
                updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
                row_version bigint(20) unsigned NOT NULL DEFAULT 1,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                UNIQUE KEY resource_key (resource_key),
                KEY source_locale (source_locale),
                KEY domain_risk (domain_name,risk_class),
                KEY status (status)
            ) {$c};",
            "CREATE TABLE {$t('secure_payloads')} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                uuid char(36) NOT NULL,
                owner_type varchar(40) NOT NULL,
                owner_uuid char(36) NOT NULL,
                purpose varchar(80) NOT NULL,
                key_id varchar(64) NOT NULL,
                algorithm varchar(32) NOT NULL,
                nonce text NOT NULL,
                auth_tag text NOT NULL,
                ciphertext longtext NOT NULL,
                aad_hash char(64) NOT NULL,
                payload_hash char(64) NOT NULL,
                created_at datetime NOT NULL,
                expires_at datetime NULL,
                deleted_at datetime NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                KEY owner_lookup (owner_type,owner_uuid),
                KEY expires_at (expires_at)
            ) {$c};",
            "CREATE TABLE {$t('projects')} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                uuid char(36) NOT NULL,
                name varchar(191) NOT NULL,
                description text NULL,
                source_snapshot_hash char(64) NOT NULL,
                source_locale varchar(35) NOT NULL,
                target_locales longtext NOT NULL,
                scope_json longtext NOT NULL,
                priority varchar(20) NOT NULL DEFAULT 'normal',
                risk_ceiling varchar(20) NOT NULL DEFAULT 'high',
                owner_id bigint(20) unsigned NOT NULL,
                provider_key varchar(80) NULL,
                release_target varchar(191) NULL,
                due_at datetime NULL,
                status varchar(24) NOT NULL DEFAULT 'draft',
                row_version bigint(20) unsigned NOT NULL DEFAULT 1,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                KEY status_due (status,due_at),
                KEY owner_id (owner_id)
            ) {$c};",
            "CREATE TABLE {$t('project_resources')} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                uuid char(36) NOT NULL,
                project_uuid char(36) NOT NULL,
                resource_uuid char(36) NOT NULL,
                source_version bigint(20) unsigned NOT NULL,
                source_hash char(64) NOT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                UNIQUE KEY project_resource (project_uuid,resource_uuid),
                KEY resource_uuid (resource_uuid)
            ) {$c};",
            "CREATE TABLE {$t('units')} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                uuid char(36) NOT NULL,
                project_uuid char(36) NOT NULL,
                resource_uuid char(36) NOT NULL,
                target_locale varchar(35) NOT NULL,
                source_version bigint(20) unsigned NOT NULL,
                source_hash char(64) NOT NULL,
                target_text longtext NULL,
                secure_payload_id bigint(20) unsigned NULL,
                status varchar(24) NOT NULL DEFAULT 'new',
                machine_draft tinyint(1) NOT NULL DEFAULT 0,
                provider_job_uuid char(36) NULL,
                translator_id bigint(20) unsigned NULL,
                linguistic_reviewer_id bigint(20) unsigned NULL,
                domain_reviewer_id bigint(20) unsigned NULL,
                qa_status varchar(24) NOT NULL DEFAULT 'pending',
                stale_reason text NULL,
                released_bundle_uuid char(36) NULL,
                row_version bigint(20) unsigned NOT NULL DEFAULT 1,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                UNIQUE KEY resource_locale_project (project_uuid,resource_uuid,target_locale),
                KEY queue (target_locale,status),
                KEY resource_uuid (resource_uuid)
            ) {$c};",
            "CREATE TABLE {$t('assignments')} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                uuid char(36) NOT NULL,
                project_uuid char(36) NOT NULL,
                unit_uuid char(36) NOT NULL,
                assignee_id bigint(20) unsigned NOT NULL,
                assignment_role varchar(32) NOT NULL,
                locale_tag varchar(35) NOT NULL,
                qualification_json longtext NOT NULL,
                conflict_status varchar(24) NOT NULL DEFAULT 'clear',
                status varchar(24) NOT NULL DEFAULT 'active',
                due_at datetime NULL,
                expires_at datetime NULL,
                row_version bigint(20) unsigned NOT NULL DEFAULT 1,
                created_by bigint(20) unsigned NOT NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                UNIQUE KEY unit_role (unit_uuid,assignment_role),
                KEY assignee_queue (assignee_id,status,due_at)
            ) {$c};",
            "CREATE TABLE {$t('comments')} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                uuid char(36) NOT NULL,
                unit_uuid char(36) NOT NULL,
                parent_uuid char(36) NULL,
                author_id bigint(20) unsigned NOT NULL,
                audience varchar(24) NOT NULL DEFAULT 'internal',
                comment_text longtext NOT NULL,
                status varchar(24) NOT NULL DEFAULT 'open',
                resolution_text text NULL,
                row_version bigint(20) unsigned NOT NULL DEFAULT 1,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                KEY unit_status (unit_uuid,status)
            ) {$c};",
            "CREATE TABLE {$t('terminology')} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                uuid char(36) NOT NULL,
                concept_id varchar(80) NOT NULL,
                domain_name varchar(80) NOT NULL,
                source_locale varchar(35) NOT NULL,
                source_term varchar(191) NOT NULL,
                target_locale varchar(35) NOT NULL,
                approved_term varchar(191) NOT NULL,
                prohibited_terms longtext NULL,
                definition_text text NULL,
                context_text text NULL,
                grammar_notes text NULL,
                created_by bigint(20) unsigned NOT NULL DEFAULT 0,
                reviewer_id bigint(20) unsigned NULL,
                status varchar(24) NOT NULL DEFAULT 'proposed',
                term_version bigint(20) unsigned NOT NULL DEFAULT 1,
                effective_at datetime NULL,
                replaced_by_uuid char(36) NULL,
                row_version bigint(20) unsigned NOT NULL DEFAULT 1,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                UNIQUE KEY concept_locale (concept_id,target_locale,term_version),
                KEY lookup_term (target_locale,domain_name,status)
            ) {$c};",
            "CREATE TABLE {$t('style_guides')} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                uuid char(36) NOT NULL,
                locale_tag varchar(35) NOT NULL,
                domain_name varchar(80) NOT NULL DEFAULT 'platform',
                guide_version bigint(20) unsigned NOT NULL DEFAULT 1,
                rules_json longtext NOT NULL,
                examples_json longtext NULL,
                created_by bigint(20) unsigned NOT NULL DEFAULT 0,
                approved_by bigint(20) unsigned NULL,
                status varchar(24) NOT NULL DEFAULT 'draft',
                effective_at datetime NULL,
                row_version bigint(20) unsigned NOT NULL DEFAULT 1,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                UNIQUE KEY locale_domain_version (locale_tag,domain_name,guide_version),
                KEY current_guide (locale_tag,domain_name,status)
            ) {$c};",
            "CREATE TABLE {$t('memory')} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                uuid char(36) NOT NULL,
                source_locale varchar(35) NOT NULL,
                target_locale varchar(35) NOT NULL,
                source_segment longtext NOT NULL,
                target_segment longtext NOT NULL,
                source_hash char(64) NOT NULL,
                context_hash char(64) NOT NULL,
                domain_name varchar(80) NOT NULL,
                risk_class varchar(20) NOT NULL,
                provenance_json longtext NOT NULL,
                license_code varchar(80) NOT NULL DEFAULT 'platform',
                status varchar(24) NOT NULL DEFAULT 'approved',
                created_from_unit_uuid char(36) NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                KEY suggestion (source_locale,target_locale,domain_name,status),
                KEY source_hash (source_hash)
            ) {$c};",
            "CREATE TABLE {$t('providers')} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                uuid char(36) NOT NULL,
                provider_key varchar(80) NOT NULL,
                provider_type varchar(32) NOT NULL,
                base_url varchar(255) NULL,
                allowed_hosts longtext NULL,
                region_code varchar(32) NULL,
                retention_days int unsigned NOT NULL DEFAULT 0,
                training_allowed tinyint(1) NOT NULL DEFAULT 0,
                subprocessors_json longtext NULL,
                credential_reference varchar(191) NULL,
                contract_version varchar(40) NOT NULL,
                status varchar(24) NOT NULL DEFAULT 'disabled',
                row_version bigint(20) unsigned NOT NULL DEFAULT 1,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                UNIQUE KEY provider_key (provider_key),
                KEY status (status)
            ) {$c};",
            "CREATE TABLE {$t('vendor_jobs')} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                uuid char(36) NOT NULL,
                provider_key varchar(80) NOT NULL,
                model_version varchar(80) NOT NULL,
                region_code varchar(32) NULL,
                purpose varchar(80) NOT NULL,
                project_uuid char(36) NULL,
                unit_uuids longtext NOT NULL,
                outbound_hash char(64) NOT NULL,
                inbound_hash char(64) NULL,
                redaction_summary longtext NULL,
                provider_reference varchar(191) NULL,
                status varchar(24) NOT NULL DEFAULT 'prepared',
                deletion_evidence longtext NULL,
                row_version bigint(20) unsigned NOT NULL DEFAULT 1,
                created_by bigint(20) unsigned NOT NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                purge_due_at datetime NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                KEY provider_status (provider_key,status),
                KEY purge_due_at (purge_due_at)
            ) {$c};",
            "CREATE TABLE {$t('bundles')} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                uuid char(36) NOT NULL,
                locale_tag varchar(35) NOT NULL,
                bundle_version bigint(20) unsigned NOT NULL,
                manifest_json longtext NOT NULL,
                payload_json longtext NOT NULL,
                source_list_json longtext NOT NULL,
                coverage decimal(6,3) NOT NULL DEFAULT 0,
                critical_coverage decimal(6,3) NOT NULL DEFAULT 0,
                bundle_hash char(64) NOT NULL,
                signature text NULL,
                status varchar(24) NOT NULL DEFAULT 'planned',
                previous_bundle_uuid char(36) NULL,
                approved_by bigint(20) unsigned NULL,
                activated_by bigint(20) unsigned NULL,
                activated_at datetime NULL,
                row_version bigint(20) unsigned NOT NULL DEFAULT 1,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                UNIQUE KEY locale_version (locale_tag,bundle_version),
                KEY active_lookup (locale_tag,status)
            ) {$c};",
            "CREATE TABLE {$t('qa_results')} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                uuid char(36) NOT NULL,
                target_type varchar(24) NOT NULL,
                target_uuid char(36) NOT NULL,
                rule_code varchar(80) NOT NULL,
                result varchar(16) NOT NULL,
                severity varchar(16) NOT NULL,
                details_json longtext NULL,
                reviewer_id bigint(20) unsigned NULL,
                fixed_at datetime NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                KEY target_lookup (target_type,target_uuid),
                KEY result_severity (result,severity)
            ) {$c};",
            "CREATE TABLE {$t('feedback')} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                uuid char(36) NOT NULL,
                reporter_id bigint(20) unsigned NOT NULL DEFAULT 0,
                locale_tag varchar(35) NOT NULL,
                resource_key varchar(191) NULL,
                route_path varchar(255) NULL,
                category varchar(40) NOT NULL,
                severity varchar(16) NOT NULL DEFAULT 'normal',
                suggestion_text text NULL,
                status varchar(24) NOT NULL DEFAULT 'reported',
                assigned_to bigint(20) unsigned NULL,
                outcome_text text NULL,
                row_version bigint(20) unsigned NOT NULL DEFAULT 1,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                KEY queue (status,severity,created_at),
                KEY locale_tag (locale_tag)
            ) {$c};",
            "CREATE TABLE {$t('content_links')} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                uuid char(36) NOT NULL,
                owner_module varchar(40) NOT NULL,
                owner_object_id varchar(191) NOT NULL,
                source_locale varchar(35) NOT NULL,
                target_locale varchar(35) NOT NULL,
                resource_uuid char(36) NULL,
                unit_uuid char(36) NULL,
                source_version varchar(80) NOT NULL,
                source_hash char(64) NOT NULL,
                translated_url varchar(255) NULL,
                canonical_url varchar(255) NULL,
                hreflang_code varchar(35) NULL,
                publication_status varchar(24) NOT NULL DEFAULT 'draft',
                owner_approval_ref varchar(191) NULL,
                row_version bigint(20) unsigned NOT NULL DEFAULT 1,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                UNIQUE KEY owner_locale (owner_module,owner_object_id,target_locale),
                KEY publication_status (publication_status)
            ) {$c};",
            "CREATE TABLE {$t('integration_evidence')} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                uuid char(36) NOT NULL,
                integration_key varchar(80) NOT NULL,
                contract_version varchar(40) NOT NULL,
                manifest_hash char(64) NOT NULL,
                evidence_hash char(64) NOT NULL,
                evidence_ref varchar(191) NOT NULL,
                environment_name varchar(24) NOT NULL,
                approved_by bigint(20) unsigned NOT NULL,
                approved_at datetime NOT NULL,
                expires_at datetime NULL,
                status varchar(24) NOT NULL DEFAULT 'accepted',
                row_version bigint(20) unsigned NOT NULL DEFAULT 1,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                UNIQUE KEY integration_key (integration_key),
                KEY status_expiry (status,expires_at)
            ) {$c};",
            "CREATE TABLE {$t('extraction_evidence')} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                uuid char(36) NOT NULL,
                owner_module varchar(40) NOT NULL,
                repository_ref varchar(191) NOT NULL,
                source_commit varchar(64) NOT NULL,
                inventory_hash char(64) NOT NULL,
                extraction_hash char(64) NOT NULL,
                evidence_ref varchar(191) NOT NULL,
                resource_count bigint(20) unsigned NOT NULL DEFAULT 0,
                environment_name varchar(24) NOT NULL,
                approved_by bigint(20) unsigned NOT NULL,
                approved_at datetime NOT NULL,
                status varchar(24) NOT NULL DEFAULT 'accepted',
                row_version bigint(20) unsigned NOT NULL DEFAULT 1,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                UNIQUE KEY module_commit_hash (owner_module,source_commit,extraction_hash),
                KEY owner_status (owner_module,status)
            ) {$c};",
            "CREATE TABLE {$t('qa_evidence')} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                uuid char(36) NOT NULL,
                target_type varchar(40) NOT NULL,
                target_uuid varchar(191) NOT NULL,
                environment_name varchar(24) NOT NULL,
                plugin_version varchar(40) NOT NULL,
                build_sha varchar(64) NOT NULL,
                test_id varchar(80) NOT NULL,
                expected_hash char(64) NOT NULL,
                actual_hash char(64) NOT NULL,
                result varchar(16) NOT NULL,
                artifact_ref varchar(191) NOT NULL,
                artifact_hash char(64) NOT NULL,
                reviewer_id bigint(20) unsigned NOT NULL,
                details_json longtext NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                KEY target_environment (target_type,target_uuid,environment_name),
                KEY result_test (result,test_id)
            ) {$c};",
            "CREATE TABLE {$t('release_approvals')} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                uuid char(36) NOT NULL,
                bundle_uuid char(36) NOT NULL,
                approval_role varchar(32) NOT NULL,
                approver_id bigint(20) unsigned NOT NULL,
                evidence_ref varchar(191) NOT NULL,
                evidence_hash char(64) NOT NULL,
                step_up_at datetime NOT NULL,
                approved_at datetime NOT NULL,
                status varchar(24) NOT NULL DEFAULT 'valid',
                row_version bigint(20) unsigned NOT NULL DEFAULT 1,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                UNIQUE KEY bundle_role (bundle_uuid,approval_role),
                KEY bundle_status (bundle_uuid,status),
                KEY approver_id (approver_id)
            ) {$c};",
            "CREATE TABLE {$t('audit')} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                uuid char(36) NOT NULL,
                trace_id char(36) NOT NULL,
                object_type varchar(40) NOT NULL,
                object_key varchar(191) NOT NULL,
                action_name varchar(80) NOT NULL,
                actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
                purpose varchar(80) NOT NULL DEFAULT 'operations',
                result varchar(20) NOT NULL,
                payload_hash char(64) NOT NULL DEFAULT '',
                previous_hash char(64) NOT NULL DEFAULT '',
                event_hash char(64) NOT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                KEY trace_id (trace_id),
                KEY object_lookup (object_type,object_key),
                KEY created_at (created_at)
            ) {$c};",
            "CREATE TABLE {$t('outbox')} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                uuid char(36) NOT NULL,
                event_name varchar(80) NOT NULL,
                aggregate_type varchar(40) NOT NULL,
                aggregate_uuid char(36) NOT NULL,
                contract_version varchar(40) NOT NULL,
                payload_json longtext NOT NULL,
                payload_hash char(64) NOT NULL,
                status varchar(24) NOT NULL DEFAULT 'pending',
                lease_owner varchar(80) NULL,
                lease_until datetime NULL,
                attempts int unsigned NOT NULL DEFAULT 0,
                available_at datetime NOT NULL,
                delivered_at datetime NULL,
                last_error text NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                KEY dispatch_queue (status,available_at),
                KEY lease_until (lease_until)
            ) {$c};",
            "CREATE TABLE {$t('jobs')} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                uuid char(36) NOT NULL,
                job_type varchar(80) NOT NULL,
                dedupe_key varchar(191) NOT NULL,
                payload_json longtext NOT NULL,
                status varchar(24) NOT NULL DEFAULT 'queued',
                attempts int unsigned NOT NULL DEFAULT 0,
                max_attempts int unsigned NOT NULL DEFAULT 5,
                available_at datetime NOT NULL,
                lease_owner varchar(80) NULL,
                lease_until datetime NULL,
                last_error text NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                UNIQUE KEY job_dedupe (job_type,dedupe_key),
                KEY queue (status,available_at),
                KEY lease_until (lease_until)
            ) {$c};",
            "CREATE TABLE {$t('idempotency')} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                actor_id bigint(20) unsigned NOT NULL,
                route_key varchar(191) NOT NULL,
                idempotency_key varchar(191) NOT NULL,
                request_hash char(64) NOT NULL,
                response_code int unsigned NULL,
                response_json longtext NULL,
                status varchar(24) NOT NULL DEFAULT 'processing',
                created_at datetime NOT NULL,
                expires_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY actor_route_key (actor_id,route_key,idempotency_key),
                KEY expires_at (expires_at)
            ) {$c};",
            "CREATE TABLE {$t('rate_limits')} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                bucket_key char(64) NOT NULL,
                window_start bigint(20) unsigned NOT NULL,
                request_count int unsigned NOT NULL DEFAULT 0,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY bucket_window (bucket_key,window_start)
            ) {$c};",
            "CREATE TABLE {$t('migrations')} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                migration_key varchar(191) NOT NULL,
                migration_version varchar(40) NOT NULL,
                status varchar(24) NOT NULL,
                checkpoint_json longtext NULL,
                dry_run_report longtext NULL,
                started_at datetime NULL,
                completed_at datetime NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY migration_key (migration_key),
                KEY status (status)
            ) {$c};",
        );
    }

    private static function normalizeIndexes(): void
    {
        global $wpdb;
        $jobs = Database::table('jobs');
        $legacy = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=%s AND index_name='dedupe_key'", $jobs));
        if ('' !== (string) $wpdb->last_error) {
            throw new \RuntimeException('CF-06 job index inventory failed.');
        }
        if ((int) $legacy > 0 && false === $wpdb->query("ALTER TABLE {$jobs} DROP INDEX dedupe_key")) {
            throw new \RuntimeException('CF-06 legacy job dedupe index could not be removed.');
        }
        $compound = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=%s AND index_name='job_dedupe'", $jobs));
        if ('' !== (string) $wpdb->last_error || (int) $compound < 2) {
            throw new \RuntimeException('CF-06 compound job dedupe index is unavailable.');
        }
    }

    private static function verifySchema(): void
    {
        global $wpdb;
        $missing = array();
        foreach (Database::ENTITIES as $entity => $suffix) {
            $table = $wpdb->prefix . $suffix;
            $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
            if ($found !== $table) {
                $missing[] = $entity;
            }
        }
        if (! empty($missing)) {
            throw new \RuntimeException('CF-06 schema activation failed: ' . implode(', ', $missing));
        }
        $required = array(
            'integration_evidence'=>array('integration_key','manifest_hash','evidence_hash','environment_name','expires_at'),
            'extraction_evidence'=>array('owner_module','source_commit','inventory_hash','extraction_hash'),
            'qa_evidence'=>array('environment_name','build_sha','test_id','artifact_hash'),
            'release_approvals'=>array('bundle_uuid','approval_role','approver_id','evidence_hash','step_up_at'),
        );
        foreach ($required as $entity => $columns) {
            $table = Database::table($entity);
            $found = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0);
            if (! is_array($found) || '' !== (string) $wpdb->last_error || array_diff($columns, $found)) {
                throw new \RuntimeException('CF-06 required schema columns are unavailable for ' . $entity . '.');
            }
        }
    }

    private static function seedLocales(): void
    {
        global $wpdb;
        $table = Database::table('locales');
        $now = Database::now();
        $seeds = array(
            array('tag' => 'en-US', 'status' => 'enabled', 'fallback' => null, 'surfaces' => array('admin', 'public', 'email')),
            array('tag' => 'ur-PK', 'status' => 'content_ready', 'fallback' => 'en-US', 'surfaces' => array('admin', 'public', 'email')),
            array('tag' => 'ar', 'status' => 'proposed', 'fallback' => 'en-US', 'surfaces' => array()),
        );
        foreach ($seeds as $seed) {
            $parsed = LocaleValidator::parse($seed['tag']);
            if (null === $parsed) {
                continue;
            }
            $written = $wpdb->query($wpdb->prepare(
                "INSERT INTO {$table} (uuid,locale_tag,language_subtag,script_subtag,region_subtag,direction,fallback_tag,plural_rules_version,format_data_version,enabled_surfaces,status,owner,row_version,created_at,updated_at)
                VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,1,%s,%s)
                ON DUPLICATE KEY UPDATE direction=VALUES(direction),fallback_tag=VALUES(fallback_tag),enabled_surfaces=VALUES(enabled_surfaces),updated_at=VALUES(updated_at)",
                Database::uuid(), $parsed['tag'], $parsed['language'], $parsed['script'], $parsed['region'], LocaleValidator::direction($parsed['tag']),
                $seed['fallback'], 'CLDR-49', 'CLDR-49', wp_json_encode($seed['surfaces']), $seed['status'], 'CF-06', $now, $now
            ));
            if (false === $written) {
                throw new \RuntimeException('CF-06 seed locale could not be persisted.');
            }
        }
    }

    private static function grantCapabilities(): void
    {
        $administrator = get_role('administrator');
        if ($administrator) {
            foreach (self::CAPABILITIES as $capability) {
                $administrator->add_cap($capability);
            }
        }
    }

    private static function scheduleJobs(): void
    {
        if (! wp_next_scheduled('slto_process_jobs')) {
            if (false === wp_schedule_event(time() + 60, 'hourly', 'slto_process_jobs')) { throw new \RuntimeException('CF-06 job schedule could not be created.'); }
        }
        if (! wp_next_scheduled('slto_daily_reconciliation')) {
            if (false === wp_schedule_event(time() + 300, 'daily', 'slto_daily_reconciliation')) { throw new \RuntimeException('CF-06 reconciliation schedule could not be created.'); }
        }
    }
}
