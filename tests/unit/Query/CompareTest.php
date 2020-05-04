<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Tests\Unit\Query;

use Inpsyde\Dbal\Query\Aliases;
use Inpsyde\Dbal\Query\Compare;
use Inpsyde\Dbal\Schema\SchemaFinder;
use Inpsyde\Dbal\Tests\TableOne;
use Inpsyde\Dbal\Tests\TableTwo;
use Inpsyde\Dbal\Tests\UnitTestCase;

class CompareTest extends UnitTestCase
{
    public function testBothColumns()
    {
        [$mainTable, $finder, $aliases, $left, $right] = $this->prepareDependencies();
        $compare = Compare::columns($left, $right, '>');

        $params = $compare->clauseParams($mainTable, $finder, $aliases);

        $colLeft = $this->quoteColName($finder, $left);
        $colRight = $this->quoteColName($finder, $right);
        $expected = [$colLeft, $colRight, null, '>'];

        static::assertSame($expected, $params, "For {$left}, {$right}");
    }

    public function testBothColumnsLeftCasted()
    {
        [$mainTable, $finder, $aliases, $left, $right] = $this->prepareDependencies();
        $compare = Compare::columns($left, $right, '>')->castLeft(Compare::CAST_UNSIGNED);

        $params = $compare->clauseParams($mainTable, $finder, $aliases);

        $colLeft = $this->quoteColName($finder, $left);
        $colRight = $this->quoteColName($finder, $right);
        $expected = ["CAST({$colLeft} AS UNSIGNED)", $colRight, null, '>'];

        static::assertSame($expected, $params, "For {$left}, {$right}");
    }

    public function testBothColumnsRightCasted()
    {
        [$mainTable, $finder, $aliases, $left, $right] = $this->prepareDependencies();
        $compare = Compare::columns($left, $right)->castRight(Compare::CAST_TIME);

        $params = $compare->clauseParams($mainTable, $finder, $aliases);

        $colLeft = $this->quoteColName($finder, $left);
        $colRight = $this->quoteColName($finder, $right);
        $expected = [$colLeft, "CAST({$colRight} AS TIME)", null, '='];

        static::assertSame($expected, $params, "For {$left}, {$right}");
    }

    public function testBothColumnsBothCasted()
    {
        [$mainTable, $finder, $aliases, $left, $right] = $this->prepareDependencies();
        $compare = Compare::columns($left, $right)
            ->castLeft(Compare::CAST_TIME)
            ->castRight(Compare::CAST_TIME);

        $params = $compare->clauseParams($mainTable, $finder, $aliases);

        $leftCol = $this->quoteColName($finder, $left);
        $rightCol = $this->quoteColName($finder, $right);
        $expected = ["CAST({$leftCol} AS TIME)", "CAST({$rightCol} AS TIME)", null, '='];

        static::assertSame($expected, $params, "For {$left}, {$right}");
    }

    public function testColumnAndValue()
    {
        [$mainTable, $finder, $aliases, $leftCol] = $this->prepareDependencies();
        $compare = Compare::columnValue($leftCol, 32);

        $params = $compare->clauseParams($mainTable, $finder, $aliases);

        $expected = [$this->quoteColName($finder, $leftCol), 32, '%d', '='];

        static::assertSame($expected, $params, "For {$leftCol}");
    }

    public function testColumnAndRawValue()
    {
        [$mainTable, $finder, $aliases, $leftCol] = $this->prepareDependencies();
        $compare = Compare::columnRawValue($leftCol, 'NOW()');

        $params = $compare->clauseParams($mainTable, $finder, $aliases);
        $expected = [$this->quoteColName($finder, $leftCol), 'NOW()', null, '='];

        static::assertSame($expected, $params, "For {$leftCol}");
    }

    public function testColumnCastedAndValue()
    {
        [$mainTable, $finder, $aliases, $leftCol] = $this->prepareDependencies();
        $compare = Compare::columnValue($leftCol, '0000-00-00 00:00:00', '>=')
            ->castLeft(Compare::CAST_DATETIME);

        $params = $compare->clauseParams($mainTable, $finder, $aliases);

        $left = $this->quoteColName($finder, $leftCol);
        $expected = ["CAST({$left} AS DATETIME)", '0000-00-00 00:00:00', '%s', '>='];

        static::assertSame($expected, $params, "For {$leftCol}");
    }

    public function testColumnCastedAndRawValue()
    {
        [$mainTable, $finder, $aliases, $leftCol] = $this->prepareDependencies();
        $compare = Compare::columnRawValue($leftCol, 'NOW()')->castLeft(Compare::CAST_DATETIME);

        $params = $compare->clauseParams($mainTable, $finder, $aliases);

        $left = $this->quoteColName($finder, $leftCol);
        $expected = ["CAST({$left} AS DATETIME)", 'NOW()', null, '='];

        static::assertSame($expected, $params, "For {$leftCol}");
    }

    /**
     * @param SchemaFinder $finder
     * @param string $column
     * @return string
     */
    private function quoteColName(SchemaFinder $finder, string $column): string
    {
        [$table, $column] = explode('.', $column, 2);
        $aliased = ($table === 'x' || $table === 'y'); // see prepareDependencies()
        $tableName = $aliased ? $table : $finder->fullTableName($finder->findSchema($table));

        return sprintf('`%s`.`%s`', $tableName, $column);
    }

    /**
     * @return array{
     *  0:\Inpsyde\Dbal\Schema\Schema,
     *  1:\Inpsyde\Dbal\Schema\SchemaFinder,
     *  2:Aliases,
     *  3:string,
     *  4:string
     * }
     */
    private function prepareDependencies(): array
    {
        $aliasLeft = rand(0, 9) < 5;
        $aliasRight = rand(10, 19) < 15;

        $mainTable = new TableOne();
        $finder = $this->initializeSampleTablesFinder();
        $aliases = Aliases::new($finder);
        $aliasLeft and $aliases = $aliases->forSchema(TableOne::NAME, 'x');
        $aliasRight and $aliases = $aliases->forSchema(TableTwo::NAME, 'y');

        $leftColName = $aliasLeft ? 'x' : TableOne::NAME;
        $leftCol = sprintf('%s.%s', $leftColName, TableOne::ID);

        $rightColName = $aliasRight ? 'y' : TableTwo::NAME;
        $rightCol = sprintf('%s.%s', $rightColName, TableTwo::ID);

        return [$mainTable, $finder, $aliases, $leftCol, $rightCol];
    }
}
