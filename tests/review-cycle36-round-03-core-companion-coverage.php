<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';

$root = dirname(__DIR__);
$t = new TestHarness();
$matrix = (string)file_get_contents($root . '/docs/REQUIREMENTS-TRACEABILITY.md');
$owners = (string)file_get_contents($root . '/docs/ARCHITECTURE.md');
$compliance = (string)file_get_contents($root . '/src/Contract/PlanCompliance.php');

// Thirty-four approved core requirements and six native journeys. Each
// independently proves that a documented requirement retains its mapped,
// readable implementation class. This is source traceability, NOT staging UAT.
$coverage = [
    1 => ['CF06-FR-001', 'src/Application/LocaleService.php'],
    2 => ['CF06-FR-002', 'src/Application/ResourceService.php'],
    3 => ['CF06-FR-003', 'src/Application/ResourceService.php'],
    4 => ['CF06-FR-004', 'src/Application/ExtractionService.php'],
    5 => ['CF06-FR-005', 'src/Domain/Translation/RiskPolicy.php'],
    6 => ['CF06-FR-006', 'src/Application/ProjectService.php'],
    7 => ['CF06-FR-007', 'src/Application/ProjectService.php'],
    8 => ['CF06-FR-008', 'src/Application/TranslationService.php'],
    9 => ['CF06-FR-009', 'src/Application/TranslationService.php'],
    10 => ['CF06-FR-010', 'src/Application/TranslationService.php'],
    11 => ['CF06-FR-011', 'src/Provider/HttpJsonProvider.php'],
    12 => ['CF06-FR-012', 'src/Application/TerminologyService.php'],
    13 => ['CF06-FR-013', 'src/Domain/Translation/RiskPolicy.php'],
    14 => ['CF06-FR-014', 'src/Domain/Translation/RiskPolicy.php'],
    15 => ['CF06-FR-015', 'src/Domain/Translation/RiskPolicy.php'],
    16 => ['CF06-FR-016', 'src/Contract/Manifest.php'],
    17 => ['CF06-FR-017', 'src/Domain/Translation/NumberUnitGuard.php'],
    18 => ['CF06-FR-018', 'src/Application/MachineTranslationService.php'],
    19 => ['CF06-FR-019', 'src/Domain/Translation/Redactor.php'],
    20 => ['CF06-FR-020', 'src/Application/MachineTranslationService.php'],
    21 => ['CF06-FR-021', 'src/Application/TerminologyService.php'],
    22 => ['CF06-FR-022', 'src/Application/ProviderService.php'],
    23 => ['CF06-FR-023', 'src/Application/QaService.php'],
    24 => ['CF06-FR-024', 'src/Application/QaEvidenceService.php'],
    25 => ['CF06-FR-025', 'src/Domain/Bundle/DeterministicBundle.php'],
    26 => ['CF06-FR-026', 'src/Application/QaService.php'],
    27 => ['CF06-FR-027', 'src/Application/BundleService.php'],
    28 => ['CF06-FR-028', 'src/Application/ContentLinkService.php'],
    29 => ['CF06-FR-029', 'src/Application/TerminologyService.php'],
    30 => ['CF06-FR-030', 'src/Application/FeedbackService.php'],
    31 => ['CF06-FR-031', 'src/Infrastructure/DependencyInvalidator.php'],
    32 => ['CF06-FR-032', 'src/Application/MetricsService.php'],
    33 => ['CF06-FR-033', 'src/Application/PrivacyService.php'],
    34 => ['CF06-FR-034', 'src/Application/ProviderService.php'],
    35 => ['CF06-NJ-01', 'src/Application/BundleService.php'],
    36 => ['CF06-NJ-02', 'src/Application/TranslationService.php'],
    37 => ['CF06-NJ-03', 'src/Infrastructure/DependencyInvalidator.php'],
    38 => ['CF06-NJ-04', 'src/Application/MachineTranslationService.php'],
    39 => ['CF06-NJ-05', 'src/Application/LocaleService.php'],
    40 => ['CF06-NJ-06', 'src/Application/BundleService.php'],
];
TestHarness::assertSame(40, count($coverage));
foreach ($coverage as $round => [$id, $path]) {
    $t->test(sprintf('Core/companion source review %02d: %s', $round, $id), static function () use ($root, $matrix, $owners, $compliance, $id, $path): void {
        $source = $root . '/' . $path;
        TestHarness::assertTrue(is_file($source), 'Missing implementation source: ' . $path);
        $body = (string)file_get_contents($source);
        $class = basename($path, '.php');
        TestHarness::assertTrue(strlen($body) >= 80, 'Empty implementation source: ' . $path);
        TestHarness::assertTrue(1 === preg_match('/\\bclass\\s+' . preg_quote($class, '/') . '\\b/', $body), 'Implementation class missing: ' . $class);
        TestHarness::assertTrue(str_contains($matrix, '| ' . $id . ' ' . (str_starts_with($id, 'CF06-FR-') ? '' : '|')) || str_contains($matrix, '| ' . $id . ' |'), 'Requirement not traced: ' . $id);
        TestHarness::assertTrue(str_contains($compliance, 'CEN-OWN-001') && str_contains($compliance, 'CEN-SHELL-001'), 'Missing central owner/shell boundaries');
        TestHarness::assertTrue(str_contains($owners, 'File 20') && str_contains($owners, 'File 25') && str_contains($owners, 'File 26'), 'Missing companion boundaries');
    });
}
$t->finish();
