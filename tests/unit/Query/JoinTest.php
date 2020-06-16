<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Tests\Unit\Query;

use Inpsyde\Dbal\Query\Aliases;
use Inpsyde\Dbal\Query\Compare;
use Inpsyde\Dbal\Query\Where;
use Inpsyde\Dbal\ErrorCollector;
use Inpsyde\Dbal\Query\Join;
use Inpsyde\Dbal\Tests\TableOne;
use Inpsyde\Dbal\Tests\TableTwo;
use Inpsyde\Dbal\Tests\UnitTestCase;

class JoinTest extends UnitTestCase
{
    public function testClauseIsEmptyWhenHasResolvingErrors()
    {
        $errors = new ErrorCollector();
        $join = Join::left(new TableOne(), new TableTwo(), 'foo', 'bar');

        $join->mergeErrors($errors);
        $errors->assert();

        $finder = $this->initializeSampleTablesFinder();

        static::assertSame('', $join->clause($finder, Aliases::new($finder)));

        $join->mergeErrors($errors);
        $this->expectExceptionMessageMatches('/column(?:.+?)not found/i');
        $errors->assert();
    }

    public function testInnerJoinWithExplicitColumnsAndNoAlias()
    {
        $join = Join::inner(new TableOne(), new TableTwo(), TableOne::INTEGER, TableTwo::ID);

        $finder = $this->initializeSampleTablesFinder();
        $clause = $join->clause($finder, Aliases::new($finder));

        $one = $finder->fullTableName(new TableOne());
        $two = $finder->fullTableName(new TableTwo());
        $expected = "INNER JOIN `{$two}` ON `{$one}`.`integer` = `{$two}`.`id`";

        static::assertSame($expected, $clause);
    }

    public function testInnerJoinWithExplicitColumnsAndTargetAlias()
    {
        $join = Join::inner(new TableOne(), new TableTwo(), TableOne::INTEGER, TableTwo::ID, 'x');

        $finder = $this->initializeSampleTablesFinder();
        $clause = $join->clause($finder, Aliases::new($finder));

        $one = $finder->fullTableName(new TableOne());
        $two = $finder->fullTableName(new TableTwo());
        $expected = "INNER JOIN `{$two}` AS `x` ON `{$one}`.`integer` = `x`.`id`";

        static::assertSame($expected, $clause);
    }

    public function testInnerJoinWithExplicitColumnsAndBothSchemaAliased()
    {
        $join = Join::inner(new TableOne(), new TableTwo(), TableOne::INTEGER, TableTwo::ID, 'x');

        $finder = $this->initializeSampleTablesFinder();
        $aliases = Aliases::new($finder)->forSchema(TableOne::NAME, 'source');

        $clause = $join->clause($finder, $aliases);

        $two = $finder->fullTableName(new TableTwo());
        $expected = "INNER JOIN `{$two}` AS `x` ON `source`.`integer` = `x`.`id`";

        static::assertSame($expected, $clause);
    }

    public function testLeftJoinWithTargetColumnImplicitAndSourceSchemaAliased()
    {
        $join = Join::left(new TableOne(), new TableTwo(), TableOne::INTEGER);

        $finder = $this->initializeSampleTablesFinder();
        $aliases = Aliases::new($finder)->forSchema(TableOne::NAME, 'main');

        $clause = $join->clause($finder, $aliases);

        $two = $finder->fullTableName(new TableTwo());
        $expected = "LEFT JOIN `{$two}` ON `main`.`integer` = `{$two}`.`id`";

        static::assertSame($expected, $clause);
    }

    public function testLeftJoinWhereWithBothSchemaAliased()
    {
        $compare = Compare::columns('st.' . TableOne::DATETIME, 'nd.' . TableTwo::VARCHAR, '>=');

        $where = Where::new()->withCompare($compare->castRight(Compare::CAST_DATETIME));

        $join = Join::leftWhere(new TableOne(), new TableTwo(), $where, 'nd');

        $finder = $this->initializeSampleTablesFinder();
        $aliases = Aliases::new($finder)->forSchema(TableOne::NAME, 'st');

        $clause = $join->clause($finder, $aliases);

        $two = $finder->fullTableName(new TableTwo());
        $expected = "LEFT JOIN `{$two}` AS `nd` ON ";
        $expected .= "`st`.`datetime` >= CAST(`nd`.`varchar` AS DATETIME)";

        static::assertSame($expected, $clause);
    }

    public function testInnerJoinWhereWithTargetSchemaAliased()
    {
        $compare = Compare::columns(TableOne::INTEGER, 'nd.' . TableTwo::ID, '>=');
        $where = Where::new()->withCompare($compare);

        $join = Join::leftWhere(new TableOne(), new TableTwo(), $where, 'nd');

        $finder = $this->initializeSampleTablesFinder();

        $clause = $join->clause($finder, Aliases::new($finder));

        $one = $finder->fullTableName(new TableOne());
        $two = $finder->fullTableName(new TableTwo());
        $expected = "LEFT JOIN `{$two}` AS `nd` ON ";
        $expected .= "`{$one}`.`integer` >= `nd`.`id`";

        static::assertSame($expected, $clause);
    }

    public function testInnerJoinRawWithAutoColumns()
    {
        $raw = "SELECT ID as id FROM wp_posts WHERE ID > 0";

        $join = Join::innerRaw(new TableOne(), $raw, 'p');
        $finder = $this->initializeSampleTablesFinder();
        $actual = $join->clause($finder, Aliases::new($finder));

        $one = $finder->fullTableName(new TableOne());
        $expected = "INNER JOIN ({$raw}) AS `p` ON ";
        $expected .= "`{$one}`.`id` = `p`.`id`";

        static::assertSame($expected, $actual);
    }

    public function testLeftJoinRawWithCustomColumns()
    {
        $raw = "SELECT ID FROM wp_posts WHERE ID > 0";

        $join = Join::innerRaw(new TableOne(), $raw, 'p', TableOne::INTEGER, 'ID');
        $finder = $this->initializeSampleTablesFinder();
        $actual = $join->clause($finder, Aliases::new($finder));

        $one = $finder->fullTableName(new TableOne());
        $expected = "INNER JOIN ({$raw}) AS `p` ON ";
        $expected .= "`{$one}`.`" .  TableOne::INTEGER . "` = `p`.`ID`";

        static::assertSame($expected, $actual);
    }

    public function testLeftJoinRawOneCustomAndOneAutoColumn()
    {
        $raw = "SELECT ID as integer FROM wp_posts WHERE ID > 0";

        $join = Join::innerRaw(new TableOne(), $raw, 'p', TableOne::INTEGER);
        $finder = $this->initializeSampleTablesFinder();
        $actual = $join->clause($finder, Aliases::new($finder));

        $one = $finder->fullTableName(new TableOne());
        $expected = "INNER JOIN ({$raw}) AS `p` ON ";
        $expected .= "`{$one}`.`integer` = `p`.`integer`";

        static::assertSame($expected, $actual);
    }
}
