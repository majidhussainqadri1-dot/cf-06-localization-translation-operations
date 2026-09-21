<?php
declare(strict_types=1);

$path = __DIR__ . '/../docs/REQUIREMENTS-TRACEABILITY.md';
$matrix = file_get_contents($path);
if (false === $matrix) {
    fwrite(STDERR, "Unable to read requirements traceability matrix.\n");
    exit(1);
}

$families = [
    'CF06-FR-' => [34, 3],
    'CF06-CEN-' => [10, 2],
    'CF06-NJ-' => [6, 2],
];

foreach ($families as $prefix => [$expectedCount, $width]) {
    $pattern = '/^\|\s*(' . preg_quote($prefix, '/') . '\d{' . $width . '})\b[^\n]*\|$/m';
    preg_match_all($pattern, $matrix, $matches);
    $ids = $matches[1] ?? [];

    if (count($ids) !== $expectedCount) {
        fwrite(STDERR, sprintf("%s traceability row count mismatch: expected %d, found %d.\n", $prefix, $expectedCount, count($ids)));
        exit(1);
    }
    if (count(array_unique($ids)) !== $expectedCount) {
        fwrite(STDERR, sprintf("%s traceability contains duplicate requirement rows.\n", $prefix));
        exit(1);
    }

    for ($i = 1; $i <= $expectedCount; $i++) {
        $expected = $prefix . str_pad((string) $i, $width, '0', STR_PAD_LEFT);
        if (!in_array($expected, $ids, true)) {
            fwrite(STDERR, "Missing structural traceability row: {$expected}.\n");
            exit(1);
        }
    }
}

echo "Core FR/CEN/NJ traceability structure OK\n";
