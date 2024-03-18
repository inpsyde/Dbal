<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Tests\Unit\Query;

use Inpsyde\Dbal\ErrorCollector;
use Inpsyde\Dbal\Query\Aliases;
use Inpsyde\Dbal\Tests\UnitTestCase;
use Inpsyde\Dbal\Tests\TableOne;
use Inpsyde\Dbal\Tests\TableTwo;

class AliasesTest extends UnitTestCase
{
    /**
     * @test
     */
    public function testForSchemaFailsForEmptySchema(): void
    {
        $aliases = Aliases::new($this->initializeSampleTablesFinder());
        $collector = new ErrorCollector();

        $aliases = $aliases->forSchema('', 'foo');
        $aliases->mergeErrors($collector);

        $this->expectExceptionMessageMatches('/empty/i');
        $collector->assert();
    }

    /**
     * @test
     */
    public function testForSchemaFailsForEmptyTable(): void
    {
        $aliases = Aliases::new($this->initializeSampleTablesFinder());
        $collector = new ErrorCollector();

        $aliases = $aliases->forSchema(TableOne::NAME, '');
        $aliases->mergeErrors($collector);

        $this->expectExceptionMessageMatches('/empty/i');
        $collector->assert();
    }

    /**
     * @test
     */
    public function testForSchemaFailsForBadAliasName(): void
    {
        $aliases = Aliases::new($this->initializeSampleTablesFinder());
        $collector = new ErrorCollector();

        $aliases = $aliases->forSchema(TableOne::NAME, ' foo ');
        $aliases->mergeErrors($collector);

        $this->expectExceptionMessageMatches('/valid/i');
        $collector->assert();
    }

    /**
     * @test
     */
    public function testForSchemaFailsForInvalidTable(): void
    {
        $aliases = Aliases::new($this->initializeSampleTablesFinder());
        $collector = new ErrorCollector();

        $aliases = $aliases->forSchema('foo', 'bar');
        $aliases->mergeErrors($collector);

        $this->expectExceptionMessageMatches('/not found/i');
        $collector->assert();
    }

    /**
     * @test
     */
    public function testForSchemaFailsForAlreadyUsedAlias(): void
    {
        $aliases = Aliases::new($this->initializeSampleTablesFinder());
        $collector = new ErrorCollector();

        $aliases = $aliases->forSchema(TableOne::NAME, 'bar');
        $aliases = $aliases->forSchema(TableTwo::NAME, 'bar');
        $aliases->mergeErrors($collector);

        $this->expectExceptionMessageMatches('/unique/i');
        $collector->assert();
    }

    /**
     * @test
     */
    public function testResolveSchemaFailsForEmptyName(): void
    {
        $aliases = Aliases::new($this->initializeSampleTablesFinder());
        $collector = new ErrorCollector();

        $aliases->forSchema(TableOne::NAME, 'foo')->resolveSchema('');
        $aliases->mergeErrors($collector);

        $this->expectExceptionMessageMatches('/empty/i');
        $collector->assert();
    }

    /**
     * @test
     */
    public function testSimpleSchemaAliasResolvedByName(): void
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

    /**
     * @test
     */
    public function testSimpleSchemaAliasResolvedByAlias(): void
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

    /**
     * @test
     */
    public function testSchemaAliasedMultipleTimesResolvedByAlias(): void
    {
        $aliases = Aliases::new($this->initializeSampleTablesFinder());
        $collector = new ErrorCollector();

        $aliases = $aliases->forSchema(TableOne::NAME, 'foo');
        $aliases = $aliases->forSchema(TableOne::NAME, 'bar');
        $aliases = $aliases->forSchema(TableOne::NAME, 'baz');

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

    /**
     * @test
     */
    public function testSchemaAliasedMultipleTimesResolvedByName(): void
    {
        $aliases = Aliases::new($this->initializeSampleTablesFinder());
        $collector = new ErrorCollector();

        $aliases = $aliases->forSchema(TableOne::NAME, 'foo');
        $aliases = $aliases->forSchema(TableOne::NAME, 'bar');
        $aliases = $aliases->forSchema(TableOne::NAME, 'baz');

        $aliases->mergeErrors($collector);
        $collector->assert();

        [$name, $schema, $alias, $allAliases] = $aliases->resolveSchema(TableOne::NAME);

        static::assertSame(TableOne::NAME, $name);
        static::assertInstanceOf(TableOne::class, $schema);
        static::assertSame(null, $alias);
        static::assertSame(['foo', 'bar', 'baz'], $allAliases);
    }

    /**
     * @test
     */
    public function testForColumnFailsForEmptyName(): void
    {
        $aliases = Aliases::new($this->initializeSampleTablesFinder());
        $collector = new ErrorCollector();

        $aliases->forColumn('', 'foo', TableOne::NAME);
        $aliases->mergeErrors($collector);

        $this->expectExceptionMessageMatches('/empty/i');
        $collector->assert();
    }

    /**
     * @test
     */
    public function testForColumnFailsForEmptyAlias(): void
    {
        $aliases = Aliases::new($this->initializeSampleTablesFinder());
        $collector = new ErrorCollector();

        $aliases->forColumn(TableOne::ID, '', TableOne::NAME);
        $aliases->mergeErrors($collector);

        $this->expectExceptionMessageMatches('/empty/i');
        $collector->assert();
    }

    /**
     * @test
     */
    public function testForColumnFailsForEmptyTable(): void
    {
        $aliases = Aliases::new($this->initializeSampleTablesFinder());
        $collector = new ErrorCollector();

        $aliases->forColumn(TableOne::ID, 'foo', '');
        $aliases->mergeErrors($collector);

        $this->expectExceptionMessageMatches('/empty/i');
        $collector->assert();
    }

    /**
     * @test
     */
    public function testForColumnFailsForInvalidTable(): void
    {
        $aliases = Aliases::new($this->initializeSampleTablesFinder());
        $collector = new ErrorCollector();

        $aliases->forColumn(TableOne::ID, 'foo', 'Bar');
        $aliases->mergeErrors($collector);

        $this->expectExceptionMessageMatches('/not found/i');
        $collector->assert();
    }

    /**
     * @test
     */
    public function testResolveColumnFailsForEmptyName(): void
    {
        $aliases = Aliases::new($this->initializeSampleTablesFinder());
        $collector = new ErrorCollector();

        $aliases->forColumn('id', 'foo', TableOne::NAME)->resolveColumn('');
        $aliases->mergeErrors($collector);

        $this->expectExceptionMessageMatches('/empty/i');
        $collector->assert();
    }

    /**
     * @test
     */
    public function testForColumnAndResolveByName(): void
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

    /**
     * @test
     */
    public function testForColumnAndResolveByAlias(): void
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

    /**
     * @test
     */
    public function testForRawColumnAndResolveByName(): void
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

    /**
     * @test
     */
    public function testForRawColumnNoTableAndResolveByName(): void
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

    /**
     * @test
     */
    public function testForRawColumnAndResolveByAlias(): void
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

    /**
     * @test
     */
    public function testForRawColumnNoTableAndResolveByAlias(): void
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
