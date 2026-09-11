<?php

declare(strict_types=1);

namespace Syde\Dbal\Tests\Unit\Query;

use Syde\Dbal\Query\Aliases;
use Syde\Dbal\Query\Compare;
use Syde\Dbal\Query\Where;
use Syde\Dbal\Tests\TableOne;
use Syde\Dbal\Tests\TableTwo;
use Syde\Dbal\Tests\UnitTestCase;

class WhereTest extends UnitTestCase
{
    /**
     * @test
     */
    public function testComplexClause(): void
    {
        $main = new TableOne();
        $finder = $this->initializeSampleTablesFinder();
        $aliases = Aliases::new($finder)
            ->forSchema(TableOne::NAME, 'main')
            ->forColumn(TableTwo::VARCHAR, 'desc', TableTwo::NAME);

        $innerAnd = Where::new()
            ->with(TableOne::INTEGER, 20, '>')
            ->or(TableOne::INTEGER, 10, '<=')
            ->andRaw('NOW()', TableOne::DATETIME, '>=');

        $innerOr = Where::new()
            ->with(TableOne::DATETIME, '0000-00-00 00:00:00')
            ->orRaw('NOW()', TableOne::DATETIME, '>=');

        $compare = Compare::columns('main.' . TableOne::DATETIME, 'desc', '>');
        $whereComp = Where::new()->withCompare($compare->castRight(Compare::CAST_DATETIME));

        $where = Where::new()
            ->with(TableOne::TEXT, '%foo', 'LIKE')
            ->and(TableOne::ENUM, 'yes')
            ->and(TableOne::DOUBLE, [0.1, 0.2], Where::NOT_IN)
            ->andWhere($innerAnd)
            ->and(TableTwo::NAME . '.' . TableTwo::ID, 12, '>')
            ->orWhere($whereComp)
            ->and(TableOne::SERIALIZED, null, Where::IS_NOT)
            ->and(TableTwo::NAME . '.' . TableTwo::VARCHAR, null)
            ->orWhere($innerOr);

        $expectedRaw = <<<'EXP'
`main`.`text` LIKE '%foo'
AND `main`.`enum` = 'yes'
AND `main`.`double` NOT IN ('0.1','0.2')
AND (`main`.`integer` > 20 OR `main`.`integer` <= 10 AND `main`.`datetime` >= NOW())
AND `wp_1_tests_sample_table_two`.`id` > 12
OR (`main`.`datetime` > CAST(`wp_1_tests_sample_table_two`.`varchar` AS DATETIME))
AND `main`.`serialized` IS NOT NULL
AND `wp_1_tests_sample_table_two`.`varchar` IS NULL
OR (`main`.`datetime` = '0000-00-00 00:00:00' OR `main`.`datetime` >= NOW())
EXP;
        global $wpdb;
        $actualRaw = $wpdb->remove_placeholder_escape($where->clause($main, $finder, $aliases));

        $expected = trim(preg_replace('/\s+/', ' ', $expectedRaw));
        $actual = trim(preg_replace('/\s+/', ' ', $actualRaw));

        static::assertSame($expected, $actual);
    }
}
