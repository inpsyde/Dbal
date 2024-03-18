<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Tests\Unit\Query;

use Inpsyde\Dbal\Query\Aliases;
use Inpsyde\Dbal\Query\ColumnsResultParser;
use Inpsyde\Dbal\Schema\Column;
use Inpsyde\Dbal\Schema\Columns;
use Inpsyde\Dbal\Tests\UnitTestCase;
use Inpsyde\Dbal\Tests\TableOne;
use Inpsyde\Dbal\Tests\TableTwo;

class ColumnsResultParserTest extends UnitTestCase
{
    /**
     * @test
     */
    public function testNewInstanceFailForEmptySchemaName(): void
    {
        $aliases = Aliases::new($this->initializeSampleTablesFinder());
        $this->expectExceptionMessageMatches('/empty/i');

        ColumnsResultParser::new(Columns::new(Column::int('x')), '', $aliases);
    }

    /**
     * @test
     */
    public function testParsingEmptyDataReturnsEmptyData(): void
    {
        $table = new TableOne();
        $finder = $this->initializeSampleTablesFinder();
        $aliases = Aliases::new($finder);
        $parser = ColumnsResultParser::new($table->columns(), TableOne::NAME, $aliases);

        static::assertSame([], $parser->parse([]));
    }

    /**
     * @test
     */
    public function testParsingDataNotInTableReturnsItUntouched(): void
    {
        $table = new TableOne();
        $finder = $this->initializeSampleTablesFinder();
        $aliases = Aliases::new($finder);
        $parser = ColumnsResultParser::new($table->columns(), TableOne::NAME, $aliases);

        $data = [
            'x' => 'A',
            'y' => 'Y',
            'z' => 'Z',
            123 => 123,
        ];

        static::assertSame($data, $parser->parse($data));
    }

    /**
     * @test
     */
    public function testParsingNumericArrayReturnsItUntouched(): void
    {
        $table = new TableOne();
        $finder = $this->initializeSampleTablesFinder();
        $aliases = Aliases::new($finder);
        $parser = ColumnsResultParser::new($table->columns(), TableOne::NAME, $aliases);

        $data = range('A', 'F');

        static::assertSame($data, $parser->parse($data));
    }

    /**
     * @test
     */
    public function testParseDataWithoutAlias(): void
    {
        $table = new TableOne();
        $finder = $this->initializeSampleTablesFinder();
        $aliases = Aliases::new($finder);
        $parser = ColumnsResultParser::new($table->columns(), TableOne::NAME, $aliases);

        $data = [
            TableOne::ID => '123',
            TableOne::DOUBLE => '123.123',
            TableOne::SERIALIZED => serialize([false]),
            TableOne::INTEGER => '0',
        ];

        $expected = [
            TableOne::ID => 123,
            TableOne::DOUBLE => 123.123,
            TableOne::SERIALIZED => [false],
            TableOne::INTEGER => 0,
        ];

        static::assertSame($expected, $parser->parse($data));
    }

    /**
     * @test
     */
    public function testParsingDataFromAnotherTableDoNothing(): void
    {
        $finder = $this->initializeSampleTablesFinder();
        $aliases = Aliases::new($finder)->forColumn(TableTwo::ID, 'tid', TableTwo::NAME);
        $table = new TableOne();
        $parser = ColumnsResultParser::new($table->columns(), TableOne::NAME, $aliases);

        $data = [
            TableTwo::ID => '123',
            TableTwo::DECIMAL => '123.123',
        ];

        static::assertSame($data, $parser->parse($data));
    }

    /**
     * @test
     */
    public function testParse(): void
    {
        $table = new TableOne();

        $finder = $this->initializeSampleTablesFinder();
        $aliases = Aliases::new($finder)
            ->forSchema(TableOne::NAME, 'o')
            ->forColumn(TableOne::INTEGER, 'count', 'o');

        $parser = ColumnsResultParser::new($table->columns(), TableOne::NAME, $aliases);

        $data = [
            'foo' => 'bar',
            'count' => '123',
            TableOne::ENUM => 'YES',
            'a' => 'foo bar baz',
            TableOne::DOUBLE => '0.001',
            TableOne::SERIALIZED => serialize(range('a', 'f')),
        ];

        $expected = [
            'foo' => 'bar',
            'count' => 123,
            TableOne::ENUM => 'yes',
            'a' => 'foo bar baz',
            TableOne::DOUBLE => 0.001,
            TableOne::SERIALIZED => range('a', 'f'),
        ];

        static::assertSame($expected, $parser->parse($data));
    }
}
