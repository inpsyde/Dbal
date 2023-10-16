<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Tests\Unit\Query;

use Inpsyde\Dbal\Query\Compare;
use Inpsyde\Dbal\Query\Select;
use Inpsyde\Dbal\Query\Where;
use Inpsyde\Dbal\Tests\TableOne;
use Inpsyde\Dbal\Tests\TablePivot;
use Inpsyde\Dbal\Tests\TableTwo;
use Inpsyde\Dbal\Tests\UnitTestCase;

class SelectTest extends UnitTestCase
{
    /**
     * @test
     */
    public function testJoinViaPivot(): void
    {
        $finder = $this->initializeSampleTablesFinder();

        $select = Select::from(TableOne::NAME, 'one', $finder)
            ->joinViaPivot(TableTwo::NAME, TablePivot::NAME, TablePivot::ONE, TablePivot::TWO)
            ->cols(TableOne::INTEGER, TableOne::TEXT, TableTwo::NAME . '.' . TableTwo::VARCHAR)
            ->where(TableOne::ENUM, 'yes')
            ->andWhere(TableTwo::NAME . '.' . TableTwo::DECIMAL, 10.123, '>=');

        $expected = <<<QUERY
SELECT `one`.`integer`, `one`.`text`, `wp_1_tests_sample_table_two`.`varchar`
FROM `wp_1_tests_sample_table` AS `one`
INNER JOIN `wp_1_tests_sample_table_pivot`
    ON `one`.`id` = `wp_1_tests_sample_table_pivot`.`one_id`
INNER JOIN `wp_1_tests_sample_table_two`
    ON `wp_1_tests_sample_table_pivot`.`two_id` = `wp_1_tests_sample_table_two`.`id`
WHERE `one`.`enum` = 'yes'
    AND `wp_1_tests_sample_table_two`.`decimal` >= '10.123'
QUERY;
        $this->assertSameQuery($expected, $select->buildSqlNoEscape());
    }

    /**
     * @test
     */
    public function testCompletePaginatedSelect(): void
    {
        $finder = $this->initializeSampleTablesFinder();

        $comp1 = Compare::columns('na', TableOne::DATETIME, '>')
            ->castLeft(Compare::CAST_DATETIME);

        $comp2 = Compare::columns(TableOne::INTEGER, 's.' . TableTwo::DECIMAL, '>')
            ->castRight(Compare::CAST_UNSIGNED);

        $comp3 = Compare::columnValue('s.' . TableTwo::DECIMAL, '3', '<')
            ->castLeft(Compare::CAST_UNSIGNED);

        $select = Select::from(TableOne::NAME, 'm', $finder)
            ->innerJoin(TableTwo::NAME, TableOne::INTEGER, TableTwo::ID, 's')
            ->cols(TableOne::ID, TableOne::ENUM, 's.' . TableTwo::DECIMAL)
            ->andCol('s.' . TableTwo::VARCHAR, 'na')
            ->where(TableOne::INTEGER, '123', '<')
            ->orWhereCompare($comp1)
            ->andWhere('s.' . TableTwo::VARCHAR, 'prefix%', 'NOT LIKE')
            ->andWhereUsing(Where::new()->withCompare($comp2)->orCompare($comp3))
            ->orderBy(TableOne::ID)
            ->thenOrderBy('s.' . TableTwo::ID, 'DESC')
            ->thenOrderByRaw('ABS(`s`.`' . TableTwo::DECIMAL . '`)')
            ->groupBy(TableOne::ID)
            ->paginated(3, 25);

        $expected = <<<QUERY
SELECT SQL_CALC_FOUND_ROWS `m`.`id`, `m`.`enum`, `s`.`decimal`, `s`.`varchar` AS `na`
    FROM `wp_1_tests_sample_table` AS `m`
    INNER JOIN `wp_1_tests_sample_table_two` AS `s` ON `m`.`integer` = `s`.`id`
WHERE `m`.`integer` < 123
    OR CAST(`s`.`varchar` AS DATETIME) > `m`.`datetime`
    AND `s`.`varchar` NOT LIKE 'prefix%'
    AND (`m`.`integer` > CAST(`s`.`decimal` AS UNSIGNED) OR CAST(`s`.`decimal` AS UNSIGNED) < 3)
GROUP BY `m`.`id`
ORDER BY `m`.`id` ASC, `s`.`id` DESC, ABS(`s`.`decimal`) ASC
LIMIT 50, 25
QUERY;

        $this->assertSameQuery($expected, $select->buildSqlNoEscape());
    }

