<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Query;

final class Pagination
{
    /**
     * @var int|null
     */
    private $perPage;

    /**
     * @var int
     */
    private $page;

    /**
     * @var int
     */
    private $totalPages;

    /**
     * @var ?int
     */
    private $totalRows = null;

    /**
     * @param int $perPage
     * @param int $page
     * @param int $totalPages
     * @return Pagination
     */
    public static function new(int $perPage, int $page, int $totalPages): Pagination
    {
        return new static($perPage, $page, $totalPages);
    }

    /**
     * @param int $totalRows
     * @param int $perPage
     * @param int $page
     * @return Pagination
     */
    public static function byTotalRows(int $totalRows, int $perPage, int $page): Pagination
    {
        $instance = static::new($perPage, $page, (int)ceil($totalRows / $perPage));
        $instance->totalRows = $totalRows;

        return $instance;
    }

    /**
     * @param int $totalRows
     * @return Pagination
     */
    public static function notPaginated(): Pagination
    {
        $instance = static::new(1, 1, 1);
        $instance->perPage = null;

        return $instance;
    }

    /**
     * @param int $perPage
     * @param int $page
     * @param int $totalPages
     */
    private function __construct(int $perPage, int $page, int $totalPages)
    {
        $this->perPage = max(1, abs($perPage));
        $this->totalPages = max(1, abs($totalPages));
        $this->page = min(abs($page), $this->totalPages);
    }

    /**
     * @return int|null
     */
    public function perPage(): ?int
    {
        return $this->perPage;
    }

    /**
     * @return int
     */
    public function page(): int
    {
        return $this->page;
    }

    /**
     * @return int
     */
    public function totalPages(): int
    {
        return $this->totalPages;
    }

    /**
     * @return int|null
     */
    public function totalRows(): ?int
    {
        return $this->totalRows;
    }

    /**
     * @psalm-assert-if-true int $this->perPage
     */
    public function hasMorePages(): bool
    {
        return ($this->perPage !== null) && ($this->page < $this->totalPages);
    }

    /**
     * @return Pagination|null
     */
    public function forNextPage(): ?Pagination
    {
        if ($this->hasMorePages()) {
            return new static($this->perPage, $this->page + 1, $this->totalPages);
        }

        return null;
    }
}
