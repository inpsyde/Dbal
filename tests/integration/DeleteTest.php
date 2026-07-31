<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Tests\Integration;

use Inpsyde\Dbal\Dbal;
use Inpsyde\Dbal\Query\Delete;
use Inpsyde\Dbal\Query\Where;
use Inpsyde\Dbal\Tests\QueriesTestCase;
use Inpsyde\Dbal\Tests\TableOne;
use Inpsyde\Dbal\Tests\TableTwo;

        /**
 * @runTestsInSeparateProcesses
 */
class DeleteTest extends QueriesTestCase
{
    /**
     * @test
     */
    public function testDeleteMultiple(): void
    {
        Dbal::writeOn(TableOne::NAME)
            ->insertMany(
                [TableOne::POST_ID => 1, TableOne::TEXT => 'One'],
                [TableOne::POST_ID => 2, TableOne::TEXT => 'Two'],
                [TableOne::POST_ID => 3, TableOne::TEXT => 'Three'],
                [TableOne::POST_ID => 4, TableOne::TEXT => 'Four']
            )
            ->assert();

        Dbal::deleteFrom(TableOne::NAME)
            ->where(TableOne::POST_ID, 1, Where::GREATER)
            ->andWhere(TableOne::TEXT, ['One', 'Four'], Where::NOT_IN)
            ->exec()
            ->assert();

        $ids = Dbal::select(TableOne::NAME, 't')
            ->cols(TableOne::POST_ID)
            ->orderBy(TableOne::POST_ID)
            ->limit(100)
            ->all()
            ->toColumnArray(TableOne::POST_ID);

        static::assertSame([1, 4], $ids);
    }

    /**
     * @test
     */
    public function testDeleteOnPrimary(): void
    {
        Dbal::writeOn(TableOne::NAME)
            ->insertMany(
                [TableOne::POST_ID => 1, TableOne::TEXT => 'One'],
                [TableOne::POST_ID => 2, TableOne::TEXT => 'Two'],
                [TableOne::POST_ID => 3, TableOne::TEXT => 'Three'],
                [TableOne::POST_ID => 4, TableOne::TEXT => 'Four']
            )
            ->assert();

        Dbal::deleteFrom(TableOne::NAME)->delOnPrimary([1, 4])->assert();

        $ids = Dbal::select(TableOne::NAME, 't')
            ->cols(TableOne::POST_ID)
            ->orderBy(TableOne::POST_ID)
            ->limit(100)
            ->all()
            ->toColumnArray(TableOne::POST_ID);

        static::assertSame([2, 3], $ids);
    }

    /**
     * @test
     */
    public function testDeleteWithJoin(): void
    {
        Dbal::writeOn(TableOne::NAME)
            ->insertMany(
                [TableOne::POST_ID => 1, TableOne::TEXT => 'One'],
                [TableOne::POST_ID => 2, TableOne::TEXT => 'Two'],
                [TableOne::POST_ID => 3, TableOne::TEXT => 'Three'],
                [TableOne::POST_ID => 4, TableOne::TEXT => 'Four']
            )
            ->assert();

        Dbal::writeOn(TableTwo::NAME)
            ->insertMany(
                [TableTwo::ID => 1, TableTwo::VARCHAR => 'a', TableTwo::DECIMAL => 1.0],
                [TableTwo::ID => 2, TableTwo::VARCHAR => 'b', TableTwo::DECIMAL => 2.0],
                [TableTwo::ID => 3, TableTwo::VARCHAR => 'c', TableTwo::DECIMAL => 3.0],
                [TableTwo::ID => 4, TableTwo::VARCHAR => 'd', TableTwo::DECIMAL => 4.0]
            )
            ->assert();

        $idsOne = Dbal::select(TableOne::NAME)
            ->cols(TableOne::POST_ID)
            ->limit(100)
            ->all()
            ->toColumnArray(TableOne::POST_ID);

        $idsTwo = Dbal::select(TableTwo::NAME)
            ->cols(TableTwo::ID)
            ->limit(100)
            ->all()
            ->toColumnArray(TableTwo::ID);

        static::assertSame([1, 2, 3, 4], $idsOne);
        static::assertSame([1, 2, 3, 4], $idsTwo);

        Dbal::deleteFrom(TableOne::NAME)
            ->innerJoin(TableTwo::NAME, TableOne::POST_ID, TableTwo::ID)
            ->where(TableOne::TEXT, 'Th%', Where::NOT_LIKE)
            ->exec()
            ->assert();

        $idsOne = Dbal::select(TableOne::NAME)
            ->cols(TableOne::POST_ID)
            ->limit(100)
            ->all()
            ->toColumnArray(TableOne::POST_ID);

        $idsTwo = Dbal::select(TableTwo::NAME)
            ->cols(TableTwo::ID)
            ->limit(100)
            ->all()
            ->toColumnArray(TableTwo::ID);

        static::assertSame([3], $idsOne);
        static::assertSame([3], $idsTwo);
    }

    /**
     * @test
     */
    public function testDeleteWithLimit(): void
    {
        // MySQL's `DELETE ... ORDER BY ... LIMIT` extension has no equivalent the SQLite
        // Database Integration plugin can translate to; it fails with a plain syntax error.
        $this->markTestSkipped('DELETE with ORDER BY/LIMIT is not supported by the SQLite test environment.');

        Dbal::writeOn(TableOne::NAME)
            ->insertMany(
                [TableOne::POST_ID => 1, TableOne::TEXT => 'One'],
                [TableOne::POST_ID => 2, TableOne::TEXT => 'Two'],
                [TableOne::POST_ID => 3, TableOne::TEXT => 'Three'],
                [TableOne::POST_ID => 4, TableOne::TEXT => 'Four']
            )
            ->assert();

        $ids = Dbal::select(TableOne::NAME)
            ->cols(TableOne::POST_ID)
            ->limit(100)
            ->all()
            ->toColumnArray(TableOne::POST_ID);

        static::assertSame([1, 2, 3, 4], $ids);

        Dbal::deleteFrom(TableOne::NAME)
            ->orderBy(TableOne::POST_ID, Delete::DESC)
            ->limit(2)
            ->exec()
            ->assert();

        $ids = Dbal::select(TableOne::NAME)
            ->cols(TableOne::POST_ID)
            ->limit(100)
            ->all()
            ->toColumnArray(TableOne::POST_ID);

        static::assertSame([1, 2], $ids);
    }
}
