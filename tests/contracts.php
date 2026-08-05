<?php

declare(strict_types=1);

require __DIR__ . '/TestHarness.php';

$root = dirname(__DIR__);
$t = new TestHarness();
$read = static fn (string $path): string => (string) file_get_contents($root . '/' . $path);
$allPhp = implode("\n", array_map(static fn (string $f): string => (string) file_get_contents($f), array_filter(iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root))), static fn ($f): bool => $f->isFile() && 'php' === $f->getExtension() && ! str_contains($f->getPathname(), DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR))));

$t->test('Plugin identity and safe default', function () use ($read): void {
    $main = $read('sabri-localization-translation-operations.php');
    TestHarness::assertTrue(str_contains($main, 'Version:           1.0.0-rc.1'));
    TestHarness::assertTrue(str_contains($main, "define('SABRI_SLTO_VERSION', '1.0.0-rc.1')"));
    $activator = $read('src/Infrastructure/Activator.php');
    TestHarness::assertTrue(str_contains($activator, "add_option('slto_runtime_enabled', false"));
});

$t->test('All canonical data domains have tables', function () use ($read): void {
    $db = $read('src/Infrastructure/Database.php');
    foreach (array('locales','resources','secure_payloads','projects','project_resources','assignments','units','comments','terminology','style_guides','memory','providers','vendor_jobs','bundles','qa_results','feedback','content_links','audit','outbox','jobs','idempotency','rate_limits','migrations') as $entity) {
        TestHarness::assertTrue(str_contains($db, "'{$entity}' =>"), "Missing table entity {$entity}");
    }
});

$t->test('No canonical content ownership takeover', function () use ($read): void {
    $manifest = $read('src/Contract/Manifest.php');
    foreach (array('global_switcher','visual_rtl_components','search_transliteration','original_domain_content') as $boundary) {
        TestHarness::assertTrue(str_contains($manifest, $boundary), "Missing boundary {$boundary}");
    }
});

$t->test('Full requirements traceability CF06-FR-001 through 034', function () use ($read): void {
    $rtm = $read('docs/REQUIREMENTS-TRACEABILITY.md');
    for ($i = 1; $i <= 34; ++$i) {
        $id = sprintf('CF06-FR-%03d', $i);
        TestHarness::assertTrue(str_contains($rtm, $id), "Missing {$id}");
    }
});

$t->test('REST surface is versioned and write-protected', function () use ($read): void {
    $routes = $read('src/Rest/Routes.php');
    TestHarness::assertTrue(str_contains($routes, "sabri-localization/v1"));
    TestHarness::assertTrue(str_contains($routes, 'permission_callback'));
    TestHarness::assertTrue(str_contains($routes, 'idempotency'));
    TestHarness::assertTrue(str_contains($routes, 'rateLimit'));
});

$t->test('Restricted payloads use authenticated encryption', function () use ($read): void {
    $crypto = $read('src/Infrastructure/Crypto.php');
    TestHarness::assertTrue(str_contains($crypto, 'aes-256-gcm'));
    TestHarness::assertTrue(str_contains($crypto, 'SLTO_DATA_ENCRYPTION_KEYS'));
    TestHarness::assertTrue(! str_contains($crypto, "'default-key'"));
});

$t->test('External MT is draft-only and privacy-bound', function () use ($read): void {
    $mt = $read('src/Application/MachineTranslationService.php');
    TestHarness::assertTrue(str_contains($mt, 'human_review_required'));
    TestHarness::assertTrue(str_contains($mt, 'Redactor::redact'));
    TestHarness::assertTrue(str_contains($mt, "'status' => 'validated'"));
    TestHarness::assertTrue(str_contains($mt, 'translations->submit'));
});

$t->test('Release requires signature, critical coverage and integrations', function () use ($read): void {
    $bundle = $read('src/Application/BundleService.php');
    TestHarness::assertTrue(str_contains($bundle, 'critical_coverage'));
    TestHarness::assertTrue(str_contains($bundle, 'DeterministicBundle::verify'));
    foreach (array('file00_membership','file20_shell','file24_assurance','file25_visual','domain_contracts') as $dependency) {
        TestHarness::assertTrue(str_contains($bundle, $dependency), "Missing gate {$dependency}");
    }
});

$t->test('No embedded credentials or common private-key material', function () use ($allPhp): void {
    foreach (array('BEGIN PRIVATE KEY','AKIA','ghp_','sk_live_','xoxb-') as $needle) {
        TestHarness::assertTrue(! str_contains($allPhp, $needle), "Secret-like token found: {$needle}");
    }
});

$t->test('Machine translation human review endpoint is domain-protected',function()use($root):void{$code=file_get_contents($root.'/src/Rest/Routes.php');TestHarness::assertTrue(str_contains($code,'/mt/jobs/(?P<uuid>[a-f0-9-]{36})/review'));TestHarness::assertTrue(str_contains($code,"'mt.review'"));TestHarness::assertTrue(str_contains($code,"),'review_domain')"));});
$t->test('Every emitted localization event is declared in the public contract',function()use($root):void{$declared=file_get_contents($root.'/src/Contract/Events.php');$files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/src/Application'));foreach($files as $file){if(!$file->isFile()||$file->getExtension()!=='php'){continue;}$code=file_get_contents($file->getPathname());preg_match_all('/\$this->outbox->enqueue\(\'([^\']+)\'/', $code, $matches);foreach($matches[1] as $event){TestHarness::assertTrue(str_contains($declared,"'".$event."'"),'Undeclared event: '.$event);}}});
$t->finish();
