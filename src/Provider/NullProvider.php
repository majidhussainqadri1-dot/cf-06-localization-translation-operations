<?php

declare(strict_types=1);

namespace Sabri\Localization\Provider;

use RuntimeException;

final class NullProvider implements MachineTranslationProvider
{
    public function key(): string { return 'disabled'; }
    public function submit(array $job, array $units): array { throw new RuntimeException('Machine translation provider is disabled.'); }
    public function purge(string $providerReference): array { return array('status'=>'not_applicable'); }
    public function health(): array { return array('status'=>'disabled','safe'=>true); }
}
