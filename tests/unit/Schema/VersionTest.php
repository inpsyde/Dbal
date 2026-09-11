<?php

declare(strict_types=1);

namespace Syde\Dbal\Tests\Unit\Schema;

use Syde\Dbal\Schema\Version;
use Syde\Dbal\Tests\UnitTestCase;

class VersionTest extends UnitTestCase
{
    /**
     * @test
     * @dataProvider provideNewData
     */
    public function testNew(string $input, ?string $expectedValue): void
    {
        $version = Version::new($input);
        static::assertSame($version->value(), $expectedValue);
        static::assertSame($version->isValid(), $expectedValue !== null);
        static::assertSame((string) $version, $expectedValue ?? '');
        static::assertTrue(Version::new($input)->match($version));
    }

    /**
     * @return \Generator
     */
    public static function provideNewData(): \Generator
    {
        return yield from [
            ['1', '1'],
            ['0', '0'],
            ['0.1', '0.1'],
            ['0.12', '0.12'],
            ['0.1.12.2', '0.1.12.2'],
            ['0.1.a.12.2', '0.1.12.2'],
            ['0.1.a12.2', '0.1.2'],
            ['1234', '1234'],
            ['1.1', '1.1'],
            ['1.2.3.4', '1.2.3.4'],
            ['1.2.3.4-beta', '1.2.3.4-beta'],
            ['1.2.3.4-beta.1.2', '1.2.3.4-beta.1.2'],
            ['1.2.3.4-0', '1.2.3.4-0'],
            ['1.a1', '1'],
            ['1.a.1', '1.1'],
            ['a1', null],
            ['a123', null],
            ['a.1', null],
            ['a.1.2', null],
            ['a.12', null],
            ['a1234567', null],
            ['', null],
        ];
    }

    /**
     * @test
     * @dataProvider provideCompareData
     */
    public function testCompare(string $left, string $right, ?string $expected): void
    {
        $leftVer = Version::new($left);
        $rightVer = Version::new($right);

        static::assertSame(
            $leftVer->lowerThan($rightVer),
            $expected === '<'
        );
        static::assertSame(
            $leftVer->lowerThanOrEquals($rightVer),
            in_array($expected, ['<', '='], true)
        );
        static::assertSame(
            $leftVer->greaterThan($rightVer),
            $expected === '>'
        );
        static::assertSame(
            $leftVer->greaterThanOrEquals($rightVer),
            in_array($expected, ['>', '='], true)
        );
        static::assertSame(
            $leftVer->equals($rightVer),
            $expected === '='
        );
    }

    /**
     * @return \Generator
     */
    public static function provideCompareData(): \Generator
    {
        return yield from [
            ['1', '1', '='],
            ['1', '1.a', '='],
            ['1', 'a', null],
            ['a', 'a', null],
            ['0', '0', '='],
            ['0', '', null],
            ['', '0', null],
            ['1.0.0', '1.0.1', '<'],
            ['1.0.1', '1.0.0', '>'],
            ['1.0.1', '1.0.1', '='],
            ['0.0.0', '0.0.1', '<'],
            ['0.0.1', '0.0.0', '>'],
            ['0.0.1', '0.0.1', '='],
            ['1.0.a.0', '1.0.1', '<'],
            ['1.0.a.1', '1.0.0', '>'],
            ['1.0.a.1', '1.0.1', '='],
            ['0.0.a.0', '0.0.1', '<'],
            ['0.0.a.1', '0.0.0', '>'],
            ['0.0.a.1', '0.0.1', '='],
            ['1.0.0-alpha', '1.0.0', '<'],
        ];
    }
}
