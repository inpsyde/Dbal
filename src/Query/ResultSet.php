<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Query;

use Inpsyde\Dbal\Error;
use Inpsyde\Dbal\Result;

class ResultSet implements \IteratorAggregate, \Countable, \JsonSerializable
{
    /**
     * @var Select|null
     */
    private $select;

    /**
     * @var Pagination|null
     */
    private $pagination;

    /**
     * @var ResultsParser|null
     */
    private $results;

    /**
     * @var array<array>|null
     */
    private $rows;

    /**
     * @var Error|null
     */
    private $error;

    /**
     * @var callable|null
     */
    private $map;

    /**
     * @var callable|null
     */
    private $filter;

    /**
     * @param Error $error
     * @return ResultSet
     */
    public static function errored(Error $error): ResultSet
    {
        $instance = static::empty();
        $instance->error = $error;

        return $instance;
    }

    /**
     * @param Select $select
     * @param ResultsParser $results
     * @param array $row
     * @return ResultSet
     */
    public static function singleRow(
        Select $select,
        ResultsParser $results,
        array $row
    ): ResultSet {

        return new static($select, $results, null, $row);
    }

    /**
     * @return ResultSet
     */
    public static function empty(): ResultSet
    {
        return new static();
    }

    /**
     * @param Select $select
     * @param ResultsParser $results
     * @param Pagination $pagination
     * @param array ...$rows
     * @return ResultSet
     */
    public static function new(
        Select $select,
        ResultsParser $results,
        Pagination $pagination,
        array ...$rows
    ): ResultSet {

        return new static($select, $results, $pagination, ...$rows);
    }

    /**
     * @param Select|null $select
     * @param ResultsParser|null $results
     * @param Pagination|null $pagination
     * @param array ...$rows
     */
    private function __construct(
        ?Select $select = null,
        ?ResultsParser $results = null,
        ?Pagination $pagination = null,
        array ...$rows
    ) {

        $this->select = $select;
        $this->pagination = $pagination;
        $this->results = $results;
        $this->rows = $rows;
    }

    private function __clone()
    {
    }

    // phpcs:disable PHPCompatibility.FunctionDeclarations.NonStaticMagicMethods.__sleepMethodVisibility
    private function __sleep()
    {
        // phpcs:enable PHPCompatibility.FunctionDeclarations.NonStaticMagicMethods.__sleepMethodVisibility
    }

    private function __wakeup()
    {
    }

    /**
     * @return bool
     *
     * @psalm-assert-if-true Error $this->error
     * @psalm-assert-if-false null $this->error
     */
    public function hasErrors(): bool
    {
        return $this->error !== null;
    }

    /**
     * @return void
     */
    public function assert(): void
    {
        if ($this->error !== null) {
            throw $this->error;
        }
    }

    /**
     * @return bool
     *
     * @psalm-assert-if-true Select $this->select
     */
    public function isValid(): bool
    {
        return !$this->hasErrors() && $this->rows && $this->results;
    }

    /**
     * @return Pagination|null
     */
    public function pagination(): ?Pagination
    {
        return $this->pagination;
    }

    /**
     * @return mixed|null
     *
     * @psalm-suppress MissingReturnType
     * phpcs:disable Inpsyde.CodeQuality.ReturnTypeDeclaration
     */
    public function first()
    {
        // phpcs:enable Inpsyde.CodeQuality.ReturnTypeDeclaration

        if (!$this->isValid()) {
            return null;
        }

        $gen = $this->yieldResults();
        foreach ($gen as $result) {
            return $result;
        }

        return null;
    }

    /**
     * @return ResultSet
     */
    public function nextPage(): ResultSet
    {
        if (!$this->isValid() || !$this->hasMorePages()) {
            return static::errored(new Error('Result set has no next page.', 0, $this->error));
        }

        /** @var Pagination $nextPagination */
        $nextPagination = $this->pagination->forNextPage();

        $resultsSet = $this->select->paginatedWith($nextPagination)->all();
        $resultsSet->map = $this->map;
        $resultsSet->filter = $this->filter;

        return $resultsSet;
    }

    /**
     * @return bool
     *
     * @psalm-assert-if-true Pagination $this->pagination
     */
    public function hasMorePages(): bool
    {
        return $this->pagination && $this->pagination->hasMorePages();
    }

    /**
     * @param callable $callback
     * @return ResultSet
     */
    public function map(callable $callback): ResultSet
    {
        if ($this->isValid()) {
            $this->map = $callback;
        }

        return $this;
    }

    /**
     * @param callable $callback
     * @return ResultSet
     */
    public function filter(callable $callback): ResultSet
    {
        if ($this->isValid()) {
            $this->filter = $callback;
        }

        return $this;
    }

    /**
     * @return \Traversable
     */
    public function getIterator()
    {
        if ($this->isValid()) {
            return $this->yieldResults();
        }

        return new \EmptyIterator();
    }

    /**
     * @return \Traversable
     */
    public function autoPaginationIterator(): \Traversable
    {
        if (!$this->isValid()) {
            return new \EmptyIterator();
        }

        $instance = $this;
        yield from $instance->yieldResults();

        while ($instance->hasMorePages()) {
            $instance = $instance->nextPage();
            if (!$instance->isValid()) {
                break;
            }

            yield from $instance->yieldResults();
        }
    }

    /**
     * @return int
     */
    public function count()
    {
        if (!$this->isValid()) {
            return 0;
        }

        return $this->rows ? count($this->rows) : 0;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        $this->assert();

        return iterator_to_array($this->getIterator());
    }

    /**
     * @return Result
     */
    public function toResult(): Result
    {
        return $this->hasErrors() ? Result::new($this->error) : Result::new($this->getIterator());
    }

    /**
     * @param string $column
     * @return array
     */
    public function toColumnArray(string $column): array
    {
        $this->assert();

        return $this->isValid() ? array_column($this->toArray(), $column) : [];
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        if ($this->hasErrors()) {
            return ['errors' => $this->error->allMessages()];
        }

        return $this->isValid() ? $this->toArray() : [];
    }

    /**
     * @return \Generator
     */
    private function yieldResults(): \Generator
    {
        /** @var array<array> $rows */
        $rows = $this->rows;
        /** @var ResultsParser $results */
        $results = $this->results;

        foreach ($rows as $row) {
            $parsed = $results->parse($row);
            if ($this->filter && !($this->filter)($parsed)) {
                continue;
            }

            yield ($this->map ? ($this->map)($parsed) : $parsed);
        }
    }
}
