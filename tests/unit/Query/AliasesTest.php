<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Tests\Unit\Query;

use Inpsyde\Dbal\Query\Aliases;
use Inpsyde\Dbal\Query\ErrorCollector;
use Inpsyde\Dbal\Tests\TableOne;
use Inpsyde\Dbal\Tests\TableTwo;
use Inpsyde\Dbal\Tests\UnitTestCase;

class AliasesTest extends UnitTestCase
{
    public function testForSchemaFailsForEmptySchema()
    {
        $aliases = Aliases::new($this->initializeSampleTablesFinder());
        $collector = new ErrorCollector();

        $aliases = $aliases->forSchema('', 'foo');
        $aliases->mergeErrors($collector);

        $this->expectExceptionMessageMatches('/empty/i');
        $collector->assert();
    }

    public function testForSchemaFailsForEmptyTable()
    {
        $aliases = Aliases::new($this->initializeSampleTablesFinder());
        $collector = new ErrorCollector();

        $aliases = $aliases->forSchema(TableOne::NAME, '');
        $aliases->mergeErrors($collector);

        $this->expectExceptionMessageMatches('/empty/i');
        $collector->assert();
    }

    public function testForSchemaFailsForBadAliasName()
    {
        $aliases = Aliases::new($this->initializeSampleTablesFinder());
        $collector = new ErrorCollector();

        $aliases = $aliases->forSchema(TableOne::NAME, '" foo "');
        $aliases->mergeErrors($collector);

        $this->expectExceptionMessageMatches('/valid/i');
        $collector->assert();
    }

    public function testForSchemaFailsForInvalidTable()
    {
        $aliases = Aliases::new($this->initializeSampleTablesFinder());
        $collector = new ErrorCollector();

        $aliases = $aliases->forSchema('foo', 'bar');
        $aliases->mergeErrors($collector);

        $this->expectExceptionMessageMatches('/not found/i');
        $collector->assert();
    }

    public function testForSchemaFailsForAlreadyUsedAlias()
    {
        $aliases = Aliases::new($this->initializeSampleTablesFinder());
        $collector = new ErrorCollector();

        $aliases = $aliases->forSchema(TableOne::NAME, 'bar');
        $aliases = $aliases->forSchema(TableTwo::NAME, 'bar');
        $aliases->mergeErrors($collector);

        $this->expectExceptionMessageMatches('/unique/i');
        $collector->assert();
    }

    public function testResolveSchemaFailsForEmptyName()
    {
        $aliases = Aliases::new($this->initializeSampleTablesFinder());
        $collector = new ErrorCollector();

        $aliases->forSchema(TableOne::NAME, 'foo')->resolveSchema('');
        $aliases->mergeErrors($collector);

        $this->expectExceptionMessageMatches('/empty/i');
        $collector->assert();
    }

    public function testSimpleSchemaAliasResolvedByName()
    {
        $aliases = Aliases::new($this->initializeSampleTablesFinder());
        $collector = new ErrorCollector();

        $aliases = $aliases->forSchema(TableOne::NAME, 'bar');
        $aliases->mergeErrors($collector);
        $collector->assert();

        [$name, $schema, $alias, $aliases] = $aliases->resolveSchema(TableOne::NAME);

        static::assertSame(TableOne::NAME, $name);
        static::assertInstanceOf(TableOne::class, $schema);
        static::assertSame('bar', $alias);
        static::assertSame(['bar'], $aliases);
    }

    public function testSimpleSchemaAliasResolvedByAlias()
    {
        $aliases = Aliases::new($this->initializeSampleTablesFinder());
        $collector = new ErrorCollector();

        $aliases = $aliases->forSchema(TableOne::NAME, 'bar');
        $aliases->mergeErrors($collector);
        $collector->assert();

        [$name, $schema, $alias, $allAliases] = $aliases->resolveSchema('bar');

        static::assertSame(TableOne::NAME, $name);
        static::assertInstanceOf(TableOne::class, $schema);
        static::assertSame('bar', $alias);
        static::assertSame(['bar'], $allAliases);
    }

