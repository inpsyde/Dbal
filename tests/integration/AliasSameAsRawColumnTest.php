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
    public function testSelectAliasAndWhereIsFailing(): void
    {
        $result = Dbal::select(TableOne::NAME)
           ->rawCol('DISTINCT(' . TableOne::TEXT . ')', TableOne::TEXT)
            ->where(TableOne::TEXT, '', Where::NOT_EQ)
            ->all();

        $result->assert();

        static::assertEmpty($result->jsonSerialize());
    }

    /**
     * @test
     */
    public function testSelectAliasAndWhere(): void
    {
        $result = Dbal::select(TableOne::NAME)
            ->rawCol('DISTINCT(' . TableOne::TEXT . ')', 'textDistinct')
            ->where(TableOne::TEXT, '', Where::NOT_EQ)
            ->all();

        $result->assert();

        static::assertEmpty($result->jsonSerialize());
    }
}
