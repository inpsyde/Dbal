<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Tests\Unit;

use Inpsyde\Dbal\ErrorCollector;
use Inpsyde\Dbal\Error;
use Inpsyde\Dbal\Tests\UnitTestCase;

class ErrorCollectorTest extends UnitTestCase
{
    public function testPushFlow()
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

    public function testAssert()
    {
        $collector = new ErrorCollector();

        $collector->assert();

        $collector->withError('Failed!');

        $this->expectExceptionMessage('Failed!');
        $collector->assert();
    }

    public function testPushFrom()
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
