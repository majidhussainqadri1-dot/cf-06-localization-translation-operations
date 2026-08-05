<?php

declare(strict_types=1);

namespace Sabri\Localization;

final class Autoloader
{
    private const PREFIX = 'Sabri\\Localization\\';

    public static function register(string $sourceDirectory): void
    {
        $base = rtrim($sourceDirectory, '/\\') . DIRECTORY_SEPARATOR;

        spl_autoload_register(
            static function (string $class) use ($base): void {
                if (! str_starts_with($class, self::PREFIX)) {
                    return;
                }

                $relative = substr($class, strlen(self::PREFIX));
                $file     = $base . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

                if (is_readable($file)) {
                    require_once $file;
                }
            }
        );
    }
}
