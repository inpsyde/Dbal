<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Tests\Unit\Schema;

use Inpsyde\Dbal\Schema\Column;
use Inpsyde\Dbal\Tests\UnitTestCase;

class ColumnRealTest extends UnitTestCase
{
    public function testDecimal()
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

    public function testFloat()
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

    public function testDouble()
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

    public function testFloatUpscaleToDoubleWhenNeeded()
    {
        $col = Column::float('Foo', 45);
        static::assertSame(Column::DOUBLE, $col->type());
    }

    public function testDoubleDownscaleToFloatWhenPossible()
    {
        $col = Column::double('Foo', 10);
        static::assertSame(Column::FLOAT, $col->type());
    }

    public function testDecimalFailsWithNegativePrecision()
    {
        $this->expectExceptionMessageMatches('/precision/i');
        Column::decimal('Foo', -1);
    }

    public function testDecimalFailsWithTooHighPrecision()
    {
        $this->expectExceptionMessageMatches('/precision/i');
        Column::decimal('Foo', 99);
    }

    public function testDecimalFailsWithNegativeScale()
    {
        $this->expectExceptionMessageMatches('/scale/i');
        Column::decimal('Foo', 10, -1);
    }

    public function testDecimalFailsWithTooHighScale()
    {
        $this->expectExceptionMessageMatches('/scale/i');
        Column::decimal('Foo', 10, 99);
    }

    public function testFloatFailsWithNegativePrecision()
    {
        $this->expectExceptionMessageMatches('/precision/i');
        Column::float('Foo', -1);
    }

    public function testFloatFailsWithTooHighPrecision()
    {
        $this->expectExceptionMessageMatches('/precision/i');
        Column::float('Foo', 99);
    }

    public function testFloatFailsWithNegativeScale()
    {
        $this->expectExceptionMessageMatches('/scale/i');
        Column::float('Foo', 10, -1);
    }

    public function testFloatFailsWithTooHighScale()
    {
        $this->expectExceptionMessageMatches('/scale/i');
        Column::float('Foo', 10, 99);
    }

    public function testDoubleFailsWithNegativePrecision()
    {
        $this->expectExceptionMessageMatches('/precision/i');
        Column::double('Foo', -1);
    }

    public function testDoubleFailsWithTooHighPrecision()
    {
        $this->expectExceptionMessageMatches('/precision/i');
        Column::double('Foo', 99);
    }

    public function testDoubleFailsWithNegativeScale()
    {
        $this->expectExceptionMessageMatches('/scale/i');
        Column::double('Foo', 10, -1);
    }

    public function testDoubleFailsWithTooHighScale()
    {
        $this->expectExceptionMessageMatches('/scale/i');
        Column::double('Foo', 10, 99);
    }

    public function testFloatFailsIfScaleIsGivenWithoutPrecision()
    {
        $this->expectExceptionMessageMatches('/scale(?:.+?)precision/i');
        Column::float('Foo', null, 20);
    }

    public function testDoubleFailsIfScaleIsGivenWithoutPrecision()
    {
        $this->expectExceptionMessageMatches('/scale(?:.+?)precision/i');
        Column::double('Foo', null, 0);
    }
}
