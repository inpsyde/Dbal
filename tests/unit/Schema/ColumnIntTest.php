<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Tests\Unit\Schema;

use Inpsyde\Dbal\Schema\Column;
use Inpsyde\Dbal\Tests\UnitTestCase;

class ColumnIntTest extends UnitTestCase
{
    /**
     * @test
     */
    public function testTinyInt(): void
    {
        $col = Column::tinyInt('Foo', 0)->makeUnsigned()->makeAutoIncrement();

        static::assertSame('Foo', $col->name());
        static::assertSame(Column::TINYINT, $col->type());
        static::assertSame('%d', $col->format());
        static::assertSame(0, $col->default());
        static::assertTrue($col->hasDefault());
        static::assertFalse($col->isNullable());
        static::assertFalse($col->isRealNumber());
        static::assertTrue($col->isInteger());
        static::assertFalse($col->isSerialized());
        static::assertFalse($col->isRequiredOnInsert());
        static::assertTrue($col->isAutoIncrement());
        static::assertTrue($col->isUnsigned());
    }

    /**
     * @test
     */
    public function testSmallInt(): void
    {
        $col = Column::smallInt('small')->makeZeroFill(4)->makeNotNull();

        static::assertSame('small', $col->name());
        static::assertSame(Column::SMALLINT, $col->type());
        static::assertSame('%d', $col->format());
        static::assertNull($col->default());
        static::assertFalse($col->hasDefault());
        static::assertFalse($col->isNullable());
        static::assertFalse($col->isRealNumber());
        static::assertTrue($col->isInteger());
        static::assertFalse($col->isSerialized());
        static::assertTrue($col->isRequiredOnInsert());
        static::assertFalse($col->isAutoIncrement());
        static::assertTrue($col->isUnsigned());
    }

    /**
     * @test
     */
    public function testMediumInt(): void
    {
        $col = Column::mediumInt('medium');

        static::assertSame('medium', $col->name());
        static::assertSame(Column::MEDIUMINT, $col->type());
        static::assertSame('%d', $col->format());
        static::assertNull($col->default());
        static::assertFalse($col->hasDefault());
        static::assertTrue($col->isNullable());
        static::assertFalse($col->isRealNumber());
        static::assertTrue($col->isInteger());
        static::assertFalse($col->isSerialized());
        static::assertFalse($col->isRequiredOnInsert());
        static::assertFalse($col->isAutoIncrement());
        static::assertFalse($col->isUnsigned());
    }

    /**
     * @test
     */
    public function testInt(): void
    {
        $col = Column::int('Integer')->makeAutoIncrement();

        static::assertSame('Integer', $col->name());
        static::assertSame(Column::INT, $col->type());
        static::assertSame('%d', $col->format());
        static::assertNull($col->default());
        static::assertFalse($col->hasDefault());
        static::assertFalse($col->isNullable());
        static::assertFalse($col->isRealNumber());
        static::assertTrue($col->isInteger());
        static::assertFalse($col->isSerialized());
        static::assertFalse($col->isRequiredOnInsert());
        static::assertTrue($col->isAutoIncrement());
        static::assertFalse($col->isUnsigned());
    }

    /**
     * @test
     */
    public function testBigInt(): void
    {
        $col = Column::bigInt('Big')->makeNotNull();

        static::assertSame('Big', $col->name());
        static::assertSame(Column::BIGINT, $col->type());
        static::assertSame('%d', $col->format());
        static::assertNull($col->default());
        static::assertFalse($col->hasDefault());
        static::assertFalse($col->isNullable());
        static::assertFalse($col->isRealNumber());
        static::assertTrue($col->isInteger());
        static::assertFalse($col->isSerialized());
        static::assertTrue($col->isRequiredOnInsert());
        static::assertFalse($col->isAutoIncrement());
        static::assertFalse($col->isUnsigned());
    }

    /**
     * @test
     */
    public function testBit(): void
    {
        $col = Column::bit('bit', 8)->makeNotNull();

        static::assertSame('bit', $col->name());
        static::assertSame(Column::BIT, $col->type());
        static::assertSame('%d', $col->format());
        static::assertNull($col->default());
        static::assertTrue($col->isBit());
        static::assertFalse($col->hasDefault());
        static::assertFalse($col->isNullable());
        static::assertFalse($col->isRealNumber());
        static::assertFalse($col->isInteger());
        static::assertFalse($col->isSerialized());
        static::assertTrue($col->isRequiredOnInsert());
        static::assertFalse($col->isAutoIncrement());
        static::assertFalse($col->isUnsigned());
        static::assertFalse($col->isBoolean());
    }

    /**
     * @test
     */
    public function testBool(): void
    {
        $col = Column::bool('yer_or_no', false)->makeNotNull();

        static::assertSame('yer_or_no', $col->name());
        static::assertSame(Column::BIT, $col->type());
        static::assertSame('%d', $col->format());
        static::assertFalse($col->default());
        static::assertTrue($col->isBit());
        static::assertTrue($col->hasDefault());
        static::assertFalse($col->isNullable());
        static::assertFalse($col->isRealNumber());
        static::assertFalse($col->isInteger());
        static::assertFalse($col->isSerialized());
        static::assertFalse($col->isRequiredOnInsert());
        static::assertFalse($col->isAutoIncrement());
        static::assertFalse($col->isUnsigned());
        static::assertTrue($col->isBoolean());
    }

    /**
     * @test
     */
    public function testEntityId(): void
    {
        $col = Column::entityId('ID');

        static::assertSame('ID', $col->name());
        static::assertSame(Column::BIGINT, $col->type());
        static::assertSame('%d', $col->format());
        static::assertNull($col->default());
        static::assertFalse($col->hasDefault());
        static::assertFalse($col->isNullable());
        static::assertFalse($col->isRealNumber());
        static::assertTrue($col->isInteger());
        static::assertFalse($col->isSerialized());
        static::assertFalse($col->isRequiredOnInsert());
        static::assertTrue($col->isAutoIncrement());
        static::assertTrue($col->isUnsigned());
    }

    /**
     * @test
     */
    public function testCollationNotSupported(): void
    {
        $this->expectExceptionMessageMatches('/not support/i');
        Column::smallInt('x')->useCharsetCollation('ut8mb4');
    }

    /**
     * @test
     */
    public function testSerializedNotSupported(): void
    {
        $this->expectExceptionMessageMatches('/serialized/i');
        Column::mediumInt('y')->storeSerialized();
    }

    /**
     * @test
     */
    public function testRetrieveAsDateTimeNotSupported(): void
    {
        $this->expectExceptionMessageMatches('/DateTime/i');
        Column::int('y')->retrieveAsDateTime();
    }

    /**
     * @test
     */
    public function testCurrentTimestampAsDefault(): void
    {
        $this->expectExceptionMessageMatches('/timestamp/i');
        Column::bigInt('y')->useCurrentTimestampAsDefault();
    }

    /**
     * @test
     */
    public function testBitDefaultCantExceedItsSize(): void
    {
        $this->expectExceptionMessageMatches('/exceed/i');
        Column::bit('bb', 8, 256); // 8 bits can store numbers from 0 to 255
    }
}
