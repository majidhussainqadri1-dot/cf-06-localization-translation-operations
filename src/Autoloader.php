<?php

declare(strict_types=1);

namespace Sabri\Localization;

final class Autoloader
{
    private static string $baseDirectory = '';

    public static function register(string $baseDirectory): void
    {
        self::$baseDirectory = rtrim($baseDirectory, '/\\') . DIRECTORY_SEPARATOR;
        spl_autoload_register(array(self::class, 'load'));
    }

    public static function load(string $class): void
    {
        $prefix = __NAMESPACE__ . '\\';
        if (0 !== strncmp($class, $prefix, strlen($prefix))) {
            return;
        }
        $relative = substr($class, strlen($prefix));
        if (false === $relative || str_contains($relative, '..')) {
            return;
        }
        $file = self::$baseDirectory . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
}
