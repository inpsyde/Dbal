<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Tests\Unit\Query;

use Inpsyde\Dbal\Query\Pagination;
use Inpsyde\Dbal\Tests\UnitTestCase;

class PaginationTest extends UnitTestCase
{
    public function testBasic()
    {
        $pagination = Pagination::new(10, 1, 3);

        static::assertSame(10, $pagination->perPage());
        static::assertSame(1, $pagination->page());
        static::assertSame(3, $pagination->totalPages());
        static::assertTrue($pagination->hasMorePages());

        $expectedPage = 1;
        while ($pagination->hasMorePages()) {
            $expectedPage++;
            $pagination = $pagination->forNextPage();
            static::assertSame($expectedPage, $pagination->page());
            static::assertSame(10, $pagination->perPage());
            static::assertSame(3, $pagination->totalPages());
        }

        static::assertSame(3, $expectedPage);
    }

    public function testByTotalRows()
    {
        $pagination = Pagination::byTotalRows(100, 33, 1);

        static::assertSame(4, $pagination->totalPages());
        static::assertSame(33, $pagination->perPage());
        static::assertSame(1, $pagination->page());

        $last = $pagination->forNextPage()->forNextPage()->forNextPage();
        static::assertSame(4, $last->page());
        static::assertNull($last->forNextPage());
    }

    public function testByTotalRowsEmpty()
    {
        $pagination = Pagination::byTotalRows(0, 33, 1);

        static::assertSame(1, $pagination->totalPages());
        static::assertSame(33, $pagination->perPage());
        static::assertSame(1, $pagination->page());
        static::assertFalse($pagination->hasMorePages());
        static::assertNull($pagination->forNextPage());
    }

    public function testByTotalRowsLessThanPerPage()
    {
        $pagination = Pagination::byTotalRows(10, 33, 1);

        static::assertSame(1, $pagination->totalPages());
        static::assertSame(33, $pagination->perPage());
        static::assertSame(1, $pagination->page());
        static::assertFalse($pagination->hasMorePages());
        static::assertNull($pagination->forNextPage());
    }

    public function testMotPaginated()
    {
        $pagination = Pagination::notPaginated();

        static::assertSame(1, $pagination->totalPages());
        static::assertNull($pagination->perPage());
        static::assertSame(1, $pagination->page());
        static::assertFalse($pagination->hasMorePages());
        static::assertNull($pagination->forNextPage());
    }
}
