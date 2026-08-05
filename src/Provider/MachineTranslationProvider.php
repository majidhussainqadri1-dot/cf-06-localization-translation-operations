<?php

declare(strict_types=1);

namespace Sabri\Localization\Provider;

interface MachineTranslationProvider
{
    public function key(): string;
    public function submit(array $job, array $units): array;
    public function purge(string $providerReference): array;
    public function health(): array;
}
