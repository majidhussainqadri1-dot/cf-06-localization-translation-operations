<?php

declare(strict_types=1);

namespace Sabri\Localization\Domain\Translation;

use InvalidArgumentException;

final class PlaceholderValidator
{
    public const TYPES = array('string', 'integer', 'decimal', 'date', 'time', 'datetime', 'currency', 'percent', 'url', 'unit', 'identifier');

    public static function extract(string $text): array
    {
        return MessageFormatValidator::analyze($text)['arguments'];
    }

    public static function normalizeSchema(mixed $provided): array
    {
        if (! is_array($provided)) {
            return array();
        }
        if (count($provided) > 500) {
            throw new InvalidArgumentException('Typed placeholder schema exceeds the bounded item limit.');
        }
        $schema = array();
        foreach ($provided as $name => $type) {
            $name = (string) $name;
            $type = strtolower((string) $type);
            if (strlen($name) > 128 || 1 !== preg_match('/^[A-Za-z][A-Za-z0-9_]*$/D', $name) || ! in_array($type, self::TYPES, true)) {
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
        MessageFormatValidator::assertEquivalent($source, $target);
        $targetArguments = self::extract($target);
        $declared = array_keys($schema);
        sort($declared, SORT_STRING);
        if ($targetArguments !== $declared) {
            throw new InvalidArgumentException('Translation placeholders do not exactly match the source schema.');
        }
    }
}
