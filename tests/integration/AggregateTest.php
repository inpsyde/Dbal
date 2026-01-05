<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Tests\Integration;

use Inpsyde\Dbal\Dbal;
use Inpsyde\Dbal\Tests\QueriesTestCase;
use Inpsyde\Dbal\Tests\TableOne;
use Inpsyde\Dbal\Tests\TablePivot;
use Inpsyde\Dbal\Tests\TableTwo;

/**
 * @runTestsInSeparateProcesses
 */
class AggregateTest extends QueriesTestCase
{
    /**
     * @test
     */
    public function testCount(): void
    {
        $count = 5;

        $oneId = Dbal::writeOn(TableOne::NAME)
            ->insert([TableOne::POST_ID => 1, TableOne::TEXT => 'Foo'])
            ->extract()
            ->insertId;

        $writeOnTwo = Dbal::writeOn(TableTwo::NAME);
        $writeOnPivot = Dbal::writeOn(TablePivot::NAME);
        for ($i = 0; $i < $count; $i++) {
            $twoId = $writeOnTwo
                ->insert([TableTwo::VARCHAR => '', TableTwo::DECIMAL => $i])
                ->extract()
                ->insertId;

            $writeOnPivot->insert([TablePivot::ONE => $oneId, TablePivot::TWO => $twoId])->assert();
        }

        $result = Dbal::select(TableTwo::NAME, 'two')
            ->joinViaPivot(TableOne::NAME, TablePivot::NAME, TablePivot::TWO, TablePivot::ONE)
            ->cols(TableOne::NAME . '.' . TableOne::TEXT)
            ->andRawCol('COUNT(`two`.`id`)', 'countTwo')
            ->where(TableOne::NAME . '.' . TableOne::POST_ID, 0, '>')
            ->andWhere('two.' . TableTwo::DECIMAL, 2, '>')
            ->pickFirst()
            ->map(
                static function (array $row): array {
                    $row['countTwo'] = (int) $row['countTwo'];

                    return $row;
                }
            );

        $result->assert();

        static::assertSame(['countTwo' => 2, 'text' => 'Foo'], $result->first());
    }
}
