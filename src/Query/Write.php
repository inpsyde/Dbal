<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Query;

use Inpsyde\Dbal\Cache;
use Inpsyde\Dbal\Dbal;
use Inpsyde\Dbal\ErrorCollector;
use Inpsyde\Dbal\Error;
use Inpsyde\Dbal\PhpErrors;
use Inpsyde\Dbal\Result;
use Inpsyde\Dbal\Schema\ColumnValueEncoder;
use Inpsyde\Dbal\Schema\Index;
use Inpsyde\Dbal\Schema\Schema;
use Inpsyde\Dbal\Schema\SchemaFinder;

class Write
{

    private const CREATE = 'create';
    private const UPDATE = 'update';
    private const DELETE = 'delete';

    /**
     * @var Schema|null
     */
    private $schema;

    /**
     * @var \Inpsyde\Dbal\Cache|null
     */
    private $cache;

    /**
     * @var \Inpsyde\Dbal\Schema\SchemaFinder
     */
    private $finder;

    /**
     * @var \Inpsyde\Dbal\ErrorCollector
     */
    private $errors;

    /**
     * @param string $tableName
     * @param \Inpsyde\Dbal\Schema\SchemaFinder|null $finder
     * @param \Inpsyde\Dbal\Cache|null $cache
     * @return \Inpsyde\Dbal\Query\Write
     */
    public static function on(
        string $tableName,
        ?SchemaFinder $finder = null,
        ?Cache $cache = null
    ): Write {

        return new self($tableName, $finder, $cache);
    }

    /**
     * @param string $tableName
     * @param \Inpsyde\Dbal\Schema\SchemaFinder|null $finder
     * @param \Inpsyde\Dbal\Cache|null $cache
     */
    private function __construct(
        string $tableName,
        ?SchemaFinder $finder = null,
        ?Cache $cache = null
    ) {

        $this->finder = $finder ?? Dbal::schemaFinder();
        $this->schema = $this->finder->findSchema($tableName);
        $this->cache = $cache;
        $this->errors = new ErrorCollector();
        if (!$this->schema) {
            $this->errors->withError("Invalid table name {$tableName}.");
        }
    }

    /**
     * @param array $insertData
     * @return \Inpsyde\Dbal\Result
     */
    public function insert(array $insertData): Result
    {
        if (!$this->errors->isEmpty()) {
            return Result::new($this->errors);
        }

        try {
            [$data, $dataFormats] = $this->prepareInsertData($insertData);
        } catch (\Exception $exception) {
            return Result::new($exception);
        }

        $formats = [];
        foreach ($data as $key => $value) {
            $formats[] = $dataFormats[$key] ?? '%s';
        }

        return $this->execute(self::CREATE, $data, $formats);
    }

    /**
     * @param array $firstRow
     * @param array $secondRow
     * @param array[] $rows
     * @return \Inpsyde\Dbal\Result
     */
    public function insertMany(array $firstRow, array $secondRow, array ...$rows): Result
    {
        if (!$this->errors->isEmpty()) {
            return Result::new($this->errors);
        }

        array_unshift($rows, $secondRow);
        array_unshift($rows, $firstRow);

        /** @var array<int, array<string, mixed>> $rowsData */
        $rowsData = [];
        /** @var array<int, array<string, string>> $rowsFormats */
        $rowsFormats = [];

        /** @var array<string>|null $keys */
        $keys = null;

        try {
            /** @var Schema $schema */
            $schema = $this->schema;
            $table = $this->finder->fullTableName($schema);

            $i = 0;
            foreach ($rows as $row) {
                $i++;
                [$data, $formats] = $this->prepareInsertData($row);
                if (!$data) {
                    $message = "Error inserting multiple rows data in {$table} table: ";
                    $message .= "data for row {$i} not found.";

                    return Result::new(new \Exception($message));
                }

                /** @var array<string> $dataKeys */
                $dataKeys = array_keys($data);
                if ($keys === null) {
                    $keys = $dataKeys;
                } elseif ($dataKeys !== $keys) {
                    $message = "Error inserting multiple rows data in {$table} table: ";
                    $message .= "column names of row {$i} do not match. ";
                    $message .= "Expected: " . implode(', ', $keys);

                    return Result::new(new \Exception("{$message}."));
                }

                $rowsData[$i] = $data;
                $rowsFormats[$i] = $formats;
            }
        } catch (\Exception $exception) {
            return Result::new($exception);
        }

        $valuesSql = '';
        foreach ($rowsData as $i => $rowData) {
            $valuesSql and $valuesSql .= ",\n";
            $valueSql = $this->buildFieldsSql($rowData, $rowsFormats[$i], false);
            $valuesSql .= "\t({$valueSql})";
        }

        /** @var array<string> $keys */
        $columnNames = implode('`, `', $keys);
        $baseSql = "INSERT INTO `{$table}` (`{$columnNames}`) VALUES \n";

        return $this->executeQuery($baseSql . $valuesSql, self::CREATE);
    }

