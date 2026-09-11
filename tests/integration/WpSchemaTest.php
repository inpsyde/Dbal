<?php

declare(strict_types=1);

namespace Syde\Dbal\Tests\Integration;

use Syde\Dbal\Dbal;
use Syde\Dbal\Schema\Column;
use Syde\Dbal\Schema\Columns;
use Syde\Dbal\Tests\IntegrationTestCase;

/**
 * @runTestsInSeparateProcesses
 */
class WpSchemaTest extends IntegrationTestCase
{
    /**
     * @test
     * @preserveGlobalState disabled
     */
    public function testSchemaRetrieval(): void
    {
        $postColumns = Dbal::wpSchema()->postsColumns();

        static::assertInstanceOf(Columns::class, $postColumns);
        static::assertTrue($postColumns->hasColumn('post_date'));
        $dateCol = $postColumns->findColumn('post_date');

        static::assertInstanceOf(Column::class, $dateCol);
        static::assertSame(Column::DATETIME, $dateCol->type());
        static::assertSame('0000-00-00', $dateCol->default());
        static::assertFalse($dateCol->isNullable());
    }
}
