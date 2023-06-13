<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Tests\Unit\Query;

use Inpsyde\Dbal\Query\Pagination;
use Inpsyde\Dbal\Tests\UnitTestCase;

class PaginationTest extends UnitTestCase
{
    /**
     * @test
     */
    public function testBasic(): void
    {
        $pagination = Pagination::new(10, 1, 3);

        static::assertSame(10, $pagination->perPage());
        static::assertSame(1, $pagination->page());
        static::assertSame(3, $pagination->totalPages());
        static::assertNull($pagination->totalRows());
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

    /**
     * @test
     */
    public function testByTotalRows(): void
    {
        $pagination = Pagination::byTotalRows(100, 33, 1);

        static::assertSame(4, $pagination->totalPages());
        static::assertSame(33, $pagination->perPage());
        static::assertSame(1, $pagination->page());

        $last = $pagination->forNextPage()->forNextPage()->forNextPage();
        static::assertSame(4, $last->page());
        static::assertNull($last->forNextPage());
        static::assertSame(100, $pagination->totalRows());
    }

    /**
     * @test
     */
    public function testByTotalRowsEmpty(): void
    {
        $pagination = Pagination::byTotalRows(0, 33, 1);

        static::assertSame(1, $pagination->totalPages());
        static::assertSame(33, $pagination->perPage());
        static::assertSame(1, $pagination->page());
        static::assertFalse($pagination->hasMorePages());
        static::assertNull($pagination->forNextPage());
        static::assertSame(0, $pagination->totalRows());
    }

    /**
     * @test
     */
    public function testByTotalRowsLessThanPerPage(): void
    {
        $pagination = Pagination::byTotalRows(10, 33, 1);

        static::assertSame(1, $pagination->totalPages());
        static::assertSame(33, $pagination->perPage());
        static::assertSame(1, $pagination->page());
        static::assertFalse($pagination->hasMorePages());
        static::assertNull($pagination->forNextPage());
    }

    /**
     * @test
     */
    public function testMotPaginated(): void
    {
        $pagination = Pagination::notPaginated();

        static::assertSame(1, $pagination->totalPages());
        static::assertNull($pagination->perPage());
        static::assertSame(1, $pagination->page());
        static::assertFalse($pagination->hasMorePages());
        static::assertNull($pagination->forNextPage());
    }
}
