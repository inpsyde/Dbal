<?php

declare(strict_types=1);

namespace Syde\Dbal\Tests\Unit;

use Syde\Dbal\Error;
use Syde\Dbal\Result;
use Syde\Dbal\Tests\UnitTestCase;
use Syde\Dbal\Transaction;

class TransactionTest extends UnitTestCase
{
    /**
     * @test
     */
    public function testDefaultTransactionSuccess(): void
    {
        $result = Transaction::new()(
            static function (): Result {
                return Result::new('Success!');
            }
        );

        global $wpdb;

        $actualQueries = array_column($wpdb->queries, 0);
        $expectedQueries = ['START TRANSACTION;', 'COMMIT;'];

        static::assertFalse($result->isErrored());
        static::assertSame('Success!', $result->extract());
        static::assertSame($expectedQueries, $actualQueries);
    }

    /**
     * @test
     */
    public function testDefaultTransactionError(): void
    {
        $result = Transaction::new()(
            static function (): Error {
                return new Error('Failed!');
            }
        );

        global $wpdb;

        $actualQueries = array_column($wpdb->queries, 0);
        $expectedQueries = ['START TRANSACTION;', 'ROLLBACK;'];

        static::assertTrue($result->isErrored());
        static::assertSame($expectedQueries, $actualQueries);

        $this->expectExceptionMessage('Failed!');
        $result->extract();
    }

    /**
     * @test
     */
    public function testDefaultTransactionNoSignal(): void
    {
        $result = Transaction::new()(
            static function (): void {
            }
        );

        global $wpdb;
        $actualQueries = array_column($wpdb->queries, 0);
        $expectedQueries = ['START TRANSACTION;', 'COMMIT;'];

        static::assertFalse($result->isErrored());
        static::assertSame($expectedQueries, $actualQueries);
        static::assertNull($result->extract());
    }

    /**
     * @test
     */
    public function testDefaultTransactionWithWarning(): void
    {
        $result = Transaction::new()(
            static function (): void {
                trigger_error('Meh!', E_USER_WARNING);
            }
        );

        global $wpdb;

        $actualQueries = array_column($wpdb->queries, 0);
        $expectedQueries = ['START TRANSACTION;', 'ROLLBACK;'];

        static::assertTrue($result->isErrored());
        static::assertSame($expectedQueries, $actualQueries);

        $this->expectExceptionMessage('Meh!');
        $result->extract();
    }

    /**
     * @test
     */
    public function testDefaultTransactionWithException(): void
    {
        $result = Transaction::new()(
            static function (): void {
                throw new \Exception('Meh!');
            }
        );

        global $wpdb;

        $actualQueries = array_column($wpdb->queries, 0);
        $expectedQueries = ['START TRANSACTION;', 'ROLLBACK;'];

        static::assertTrue($result->isErrored());
        static::assertSame($expectedQueries, $actualQueries);

        $this->expectExceptionMessage('Meh!');
        $result->extract();
    }

    /**
     * @test
     */
    public function testDefaultTransactionWhenTransactionSetupFails(): void
    {
        $this->infixNextWpdbQueryResult(false);

        $result = Transaction::new(Transaction::READ_COMMITTED)(
            static function (): void {
                static::fail();
            }
        );

        global $wpdb;

        $actualQueries = array_column($wpdb->queries, 0);
        $expectedQueries = ['SET TRANSACTION ISOLATION LEVEL READ COMMITTED;'];

        static::assertTrue($result->isErrored());
        static::assertSame($expectedQueries, $actualQueries);

        $this->expectExceptionMessageMatches('/error executing query/i');
        $result->extract();
    }

    /**
     * @test
     */
    public function testDefaultTransactionWhenTransactionStartFails(): void
    {
        $this->infixNumWpdbQueryResult(2, false);

        $result = Transaction::new(Transaction::READ_COMMITTED)(
            static function (): void {
                static::fail();
            }
        );

        global $wpdb;

        $actualQueries = array_column($wpdb->queries, 0);
        $expectedQueries = [
            'SET TRANSACTION ISOLATION LEVEL READ COMMITTED;',
            'START TRANSACTION;',
            'ROLLBACK;',
        ];

        static::assertTrue($result->isErrored());
        static::assertSame($expectedQueries, $actualQueries);

        $this->expectExceptionMessageMatches('/error executing query/i');
        $result->extract();
    }

