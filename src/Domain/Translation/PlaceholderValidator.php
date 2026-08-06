<?php

declare(strict_types=1);

namespace Sabri\Localization\Domain\Translation;

use InvalidArgumentException;

final class PlaceholderValidator
{
    public const TYPES = array('string', 'integer', 'decimal', 'date', 'time', 'datetime', 'currency', 'percent', 'url', 'unit', 'identifier');

    public static function extract(string $text): array
    {
        preg_match_all('/(?<!\{)\{([A-Za-z][A-Za-z0-9_]*)\}(?!\})/', $text, $matches);
        $items = array_values(array_unique($matches[1] ?? array()));
        sort($items, SORT_STRING);
        return $items;
    }

    public static function normalizeSchema(mixed $provided): array
    {
        if (! is_array($provided)) {
            return array();
        }
        $schema = array();
        foreach ($provided as $name => $type) {
            $name = (string) $name;
            $type = strtolower((string) $type);
            if (1 !== preg_match('/^[A-Za-z][A-Za-z0-9_]*$/D', $name) || ! in_array($type, self::TYPES, true)) {
                throw new InvalidArgumentException('Invalid typed placeholder schema.');
            }
            $schema[$name] = $type;
        }
        ksort($schema, SORT_STRING);
        return $schema;
    }

    public static function assertSource(string $source, array $schema): void
    {
        $found = self::extract($source);
        $declared = array_keys($schema);
        sort($declared, SORT_STRING);
        if ($found !== $declared) {
            throw new InvalidArgumentException('Declared placeholders must exactly match source placeholders.');
        }
    }

    public static function assertTarget(string $source, string $target, array $schema): void
    {
        self::assertSource($source, $schema);
        if (self::extract($target) !== array_keys($schema)) {
            throw new InvalidArgumentException('Translation placeholders do not exactly match the source schema.');
        }
    }
}
