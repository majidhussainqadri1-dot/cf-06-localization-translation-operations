<?php

declare(strict_types=1);

namespace Sabri\Localization\Application;

use DomainException;
use InvalidArgumentException;
use RuntimeException;
use Sabri\Localization\Domain\Locale\PublicEditionPolicy;
use Sabri\Localization\Infrastructure\Database;
use Sabri\Localization\Infrastructure\Repository\AuditRepository;
use Sabri\Localization\Infrastructure\Transaction;
use Sabri\Localization\Security\Authorization;

/**
 * Immutable Founder-approved V12.3 public-edition policy revisions.
 * Uses WordPress core options with transactional inserts; no plugin schema
 * upgrade and no automatically selected/activated language policy.
 */
final class EditionPolicyRegistry
{
    private const PREFIX = 'slto_v123_edition_';
    private const MAX_JSON = 16384;

    public function __construct(
        private readonly AuditRepository $audit,
        private readonly Transaction $tx
    ) {
    }

    public function register(array $candidate): array
    {
        if (!Authorization::allowed('release')) {
            throw new InvalidArgumentException('Edition policy registration requires authorized release authority and recent authentication.');
        }
        $policy = PublicEditionPolicy::validate($candidate);
        $identity = $this->approvalIdentity($policy);
        if (true !== apply_filters('slto_verify_founder_edition_policy', false, $identity)) {
            throw new InvalidArgumentException('Independent Founder edition policy approval is not verified.');
        }
        $policyJson = wp_json_encode($policy, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($policyJson) || strlen($policyJson) > self::MAX_JSON) {
            throw new InvalidArgumentException('Edition policy record is invalid or exceeds storage limits.');
        }
        $hash = hash('sha256', $policyJson);
        $name = self::optionName($policy['policy_id'], $policy['revision']);
        $record = [
            'policy_id' => $policy['policy_id'],
            'revision' => $policy['revision'],
            'policy_hash' => $hash,
            'policy' => $policy,
            'registered_at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
            'status' => 'registered',
            'activation_authorized' => false,
            'automatic_publication_allowed' => false,
        ];
        $recordJson = wp_json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($recordJson) || strlen($recordJson) > self::MAX_JSON) {
            throw new InvalidArgumentException('Edition registry evidence is invalid or oversized.');
        }

        global $wpdb;
        $result = $this->tx->run(function () use ($wpdb, $name, $record, $recordJson, $hash): array {
            // Both WordPress options and our audit ledger must be transactional.
            foreach ([$wpdb->options, Database::table('audit')] as $tableName) {
                $status = $wpdb->get_row(
                    $wpdb->prepare('SHOW TABLE STATUS LIKE %s', $wpdb->esc_like($tableName)), ARRAY_A
                );
                if ('' !== (string)$wpdb->last_error || !is_array($status)
                    || 'innodb' !== strtolower((string)($status['Engine'] ?? ''))) {
                    throw new RuntimeException('Immutable edition policy requires InnoDB options and audit tables.');
                }
            }
            // option_name has a unique key; concurrent registration is atomic.
            $written = $wpdb->query($wpdb->prepare(
                "INSERT INTO {$wpdb->options} (option_name,option_value,autoload)
                 VALUES (%s,%s,'no')
                 ON DUPLICATE KEY UPDATE option_name=option_name",
                $name, $recordJson
            ));
            if (false === $written || '' !== (string)$wpdb->last_error) {
                throw new RuntimeException('Edition policy immutable storage is unavailable.');
            }
            if (0 === (int)$written) {
                $previous = $this->readRecordByName($name);
                if (!is_array($previous) || !hash_equals((string)$previous['policy_hash'], $hash)) {
                    throw new DomainException('edition_policy_revision_conflict');
                }
                return ['created'=>false,'record'=>$previous];
            }
            if (1 !== (int)$written) {
                throw new RuntimeException('Edition policy insert did not have a known atomic result.');
            }
            $this->audit->record(
                'edition_policy', $name, 'founder_edition_policy_registered', 'success',
                ['policy_id'=>$record['policy_id'],'revision'=>$record['revision'],'hash'=>$hash,'activation_authorized'=>false]
            );
            return ['created'=>true,'record'=>$record];
        });
        return $result;
    }

    public function get(string $policyId, string $revision): ?array
    {
        if (!Authorization::allowed('audit') && !Authorization::allowed('release')) {
            throw new InvalidArgumentException('Edition policy retrieval requires privileged access.');
        }
        return $this->readRecordByName(self::optionName($policyId, $revision));
    }

    private function readRecordByName(string $name): ?array
    {
        global $wpdb;
        $json = $wpdb->get_var(
            $wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s LIMIT 1", $name)
        );
        if ('' !== (string)$wpdb->last_error) {
            throw new RuntimeException('Edition policy record cannot be read.');
        }
        if (null === $json) {
            return null;
        }
        if (!is_string($json) || strlen($json) > self::MAX_JSON) {
            throw new RuntimeException('Edition policy record is malformed.');
        }
        $record = json_decode($json, true);
        if (!is_array($record) || !is_array($record['policy'] ?? null)
            || !is_string($record['policy_hash'] ?? null)
            || 'registered' !== ($record['status'] ?? null)
            || false !== ($record['automatic_publication_allowed'] ?? null)
            || false !== ($record['activation_authorized'] ?? null)) {
            throw new RuntimeException('Edition policy integrity check failed.');
        }
        try {
            $policy = PublicEditionPolicy::validate($record['policy']);
        } catch (InvalidArgumentException $e) {
            throw new RuntimeException('Stored edition policy validation failed.', 0, $e);
        }
        if ($name !== self::optionName($policy['policy_id'], $policy['revision'])) {
            throw new RuntimeException('Stored edition policy identity mismatch.');
        }
        $canonical = wp_json_encode($policy, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($canonical) || !hash_equals((string)$record['policy_hash'], hash('sha256', $canonical))) {
            throw new RuntimeException('Stored edition policy content hash mismatch.');
        }
        return $record;
    }

    private function approvalIdentity(array $policy): array
    {
        return [
            'policy_id'=>$policy['policy_id'],
            'revision'=>$policy['revision'],
            'founder_approval_ref'=>$policy['founder_approval_ref'],
            'effective_date'=>$policy['effective_date'],
            'target_locales'=>$policy['target_locales'],
            'reserve_locales'=>$policy['reserve_locales'],
            'aliases'=>$policy['aliases'],
        ];
    }

    private static function optionName(string $id, string $revision): string
    {
        foreach ([$id, $revision] as $value) {
            if ('' === $value || strlen($value) > 80
                || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/D', $value)) {
                throw new InvalidArgumentException('Edition registry policy identity is invalid.');
            }
        }
        return self::PREFIX . hash('sha256', $id . "\0" . $revision);
    }
}
