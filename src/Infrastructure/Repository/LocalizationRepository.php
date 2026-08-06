<?php

declare(strict_types=1);

namespace Sabri\Localization\Infrastructure\Repository;

use InvalidArgumentException;
use RuntimeException;
use Sabri\Localization\Infrastructure\Crypto;
use Sabri\Localization\Infrastructure\Database;

final class LocalizationRepository
{
    private const FILTER_COLUMNS = array(
        'status','locale_tag','target_locale','project_uuid','resource_uuid','unit_uuid','domain_name','provider_key',
        'reporter_id','assignee_id','owner_id','target_type','source_locale','assignment_role','owner_module',
        'publication_status','category','severity','target_uuid','integration_key','environment_name','bundle_uuid','approval_role',
    );

    public function __construct(private readonly Crypto $crypto) {}

    public function find(string $entity, string $uuid): ?array
    {
        global $wpdb;
        $table = Database::table($entity);
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE uuid=%s LIMIT 1", $uuid), ARRAY_A);
        $this->assertReadSucceeded('find ' . $entity);
        return is_array($row) ? $row : null;
    }

    public function findOne(string $entity, string $column, string|int $value): ?array
    {
        global $wpdb;
        $allowed = array_merge(self::FILTER_COLUMNS, array('resource_key','concept_id','id','uuid','bundle_hash','evidence_hash','inventory_hash'));
        if (! in_array($column, $allowed, true)) {
            throw new InvalidArgumentException('Unsafe repository lookup column.');
        }
        $table = Database::table($entity);
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE {$column}=%s LIMIT 1", (string) $value), ARRAY_A);
        $this->assertReadSucceeded('findOne ' . $entity);
        return is_array($row) ? $row : null;
    }

