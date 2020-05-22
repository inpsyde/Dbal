<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Tests\Unit;

use Inpsyde\Dbal\ErrorCollector;
use Inpsyde\Dbal\Tests\UnitTestCase;
use Inpsyde\Dbal\Transaction;

class TransactionTest extends UnitTestCase
{
    public function testDefaultTransactionSuccess()
    {
        $result = Transaction::new()(
            static function (callable $success, ErrorCollector $errors): void {
                static::assertTrue($errors->isEmpty());

                $success('Success!');
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
            static function (callable $success, ErrorCollector $errors): void {
                $errors->withError('Failed!');
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

        static::assertTrue($result->isErrored());
        static::assertSame($expectedQueries, $actualQueries);

        $this->expectExceptionMessageMatches('/not signal/i');
        $result->extract();
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
