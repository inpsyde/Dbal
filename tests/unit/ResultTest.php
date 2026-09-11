<?php

declare(strict_types=1);

namespace Syde\Dbal\Tests\Unit;

use Syde\Dbal\Error;
use Syde\Dbal\ErrorCollector;
use Syde\Dbal\Result;
use Syde\Dbal\Tests\UnitTestCase;

class ResultTest extends UnitTestCase
{
    /**
     * @test
     */
    public function testResultFromThrowable(): void
    {
        $result = Result::new(new \Exception('Meh'));

        static::assertTrue($result->isErrored());

        $this->expectExceptionMessage('Meh');
        $result->extract();
    }

    /**
     * @test
     */
    public function testResultFromErrorCollectorWithErrors(): void
    {
        $errors = new ErrorCollector();
        $errors->withError('Meh');
        $result = Result::new($errors);

        static::assertTrue($result->isErrored());

        $this->expectExceptionMessage('Meh');
        $result->extract();
    }

    /**
     * @test
     */
    public function testResultFromEmptyErrorCollector(): void
    {
        $result = Result::new(new ErrorCollector());

        static::assertFalse($result->isErrored());
        static::assertNull($result->extract());
    }

    /**
     * @test
     */
    public function testResultFromResultSuccess(): void
    {
        $result = Result::new(Result::new(true));

        static::assertFalse($result->isErrored());
        static::assertTrue($result->extract());
    }

    /**
     * @test
     */
    public function testResultFromResultErrored(): void
    {
        $result = Result::new(Result::new(new \Exception('Meh')));

        static::assertTrue($result->isErrored());

        $this->expectExceptionMessage('Meh');
        $result->extract();
    }

    /**
     * @test
     */
    public function testResultFromValue(): void
    {
        $result = Result::new(123);

        static::assertFalse($result->isErrored());
        static::assertSame(123, $result->extract());
    }

    /**
     * @test
     */
    public function testBindSuccess(): void
    {
        $result = Result::new(123)->bind(
            static function (int $value): int {
                static::assertSame(123, $value);

                return 456;
            },
            static function (): void {
                static::fail();
            }
        );

        $result->assert();

        static::assertFalse($result->isErrored());
        static::assertSame(456, $result->extract());
    }

    /**
     * @test
     */
    public function testBindError(): void
    {
        $result = Result::new(new \Exception('Meh'))->bind(
            static function (): void {
                static::fail();
            },
            static function (): \Throwable {
                return new \Exception('Meh meh!');
            }
        );

        static::assertTrue($result->isErrored());

        $this->expectExceptionMessage('Meh meh!');
        $result->assert();
    }

    /**
     * @test
     */
    public function testPushErrorToCollectorViaBind(): void
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

    /**
     * @test
     */
    public function testPushErrorToCollectorViaMerge(): void
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

    /**
     * @test
     */
    public function testBindErrorMerge(): void
    {
        $result = Result::new(new \Exception('Meh'))->bind(
            null,
            static function (): Result {
                return Result::new(new \Exception('Meh meh!'));
            }
        );

        static::assertTrue($result->isErrored());
        static::assertInstanceOf(Error::class, $result->error());
        static::assertSame('Meh meh!', $result->error()->getMessage());
        static::assertInstanceOf(Error::class, $result->error());
        static::assertSame('Meh', $result->error()->getPrevious()->getMessage());
    }

    /**
     * @test
     */
    public function testMergeAllSuccessPropagateResult(): void
    {
        $value = Result::new(1)->merge(Result::new(2))->merge(Result::new(3))->extract();

        static::assertSame(3, $value);
    }

    /**
     * @test
     */
    public function testMergeOneErrorPropagatesIt(): void
    {
        $erroneous = Result::new(new \Exception('Meh'));
        $result = Result::new(1)->merge($erroneous)->merge(Result::new(3))->merge(Result::new(4));

        static::assertTrue($result->isErrored());
        static::assertSame('Meh', $result->error()->getMessage());
    }

    /**
     * @test
     */
    public function testMergeMoreErrorsPropagatesAndMergeThem(): void
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

    /**
     * @test
     */
    public function testExtractWith(): void
    {
        $id = Result::new((object) ['id' => '2'])
            ->extractWith(
                static function (\stdClass $data): int {
                    return (int) ($data->id ?? 0);
                }
            );

        static::assertSame(2, $id);
    }
}
