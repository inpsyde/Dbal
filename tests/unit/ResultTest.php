<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Tests\Unit;

use Inpsyde\Dbal\Error;
use Inpsyde\Dbal\ErrorCollector;
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

        $result->assert();

        static::assertFalse($result->isErrored());
        static::assertTrue($result->isValid());
        static::assertSame(456, $result->extract());
    }

    public function testBindError()
    {
        $result = Result::new(new \Exception('Meh'))->bind(
            static function (): void {
                static::assertTrue(false);
            },
            static function (): \Exception {
                return new \Exception('Meh meh!');
            }
        );

        static::assertTrue($result->isErrored());
        static::assertFalse($result->isValid());

        $this->expectExceptionMessage('Meh meh!');
        $result->assert();
    }

    public function testPushErrorToCollectorViaBind()
    {
        $errorCollector = new ErrorCollector();

        $function1 = static function (): Result {
            return Result::new(1);
        };

        $function2 = static function (): Result {
            return Result::new(new \Error('Failed!'));
        };

        $function3 = static function (): Result {
            throw new \Exception('I never run because previous failed!');
        };

        $function4 = static function (): Result {
            throw new \Exception('I never run because previous failed!');
        };

        $function1()
            ->bind($function2)
            ->bind($function3)
            ->bind($function4)
            ->bind(null, [$errorCollector, 'pushError']);

        static::assertFalse($errorCollector->isEmpty());
        static::assertSame('Failed!', $errorCollector->error()->getMessage());
    }

    public function testPushErrorToCollectorViaMerge()
    {
        $errorCollector = new ErrorCollector();

        $secondFunctionRun = false;

        $function1 = static function (): Result {
            return Result::new(new \Error('Failed!'));
        };

        $function2 = static function () use (&$secondFunctionRun): Result {
            $secondFunctionRun = true;

            return Result::new(1);
        };

        $function3 = static function (): Result {
            return Result::new(new \Exception('Failed again!'));
        };

        $function1()
            ->merge($function2())
            ->merge($function3())
            ->bind(null, [$errorCollector, 'pushError']);

        static::assertFalse($errorCollector->isEmpty());
        static::assertSame('Failed again!', $errorCollector->error()->getMessage());
        static::assertSame('Failed!', $errorCollector->error()->getPrevious()->getMessage());

        static::assertTrue($secondFunctionRun);
    }

    public function testBindErrorMerge()
    {
        $result = Result::new(new \Exception('Meh'))->bind(
            null,
            static function (): Result {
                return Result::new(new \Exception('Meh meh!'));
            }
        );

        static::assertTrue($result->isErrored());
        static::assertFalse($result->isValid());
        static::assertInstanceOf(Error::class, $result->error());
        static::assertSame('Meh meh!', $result->error()->getMessage());
        static::assertInstanceOf(Error::class, $result->error());
        static::assertSame('Meh', $result->error()->getPrevious()->getMessage());
    }

    public function testMergeAllSuccessPropagateResult()
    {
        $value = Result::new(1)->merge(Result::new(2))->merge(Result::new(3))->extract();

        static::assertSame(3, $value);
    }

    public function testMergeOneErrorPropagatesIt()
    {
        $erroneous = Result::new(new \Exception('Meh'));
        $result = Result::new(1)->merge($erroneous)->merge(Result::new(3))->merge(Result::new(4));

        static::assertTrue($result->isErrored());
        static::assertSame('Meh', $result->error()->getMessage());
    }

    public function testMergeMoreErrorsPropagatesAndMergeThem()
    {
        $result = Result::new(1)
            ->merge(Result::new(new \Exception('First!')))
            ->merge(Result::new(3))
            ->merge(Result::new(new \Exception('Second!')))
            ->merge(Result::new(4))
            ->merge(Result::new(new \Exception('Third!')))
            ->merge(Result::new(5));

        static::assertTrue($result->isErrored());
        static::assertSame('Third!', $result->error()->getMessage());
        static::assertSame('Second!', $result->error()->getPrevious()->getMessage());
        static::assertSame('First!', $result->error()->getPrevious()->getPrevious()->getMessage());
    }

    public function testExtractWith()
    {
        $id = Result::new((object)['id' => '2'])->extractWith(function (\stdClass $data): int {
            return (int)($data->id ?? 0);
        });

        static::assertSame(2, $id);

    }
}
