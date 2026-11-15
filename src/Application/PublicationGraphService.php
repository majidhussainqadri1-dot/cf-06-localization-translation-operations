<?php

declare(strict_types=1);

namespace Sabri\Localization\Application;

use Sabri\Localization\Domain\Locale\PublicEditionGraphAuditor;
use InvalidArgumentException;

/** Read-only bridge to independent native owner and File 26 attestors. */
final class PublicationGraphService
{
    public function audit(array $source, array $translations): array
    {
        $fields = [
            'locale'=>true, 'url'=>true, 'canonical_url'=>true,
            'source_version'=>true, 'source_hash'=>true,
            'publication_status'=>true, 'public'=>true, 'hreflang'=>true,
            'owner_module'=>true, 'owner_object_id'=>true, 'owner_approval_ref'=>true,
        ];
        $original = array_intersect_key($source, $fields);
        if (count($translations) > 24) { throw new InvalidArgumentException('Publication graph evidence exceeds bounded limit.'); }
        foreach ($translations as $row) { if (!is_array($row)) { throw new InvalidArgumentException('Publication graph evidence row must be structured.'); } }
        $targets = array_map(static fn (array $r): array => array_intersect_key($r, $fields), $translations);
        return PublicEditionGraphAuditor::audit(
            $original, $targets, home_url('/'),
            static fn (array $r): bool =>
                true === apply_filters('slto_verify_graph_original_owner', false, $r),
            static fn (array $r): bool =>
                true === apply_filters('slto_verify_graph_translation_owner', false, $r),
            static fn (array $approved, array $hreflang): bool =>
                true === apply_filters('slto_verify_file26_search_graph', false, $approved, $hreflang)
        );
    }
}