    /**
     * @param array $updateData
     * @param array $whereData
     * @return Result
     */
    public function update(array $updateData, array $whereData): Result
    {
        if (!$this->errors->isEmpty()) {
            return Result::new($this->errors);
        }

        /** @var Schema $schema */
        $schema = $this->schema;
        $columns = $schema->columns();
        [$updateValues, $dataFormats] = $columns->columnsInfoForDataUpdate($updateData);
        [$whereValues, $whereDataFormats] = $columns->columnsInfoForDataRead($whereData);

        $data = [];
        $where = [];
        $formats = [];
        $whereFormats = [];

        /** @var \Inpsyde\Dbal\Schema\Column $column */
        foreach ($columns as $column) {
            $name = $column->name();
            if (array_key_exists($name, $updateValues)) {
                $data[$name] = ColumnValueEncoder::for($column)->encode($updateValues[$name]);
                $formats[] = $dataFormats[$name] ?? '%s';
            }

            if (array_key_exists($name, $whereValues)) {
                $where[$name] = ColumnValueEncoder::for($column)->encode($whereValues[$name]);
                $whereFormats[] = $whereDataFormats[$name] ?? '%s';
            }
        }

        return $this->execute(self::UPDATE, $data, $formats, $where, $whereFormats);
    }

    /**
     * @param array $data
     * @param $primaryValue
     * @return Result
     *
     * @psalm-suppress MissingParamType
     * phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration
     */
    public function updateOnPrimary(array $data, $primaryValue): Result
    {
        if (!$this->errors->isEmpty()) {
            return Result::new($this->errors);
        }

        // phpcs:enable Inpsyde.CodeQuality.ArgumentTypeDeclaration

        try {
            $primary = $this->findPrimary();
        } catch (\Error $error) {
            return Result::new($error);
        }

        return $this->update($data, [$primary->name() => $primaryValue]);
    }

    /**
     * @param \Inpsyde\Dbal\Schema\Schema $schema
     * @param array $data
     * @param \Inpsyde\Dbal\Query\Where $where
     * @return Result
     */
    public function updateWhere(array $data, Where $where): Result
    {
        if (!$this->errors->isEmpty()) {
            return Result::new($this->errors);
        }

        /** @var Schema $schema */
        $schema = $this->schema;
        $table = $this->finder->fullTableName($schema);

        if (!$where->hasClauses()) {
            return Result::new(new \Error("Can't update {$table} without WHERE clauses."));
        }

        $columns = $schema->columns();
        [$updateValues, $dataFormats] = $columns->columnsInfoForDataUpdate($data);
        if (!$updateValues) {
            return Result::new(new \Error("Can't update {$table} without data."));
        }

        $data = [];
        $formats = [];

        /** @var \Inpsyde\Dbal\Schema\Column $column */
        foreach ($columns as $column) {
            $name = $column->name();
            if (array_key_exists($name, $updateValues)) {
                $data[$name] = ColumnValueEncoder::for($column)->encode($updateValues[$name]);
                $formats[$name] = $dataFormats[$name] ?? '%s';
            }
        }

        $valuesSql = $this->buildFieldsSql($data, $formats, true);
        $whereClause = rtrim($where->clause($schema, $this->finder, Aliases::new($this->finder)));

        return $this->executeQuery(
            "UPDATE `{$table}` SET {$valuesSql} WHERE {$whereClause};",
            self::UPDATE
        );
    }

