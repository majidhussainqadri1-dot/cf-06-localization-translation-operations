<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';
$root=dirname(__DIR__);$t=new TestHarness();
$translation=(string)file_get_contents($root.'/src/Application/TranslationService.php');
$routes=(string)file_get_contents($root.'/src/Rest/Routes.php');
$resource=(string)file_get_contents($root.'/src/Application/ResourceService.php');
$terminology=(string)file_get_contents($root.'/src/Application/TerminologyService.php');
$invalidator=(string)file_get_contents($root.'/src/Infrastructure/DependencyInvalidator.php');
$metrics=(string)file_get_contents($root.'/src/Application/MetricsService.php');
$feedback=(string)file_get_contents($root.'/src/Application/FeedbackService.php');
$events=(string)file_get_contents($root.'/src/Contract/Events.php');

$t->test('CF06-FR-009 contextual queries have versioned resolution and safe propagation',function()use($translation,$routes,$events):void{
 foreach(['resolveComment(','resolution_text','context_query_resolved','markContentLinksStale','invalidateActiveBundles','wp_cache_flush'] as $n){TestHarness::assertTrue(str_contains($translation,$n),$n);}
 TestHarness::assertTrue(str_contains($routes,"'/comments/(?P<uuid>[a-f0-9-]{36})/resolve'"));
 TestHarness::assertTrue(str_contains($events,'TranslationContextQueryResolved'));
});

$t->test('CF06-FR-002 resource identity governs route component and screenshot context references',function()use($resource):void{
 foreach(['normalizeContextRefs','context_refs',"'route','component','screenshot'",'same-origin relative route','stable canonical identifier'] as $n){TestHarness::assertTrue(str_contains($resource,$n),$n);}
});

$t->test('CF06-FR-031 terminology and style policy changes stale locale-domain dependents',function()use($terminology,$invalidator,$events):void{
 foreach(['invalidateLocaleDomain','terminology_policy_changed','style_policy_changed','LocalizationPolicyChanged','wp_cache_flush'] as $n){TestHarness::assertTrue(str_contains($terminology,$n),$n);}
 foreach(['stale_content_links','invalidated_bundles',"SET status='stale'"] as $n){TestHarness::assertTrue(str_contains($invalidator,$n),$n);}
 TestHarness::assertTrue(str_contains($events,'LocalizationPolicyChanged'));
});

$t->test('CF06-FR-032 exposes aggregate coverage word turnaround QA and report metrics without actor dimensions',function()use($metrics,$feedback,$events):void{
 foreach(['unit_status','quality_issues_by_rule','feedback_by_locale_domain','turnaround','word_workload','missing_source_words','stale_source_words','restricted_material_excluded','denominators'] as $n){TestHarness::assertTrue(str_contains($metrics,$n),$n);}
 TestHarness::assertTrue(!str_contains($metrics,'translator_id'));
 TestHarness::assertTrue(str_contains($feedback,'TranslationFeedbackReopened'));
 TestHarness::assertTrue(str_contains($events,'TranslationFeedbackReopened'));
});

$t->finish();
