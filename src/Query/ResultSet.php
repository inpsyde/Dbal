<?php

declare(strict_types=1);

namespace Syde\Dbal\Query;

use Syde\Dbal\Error;
use Syde\Dbal\Result;

/**
 * @template-implements \IteratorAggregate<int, array>
 */
class ResultSet implements \IteratorAggregate, \Countable, \JsonSerializable
{
    private ?Select $select;
    private ?Pagination $pagination;
    private ?ResultsParser $results;
    private ?Error $error = null;

    /** @var string[] */
    private array $rows;

    /** @var callable|null */
    private $map;

    /** @var callable|null */
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
     * @param string[] $row
     * @return ResultSet
     */
    public static function singleRow(
        Select $select,
        ResultsParser $results,
        array $row
    ): ResultSet {

        return new self($select, $results, null, $row);
    }

    /**
     * @return ResultSet
     */
    public static function empty(): ResultSet
    {
        return new self();
    }

    /**
     * @param Select $select
     * @param ResultsParser $results
     * @param Pagination $pagination
     * @param string[] ...$rows
     * @return ResultSet
     */
    public static function new(
        Select $select,
        ResultsParser $results,
        Pagination $pagination,
        array ...$rows
    ): ResultSet {

        return new self($select, $results, $pagination, ...$rows);
    }

    /**
     * @param Select|null $select
     * @param ResultsParser|null $results
     * @param Pagination|null $pagination
     * @param list<string> ...$rows
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
        /** @phpstan-ignore assign.propertyType */
        $this->rows = array_values($rows);
    }

    private function __clone()
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
        return !$this->hasErrors() && ($this->rows !== []) && $this->results;
    }

    /**
     * @return Pagination|null
     */
    public function pagination(): ?Pagination
    {
        return $this->pagination;
    }

    /**
     * @return mixed
     *
     * phpcs:disable Syde.Functions.ReturnTypeDeclaration.NoReturnType
     */
    public function first()
    {
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
     * @return \Traversable<int, array<mixed>>
     */
    public function getIterator(): \Traversable
    {
        if ($this->isValid()) {
            return $this->yieldResults();
        }

        return new \EmptyIterator();
    }

    /**
     * @return \Traversable<int, array<mixed>>
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
    public function count(): int
    {
        if (!$this->isValid()) {
            return 0;
        }

        return count($this->rows);
    }

    /**
     * @return array<mixed>
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
     * @return array<string>
     */
    public function toColumnArray(string $column): array
    {
        $this->assert();

        return $this->isValid() ? array_column($this->toArray(), $column) : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
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
        $rows = $this->rows;
        /** @var ResultsParser $results */
        $results = $this->results;

        foreach ($rows as $row) {
            /** @phpstan-ignore-next-line */
            $parsed = $results->parse($row);
            if (($this->filter !== null) && !($this->filter)($parsed)) {
                continue;
            }

            yield (($this->map !== null) ? ($this->map)($parsed) : $parsed);
        }
    }
}
