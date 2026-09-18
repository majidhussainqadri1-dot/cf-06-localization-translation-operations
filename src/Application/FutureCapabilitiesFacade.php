<?php

declare(strict_types=1);

namespace Sabri\Localization\Application;

use Sabri\Localization\Domain\Future\FutureCapabilityGuard;
use Sabri\Localization\Domain\Future\HotfixApprovalGuard;
use Sabri\Localization\Domain\Future\LocaleAccessibilityGuard;
use Sabri\Localization\Domain\Future\ProviderEligibilityGuard;
use Sabri\Localization\Domain\Future\ReleaseLifecycleGuard;
use Sabri\Localization\Domain\Future\SemanticIntegrityGuard;

/**
 * Canonical exposed Future40 service. The underlying handler collection stays
 * pure; this facade enforces cross-capability validation before any handler runs.
 */
final class FutureCapabilitiesFacade
{
    private const MAX_EVALUATION_BYTES = 262144;
    private const MAX_EVALUATION_NODES = 5000;
    public function __construct(private readonly FutureCapabilitiesService $handlers = new FutureCapabilitiesService()) {}

    public function catalogue(): array
    {
        return $this->handlers->catalogue();
    }

    public function evaluate(string $id, array $input): array
    {
        $this->assertBoundedInput($input);
        $id = strtoupper(trim($id));
        $input = ReleaseLifecycleGuard::normalize($id, $input);
        if ('CF06-FUT-028' === $id) {
            $input = HotfixApprovalGuard::normalize($input);
        }
        $input = FutureCapabilityGuard::normalize($id, $input);
        $input = LocaleAccessibilityGuard::normalize($id, $input);
        $input = SemanticIntegrityGuard::normalize($id, $input);
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
        $out = LocaleAccessibilityGuard::apply($id, $input, $out);
        return ReleaseLifecycleGuard::apply($id, $input, $out);
    }

    private function assertBoundedInput(array $input): void
    {
        try {
            $encoded=json_encode($input,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException('Future capability input is not safely encodable.',0,$exception);
        }
        if(strlen($encoded)>self::MAX_EVALUATION_BYTES){
            throw new \InvalidArgumentException('Future capability input exceeds the bounded byte limit.');
        }
        $count=0;$stack=[$input];
        while([]!==$stack){
            $current=array_pop($stack);
            foreach($current as $item){
                ++$count;
                if($count>self::MAX_EVALUATION_NODES){
                    throw new \InvalidArgumentException('Future capability input exceeds the bounded node limit.');
                }
                if(is_array($item)){$stack[]=$item;}
            }
        }
    }
}
