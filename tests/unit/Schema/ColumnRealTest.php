<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Tests\Unit\Schema;

use Inpsyde\Dbal\Schema\Column;
use Inpsyde\Dbal\Tests\UnitTestCase;

class ColumnRealTest extends UnitTestCase
{
    /**
     * @test
     */
    public function testDecimal(): void
    {
        $col = Column::decimal('Foo', 10, 0, 0.0)->makeUnsigned();

        static::assertSame('Foo', $col->name());
        static::assertSame(Column::DECIMAL, $col->type());
        static::assertSame('%s', $col->format());
        static::assertSame(0.0, $col->default());
        static::assertTrue($col->hasDefault());
        static::assertTrue($col->isNullable());
        static::assertTrue($col->isRealNumber());
        static::assertFalse($col->isInteger());
        static::assertFalse($col->isSerialized());
        static::assertFalse($col->isRequiredOnInsert());
        static::assertFalse($col->isAutoIncrement());
        static::assertTrue($col->isUnsigned());
        static::assertFalse($col->isBoolean());
    }

    /**
     * @test
     */
    public function testFloat(): void
    {
        $col = Column::float('Bar')->makeNotNull()->makeAutoIncrement();

        static::assertSame('Bar', $col->name());
        static::assertSame(Column::FLOAT, $col->type());
        static::assertSame('%s', $col->format());
        static::assertNull($col->default());
        static::assertFalse($col->hasDefault());
        static::assertFalse($col->isNullable());
        static::assertTrue($col->isRealNumber());
        static::assertFalse($col->isInteger());
        static::assertFalse($col->isSerialized());
        static::assertFalse($col->isRequiredOnInsert());
        static::assertTrue($col->isAutoIncrement());
        static::assertFalse($col->isUnsigned());
        static::assertFalse($col->isBoolean());
    }

    /**
     * @test
     */
    public function testDouble(): void
    {
        $col = Column::double('Baz', 30, 0, 5.5)->makeNotNull()->makeUnsigned();

        static::assertSame('Baz', $col->name());
        static::assertSame(Column::DOUBLE, $col->type());
        static::assertSame('%s', $col->format());
        static::assertSame(5.5, $col->default());
        static::assertTrue($col->hasDefault());
        static::assertFalse($col->isNullable());
        static::assertTrue($col->isRealNumber());
        static::assertFalse($col->isInteger());
        static::assertFalse($col->isSerialized());
        static::assertFalse($col->isRequiredOnInsert());
        static::assertFalse($col->isAutoIncrement());
        static::assertTrue($col->isUnsigned());
        static::assertFalse($col->isBoolean());
    }

    /**
     * @test
     */
    public function testFloatUpscaleToDoubleWhenNeeded(): void
    {
        $col = Column::float('Foo', 45);
        static::assertSame(Column::DOUBLE, $col->type());
    }

    /**
     * @test
     */
    public function testDoubleDownscaleToFloatWhenPossible(): void
    {
        $col = Column::double('Foo', 10);
        static::assertSame(Column::FLOAT, $col->type());
    }

    /**
     * @test
     */
    public function testDecimalFailsWithNegativePrecision(): void
    {
        $this->expectExceptionMessageMatches('/precision/i');
        Column::decimal('Foo', -1);
    }

    /**
     * @test
     */
    public function testDecimalFailsWithTooHighPrecision(): void
    {
        $this->expectExceptionMessageMatches('/precision/i');
        Column::decimal('Foo', 99);
    }

    /**
     * @test
     */
    public function testDecimalFailsWithNegativeScale(): void
    {
        $this->expectExceptionMessageMatches('/scale/i');
        Column::decimal('Foo', 10, -1);
    }

    /**
     * @test
     */
    public function testDecimalFailsWithTooHighScale(): void
    {
        $this->expectExceptionMessageMatches('/scale/i');
        Column::decimal('Foo', 10, 99);
    }

    /**
     * @test
     */
    public function testFloatFailsWithNegativePrecision(): void
    {
        $this->expectExceptionMessageMatches('/precision/i');
        Column::float('Foo', -1);
    }

    /**
     * @test
     */
    public function testFloatFailsWithTooHighPrecision(): void
    {
        $this->expectExceptionMessageMatches('/precision/i');
        Column::float('Foo', 99);
    }

    /**
     * @test
     */
    public function testFloatFailsWithNegativeScale(): void
    {
        $this->expectExceptionMessageMatches('/scale/i');
        Column::float('Foo', 10, -1);
    }

    /**
     * @test
     */
    public function testFloatFailsWithTooHighScale(): void
    {
        $this->expectExceptionMessageMatches('/scale/i');
        Column::float('Foo', 10, 99);
    }

    /**
     * @test
     */
    public function testDoubleFailsWithNegativePrecision(): void
    {
        $this->expectExceptionMessageMatches('/precision/i');
        Column::double('Foo', -1);
    }

    /**
     * @test
     */
    public function testDoubleFailsWithTooHighPrecision(): void
    {
        $this->expectExceptionMessageMatches('/precision/i');
        Column::double('Foo', 99);
    }

    /**
     * @test
     */
    public function testDoubleFailsWithNegativeScale(): void
    {
        $this->expectExceptionMessageMatches('/scale/i');
        Column::double('Foo', 10, -1);
    }

    /**
     * @test
     */
    public function testDoubleFailsWithTooHighScale(): void
    {
        $this->expectExceptionMessageMatches('/scale/i');
        Column::double('Foo', 10, 99);
    }

    /**
     * @test
     */
    public function testFloatFailsIfScaleIsGivenWithoutPrecision(): void
    {
        $this->expectExceptionMessageMatches('/scale(?:.+?)precision/i');
        Column::float('Foo', null, 20);
    }

    /**
     * @test
     */
    public function testDoubleFailsIfScaleIsGivenWithoutPrecision(): void
    {
        $this->expectExceptionMessageMatches('/scale(?:.+?)precision/i');
        Column::double('Foo', null, 0);
    }
}
