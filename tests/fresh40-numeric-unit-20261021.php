<?php
declare(strict_types=1);
/**
 * Fresh 40 behavioral checks for CF06-FR-017 / CF06-FR-023.
 * Uses synthetic source+translation text, not actual patient/payment data.
 */
require __DIR__ . '/bootstrap.php';

use Sabri\Localization\Domain\Translation\NumberUnitGuard;

$cases = [
    ['N01', 'unchanged mg dose', true, '5 mg', '5 mg'],
    ['N02', 'space-insensitive dose', true, 'Dose 5 mg', 'خوراک 5mg'],
    ['N03', 'ordered two doses kept', true, '5 mg then 10 mg', '5 mg پھر 10 mg'],
    ['N04', 'ordered three values kept', true, '1 mg 2 mg 3 mg', '1 mg، 2 mg، 3 mg'],
    ['N05', 'decimal amount unchanged', true, '0.5 mL', '0.5 mL'],
    ['N06', 'homeopathic 200C unchanged', true, '200C', '200C'],
    ['N07', 'homeopathic 30C unchanged', true, '30C', '30C'],
    ['N08', 'potency 1M unchanged', true, '1M', '1M'],
    ['N09', 'PKR prefix unchanged', true, 'PKR 500', 'PKR 500'],
    ['N10', 'USD prefix unchanged', true, 'USD 20', 'USD 20'],
    ['N11', 'EUR prefix unchanged', true, 'EUR 30', 'EUR 30'],
    ['N12', 'IU unchanged', true, '5 IU', '5 IU'],
    ['N13', 'mEq/L unchanged', true, '2 mEq/L', '2 mEq/L'],
    ['N14', 'mmol/L unchanged', true, '3 mmol/L', '3 mmol/L'],
    ['N15', 'signed negative unchanged', true, '-5 mg', '-5 mg'],
    ['N16', 'signed positive unchanged', true, '+5 mg', '+5 mg'],
    ['N17', 'fractional microgram unchanged', true, '2.5 mcg/kg/min', '2.5 mcg/kg/min'],
    ['N18', 'percent unchanged', true, '15%', '15 %'],
    ['N19', 'millimeter mercury unchanged', true, '120 mmHg', '120 mmHg'],
    ['N20', 'mixed text and quantity', true, '200C then 5 ml', '200C اور 5 ml'],
    ['N21', 'swapped doses forbidden', false, '5 mg then 10 mg', '10 mg پھر 5 mg'],
    ['N22', 'swapped different units forbidden', false, '5 mg then 5 mL', '5 mL پھر 5 mg'],
    ['N23', 'mg to mL forbidden', false, '5 mg', '5 mL'],
    ['N24', 'IU to mEq forbidden', false, '5 IU', '5 mEq'],
    ['N25', 'mEq/L to mmol/L forbidden', false, '2 mEq/L', '2 mmol/L'],
    ['N26', 'PKR to USD forbidden', false, 'PKR 500', 'USD 500'],
    ['N27', 'USD to EUR forbidden', false, 'USD 25', 'EUR 25'],
    ['N28', 'negative sign dropped forbidden', false, '-5 mg', '5 mg'],
    ['N29', 'positive sign dropped forbidden', false, '+5 mg', '5 mg'],
    ['N30', 'negative sign added forbidden', false, '5 mg', '-5 mg'],
    ['N31', 'homeopathic potency altered', false, '30C', '200C'],
    ['N32', 'decimal quantity changed', false, '0.5 mg', '0.05 mg'],
    ['N33', 'dose omitted forbidden', false, '5 mg then 10 mg', '5 mg'],
    ['N34', 'extra dose inserted forbidden', false, '5 mg', '5 mg then 10 mg'],
    ['N35', 'same-value quantities swapped forbidden', false, '5 mg, 5 mL', '5 mL، 5 mg'],
    ['N36', 'mmol to IU forbidden', false, '5 mmol', '5 IU'],
    ['N37', 'glucose unit drift forbidden', false, '100 mg/dL', '100 mmol/L'],
    ['N38', 'microgram to milligram forbidden', false, '5 µg', '5 mg'],
    ['N39', 'pressure unit drift forbidden', false, '120 mmHg', '120 kPa'],
    ['N40', 'insulin unit IU to U forbidden', false, '5 IU', '5 U'],
];
if (count($cases) !== 40) {
    throw new RuntimeException('The fresh numeric suite must have exactly 40 distinct fixtures.');
}
$failures = [];
foreach ($cases as [$id, $label, $expectedAllowed, $source, $target]) {
    $allowed = true;
    try {
        NumberUnitGuard::assertImmutable($source, $target);
    } catch (\InvalidArgumentException $error) {
        $allowed = false;
    }
    $success = $allowed === $expectedAllowed;
    echo ($success ? 'PASS ' : 'FAIL ') . $id . ' ' . $label . "\n";
    if (!$success) {
        $failures[] = $id;
    }
}
echo 'CF06 fresh 40 ordered-unit/currency/dose tests: '
    . (40 - count($failures)) . '/40 PASS' . "\n";
exit($failures === [] ? 0 : 1);
