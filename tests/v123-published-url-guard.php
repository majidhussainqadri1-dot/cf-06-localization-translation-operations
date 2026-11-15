<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/TestHarness.php';
require_once dirname(__DIR__) . '/src/Domain/Locale/PublishedUrlGuard.php';

use Sabri\Localization\Domain\Locale\PublishedUrlGuard;

$t = new TestHarness();
$good = 'https://example.test/ar/public-edition';
$t->test('Draft may have no public URLs', fn () => PublishedUrlGuard::assertValid('draft', null, null));
$t->test('Approved but unpublished may have no public URLs', fn () => PublishedUrlGuard::assertValid('approved', null, null));
$t->test('Published source-linked translation must have both HTTPS URLs', fn () => PublishedUrlGuard::assertValid('published', $good, $good));
$t->test('Published translation with no translated URL fails', fn () => TestHarness::assertThrows(
    fn () => PublishedUrlGuard::assertValid('published', null, $good), InvalidArgumentException::class
));
$t->test('Published translation with no canonical URL fails', fn () => TestHarness::assertThrows(
    fn () => PublishedUrlGuard::assertValid('published', $good, null), InvalidArgumentException::class
));
$t->test('Published translation refuses HTTP URL', fn () => TestHarness::assertThrows(
    fn () => PublishedUrlGuard::assertValid('published', 'http://example.test/ur', $good), InvalidArgumentException::class
));
$t->test('Published translation refuses URL credentials', fn () => TestHarness::assertThrows(
    fn () => PublishedUrlGuard::assertValid('published', $good, 'https://name:secret@example.test/ur'), InvalidArgumentException::class
));
$t->test('Published translation refuses canonical fragment', fn () => TestHarness::assertThrows(
    fn () => PublishedUrlGuard::assertValid('published', $good, $good.'#section'), InvalidArgumentException::class
));
$t->test('Published URL validation is wired before storage mutation', function (): void {
    $source = (string)file_get_contents(dirname(__DIR__).'/src/Application/ContentLinkService.php');
    TestHarness::assertTrue(str_contains($source, 'PublishedUrlGuard::assertValid($status,$urls'));
    TestHarness::assertTrue(strpos($source, 'PublishedUrlGuard::assertValid(') < strpos($source, '$this->tx->run('));
});
$t->finish();
