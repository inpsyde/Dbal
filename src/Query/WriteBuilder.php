<?php

declare(strict_types=1);

namespace Syde\Dbal\Query;

use Syde\Dbal\Dbal;
use Syde\Dbal\Error;
use Syde\Dbal\Result;

final class WriteBuilder
{
    private string $tableName;

    /**
     * @param string $tableName
     * @return WriteBuilder
     */
    public static function new(string $tableName): WriteBuilder
    {
        return new self($tableName);
    }

    /**
     * @param string $tableName
     */
    private function __construct(string $tableName)
    {
        $this->tableName = $tableName;
    }

    /**
     * @return Result
     */
    public function build(): Result
    {
        $write = Dbal::writeOn($this->tableName);

        if (!Dbal::isReady()) {
            return Result::new(
                new Error(sprintf('Invalid call to %s() before DBAL is ready.', __METHOD__))
            );
        }

        return Result::new($write);
    }

    /**
     * @param list<mixed> $insertData
     * @return Result
     */
    public function insert(array $insertData): Result
    {
        return $this->execute(__FUNCTION__, func_get_args());
    }

    /**
     * @param array<string, mixed> $firstRow
     * @param array<string, mixed> $secondRow
     * @param array<array<mixed>> $rows
     * @return Result
     */
    public function insertMany(array $firstRow, array $secondRow, array ...$rows): Result
    {
        return $this->execute(__FUNCTION__, func_get_args());
    }

    /**
     * @param array<string, mixed> $updateData
     * @param array<string, mixed> $whereData
     * @return Result
     */
    public function update(array $updateData, array $whereData): Result
    {
        return $this->execute(__FUNCTION__, func_get_args());
    }

    /**
     * @param array<string, mixed> $data
     * @param mixed $primaryValue
     * @return Result
     */
    public function updateOnPrimary(array $data, mixed $primaryValue): Result
    {
        return $this->execute(__FUNCTION__, func_get_args());
    }

    /**
     * @param array<string, mixed> $data
     * @param Where $where
     * @return Result
     */
    public function updateWhere(array $data, Where $where): Result
    {
        return $this->execute(__FUNCTION__, func_get_args());
    }

    /**
     * @param array<string, mixed> $whereData
     * @return Result
     */
    public function delete(array $whereData): Result
    {
        return $this->execute(__FUNCTION__, func_get_args());
    }

    /**
     * @param Where $where
     * @return Result
     */
    public function deleteWhere(Where $where): Result
    {
        return $this->execute(__FUNCTION__, func_get_args());
    }

    /**
     * @param mixed $primaryValue
     * @return Result
     */
    public function deleteOnPrimary(mixed $primaryValue): Result
    {
        return $this->execute(__FUNCTION__, func_get_args());
    }

    /**
     * @param string $method
     * @param array<string, mixed>|array<mixed> $args
     * @return Result
     */
    private function execute(string $method, array $args): Result
    {
        return $this->build()->bind(
            static function (Write $write) use ($method, $args): Result {
                /** @var Result $result */
                $result = $write->{$method}(...$args);

                return $result;
            },
            static function (Error $error) use ($method): Error {
                return $error->merge(new Error("Could not execute Write::{$method}()"));
            }
        );
    }
}
