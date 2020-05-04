<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Tests\Unit;

use Inpsyde\Dbal\Query\Error;
use Inpsyde\Dbal\Query\ErrorCollector;
use Inpsyde\Dbal\Result;
use Inpsyde\Dbal\Tests\UnitTestCase;

class ResultTest extends UnitTestCase
{
    public function testResultFromThrowable()
    {
        $result = Result::new(new \Exception('Meh'));

        static::assertTrue($result->isErrored());
        static::assertFalse($result->isValid());

        $this->expectExceptionMessage('Meh');
        $result->extract();
    }

    public function testResultFromErrorCollectorWithErrors()
    {
        $errors = new ErrorCollector();
        $errors->withError('Meh');
        $result = Result::new($errors);

        static::assertTrue($result->isErrored());
        static::assertFalse($result->isValid());

        $this->expectExceptionMessage('Meh');
        $result->extract();
    }

    public function testResultFromEmptyErrorCollector()
    {
        $result = Result::new(new ErrorCollector());

        static::assertFalse($result->isErrored());
        static::assertTrue($result->isValid());
        static::assertNull($result->extract());
    }

    public function testResultFromResultSuccess()
    {
        $result = Result::new(Result::new(true));

        static::assertFalse($result->isErrored());
        static::assertTrue($result->isValid());
        static::assertTrue($result->extract());
    }

    public function testResultFromResultErrored()
    {
        $result = Result::new(Result::new(new \Exception('Meh')));

        static::assertTrue($result->isErrored());
        static::assertFalse($result->isValid());

        $this->expectExceptionMessage('Meh');
        $result->extract();
    }

    public function testResultFromValue()
    {
        $result = Result::new(123);

        static::assertFalse($result->isErrored());
        static::assertTrue($result->isValid());
        static::assertSame(123, $result->extract());
    }

    public function testBindSuccess()
    {
        $result = Result::new(123)->bind(
            function (int $value): int {
                static::assertSame(123, $value);

                return 456;
            },
            function (): void {
                static::assertTrue(false);
            }
        );

        static::assertFalse($result->isErrored());
        static::assertTrue($result->isValid());
        static::assertSame(456, $result->extract());
    }

    public function testBindError()
    {
        $result = Result::new(new \Exception('Meh'))->bind(
            function (): void {
                static::assertTrue(false);
            },
            function (ErrorCollector $errors): \Exception {
                static::assertFalse($errors->isEmpty());
                static::assertSame('Meh', $errors->error()->getMessage());

                return new \Exception('Meh meh!');
            }
        );

        static::assertTrue($result->isErrored());
        static::assertFalse($result->isValid());

        $this->expectExceptionMessage('Meh meh!');
        $result->extract();
    }
}