    public function testSchemaAliasedMultipleTimesResolvedByAlias()
    {
        $aliases = Aliases::new($this->initializeSampleTablesFinder());
        $collector = new ErrorCollector();

        $aliases = $aliases->forSchema(TableOne::NAME, 'foo');
        $aliases = $aliases->forSchema(TableOne::NAME, 'bar');
        $aliases = $aliases->forSchema(TableOne::NAME, 'baz');

        /** @var Aliases $aliases */
        $aliases->mergeErrors($collector);
        $collector->assert();

        [$name1, $schema1, $alias1, $aliases1] = $aliases->resolveSchema('foo');
        [$name2, $schema2, $alias2, $aliases2] = $aliases->resolveSchema('bar');
        [$name3, $schema3, $alias3, $aliases3] = $aliases->resolveSchema('baz');

        static::assertSame(TableOne::NAME, $name1);
        static::assertInstanceOf(TableOne::class, $schema1);
        static::assertSame('foo', $alias1);
        static::assertSame(['foo', 'bar', 'baz'], $aliases1);

        static::assertSame(TableOne::NAME, $name2);
        static::assertInstanceOf(TableOne::class, $schema2);
        static::assertSame('bar', $alias2);
        static::assertSame(['foo', 'bar', 'baz'], $aliases2);

        static::assertSame(TableOne::NAME, $name3);
        static::assertInstanceOf(TableOne::class, $schema3);
        static::assertSame('baz', $alias3);
        static::assertSame(['foo', 'bar', 'baz'], $aliases3);
    }

    public function testSchemaAliasedMultipleTimesResolvedByName()
    {
        $aliases = Aliases::new($this->initializeSampleTablesFinder());
        $collector = new ErrorCollector();

        $aliases = $aliases->forSchema(TableOne::NAME, 'foo');
        $aliases = $aliases->forSchema(TableOne::NAME, 'bar');
        $aliases = $aliases->forSchema(TableOne::NAME, 'baz');

        /** @var Aliases $aliases */
        $aliases->mergeErrors($collector);
        $collector->assert();

        [$name, $schema, $alias, $allAliases] = $aliases->resolveSchema(TableOne::NAME);

        static::assertSame(TableOne::NAME, $name);
        static::assertInstanceOf(TableOne::class, $schema);
        static::assertSame(null, $alias);
        static::assertSame(['foo', 'bar', 'baz'], $allAliases);
    }
    
    public function testForColumnFailsForEmptyName()
    {
        $aliases = Aliases::new($this->initializeSampleTablesFinder());
        $collector = new ErrorCollector();

        $aliases->forColumn('', 'foo', TableOne::NAME);
        $aliases->mergeErrors($collector);

        $this->expectExceptionMessageMatches('/empty/i');
        $collector->assert();
    }

    public function testForColumnFailsForEmptyAlias()
    {
        $aliases = Aliases::new($this->initializeSampleTablesFinder());
        $collector = new ErrorCollector();

        $aliases->forColumn(TableOne::ID, '', TableOne::NAME);
        $aliases->mergeErrors($collector);

        $this->expectExceptionMessageMatches('/empty/i');
        $collector->assert();
    }

    public function testForColumnFailsForEmptyTable()
    {
        $aliases = Aliases::new($this->initializeSampleTablesFinder());
        $collector = new ErrorCollector();

        $aliases->forColumn(TableOne::ID, 'foo', '');
        $aliases->mergeErrors($collector);

        $this->expectExceptionMessageMatches('/empty/i');
        $collector->assert();
    }

    public function testForColumnFailsForInvalidTable()
    {
        $aliases = Aliases::new($this->initializeSampleTablesFinder());
        $collector = new ErrorCollector();

        $aliases->forColumn(TableOne::ID, 'foo', 'Bar');
        $aliases->mergeErrors($collector);

        $this->expectExceptionMessageMatches('/not found/i');
        $collector->assert();
    }

