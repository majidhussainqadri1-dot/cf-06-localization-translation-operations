<?php

declare(strict_types=1);

namespace Sabri\Localization\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Sabri\Localization\Domain\Locale\LocaleValidator;

final class LocaleValidatorTest extends TestCase
{
    public function testCanonicalizesCoreLocaleTags(): void
    {
        self::assertSame('en-US', LocaleValidator::canonicalize('EN_us'));
        self::assertSame('ur-Arab-PK', LocaleValidator::canonicalize('ur-arab-pk'));
        self::assertSame('ar', LocaleValidator::canonicalize('ar'));
    }

    public function testRejectsMalformedLocaleTags(): void
    {
        self::assertNull(LocaleValidator::canonicalize(''));
        self::assertNull(LocaleValidator::canonicalize('e'));
        self::assertNull(LocaleValidator::canonicalize('en--US'));
        self::assertNull(LocaleValidator::canonicalize('../ur-PK'));
    }
}
