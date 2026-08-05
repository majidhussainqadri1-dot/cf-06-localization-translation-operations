<?php

declare(strict_types=1);

namespace Sabri\Localization\Application;

use InvalidArgumentException;
use Sabri\Localization\Domain\Locale\LocaleValidator;
use Sabri\Localization\Infrastructure\Repository\AuditRepository;
use Sabri\Localization\Infrastructure\Repository\LocalizationRepository;

final class ContentLinkService
{
    public function __construct(private readonly LocalizationRepository $repo, private readonly AuditRepository $audit)
    {
    }

    public function register(array $input): array
    {
        $owner = sanitize_key((string) ($input['owner_module'] ?? ''));
        $object = sanitize_text_field((string) ($input['owner_object_id'] ?? ''));
        $source = LocaleValidator::canonicalize((string) ($input['source_locale'] ?? ''));
        $target = LocaleValidator::canonicalize((string) ($input['target_locale'] ?? ''));
        $hash = (string) ($input['source_hash'] ?? '');
        if ('' === $owner || '' === $object || null === $source || null === $target || $source === $target || 1 !== preg_match('/^[a-f0-9]{64}$/D', $hash)) {
            throw new InvalidArgumentException('Content translation relationship is invalid.');
        }

        $urls = array();
        foreach (array('translated_url', 'canonical_url') as $field) {
            $url = (string) ($input[$field] ?? '');
            if ('' !== $url) {
                $this->assertPlatformUrl($url);
                $urls[$field] = esc_url_raw($url);
            } else {
                $urls[$field] = null;
            }
        }
        $status = sanitize_key((string) ($input['publication_status'] ?? 'draft'));
        if (! in_array($status, array('draft', 'review', 'approved', 'published', 'stale', 'retracted', 'retired'), true)) {
            throw new InvalidArgumentException('Content translation publication status is invalid.');
        }
        if ('published' === $status) {
            $approvalRef = sanitize_text_field((string) ($input['owner_approval_ref'] ?? ''));
            if ('' === $approvalRef) {
                throw new InvalidArgumentException('Native owner approval evidence is required before publication.');
            }
            $approved = apply_filters('slto_validate_owner_approval', false, $owner, $object, $target, $approvalRef, $input);
            if (true !== $approved) {
                throw new InvalidArgumentException('Native owner approval evidence could not be verified.');
            }
        }

        $data = array(
            'source_version' => sanitize_text_field((string) ($input['source_version'] ?? '')),
            'source_hash' => $hash,
            'resource_uuid' => (string) ($input['resource_uuid'] ?? '') ?: null,
            'unit_uuid' => (string) ($input['unit_uuid'] ?? '') ?: null,
            'translated_url' => $urls['translated_url'],
            'canonical_url' => $urls['canonical_url'],
            'hreflang_code' => $target,
            'publication_status' => $status,
            'owner_approval_ref' => sanitize_text_field((string) ($input['owner_approval_ref'] ?? '')) ?: null,
        );
        $existing = $this->repo->findContentLink($owner, $object, $target);
        if (is_array($existing)) {
            $row = $this->repo->updateVersioned('content_links', (string) $existing['uuid'], (int) ($input['row_version'] ?? $existing['row_version']), $data);
        } else {
            $row = $this->repo->insert('content_links', array_merge($data, array(
                'owner_module' => $owner,
                'owner_object_id' => $object,
                'source_locale' => $source,
                'target_locale' => $target,
                'row_version' => 1,
            )));
        }
        $this->audit->record('content_link', (string) $row['uuid'], 'content_translation_link_registered', 'success', array(
            'owner_module' => $owner,
            'target_locale' => $target,
            'source_hash' => $hash,
            'publication_status' => $status,
        ));
        return $row;
    }

    private function assertPlatformUrl(string $url): void
    {
        if (! wp_http_validate_url($url)) {
            throw new InvalidArgumentException('Content translation URL is invalid.');
        }
        $targetHost = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        $homeHost = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
        if ('' === $targetHost || '' === $homeHost || ! hash_equals($homeHost, $targetHost)) {
            throw new InvalidArgumentException('Content translation URL must remain on the canonical platform host.');
        }
    }
}
