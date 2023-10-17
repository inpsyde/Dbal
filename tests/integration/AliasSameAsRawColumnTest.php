<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Tests\Integration;

use Inpsyde\Dbal\Dbal;
use Inpsyde\Dbal\Query\Where;
use Inpsyde\Dbal\Tests\QueriesTestCase;
use Inpsyde\Dbal\Tests\TableOne;

class AliasSameAsRawColumnTest extends QueriesTestCase
{
    /**
     * @test
     */
    public function testSelectAliasSameAsColumn(): void
    {
        $this->insertData();

        $result = Dbal::select(TableOne::NAME)
            ->rawCol('DISTINCT(' . TableOne::TEXT . ')', TableOne::TEXT)
            ->where(TableOne::TEXT, '', Where::NOT_EQ)
            ->all();

        $result->assert();

        static::assertSame(['One', 'Two'], $result->toColumnArray(TableOne::TEXT));
    }

    /**
     * @test
     */
    public function testSelectAliasDifferentThanColumn(): void
    {
        $this->insertData();

        $result = Dbal::select(TableOne::NAME)
            ->rawCol('DISTINCT(' . TableOne::TEXT . ')', 'textDistinct')
            ->where(TableOne::TEXT, '', Where::NOT_EQ)
            ->all();

        $result->assert();

        static::assertSame(['One', 'Two'], $result->toColumnArray('textDistinct'));
    }

    /**
     * @return void
     */
    private function insertData(): void
    {
        $write = Dbal::writeOn(TableOne::NAME)
            ->insertMany(
                [TableOne::POST_ID => 1, TableOne::TEXT => 'One'],
                [TableOne::POST_ID => 2, TableOne::TEXT => 'One'],
                [TableOne::POST_ID => 3, TableOne::TEXT => 'Two'],
                [TableOne::POST_ID => 4, TableOne::TEXT => 'One']
            );

        $write->assert();
    }
}