    public function testResolveColumnFailsForEmptyName()
    {
        $aliases = Aliases::new($this->initializeSampleTablesFinder());
        $collector = new ErrorCollector();

        $aliases->forColumn('id', 'foo', TableOne::NAME)->resolveColumn('');
        $aliases->mergeErrors($collector);

        $this->expectExceptionMessageMatches('/empty/i');
        $collector->assert();
    }

    public function testForColumnAndResolveByName()
    {
        $aliases = Aliases::new($this->initializeSampleTablesFinder());
        $collector = new ErrorCollector();

        $aliases = $aliases->forColumn(TableOne::ID, 'theId', TableOne::NAME);
        $aliases->mergeErrors($collector);
        $collector->assert();

        [$name, $alias, $tableFullName, $tableName] = $aliases->resolveColumn(TableOne::ID);

        static::assertSame(TableOne::ID, $name);
        static::assertSame('theId', $alias);
        static::assertSame('wp_1_' . TableOne::NAME, $tableFullName);
        static::assertSame(TableOne::NAME, $tableName);
    }

    public function testForColumnAndResolveByAlias()
    {
        $aliases = Aliases::new($this->initializeSampleTablesFinder());
        $collector = new ErrorCollector();

        $aliases = $aliases->forColumn(TableOne::ID, 'theId', TableOne::NAME);
        $aliases->mergeErrors($collector);
        $collector->assert();

        [$name, $alias, $tableFullName, $tableName] = $aliases->resolveColumn('theId');

        static::assertSame(TableOne::ID, $name);
        static::assertSame('theId', $alias);
        static::assertSame('wp_1_' . TableOne::NAME, $tableFullName);
        static::assertSame(TableOne::NAME, $tableName);
    }

    public function testForRawColumnAndResolveByName()
    {
        $aliases = Aliases::new($this->initializeSampleTablesFinder());
        $collector = new ErrorCollector();

        $aliases = $aliases->forRawColumn('FOO()', "xyz", TableOne::NAME);
        $aliases->mergeErrors($collector);
        $collector->assert();

        [$name, $alias, $tableFullName, $tableName] = $aliases->resolveColumn('FOO()');

        static::assertSame('FOO()', $name);
        static::assertSame('xyz', $alias);
        static::assertSame('wp_1_' . TableOne::NAME, $tableFullName);
        static::assertSame(TableOne::NAME, $tableName);
    }

    public function testForRawColumnNoTableAndResolveByName()
    {
        $aliases = Aliases::new($this->initializeSampleTablesFinder());
        $collector = new ErrorCollector();

        $aliases = $aliases->forRawColumn('FOO()', "xyz");
        $aliases->mergeErrors($collector);
        $collector->assert();

        [$name, $alias, $tableFullName, $tableName] = $aliases->resolveColumn('FOO()');

        static::assertSame('FOO()', $name);
        static::assertSame('xyz', $alias);
        static::assertNull($tableFullName);
        static::assertNull($tableName);
    }

    public function testForRawColumnAndResolveByAlias()
    {
        $aliases = Aliases::new($this->initializeSampleTablesFinder());
        $collector = new ErrorCollector();

        $aliases = $aliases->forRawColumn('FOO()', "xyz", TableOne::NAME);
        $aliases->mergeErrors($collector);
        $collector->assert();

        [$name, $alias, $tableFullName, $tableName] = $aliases->resolveColumn('xyz');

        static::assertSame('FOO()', $name);
        static::assertSame('xyz', $alias);
        static::assertSame('wp_1_' . TableOne::NAME, $tableFullName);
        static::assertSame(TableOne::NAME, $tableName);
    }

    public function testForRawColumnNoTableAndResolveByAlias()
    {
        $aliases = Aliases::new($this->initializeSampleTablesFinder());
        $collector = new ErrorCollector();

        $aliases = $aliases->forRawColumn('FOO()', "xyz");
        $aliases->mergeErrors($collector);
        $collector->assert();

        [$name, $alias, $tableFullName, $tableName] = $aliases->resolveColumn('xyz');

        static::assertSame('FOO()', $name);
        static::assertSame('xyz', $alias);
        static::assertNull($tableFullName);
        static::assertNull($tableName);
    }
}
