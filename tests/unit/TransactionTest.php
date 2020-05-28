<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Tests\Unit;

use Inpsyde\Dbal\Error;
use Inpsyde\Dbal\Result;
use Inpsyde\Dbal\Tests\UnitTestCase;
use Inpsyde\Dbal\Transaction;

class TransactionTest extends UnitTestCase
{
    public function testDefaultTransactionSuccess()
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

    public function testDefaultTransactionError()
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

    public function testDefaultTransactionNoSignal()
    {
        $result = Transaction::new()(
            static function (): void {}
        );

        global $wpdb;
        $actualQueries = array_column($wpdb->queries, 0);
        $expectedQueries = ['START TRANSACTION;', 'COMMIT;'];

        static::assertFalse($result->isErrored());
        static::assertSame($expectedQueries, $actualQueries);
        static::assertNull($result->extract());
    }

    public function testDefaultTransactionWithWarning()
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

    public function testDefaultTransactionWithException()
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

    public function testDefaultTransactionWhenTransactionSetupFails()
    {
        $this->infixNextWpdbQueryResult(false);

        $result = Transaction::new(Transaction::READ_COMMITTED)(
            static function (): void {
                static::assertTrue(false);
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

    public function testDefaultTransactionWhenTransactionStartFails()
    {
        $this->infixNumWpdbQueryResult(2, false);

        $result = Transaction::new(Transaction::READ_COMMITTED)(
            static function (): void {
                static::assertTrue(false);
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

    public function testIsolationReadCommitted()
    {
        Transaction::new(Transaction::READ_COMMITTED)(
            static function (): void {}
        );

        global $wpdb;
        $actualQueries = array_column($wpdb->queries, 0);
        $expectedQueries = [
            'SET TRANSACTION ISOLATION LEVEL READ COMMITTED;',
            'START TRANSACTION;',
            'COMMIT;'
        ];

        static::assertSame($expectedQueries, $actualQueries);
    }

    public function testIsolationReadUncommitted()
    {
        Transaction::new(Transaction::READ_UNCOMMITTED)(
            static function (): void {}
        );

        global $wpdb;
        $actualQueries = array_column($wpdb->queries, 0);
        $expectedQueries = [
            'SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;',
            'START TRANSACTION;',
            'COMMIT;'
        ];

        static::assertSame($expectedQueries, $actualQueries);
    }

    public function testIsolationSerializable()
    {
        Transaction::new(Transaction::SERIALIZABLE)(
            static function (): void {}
        );

        global $wpdb;
        $actualQueries = array_column($wpdb->queries, 0);
        $expectedQueries = [
            'SET TRANSACTION ISOLATION LEVEL SERIALIZABLE;',
            'START TRANSACTION;',
            'COMMIT;'
        ];

        static::assertSame($expectedQueries, $actualQueries);
    }

    public function testIsolationRepeatableRead()
    {
        Transaction::new(Transaction::REPEATABLE_READ)(
            static function (): void {}
        );

        global $wpdb;
        $actualQueries = array_column($wpdb->queries, 0);
        $expectedQueries = [
            'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ;',
            'START TRANSACTION;',
            'COMMIT;'
        ];

        static::assertSame($expectedQueries, $actualQueries);
    }

    public function testFailedQueryMakeItErroredEvenIfCallableReturnedValue()
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

    public function testMergingSuccessfulResultsInTransaction()
    {
        $f1 = static function (): Result {
            return Result::new(1);
        };

        $f2 = static function (Result $result): Result {
            return $result->merge(Result::new(2));
        };

        $f3 = static function (Result $result): Result {
            return $result->merge(Result::new(3));
        };

        $result = Transaction::new()(
            static function () use ($f1, $f2, $f3): Result {
                return $f3($f2($f1()));
            }
        );

        static::assertSame(3, $result->extract());
    }

    public function testMergingResultsWithErrorInTransaction()
    {
        $f1 = static function (): Result {
            return Result::new(1);
        };

        $f2 = static function (Result $result): Result {
            return $result->merge(Result::new(new \Exception('Meh')));
        };

        $f3 = static function (Result $result): Result {
            return $result->merge(Result::new(3));
        };

        $result = Transaction::new()(
            static function () use ($f1, $f2, $f3): Result {
                return $f3($f2($f1()));
            }
        );

        static::assertSame('Meh', $result->error()->getMessage());
    }

    public function testMultipleSuccessfulCallbacksInTransaction()
    {
        $f2 = static function (): Result {
            return Result::new(2);
        };

        $f3 = static function (Result $result): Result {
            return $result->merge(Result::new(3));
        };

        $result = Transaction::new()(
            static function (): Result {
                return Result::new(1);
            },
            static function () use ($f2, $f3): Result {
                return $f3($f2());
            },
            static function (): Result {
                return Result::new(4);
            }
        );

        static::assertSame(4, $result->extract());
    }

    public function testMultipleCallbacksWithErrorsInTransaction()
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

    public function testMultipleCallbacksForwardingResultValue()
    {
        $insert1 = function (): Result {
            $insert1 = Result::new(['rows' => 1, 'insertId' => 123]);
            if ($insert1->isErrored()) {
                return $insert1;
            }

            $data = new \stdClass();
            $data->ids = [(int)$insert1->extract()['insertId']];

            return  Result::new($data);
        };

        $insert2 = function (Result $previous): Result {
            if ($previous->isErrored()) {
                return $previous;
            }

            $insert2 = Result::new(['rows' => 1, 'insertId' => 456]);
            if ($insert2->isErrored()) {
                return $insert2;
            }

            $data = $previous->extract();
            $data->ids[] = (int)$insert2->extract()['insertId'];

            return Result::new($data);
        };

        $result = Transaction::new()($insert1, $insert2);

        static::assertSame([123, 456], $result->extract()->ids);
    }

    public function testWithConsistentSnapshot()
    {
        Transaction::new(Transaction::CONSISTENT_SNAPSHOT)(
            static function (): void {}
        );

        global $wpdb;
        $actualQueries = array_column($wpdb->queries, 0);

        $expectedQueries = [
            'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ;',
            'START TRANSACTION WITH CONSISTENT SNAPSHOT;',
            'COMMIT;'
        ];

        static::assertSame($expectedQueries, $actualQueries);
    }

    public function testWithConsistentSnapshotWithOverrideIsolation()
    {
        Transaction::new(Transaction::SERIALIZABLE|Transaction::CONSISTENT_SNAPSHOT)(
            static function (): void {}
        );

        global $wpdb;
        $actualQueries = array_column($wpdb->queries, 0);

        $expectedQueries = [
            'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ;',
            'START TRANSACTION WITH CONSISTENT SNAPSHOT;',
            'COMMIT;'
        ];

        static::assertSame($expectedQueries, $actualQueries);
    }

    public function testReadOnlyMode()
    {
        Transaction::new(Transaction::READ_ONLY)(
            static function (): void {}
        );

        global $wpdb;
        $actualQueries = array_column($wpdb->queries, 0);

        $expectedQueries = [
            'START TRANSACTION READ ONLY;',
            'COMMIT;'
        ];

        static::assertSame($expectedQueries, $actualQueries);
    }

    public function testReadCommittedAndReadOnlyMode()
    {
        Transaction::new(Transaction::READ_COMMITTED|Transaction::READ_ONLY)(
            static function (): void {}
        );

        global $wpdb;
        $actualQueries = array_column($wpdb->queries, 0);

        $expectedQueries = [
            'SET TRANSACTION ISOLATION LEVEL READ COMMITTED;',
            'START TRANSACTION READ ONLY;',
            'COMMIT;'
        ];

        static::assertSame($expectedQueries, $actualQueries);
    }

    public function testReadUncommittedAndReadWriteMode()
    {
        Transaction::new(Transaction::READ_UNCOMMITTED|Transaction::READ_WRITE)(
            static function (): void {}
        );

        global $wpdb;
        $actualQueries = array_column($wpdb->queries, 0);

        $expectedQueries = [
            'SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;',
            'START TRANSACTION READ WRITE;',
            'COMMIT;'
        ];

        static::assertSame($expectedQueries, $actualQueries);
    }

    public function testReadOnlyModeWithConsistentSnapshot()
    {
        Transaction::new(Transaction::CONSISTENT_SNAPSHOT|Transaction::READ_ONLY)(
            static function (): void {}
        );

        global $wpdb;
        $actualQueries = array_column($wpdb->queries, 0);

        $expectedQueries = [
            'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ;',
            'START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY;',
            'COMMIT;'
        ];

        static::assertSame($expectedQueries, $actualQueries);
    }

    public function testReadWriteWithConsistentSnapshot()
    {
        Transaction::new(Transaction::CONSISTENT_SNAPSHOT|Transaction::READ_WRITE)(
            static function (): void {}
        );

        global $wpdb;
        $actualQueries = array_column($wpdb->queries, 0);

        $expectedQueries = [
            'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ;',
            'START TRANSACTION WITH CONSISTENT SNAPSHOT, READ WRITE;',
            'COMMIT;'
        ];

        static::assertSame($expectedQueries, $actualQueries);
    }
}
