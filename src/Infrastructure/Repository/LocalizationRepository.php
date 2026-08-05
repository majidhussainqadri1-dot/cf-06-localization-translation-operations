<?php

declare(strict_types=1);

namespace Sabri\Localization\Infrastructure\Repository;

use RuntimeException;
use Sabri\Localization\Infrastructure\Crypto;
use Sabri\Localization\Infrastructure\Database;

final class LocalizationRepository
{
    public function __construct(private readonly Crypto $crypto)
    {
    }

    public function find(string $entity, string $uuid): ?array
    {
        global $wpdb;
        $table = Database::table($entity);
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE uuid = %s LIMIT 1", $uuid), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    public function findOne(string $entity, string $column, string|int $value): ?array
    {
        global $wpdb;
        $allowed = array('locale_tag','resource_key','provider_key','concept_id','id','uuid','project_uuid','unit_uuid','bundle_hash');
        if (! in_array($column, $allowed, true)) {
            throw new \InvalidArgumentException('Unsafe repository lookup column.');
        }
        $table = Database::table($entity);
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE {$column} = %s LIMIT 1", (string) $value), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    public function list(string $entity, array $where = array(), int $limit = 100, int $offset = 0, string $order = 'id DESC'): array
    {
        global $wpdb;
        $table = Database::table($entity);
        $allowedColumns = array('status','locale_tag','target_locale','project_uuid','resource_uuid','unit_uuid','domain_name','provider_key','reporter_id','assignee_id','owner_id','target_type','source_locale','assignment_role','owner_module','publication_status','category','severity','target_uuid');
        $allowedOrders = array('id DESC','id ASC','created_at DESC','updated_at DESC','due_at ASC','bundle_version DESC');
        if (! in_array($order, $allowedOrders, true)) {
            $order = 'id DESC';
        }
        $clauses = array();
        $values = array();
        foreach ($where as $column => $value) {
            if (! in_array((string) $column, $allowedColumns, true)) {
                continue;
            }
            $clauses[] = $column . ' = %s';
            $values[] = (string) $value;
        }
        $sql = "SELECT * FROM {$table}" . (empty($clauses) ? '' : ' WHERE ' . implode(' AND ', $clauses)) . " ORDER BY {$order} LIMIT %d OFFSET %d";
        $values[] = max(1, min(500, $limit));
        $values[] = max(0, $offset);
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$values), ARRAY_A);
        return is_array($rows) ? $rows : array();
    }

    public function count(string $entity, array $where = array(), array $excludeStatuses = array()): int
    {
        global $wpdb;
        $table = Database::table($entity);
        $allowedColumns = array('status','locale_tag','target_locale','project_uuid','resource_uuid','unit_uuid','domain_name','provider_key','reporter_id','assignee_id','owner_id','target_type','source_locale','assignment_role','owner_module','publication_status','category','severity','target_uuid');
        $clauses = array();
        $values = array();
        foreach ($where as $column => $value) {
            if (! in_array((string) $column, $allowedColumns, true)) {
                throw new \InvalidArgumentException('Unsafe repository count column.');
            }
            $clauses[] = $column . ' = %s';
            $values[] = (string) $value;
        }
        if (! empty($excludeStatuses)) {
            $statuses = array_values(array_unique(array_filter(array_map('sanitize_key', $excludeStatuses))));
            if (! empty($statuses)) {
                $clauses[] = 'status NOT IN (' . implode(',', array_fill(0, count($statuses), '%s')) . ')';
                array_push($values, ...$statuses);
            }
        }
        $sql = "SELECT COUNT(*) FROM {$table}" . (empty($clauses) ? '' : ' WHERE ' . implode(' AND ', $clauses));
        return (int) $wpdb->get_var(empty($values) ? $sql : $wpdb->prepare($sql, ...$values));
    }

    public function insert(string $entity, array $data): array
    {
        global $wpdb;
        $table = Database::table($entity);
        $data['uuid'] = $data['uuid'] ?? Database::uuid();
        $data['created_at'] = $data['created_at'] ?? Database::now();
        if ($this->hasColumn($entity, 'updated_at')) {
            $data['updated_at'] = $data['updated_at'] ?? Database::now();
        }
        $ok = $wpdb->insert($table, $data);
        if (false === $ok) {
            throw new RuntimeException('Localization database insert failed for ' . $entity . '.');
        }
        return $this->find($entity, (string) $data['uuid']) ?? $data;
    }

    public function updateVersioned(string $entity, string $uuid, int $expectedVersion, array $changes): array
    {
        global $wpdb;
        $table = Database::table($entity);
        $changes['row_version'] = $expectedVersion + 1;
        if ($this->hasColumn($entity, 'updated_at')) {
            $changes['updated_at'] = Database::now();
        }
        $where = array('uuid' => $uuid, 'row_version' => $expectedVersion);
        $ok = $wpdb->update($table, $changes, $where);
        if (false === $ok) {
            throw new RuntimeException('Localization database update failed for ' . $entity . '.');
        }
        if (0 === $ok) {
            throw new \DomainException('stale_version');
        }
        return $this->find($entity, $uuid) ?? throw new RuntimeException('Updated localization record disappeared.');
    }

