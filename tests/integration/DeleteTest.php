<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Tests\Integration;

use Inpsyde\Dbal\Dbal;
use Inpsyde\Dbal\Query\Where;
use Inpsyde\Dbal\Tests\QueriesTestCase;
use Inpsyde\Dbal\Tests\TableOne;
use Inpsyde\Dbal\Tests\TablePivot;
use Inpsyde\Dbal\Tests\TableTwo;

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

        Dbal::select(TableOne::NAME)
            ->where(TableOne::POST_ID, 1, Where::GREATER)
            ->andWhere(TableOne::TEXT, ['One', 'Four'], Where::NOT_IN)
            ->delete()
            ->assert();

        $ids = Dbal::select(TableOne::NAME, 't')
            ->cols(TableOne::POST_ID)
            ->orderBy(TableOne::POST_ID)
            ->limit(100)
            ->all()
            ->toColumnArray(TableOne::POST_ID);

        static::assertSame([1, 4], $ids);
    }
}
