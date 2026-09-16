<?php

declare(strict_types=1);

namespace Sabri\Localization\Application;

use Sabri\Localization\Domain\Future\FutureCapabilityGuard;
use Sabri\Localization\Domain\Future\LocaleAccessibilityGuard;
use Sabri\Localization\Domain\Future\ProviderEligibilityGuard;
use Sabri\Localization\Domain\Future\SemanticIntegrityGuard;

/**
 * Canonical exposed Future40 service. The underlying handler collection stays
 * pure; this facade enforces cross-capability validation before any handler runs.
 */
final class FutureCapabilitiesFacade
{
    public function __construct(private readonly FutureCapabilitiesService $handlers = new FutureCapabilitiesService()) {}

    public function catalogue(): array
    {
        return $this->handlers->catalogue();
    }

    public function evaluate(string $id, array $input): array
    {
        $id = strtoupper(trim($id));
        $input = FutureCapabilityGuard::normalize($id, $input);
        $input = LocaleAccessibilityGuard::normalize($id, $input);
        if ('CF06-FUT-033' === $id) {
            $input = ProviderEligibilityGuard::normalize($input);
        }
        $out = $this->handlers->evaluate($id, $input);
        if ('CF06-FUT-018' === $id && isset($out['result']['fallback_chain']) && is_array($out['result']['fallback_chain'])) {
            $out['result']['fallback_chain'] = array_values(array_unique(array_map('strval', $out['result']['fallback_chain'])));
        }
        if ('CF06-FUT-034' === $id) {
            $out['result']['score_semantics'] = '0-100-normalized-utility-higher-is-better';
        }
        $out = SemanticIntegrityGuard::apply($id, $input, $out);
        return LocaleAccessibilityGuard::apply($id, $input, $out);
    }
}
