<?php

declare(strict_types=1);

namespace Sabri\Localization\Application;

use Sabri\Localization\Domain\Future\FutureCapabilityGuard;

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
        return $this->handlers->evaluate($id, $input);
    }
}
