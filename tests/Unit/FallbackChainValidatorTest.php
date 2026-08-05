<?php

declare(strict_types=1);

namespace Sabri\Localization\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sabri\Localization\Domain\Locale\FallbackChainValidator;

final class FallbackChainValidatorTest extends TestCase
{
    public function testBuildsDeterministicFallbackChain(): void
    {
        $map = array('ur-PK' => 'en-US', 'en-US' => null, 'ar' => 'ur-PK');
        self::assertSame(array('ur-PK', 'en-US'), FallbackChainValidator::chainFor('ar', $map));
    }

    public function testRejectsCycles(): void
    {
        $this->expectException(InvalidArgumentException::class);
        FallbackChainValidator::assertValid(array('ur-PK' => 'en-US', 'en-US' => 'ur-PK'));
    }

    public function testRejectsMissingFallbackTarget(): void
    {
        $this->expectException(InvalidArgumentException::class);
        FallbackChainValidator::assertValid(array('ur-PK' => 'en-US'));
    }
}
