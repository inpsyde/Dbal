<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Tests\Integration;

use Inpsyde\Dbal\Dbal;
use Inpsyde\Dbal\Schema\Column;
use Inpsyde\Dbal\Tests\IntegrationTestCase;

class WpSchemaTest extends IntegrationTestCase
{
    /**
     * @test
     */
    public function testSchemaRetrieval(): void
    {
        $postColumns = Dbal::wpSchema()->postsColumns();

        static::assertTrue($postColumns->hasColumn('post_date'));
        $date = $postColumns->findColumn('post_date');

        static::assertSame(Column::DATETIME, $date->type());
        static::assertSame('0000-00-00', $date->default());
        static::assertFalse($date->isNullable());

        $this->resetDb();
    }
}