    /**
     * @test
     */
    public function testIsolationReadCommitted(): void
    {
        Transaction::new(Transaction::READ_COMMITTED)(
            static function (): void {
            }
        );

        global $wpdb;
        $actualQueries = array_column($wpdb->queries, 0);
        $expectedQueries = [
            'SET TRANSACTION ISOLATION LEVEL READ COMMITTED;',
            'START TRANSACTION;',
            'COMMIT;',
        ];

        static::assertSame($expectedQueries, $actualQueries);
    }

    /**
     * @test
     */
    public function testIsolationReadUncommitted(): void
    {
        Transaction::new(Transaction::READ_UNCOMMITTED)(
            static function (): void {
            }
        );

        global $wpdb;
        $actualQueries = array_column($wpdb->queries, 0);
        $expectedQueries = [
            'SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;',
            'START TRANSACTION;',
            'COMMIT;',
        ];

        static::assertSame($expectedQueries, $actualQueries);
    }

    /**
     * @test
     */
    public function testIsolationSerializable(): void
    {
        Transaction::new(Transaction::SERIALIZABLE)(
            static function (): void {
            }
        );

        global $wpdb;
        $actualQueries = array_column($wpdb->queries, 0);
        $expectedQueries = [
            'SET TRANSACTION ISOLATION LEVEL SERIALIZABLE;',
            'START TRANSACTION;',
            'COMMIT;',
        ];

        static::assertSame($expectedQueries, $actualQueries);
    }

    /**
     * @test
     */
    public function testIsolationRepeatableRead(): void
    {
        Transaction::new(Transaction::REPEATABLE_READ)(
            static function (): void {
            }
        );

        global $wpdb;
        $actualQueries = array_column($wpdb->queries, 0);
        $expectedQueries = [
            'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ;',
            'START TRANSACTION;',
            'COMMIT;',
        ];

        static::assertSame($expectedQueries, $actualQueries);
    }

    /**
     * @test
     */
    public function testFailedQueryMakeItErroredEvenIfCallableReturnedValue(): void
    {
        $result = Transaction::new()(
            static function (): Result {
                global $wpdb;
                $wpdb->last_error = 'Something failed!';

                return Result::new(1);
            }
        );

        static::assertTrue($result->isErrored());
        static::assertSame('Something failed!', $result->error()->getMessage());
    }

    /**
     * @test
     */
    public function testMergingSuccessfulResultsInTransaction(): void
    {
        $cb1 = static function (): Result {
            return Result::new(1);
        };

        $cb2 = static function (Result $result): Result {
            return $result->merge(Result::new(2));
        };

        $cb3 = static function (Result $result): Result {
            return $result->merge(Result::new(3));
        };

        $result = Transaction::new()(
            static function () use ($cb1, $cb2, $cb3): Result {
                return $cb3($cb2($cb1()));
            }
        );

        static::assertSame(3, $result->extract());
    }

    /**
     * @test
     */
    public function testMergingResultsWithErrorInTransaction(): void
    {
        $cb1 = static function (): Result {
            return Result::new(1);
        };

        $cb2 = static function (Result $result): Result {
            return $result->merge(Result::new(new \Exception('Meh')));
        };

        $cb3 = static function (Result $result): Result {
            return $result->merge(Result::new(3));
        };

        $result = Transaction::new()(
            static function () use ($cb1, $cb2, $cb3): Result {
                return $cb3($cb2($cb1()));
            }
        );

        static::assertSame('Meh', $result->error()->getMessage());
    }

    /**
     * @test
     */
    public function testMultipleSuccessfulCallbacksInTransaction(): void
    {
        $cb2 = static function (): Result {
            return Result::new(2);
        };

        $cb3 = static function (Result $result): Result {
            return $result->merge(Result::new(3));
        };

        $result = Transaction::new()(
            static function (): Result {
                return Result::new(1);
            },
            static function () use ($cb2, $cb3): Result {
                return $cb3($cb2());
            },
            static function (): Result {
                return Result::new(4);
            }
        );

        static::assertSame(4, $result->extract());
    }

    /**
     * @test
     */
    public function testMultipleCallbacksWithErrorsInTransaction(): void
    {
        $result = Transaction::new()(
            static function (): Result {
                return Result::new(1);
            },
            static function (): Error {
                return new Error('First.');
            },
            static function (): int {
                return 3;
            },
            static function (): Result {
                return Result::new(new \Exception('Second.'));
            },
            static function (): Result {
                return Result::new(5);
            }
        );

        static::assertSame('Second.', $result->error()->getMessage());
        static::assertSame('First.', $result->error()->getPrevious()->getMessage());
    }

