<?php
declare(strict_types=1);
require __DIR__ . '/TestHarness.php';

use Sabri\Localization\Domain\Locale\LocaleValidator;

$root=dirname(__DIR__);
require_once $root.'/src/Autoloader.php';
Sabri\Localization\Autoloader::register($root.'/src');

$t=new TestHarness();

$t->test('Explicit locale script overrides language default direction',function():void{
    TestHarness::assertSame('ltr',LocaleValidator::direction('ku-Latn'));
    TestHarness::assertSame('ltr',LocaleValidator::direction('ur-Latn'));
    TestHarness::assertSame('ltr',LocaleValidator::direction('sd-Deva'));
    TestHarness::assertSame('rtl',LocaleValidator::direction('ku-Arab'));
    TestHarness::assertSame('rtl',LocaleValidator::direction('ar-Arab'));
    TestHarness::assertSame('rtl',LocaleValidator::direction('he-Hebr'));
});

$t->test('Language direction remains the fallback when script is absent',function():void{
    TestHarness::assertSame('rtl',LocaleValidator::direction('ur-PK'));
    TestHarness::assertSame('rtl',LocaleValidator::direction('ar'));
    TestHarness::assertSame('ltr',LocaleValidator::direction('en-US'));
});

$t->finish();