    public function list(string $entity, array $where = array(), int $limit = 100, int $offset = 0, string $order = 'id DESC'): array
    {
        global $wpdb;
        $table = Database::table($entity);
        $allowedOrders = array('id DESC','id ASC','created_at DESC','updated_at DESC','due_at ASC','bundle_version DESC','approved_at DESC');
        if (! in_array($order, $allowedOrders, true)) {
            $order = 'id DESC';
        }
        $clauses = array();
        $values = array();
        foreach ($where as $column => $value) {
            if (! in_array((string) $column, self::FILTER_COLUMNS, true)) {
                throw new InvalidArgumentException('Unsafe repository list column.');
            }
            $clauses[] = $column . '=%s';
            $values[] = (string) $value;
        }
        $sql = "SELECT * FROM {$table}" . (empty($clauses) ? '' : ' WHERE ' . implode(' AND ', $clauses)) . " ORDER BY {$order} LIMIT %d OFFSET %d";
        $values[] = max(1, min(500, $limit));
        $values[] = max(0, $offset);
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$values), ARRAY_A);
        $this->assertReadSucceeded('list ' . $entity);
        return is_array($rows) ? $rows : array();
    }

    public function count(string $entity, array $where = array(), array $excludeStatuses = array()): int
    {
        global $wpdb;
        $table = Database::table($entity);
        $clauses = array();
        $values = array();
        foreach ($where as $column => $value) {
            if (! in_array((string) $column, self::FILTER_COLUMNS, true)) {
                throw new InvalidArgumentException('Unsafe repository count column.');
            }
            $clauses[] = $column . '=%s';
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
        $value = $wpdb->get_var(empty($values) ? $sql : $wpdb->prepare($sql, ...$values));
        $this->assertReadSucceeded('count ' . $entity);
        return (int) $value;
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
        if (false === $wpdb->insert($table, $data)) {
            throw new RuntimeException('Localization database insert failed for ' . $entity . '.');
        }
        return $this->find($entity, (string) $data['uuid']) ?? $data;
    }

    public function updateVersioned(string $entity, string $uuid, int $expectedVersion, array $changes): array
    {
        global $wpdb;
        if ($expectedVersion <= 0) {
            throw new InvalidArgumentException('A positive record version is required.');
        }
        $table = Database::table($entity);
        $changes['row_version'] = $expectedVersion + 1;
        if ($this->hasColumn($entity, 'updated_at')) {
            $changes['updated_at'] = Database::now();
        }
        $ok = $wpdb->update($table, $changes, array('uuid'=>$uuid,'row_version'=>$expectedVersion));
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
            'owner_type'=>$ownerType,'owner_uuid'=>$ownerUuid,'purpose'=>$purpose,
            'key_id'=>$envelope['key_id'],'algorithm'=>$envelope['algorithm'],'nonce'=>$envelope['nonce'],
            'auth_tag'=>$envelope['tag'],'ciphertext'=>$envelope['ciphertext'],'aad_hash'=>$envelope['aad_hash'],
            'payload_hash'=>hash('sha256',$plaintext),'expires_at'=>$expiresAt,
        ));
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('Secure localization payload identity is unavailable.');
        }
        return $id;
    }

    public function readSecurePayload(int $id, string $ownerUuid, string $purpose): string
    {
        global $wpdb;
        $table = Database::table('secure_payloads');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d AND owner_uuid=%s AND purpose=%s AND deleted_at IS NULL LIMIT 1", $id, $ownerUuid, $purpose), ARRAY_A);
        $this->assertReadSucceeded('secure payload');
        if (! is_array($row)) {
            throw new RuntimeException('Secure localization payload is unavailable.');
        }
        return $this->crypto->decrypt(array(
            'key_id'=>$row['key_id'],'algorithm'=>$row['algorithm'],'nonce'=>$row['nonce'],
            'tag'=>$row['auth_tag'],'ciphertext'=>$row['ciphertext'],'aad_hash'=>$row['aad_hash'],
        ), $purpose . '|' . $ownerUuid);
    }

    public function retireSecurePayload(int $id): void
    {
        global $wpdb;
        $ok = $wpdb->query($wpdb->prepare('UPDATE ' . Database::table('secure_payloads') . ' SET deleted_at=%s WHERE id=%d AND deleted_at IS NULL', Database::now(), $id));
        if (false === $ok) {
            throw new RuntimeException('Secure localization payload could not be retired.');
        }
    }

    public function findContentLink(string $ownerModule, string $ownerObjectId, string $targetLocale): ?array
    {
        global $wpdb;
        $table = Database::table('content_links');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE owner_module=%s AND owner_object_id=%s AND target_locale=%s LIMIT 1", $ownerModule, $ownerObjectId, $targetLocale), ARRAY_A);
        $this->assertReadSucceeded('content link');
        return is_array($row) ? $row : null;
    }

    public function activeBundle(string $locale): ?array
    {
        global $wpdb;
        $table = Database::table('bundles');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE locale_tag=%s AND status='active' ORDER BY bundle_version DESC LIMIT 1", $locale), ARRAY_A);
        $this->assertReadSucceeded('active bundle');
        return is_array($row) ? $row : null;
    }

    public function nextBundleVersion(string $locale): int
    {
        global $wpdb;
        $table = Database::table('bundles');
        $value = $wpdb->get_var($wpdb->prepare("SELECT COALESCE(MAX(bundle_version),0) FROM {$table} WHERE locale_tag=%s", $locale));
        $this->assertReadSucceeded('bundle version');
        return 1 + (int) $value;
    }

    public function projectResources(string $projectUuid): array
    {
        global $wpdb;
        $pr = Database::table('project_resources');
        $r = Database::table('resources');
        $rows = $wpdb->get_results($wpdb->prepare("SELECT r.*,pr.source_version frozen_source_version,pr.source_hash frozen_source_hash FROM {$pr} pr JOIN {$r} r ON r.uuid=pr.resource_uuid WHERE pr.project_uuid=%s ORDER BY r.resource_key", $projectUuid), ARRAY_A);
        $this->assertReadSucceeded('project resources');
        return is_array($rows) ? $rows : array();
    }

    public function releasedItems(string $locale): array
    {
        global $wpdb;
        $u = Database::table('units');
        $r = Database::table('resources');
        $rows = $wpdb->get_results($wpdb->prepare("SELECT u.*,r.resource_key,r.critical,r.risk_class,r.domain_name,r.source_hash current_source_hash,r.source_version current_source_version,r.placeholders,r.data_class FROM {$u} u JOIN {$r} r ON r.uuid=u.resource_uuid WHERE u.target_locale=%s AND u.status IN ('approved','released') AND u.source_hash=r.source_hash AND u.source_version=r.source_version ORDER BY r.resource_key", $locale), ARRAY_A);
        $this->assertReadSucceeded('released items');
        return is_array($rows) ? $rows : array();
    }

    public function coverage(string $locale): array
    {
        global $wpdb;
        $r = Database::table('resources');
        $u = Database::table('units');
        $totals = $wpdb->get_row("SELECT COUNT(*) total,COALESCE(SUM(critical=1),0) critical FROM {$r} WHERE status='active'", ARRAY_A);
        $this->assertReadSucceeded('coverage totals');
        $translated = $wpdb->get_row($wpdb->prepare("SELECT COUNT(DISTINCT u.resource_uuid) total,COALESCE(SUM(r.critical=1),0) critical FROM {$u} u JOIN {$r} r ON r.uuid=u.resource_uuid WHERE u.target_locale=%s AND u.status IN ('approved','released') AND u.source_hash=r.source_hash AND u.source_version=r.source_version", $locale), ARRAY_A);
        $this->assertReadSucceeded('coverage translations');
        if (! is_array($totals) || ! is_array($translated)) {
            throw new RuntimeException('Localization coverage could not be calculated.');
        }
        $total = max(0, (int) $totals['total']);
        $critical = max(0, (int) $totals['critical']);
        return array(
            'total'=>$total,'translated'=>(int)$translated['total'],
            'coverage'=>0===$total?100.0:round(((int)$translated['total']/$total)*100,3),
            'critical_total'=>$critical,'critical_translated'=>(int)$translated['critical'],
            'critical_coverage'=>0===$critical?100.0:round(((int)$translated['critical']/$critical)*100,3),
        );
    }

    public function markDependentUnitsStale(string $resourceUuid, string $reason): int
    {
        global $wpdb;
        $table = Database::table('units');
        $result = $wpdb->query($wpdb->prepare("UPDATE {$table} SET status='stale',stale_reason=%s,row_version=row_version+1,updated_at=%s WHERE resource_uuid=%s AND status NOT IN ('new','retired','stale')", $reason, Database::now(), $resourceUuid));
        if (false === $result) {
            throw new RuntimeException('Dependent translations could not be marked stale.');
        }
        return (int) $result;
    }

    public function storeIdempotency(int $actorId, string $route, string $key, string $requestHash, int $ttl = 86400): array
    {
        global $wpdb;
        $table = Database::table('idempotency');
        $expires = gmdate('Y-m-d H:i:s', time() + max(60, min(604800, $ttl)));
        $inserted = $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$table} (actor_id,route_key,idempotency_key,request_hash,status,created_at,expires_at) VALUES (%d,%s,%s,%s,'processing',%s,%s)", $actorId, $route, $key, $requestHash, Database::now(), $expires));
        if (false === $inserted) {
            throw new RuntimeException('Idempotency state could not be written.');
        }
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE actor_id=%d AND route_key=%s AND idempotency_key=%s", $actorId, $route, $key), ARRAY_A);
        $this->assertReadSucceeded('idempotency state');
        if (! is_array($row)) {
            throw new RuntimeException('Idempotency state could not be established.');
        }
        if (0 === $inserted && ! hash_equals((string)$row['request_hash'], $requestHash)) {
            throw new \DomainException('idempotency_conflict');
        }
        if (0 === $inserted && 'failed' === (string)$row['status']) {
            $reopened = $wpdb->query($wpdb->prepare("UPDATE {$table} SET status='processing',response_code=NULL,response_json=NULL,expires_at=%s WHERE actor_id=%d AND route_key=%s AND idempotency_key=%s AND status='failed'", $expires, $actorId, $route, $key));
            if (false === $reopened) {
                throw new RuntimeException('Failed idempotency request could not be reopened.');
            }
            if (1 === $reopened) {
                $row['status'] = 'processing';
                return array('new'=>true,'record'=>$row);
            }
        }
        return array('new'=>1===$inserted,'record'=>$row);
    }

    public function completeIdempotency(int $actorId, string $route, string $key, int $code, array $response): void
    {
        global $wpdb;
        $json = wp_json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (! is_string($json)) {
            throw new RuntimeException('Idempotency response could not be encoded.');
        }
        $ok = $wpdb->update(Database::table('idempotency'), array('response_code'=>$code,'response_json'=>$json,'status'=>'completed'), array('actor_id'=>$actorId,'route_key'=>$route,'idempotency_key'=>$key,'status'=>'processing'));
        if (false === $ok || 0 === $ok) {
            throw new RuntimeException('Idempotency completion state could not be persisted.');
        }
    }

    public function failIdempotency(int $actorId, string $route, string $key, string $errorCode): void
    {
        global $wpdb;
        $json = wp_json_encode(array('error_code'=>$errorCode));
        $ok = $wpdb->update(Database::table('idempotency'), array('response_code'=>null,'response_json'=>$json,'status'=>'failed'), array('actor_id'=>$actorId,'route_key'=>$route,'idempotency_key'=>$key,'status'=>'processing'));
        if (false === $ok) {
            throw new RuntimeException('Idempotency failure state could not be persisted.');
        }
    }

    public function cleanupOperationalState(): array
    {
        global $wpdb;
        $now = Database::now();
        $operations = array(
            'idempotency'=>$wpdb->query($wpdb->prepare('DELETE FROM '.Database::table('idempotency').' WHERE expires_at<%s',$now)),
            'rate_limits'=>$wpdb->query($wpdb->prepare('DELETE FROM '.Database::table('rate_limits').' WHERE window_start<%d',time()-172800)),
            'secure_payloads'=>$wpdb->query($wpdb->prepare('UPDATE '.Database::table('secure_payloads').' SET deleted_at=%s WHERE expires_at IS NOT NULL AND expires_at<%s AND deleted_at IS NULL',$now,$now)),
        );
        if (in_array(false, $operations, true)) {
            throw new RuntimeException('Localization operational cleanup failed.');
        }
        return array_map('intval', $operations);
    }

    public function rateLimit(string $bucket, int $limit, int $windowSeconds): bool
    {
        global $wpdb;
        $limit = max(1, min(10000, $limit));
        $windowSeconds = max(1, min(86400, $windowSeconds));
        $window = intdiv(time(), $windowSeconds) * $windowSeconds;
        $key = hash('sha256', $bucket);
        $written = $wpdb->query($wpdb->prepare("INSERT INTO ".Database::table('rate_limits')." (bucket_key,window_start,request_count,updated_at) VALUES (%s,%d,1,%s) ON DUPLICATE KEY UPDATE request_count=request_count+1,updated_at=VALUES(updated_at)", $key, $window, Database::now()));
        if (false === $written) {
            return false;
        }
        $count = $wpdb->get_var($wpdb->prepare("SELECT request_count FROM ".Database::table('rate_limits')." WHERE bucket_key=%s AND window_start=%d", $key, $window));
        if (null === $count || '' !== (string) $wpdb->last_error) {
            return false;
        }
        return (int) $count <= $limit;
    }

    private function hasColumn(string $entity, string $column): bool
    {
        return ! in_array($entity, array('project_resources','secure_payloads','audit','outbox','qa_results','idempotency','rate_limits','migrations','qa_evidence'), true) || 'updated_at' !== $column;
    }

    private function assertReadSucceeded(string $operation): void
    {
        global $wpdb;
        if ('' !== (string) $wpdb->last_error) {
            throw new RuntimeException('Localization database read failed: ' . $operation . '.');
        }
    }
}
