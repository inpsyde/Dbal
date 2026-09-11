<?php

declare(strict_types=1);

namespace Syde\Dbal\Tests\Unit;

use Syde\Dbal\Error;
use Syde\Dbal\ErrorCollector;
use Syde\Dbal\Tests\UnitTestCase;

class ErrorCollectorTest extends UnitTestCase
{
    /**
     * @test
     */
    public function testPushFlow(): void
    {
        $collector = new ErrorCollector();

        static::assertTrue($collector->isEmpty());
        static::assertNull($collector->error());

        $collector->withError('First');

        static::assertFalse($collector->isEmpty());
        static::assertInstanceOf(Error::class, $collector->error());

        $collector->withError('Second');
        $collector->withError('Third');

        $error = $collector->error();

        static::assertInstanceOf(Error::class, $error);
        static::assertSame('Third', $error->getMessage());
        static::assertSame("First\n> Second\n> Third", $error->serialize());
    }

    /**
     * @test
     */
    public function testAssert(): void
    {
        $collector = new ErrorCollector();

        $collector->assert();

        $collector->withError('Failed!');

        $this->expectExceptionMessage('Failed!');
        $collector->assert();
    }

    /**
     * @test
     */
    public function testPushFrom(): void
    {
        $one = new ErrorCollector();
        $two = new ErrorCollector();

        $one->pushFrom($two);

        self::assertTrue($one->isEmpty());

        $two->withError('First');
        $one->pushFrom($two);

        self::assertFalse($one->isEmpty());
        self::assertSame('First', $one->error()->serialize());

        $two->withError('Second');
        $one->pushFrom($two);

        self::assertSame("First\n> Second", $one->error()->serialize());
    }
}