    public function storeSecurePayload(string $ownerType, string $ownerUuid, string $purpose, string $plaintext, ?string $expiresAt = null): int
    {
        $envelope = $this->crypto->encrypt($plaintext, $purpose . '|' . $ownerUuid);
        $row = $this->insert('secure_payloads', array(
            'owner_type' => $ownerType, 'owner_uuid' => $ownerUuid, 'purpose' => $purpose,
            'key_id' => $envelope['key_id'], 'algorithm' => $envelope['algorithm'], 'nonce' => $envelope['nonce'],
            'auth_tag' => $envelope['tag'], 'ciphertext' => $envelope['ciphertext'], 'aad_hash' => $envelope['aad_hash'],
            'payload_hash' => hash('sha256', $plaintext), 'expires_at' => $expiresAt,
        ));
        return (int) ($row['id'] ?? 0);
    }

    public function readSecurePayload(int $id, string $ownerUuid, string $purpose): string
    {
        global $wpdb;
        $table = Database::table('secure_payloads');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d AND owner_uuid=%s AND purpose=%s AND deleted_at IS NULL LIMIT 1", $id, $ownerUuid, $purpose), ARRAY_A);
        if (! is_array($row)) {
            throw new RuntimeException('Secure localization payload is unavailable.');
        }
        return $this->crypto->decrypt(array(
            'key_id' => $row['key_id'], 'algorithm' => $row['algorithm'], 'nonce' => $row['nonce'],
            'tag' => $row['auth_tag'], 'ciphertext' => $row['ciphertext'], 'aad_hash' => $row['aad_hash'],
        ), $purpose . '|' . $ownerUuid);
    }


    public function retireSecurePayload(int $id): void
    {
        global $wpdb;
        $table = Database::table('secure_payloads');
        $ok = $wpdb->update($table, array('deleted_at' => Database::now()), array('id' => $id, 'deleted_at' => null));
        if (false === $ok) {
            throw new RuntimeException('Secure localization payload could not be retired.');
        }
    }

    public function findContentLink(string $ownerModule, string $ownerObjectId, string $targetLocale): ?array
    {
        global $wpdb;
        $table = Database::table('content_links');
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE owner_module=%s AND owner_object_id=%s AND target_locale=%s LIMIT 1",
            $ownerModule, $ownerObjectId, $targetLocale
        ), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    public function activeBundle(string $locale): ?array
    {
        global $wpdb;
        $table = Database::table('bundles');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE locale_tag=%s AND status='active' ORDER BY bundle_version DESC LIMIT 1", $locale), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    public function nextBundleVersion(string $locale): int
    {
        global $wpdb;
        $table = Database::table('bundles');
        return 1 + (int) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(MAX(bundle_version),0) FROM {$table} WHERE locale_tag=%s", $locale));
    }

    public function projectResources(string $projectUuid): array
    {
        global $wpdb;
        $pr = Database::table('project_resources');
        $r = Database::table('resources');
        $rows = $wpdb->get_results($wpdb->prepare("SELECT r.*, pr.source_version AS frozen_source_version, pr.source_hash AS frozen_source_hash FROM {$pr} pr JOIN {$r} r ON r.uuid=pr.resource_uuid WHERE pr.project_uuid=%s ORDER BY r.resource_key", $projectUuid), ARRAY_A);
        return is_array($rows) ? $rows : array();
    }

    public function releasedItems(string $locale): array
    {
        global $wpdb;
        $u = Database::table('units');
        $r = Database::table('resources');
        $rows = $wpdb->get_results($wpdb->prepare("SELECT u.*,r.resource_key,r.critical,r.risk_class,r.domain_name,r.source_hash AS current_source_hash,r.source_version AS current_source_version,r.placeholders,r.data_class FROM {$u} u JOIN {$r} r ON r.uuid=u.resource_uuid WHERE u.target_locale=%s AND u.status IN ('approved','released') AND u.source_hash=r.source_hash AND u.source_version=r.source_version ORDER BY r.resource_key", $locale), ARRAY_A);
        return is_array($rows) ? $rows : array();
    }

    public function coverage(string $locale): array
    {
        global $wpdb;
        $r = Database::table('resources');
        $u = Database::table('units');
        $totals = $wpdb->get_row("SELECT COUNT(*) total, SUM(critical=1) critical FROM {$r} WHERE status='active'", ARRAY_A) ?: array('total'=>0,'critical'=>0);
        $translated = $wpdb->get_row($wpdb->prepare("SELECT COUNT(DISTINCT u.resource_uuid) total, SUM(r.critical=1) critical FROM {$u} u JOIN {$r} r ON r.uuid=u.resource_uuid WHERE u.target_locale=%s AND u.status IN ('approved','released') AND u.source_hash=r.source_hash AND u.source_version=r.source_version", $locale), ARRAY_A) ?: array('total'=>0,'critical'=>0);
        $total = max(0, (int) $totals['total']);
        $critical = max(0, (int) $totals['critical']);
        return array(
            'total' => $total,
            'translated' => (int) $translated['total'],
            'coverage' => 0 === $total ? 100.0 : round(((int) $translated['total'] / $total) * 100, 3),
            'critical_total' => $critical,
            'critical_translated' => (int) $translated['critical'],
            'critical_coverage' => 0 === $critical ? 100.0 : round(((int) $translated['critical'] / $critical) * 100, 3),
        );
    }

    public function markDependentUnitsStale(string $resourceUuid, string $reason): int
    {
        global $wpdb;
        $table = Database::table('units');
        return (int) $wpdb->query($wpdb->prepare("UPDATE {$table} SET status='stale',stale_reason=%s,row_version=row_version+1,updated_at=%s WHERE resource_uuid=%s AND status IN ('approved','released','linguistic_review','domain_review')", $reason, Database::now(), $resourceUuid));
    }

    public function storeIdempotency(int $actorId, string $route, string $key, string $requestHash, int $ttl = 86400): array
    {
        global $wpdb;
        $table = Database::table('idempotency');
        $now = Database::now();
        $expires = gmdate('Y-m-d H:i:s', time() + $ttl);
        $inserted = $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$table} (actor_id,route_key,idempotency_key,request_hash,status,created_at,expires_at) VALUES (%d,%s,%s,%s,'processing',%s,%s)", $actorId, $route, $key, $requestHash, $now, $expires));
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE actor_id=%d AND route_key=%s AND idempotency_key=%s", $actorId, $route, $key), ARRAY_A);
        if (! is_array($row)) {
            throw new RuntimeException('Idempotency state could not be established.');
        }
        if (0 === $inserted && ! hash_equals((string) $row['request_hash'], $requestHash)) {
            throw new \DomainException('idempotency_conflict');
        }
        if (0 === $inserted && 'failed' === (string) $row['status']) {
            $reopened = $wpdb->query($wpdb->prepare("UPDATE {$table} SET status='processing',response_code=NULL,response_json=NULL,expires_at=%s WHERE actor_id=%d AND route_key=%s AND idempotency_key=%s AND status='failed'", $expires, $actorId, $route, $key));
            if (1 === $reopened) {
                $row['status'] = 'processing';
                return array('new' => true, 'record' => $row);
            }
        }
        return array('new' => 1 === $inserted, 'record' => $row);
    }

