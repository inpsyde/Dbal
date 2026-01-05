<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Tests\Unit\Schema;

use Brain\Monkey;
use Inpsyde\Dbal\Schema\Column;
use Inpsyde\Dbal\Schema\Columns;
use Inpsyde\Dbal\Schema\WpSchemas;
use Inpsyde\Dbal\Tests\UnitTestCase;

class WpSchemasTest extends UnitTestCase
{
    /**
     * @test
     */
    public function testSingleSiteWpSchemaParsing(): void
    {
        Monkey\Functions\when('is_multisite')->justReturn(false);

        $postColumns = WpSchemas::new()->postsColumns();

        static::assertInstanceOf(Columns::class, $postColumns);
        static::assertTrue($postColumns->hasColumn('post_date'));

        $dateCol = $postColumns->findColumn('post_date');
        static::assertInstanceOf(Column::class, $dateCol);
        static::assertSame(Column::DATETIME, $dateCol->type());
        static::assertSame('0000-00-00', $dateCol->default());
        static::assertFalse($dateCol->isNullable());
    }
}
