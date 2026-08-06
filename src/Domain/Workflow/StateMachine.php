<?php

declare(strict_types=1);

namespace Sabri\Localization\Domain\Workflow;

use InvalidArgumentException;

final class StateMachine
{
    public const MAPS = array(
        'locale' => array(
            'proposed' => array('tested'),
            'tested' => array('content_ready', 'disabled'),
            'content_ready' => array('enabled', 'disabled'),
            'enabled' => array('degraded', 'deprecated', 'disabled'),
            'degraded' => array('enabled', 'deprecated', 'disabled'),
            'deprecated' => array('disabled', 'enabled'),
            'disabled' => array('proposed'),
        ),
        'unit' => array(
            'new' => array('assigned', 'retired'),
            'changed' => array('assigned', 'retired'),
            'assigned' => array('translating', 'retired'),
            'translating' => array('linguistic_review', 'assigned'),
            'linguistic_review' => array('domain_review', 'approved', 'translating'),
            'domain_review' => array('approved', 'translating'),
            'approved' => array('released', 'stale', 'retired'),
            'released' => array('stale', 'retired'),
            'stale' => array('assigned', 'retired'),
            'retired' => array(),
        ),
        'terminology' => array(
            'proposed' => array('domain_review', 'retired'),
            'domain_review' => array('approved', 'proposed', 'retired'),
            'approved' => array('active', 'retired'),
            'active' => array('deprecated', 'replaced', 'retired'),
            'deprecated' => array('replaced', 'retired', 'active'),
            'replaced' => array('retired'),
            'retired' => array(),
        ),
        'bundle' => array(
            'planned' => array('built'),
            'built' => array('automated_qa', 'failed'),
            'automated_qa' => array('in_context_qa', 'failed'),
            'in_context_qa' => array('approved', 'failed'),
            'approved' => array('staged', 'failed'),
            'staged' => array('canary', 'active', 'failed'),
            'canary' => array('active', 'rolled_back', 'failed'),
            'active' => array('rolled_back', 'superseded'),
            'rolled_back' => array('superseded'),
            'superseded' => array(),
            'failed' => array('planned'),
        ),
        'defect' => array(
            'reported' => array('triaged', 'closed'),
            'triaged' => array('reproduced', 'closed'),
            'reproduced' => array('correcting', 'rolled_back'),
            'correcting' => array('reviewed', 'rolled_back'),
            'reviewed' => array('released', 'correcting'),
            'released' => array('closed'),
            'rolled_back' => array('correcting', 'closed'),
            'closed' => array('triaged'),
        ),
        'vendor_job' => array(
            'prepared' => array('sent', 'rejected'),
            'sent' => array('received', 'failed'),
            'received' => array('validated', 'rejected'),
            'validated' => array('human_reviewed', 'rejected'),
            'human_reviewed' => array('accepted', 'rejected'),
            'accepted' => array('purged'),
            'rejected' => array('purged'),
            'failed' => array('prepared', 'purged'),
            'purged' => array(),
        ),
        'project' => array(
            'draft' => array('active', 'cancelled'),
            'active' => array('paused', 'completed', 'cancelled'),
            'paused' => array('active', 'cancelled'),
            'completed' => array('archived'),
            'cancelled' => array('archived'),
            'archived' => array(),
        ),
    );

    public static function can(string $machine, string $from, string $to): bool
    {
        return in_array($to, self::MAPS[$machine][$from] ?? array(), true);
    }

    public static function assert(string $machine, string $from, string $to): void
    {
        if (! self::can($machine, $from, $to)) {
            throw new InvalidArgumentException(sprintf('Invalid %s transition from %s to %s.', $machine, $from, $to));
        }
    }
}