    /**
     * @test
     */
    public function testMultipleCallbacksForwardingResultValue(): void
    {
        $insert1 = static function (): Result {
            $insert1 = Result::new(['rows' => 1, 'insertId' => 123]);
            if ($insert1->isErrored()) {
                return $insert1;
            }

            $data = new \stdClass();
            $data->ids = [(int) $insert1->extract()['insertId']];

            return Result::new($data);
        };

        $insert2 = static function (Result $previous): Result {
            if ($previous->isErrored()) {
                return $previous;
            }

            $insert2 = Result::new(['rows' => 1, 'insertId' => 456]);
            if ($insert2->isErrored()) {
                return $insert2;
            }

            $data = $previous->extract();
            $data->ids[] = (int) $insert2->extract()['insertId'];

            return Result::new($data);
        };

        $result = Transaction::new()($insert1, $insert2);

        static::assertSame([123, 456], $result->extract()->ids);
    }

    /**
     * @test
     */
    public function testWithConsistentSnapshot(): void
    {
        Transaction::new(Transaction::CONSISTENT_SNAPSHOT)(
            static function (): void {
            }
        );

        global $wpdb;
        $actualQueries = array_column($wpdb->queries, 0);

        $expectedQueries = [
            'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ;',
            'START TRANSACTION WITH CONSISTENT SNAPSHOT;',
            'COMMIT;',
        ];

        static::assertSame($expectedQueries, $actualQueries);
    }

    /**
     * @test
     */
    public function testWithConsistentSnapshotWithOverrideIsolation(): void
    {
        Transaction::new(Transaction::SERIALIZABLE | Transaction::CONSISTENT_SNAPSHOT)(
            static function (): void {
            }
        );

        global $wpdb;
        $actualQueries = array_column($wpdb->queries, 0);

        $expectedQueries = [
            'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ;',
            'START TRANSACTION WITH CONSISTENT SNAPSHOT;',
            'COMMIT;',
        ];

        static::assertSame($expectedQueries, $actualQueries);
    }

    /**
     * @test
     */
    public function testReadOnlyMode(): void
    {
        Transaction::new(Transaction::READ_ONLY)(
            static function (): void {
            }
        );

        global $wpdb;
        $actualQueries = array_column($wpdb->queries, 0);

        $expectedQueries = [
            'START TRANSACTION READ ONLY;',
            'COMMIT;',
        ];

        static::assertSame($expectedQueries, $actualQueries);
    }

    /**
     * @test
     */
    public function testReadCommittedAndReadOnlyMode(): void
    {
        Transaction::new(Transaction::READ_COMMITTED | Transaction::READ_ONLY)(
            static function (): void {
            }
        );

        global $wpdb;
        $actualQueries = array_column($wpdb->queries, 0);

        $expectedQueries = [
            'SET TRANSACTION ISOLATION LEVEL READ COMMITTED;',
            'START TRANSACTION READ ONLY;',
            'COMMIT;',
        ];

        static::assertSame($expectedQueries, $actualQueries);
    }

    /**
     * @test
     */
    public function testReadUncommittedAndReadWriteMode(): void
    {
        Transaction::new(Transaction::READ_UNCOMMITTED | Transaction::READ_WRITE)(
            static function (): void {
            }
        );

        global $wpdb;
        $actualQueries = array_column($wpdb->queries, 0);

        $expectedQueries = [
            'SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;',
            'START TRANSACTION READ WRITE;',
            'COMMIT;',
        ];

        static::assertSame($expectedQueries, $actualQueries);
    }

    /**
     * @test
     */
    public function testReadOnlyModeWithConsistentSnapshot(): void
    {
        Transaction::new(Transaction::CONSISTENT_SNAPSHOT | Transaction::READ_ONLY)(
            static function (): void {
            }
        );

        global $wpdb;
        $actualQueries = array_column($wpdb->queries, 0);

        $expectedQueries = [
            'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ;',
            'START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY;',
            'COMMIT;',
        ];

        static::assertSame($expectedQueries, $actualQueries);
    }

    /**
     * @test
     */
    public function testReadWriteWithConsistentSnapshot(): void
    {
        Transaction::new(Transaction::CONSISTENT_SNAPSHOT | Transaction::READ_WRITE)(
            static function (): void {
            }
        );

        global $wpdb;
        $actualQueries = array_column($wpdb->queries, 0);

        $expectedQueries = [
            'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ;',
            'START TRANSACTION WITH CONSISTENT SNAPSHOT, READ WRITE;',
            'COMMIT;',
        ];

        static::assertSame($expectedQueries, $actualQueries);
    }
}