    /**
     * @test
     */
    public function testWhereEncoding(): void
    {
        $finder = $this->initializeSampleTablesFinder();

        $dateFmt = 'Y-m-d H:i:s';
        $utc = new \DateTimeZone('UTC');
        $expectedDate = \DateTimeImmutable::createFromFormat($dateFmt, '2012-12-20 20:12:20', $utc);

        $dateInRome = $expectedDate->setTimezone(new \DateTimeZone('Europe/Rome'));

        static::assertNotSame($expectedDate, $dateInRome);
        static::assertSame('2012-12-20 20:12:20', $expectedDate->format($dateFmt));
        static::assertSame('2012-12-20 21:12:20', $dateInRome->format($dateFmt));

        $array = ['foo' => 'bar', 'bar' => 'baz'];
        $serialized = esc_sql(serialize(['foo' => 'bar', 'bar' => 'baz']));

        // We pass a Date object in Rome timezone, but then expect in UTC timezone, because that
        // is what is set in the Table definition
        $select = Select::from(TableOne::NAME, null, $finder)
            ->allCols()
            ->where(TableOne::DATETIME, $dateInRome)
            ->andWhere(TableOne::SERIALIZED, $array);

        $actualSql = $select->buildSqlNoEscape();

        $expectedSql = <<<SQL
SELECT `wp_1_tests_sample_table`.* FROM `wp_1_tests_sample_table`
WHERE `wp_1_tests_sample_table`.`datetime` = '2012-12-20 20:12:20'
  AND `wp_1_tests_sample_table`.`serialized` = '{$serialized}'
SQL;
        static::assertFalse($select->hasErrors());
        $this->assertSameQuery($expectedSql, $actualSql);
    }

    /**
     * @test
     */
    public function testAliasesWithJoinWhere(): void
    {
        $finder = $this->initializeSampleTablesFinder();

        $select = Select::from(TableOne::NAME, 'm', $finder)
            ->innerJoinWhere(TableTwo::NAME, Where::new()->withCompare(
                Compare::columns('m.' . TableOne::ID, 's.' . TableTwo::ID, Where::EQ)
            ),
                's')
            ->andCol('m.' . TableOne::ID, 'Table One Id');


        $expected = <<<QUERY
SELECT `m`.`id` AS `Table One Id`
    FROM `wp_1_tests_sample_table` AS `m`
    INNER JOIN `wp_1_tests_sample_table_two` AS `s` ON `m`.`id` = `s`.`id`
QUERY;

        $this->assertSameQuery($expected, $select->buildSqlNoEscape());
    }

    /**
     * @test
     */
    public function testAliasesWithJoin(): void
    {
        $finder = $this->initializeSampleTablesFinder();

        $select = Select::from(TableOne::NAME, 'm', $finder)
            ->innerJoin(TableTwo::NAME, 'm.' . TableOne::ID, 's.' . TableTwo::ID,'s')
            ->andCol('m.' . TableOne::ID, 'Table One Id');


        $expected = <<<QUERY
SELECT `m`.`id` AS `Table One Id`
    FROM `wp_1_tests_sample_table` AS `m`
    INNER JOIN `wp_1_tests_sample_table_two` AS `s` ON `m`.`id` = `s`.`id`
QUERY;

        $this->assertSameQuery($expected, $select->buildSqlNoEscape());
    }

    /**
     * @test
     */
    public function testAliasesForEqualColumns(): void
    {
        $finder = $this->initializeSampleTablesFinder();

        $select = Select::from(TableOne::NAME, 'm', $finder)
            ->innerJoin(TableTwo::NAME, 'm.' . TableOne::ID, 's.' . TableTwo::ID,'s')
            ->andCol('m.id', 'Table One Id')
            ->andCol('s.id', 'Table Two Id');


        $expected = <<<QUERY
SELECT `m`.`id` AS `Table One Id`, `s`.`id` AS `Table Two Id`
    FROM `wp_1_tests_sample_table` AS `m`
    INNER JOIN `wp_1_tests_sample_table_two` AS `s` ON `m`.`id` = `s`.`id`
QUERY;

        $this->assertSameQuery($expected, $select->buildSqlNoEscape());
    }

    /**
     * @test
     */
    public function testGroupBy(): void
    {
        $finder = $this->initializeSampleTablesFinder();

        $select = Select::from(TableOne::NAME, 'm', $finder)
            ->groupBy('m.integer')
            ->thenGroupBy('m.double')
            ->andCol('m.integer')
            ->andCol('m.double');

        $expected = <<<QUERY
SELECT `m`.`integer`, `m`.`double`
    FROM `wp_1_tests_sample_table` AS `m`
    GROUP BY `m`.`integer`, `m`.`double`
QUERY;

        $this->assertSameQuery($expected, $select->buildSqlNoEscape());
    }
}