    /**
     * @param array $whereData
     * @return Result
     */
    public function delete(array $whereData): Result
    {
        if (!$this->errors->isEmpty()) {
            return Result::new($this->errors);
        }

        /** @var Schema $schema */
        $schema = $this->schema;
        $columns = $schema->columns();
        [$whereValues, $whereDataFormats] = $columns->columnsInfoForDataRead($whereData);

        $where = [];
        $whereFormats = [];

        /** @var \Inpsyde\Dbal\Schema\Column $column */
        foreach ($columns as $column) {
            $name = $column->name();

            if (array_key_exists($name, $whereValues)) {
                $where[$name] = ColumnValueEncoder::for($column)->encode($whereValues[$name]);
                $whereFormats[] = $whereDataFormats[$name] ?? '%s';
            }
        }

        return $this->execute(self::DELETE, [], [], $where, $whereFormats);
    }

    /**
     * @param \Inpsyde\Dbal\Query\Where $where
     * @return Result
     */
    public function deleteWhere(Where $where): Result
    {
        if (!$this->errors->isEmpty()) {
            return Result::new($this->errors);
        }

        /** @var Schema $schema */
        $schema = $this->schema;
        $table = $this->finder->fullTableName($schema);

        if (!$where->hasClauses()) {
            return Result::new(new \Error("Can't update {$table} without WHERE clauses."));
        }

        $whereClause = rtrim($where->clause($schema, $this->finder, Aliases::new($this->finder)));

        return $this->executeQuery("DELETE FROM `{$table}` WHERE {$whereClause};", self::DELETE);
    }

    /**
     * @param $primaryValue
     * @return Result
     *
     * @psalm-suppress MissingParamType
     * phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration
     */
    public function deleteOnPrimary($primaryValue): Result
    {
        // phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration

        if (!$this->errors->isEmpty()) {
            return Result::new($this->errors);
        }

        try {
            $primary = $this->findPrimary();
        } catch (\Error $error) {
            return Result::new($error);
        }

        return $this->delete([$primary->name() => $primaryValue]);
    }

