<?php

declare(strict_types=1);

namespace Sabri\Localization\Application;

use RuntimeException;
use Sabri\Localization\Infrastructure\Database;
use Sabri\Localization\Infrastructure\JobQueue;
use Sabri\Localization\Infrastructure\Repository\AuditRepository;
use Sabri\Localization\Infrastructure\Transaction;

final class PrivacyService
{
    public function __construct(private readonly JobQueue $jobs, private readonly AuditRepository $audit, private readonly Transaction $tx)
    {
    }

    public function exportUser(int $userId, int $page = 1, int $perPage = 100): array
    {
        global $wpdb;
        $page = max(1, $page);
        $perPage = max(20, min(200, $perPage));
        $offset = ($page - 1) * $perPage;
        $assignments = $wpdb->get_results($wpdb->prepare(
            'SELECT uuid,project_uuid,unit_uuid,assignment_role,locale_tag,status,due_at,expires_at,created_at,updated_at FROM ' . Database::table('assignments') . ' WHERE assignee_id=%d ORDER BY id ASC LIMIT %d OFFSET %d',
            $userId, $perPage, $offset
        ), ARRAY_A) ?: array();
        $comments = $wpdb->get_results($wpdb->prepare(
            'SELECT uuid,unit_uuid,audience,status,created_at,updated_at FROM ' . Database::table('comments') . ' WHERE author_id=%d ORDER BY id ASC LIMIT %d OFFSET %d',
            $userId, $perPage, $offset
        ), ARRAY_A) ?: array();
        $feedback = $wpdb->get_results($wpdb->prepare(
            'SELECT uuid,locale_tag,resource_key,route_path,category,severity,status,outcome_text,created_at,updated_at FROM ' . Database::table('feedback') . ' WHERE reporter_id=%d ORDER BY id ASC LIMIT %d OFFSET %d',
            $userId, $perPage, $offset
        ), ARRAY_A) ?: array();
        return array(
            'data' => array('assignments'=>$assignments,'comments_metadata'=>$comments,'feedback'=>$feedback),
            'done' => count($assignments) < $perPage && count($comments) < $perPage && count($feedback) < $perPage,
        );
    }

    public function requestErasure(int $userId, string $reason = 'user_request'): string
    {
        $key = 'privacy-erasure-' . $userId . '-' . hash('sha256', $reason);
        $uuid = $this->jobs->enqueue('privacy_erasure', $key, array('user_id'=>$userId,'reason'=>$reason));
        $this->audit->record('privacy', (string) $userId, 'privacy_erasure_queued', 'success', array('reason'=>$reason,'job_uuid'=>$uuid), 'privacy');
        return $uuid;
    }

    public function processErasure(array $payload): void
    {
        global $wpdb;
        $userId = (int) ($payload['user_id'] ?? 0);
        if ($userId <= 0) {
            return;
        }
        $this->tx->run(function () use ($wpdb, $userId): void {
            $operations = array(
                $wpdb->update(Database::table('comments'), array('comment_text'=>'[ERASED]','author_id'=>0), array('author_id'=>$userId)),
                $wpdb->update(Database::table('feedback'), array('suggestion_text'=>'[ERASED]','reporter_id'=>0), array('reporter_id'=>$userId)),
                $wpdb->update(Database::table('assignments'), array('status'=>'revoked','assignee_id'=>0,'updated_at'=>Database::now()), array('assignee_id'=>$userId)),
            );
            if (in_array(false, $operations, true)) {
                throw new RuntimeException('Localization privacy erasure could not be completed atomically.');
            }
            $this->audit->record('privacy', (string) $userId, 'privacy_erasure_completed', 'success', array(), 'privacy');
        });
    }
}