    public function completeIdempotency(int $actorId, string $route, string $key, int $code, array $response): void
    {
        global $wpdb;
        $table = Database::table('idempotency');
        $ok=$wpdb->update($table, array('response_code'=>$code,'response_json'=>wp_json_encode($response),'status'=>'completed'), array('actor_id'=>$actorId,'route_key'=>$route,'idempotency_key'=>$key,'status'=>'processing'));if(false===$ok||0===$ok){throw new RuntimeException('Idempotency completion state could not be persisted.');}
    }

    public function failIdempotency(int $actorId, string $route, string $key, string $errorCode): void
    {
        global $wpdb;
        $table = Database::table('idempotency');
        $wpdb->update($table, array('response_code'=>null,'response_json'=>wp_json_encode(array('error_code'=>$errorCode)),'status'=>'failed'), array('actor_id'=>$actorId,'route_key'=>$route,'idempotency_key'=>$key,'status'=>'processing'));
    }

    public function cleanupOperationalState(): array
    {
        global $wpdb;
        $now = Database::now();
        $idempotency = (int) $wpdb->query($wpdb->prepare('DELETE FROM ' . Database::table('idempotency') . ' WHERE expires_at < %s', $now));
        $rateWindow = time() - 172800;
        $rateLimits = (int) $wpdb->query($wpdb->prepare('DELETE FROM ' . Database::table('rate_limits') . ' WHERE window_start < %d', $rateWindow));
        $secure = (int) $wpdb->query($wpdb->prepare('UPDATE ' . Database::table('secure_payloads') . ' SET deleted_at=%s WHERE expires_at IS NOT NULL AND expires_at < %s AND deleted_at IS NULL', $now, $now));
        return array('idempotency'=>$idempotency,'rate_limits'=>$rateLimits,'secure_payloads'=>$secure);
    }

    public function rateLimit(string $bucket, int $limit, int $windowSeconds): bool
    {
        global $wpdb;
        $table = Database::table('rate_limits');
        $window = intdiv(time(), $windowSeconds) * $windowSeconds;
        $key = hash('sha256', $bucket);
        $wpdb->query($wpdb->prepare("INSERT INTO {$table} (bucket_key,window_start,request_count,updated_at) VALUES (%s,%d,1,%s) ON DUPLICATE KEY UPDATE request_count=request_count+1,updated_at=VALUES(updated_at)", $key, $window, Database::now()));
        $count = (int) $wpdb->get_var($wpdb->prepare("SELECT request_count FROM {$table} WHERE bucket_key=%s AND window_start=%d", $key, $window));
        return $count <= $limit;
    }

    private function hasColumn(string $entity, string $column): bool
    {
        return ! in_array($entity, array('project_resources','secure_payloads','audit','outbox','qa_results','idempotency','rate_limits'), true) || 'updated_at' !== $column;
    }
}