    /**
     * @param array $data
     * @return array{0:array<string, mixed>, 1:array<string, string>}
     */
    private function prepareInsertData(array $data): array
    {
        /** @var Schema $schema */
        $schema = $this->schema;
        $columns = $schema->columns();
        [$data, $formats, $missing] = $columns->columnsInfoForDataInsert($data);

        $name = $schema->name();
        if ($missing) {
            $missingStr = count($missing) === 1 ? " column: " : " columns: ";
            $missingCol = implode("', '", $missing);
            $missingStr .= "'{$missingCol}'";
            throw new \Exception("Error inserting row in {$name} table, missing {$missingStr}.");
        }

        if (!$data) {
            throw new \Exception("Error inserting row in {$name} table, no data.");
        }

        $toPrepare = [];

        /** @var \Inpsyde\Dbal\Schema\Column $column */
        foreach ($columns as $column) {
            $name = $column->name();
            if (array_key_exists($name, $data)) {
                $toPrepare[$name] = ColumnValueEncoder::for($column)->encode($data[$name]);
            }
        }

        return [$toPrepare, $formats];
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $formats
     * @return string
     */
    private function buildFieldsSql(array $data, array $formats, bool $update): string
    {
        $valuesSql = [];
        $escParams = [];
        foreach ($data as $key => $value) {
            $format = $formats[$key] ?? '%s';
            $valuesSql[] = $update ? "`{$key}` = {$format}" : $format;
            $escParams[] = $value;
        }

        return (string)(Dbal::wpdb()->prepare(implode(', ', $valuesSql), $escParams) ?? '');
    }

    /**
     * @param string $type
     * @param array $data
     * @param array $formats
     * @param array $whereData
     * @param array $whereFormats
     * @return \Inpsyde\Dbal\Result
     */
    private function execute(
        string $type,
        array $data = [],
        array $formats = [],
        array $whereData = [],
        array $whereFormats = []
    ): Result {

        switch ($type) {
            case self::DELETE:
                $func = 'delete';
                $args = [$whereData, $whereFormats];
                break;
            case self::CREATE:
                $func = 'insert';
                $args = [$data, $formats];
                break;
            case self::UPDATE:
                $func = 'update';
                $args = [$data, $whereData, $formats, $whereFormats];
                break;
            default:
                return Result::new(new Error("Invalid query type {$type}."));
        }

        $finder = $this->finder;

        $execute = static function (\wpdb $wpdb, Schema $schema) use ($finder, $args, $func): int {
            /** @var callable $method */
            $method = [$wpdb, $func];

            return (int)$method($finder->fullTableName($schema), ...$args);
        };

        return $this->safeExecute($execute, $type);
    }

    /**
     * @param string $query
     * @param string $type
     * @return Result
     */
    private function executeQuery(string $query, string $type): Result
    {
        $execute = static function (\wpdb $wpdb) use ($query): int {
            return (int)$wpdb->query($query);
        };

        return $this->safeExecute($execute, $type);
    }

    /**
     * @param callable $callback
     * @param string $operation
     * @return Result
     */
    private function safeExecute(callable $callback, string $operation): Result
    {
        $wpdb = Dbal::wpdb();
        $phpErrors = PhpErrors::convertToExceptions();
        $suppressErrors = $wpdb->suppress_errors(true);

        $error = new Error("Failed executing {$operation} operation.");

        try {
            /** @var Schema $schema */
            $schema = $this->schema;
            $table = $this->finder->fullTableName($schema);
            switch ($operation) {
                case self::CREATE:
                    $error = new Error("Failed inserting row(s) into {$table}.");
                    break;
                case self::DELETE:
                    $error = new Error("Failed deleting row(s) from {$table}.");
                    break;
                case self::UPDATE:
                default:
                    $error = new Error("Failed updating row(s) of {$table}.");
                    break;
            }

            $result = $callback($wpdb, $schema);
            $error = Error::withMerged($error, "Errored query: {$wpdb->last_query}.");
            $this->cache and $this->cache->cleanCacheForTables($schema->name());

            if ($result === false || $wpdb->last_error) {
                $value = $wpdb->last_error ? new Error($wpdb->last_error) : null;

                return Result::new($value)->mergeError($error);
            }

            $data = (object)['rows' => (int)$result];
            if ($operation === self::CREATE) {
                $data->insertId = (int)$wpdb->insert_id;
            }

            return Result::new($data);
        } catch (\Throwable $throwable) {
            return Result::new(Error::withMergedThrowable($error, $throwable));
        } finally {
            $phpErrors->restoreHandler();
            $wpdb->suppress_errors($suppressErrors);
        }
    }

    /**
     * @return Index
     */
    private function findPrimary(): Index
    {
        /** @var Schema $schema */
        $schema = $this->schema;
        $indexes = $schema->indexes();
        $primary = $indexes ? $indexes->primary() : null;
        if (!$primary) {
            $name = $this->finder->fullTableName($schema);

            throw new \Error("Table {$name} doesn't have a primary column.");
        }

        return $primary;
    }
}
