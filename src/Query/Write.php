<?php

declare(strict_types=1);

namespace Syde\Dbal\Query;

use Syde\Dbal\Cache;
use Syde\Dbal\Dbal;
use Syde\Dbal\Error;
use Syde\Dbal\ErrorCollector;
use Syde\Dbal\PhpErrors;
use Syde\Dbal\Result;
use Syde\Dbal\Schema\Columns;
use Syde\Dbal\Schema\ColumnValueEncoder;
use Syde\Dbal\Schema\Index;
use Syde\Dbal\Schema\Schema;
use Syde\Dbal\Schema\SchemaFinder;

/**
 * @phpstan-import-type ColumnsData from Columns
 */
class Write
{
    use PrimaryAware;

    private const CREATE = 'create';
    private const UPDATE = 'update';
    private ?Schema $schema;
    private ?Cache $cache;
    private SchemaFinder $finder;
    private ErrorCollector $errors;

    /**
     * @param string $tableName
     * @param SchemaFinder|null $finder
     * @param Cache|null $cache
     * @return Write
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
     * @param SchemaFinder|null $finder
     * @param Cache|null $cache
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
     * @param ColumnsData $insertData
     * @return Result
     */
    public function insert(array $insertData): Result
    {
        if (!$this->errors->isEmpty()) {
            return Result::new($this->errors);
        }

        try {
            [$data, $dataFormats] = $this->prepareInsertData($insertData);
        } catch (\Throwable $exception) {
            return Result::new($exception);
        }

        $formats = [];
        foreach (array_keys($data) as $key) {
            $formats[] = $dataFormats[$key] ?? '%s';
        }

        return $this->execute(self::CREATE, $data, $formats);
    }

    /**
     * @param ColumnsData $firstRow
     * @param ColumnsData $secondRow
     * @param ColumnsData[] ...$rows
     * @return Result
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
        } catch (\Throwable $exception) {
            return Result::new($exception);
        }

        $valuesSql = '';
        foreach ($rowsData as $i => $rowData) {
            if ($valuesSql !== '') {
                $valuesSql .= ",\n";
            }
            $valueSql = $this->buildFieldsSql($rowData, $rowsFormats[$i], false);
            $valuesSql .= "\t({$valueSql})";
        }

        $columnNames = implode('`, `', $keys);
        $baseSql = "INSERT INTO `{$table}` (`{$columnNames}`) VALUES \n";

        return $this->executeQuery($baseSql . $valuesSql, self::CREATE);
    }

    /**
     * @param ColumnsData $updateData
     * @param ColumnsData $whereData
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
     * @param ColumnsData $data
     * @param mixed $primaryValue
     * @return Result
     */
    public function updateOnPrimary(array $data, mixed $primaryValue): Result
    {
        if (!$this->errors->isEmpty()) {
            return Result::new($this->errors);
        }

        return $this->findPrimary($this->schema, $this->finder)
            ->bind(
                function (Index $index) use ($data, $primaryValue): Result {
                    return $this->update($data, [$index->name() => $primaryValue]);
                }
            );
    }

    /**
     * @param ColumnsData $data
     * @param Where $where
     * @return Result
     */
    public function updateWhere(array $data, Where $where): Result
    {
        if (!$this->errors->isEmpty()) {
            return Result::new($this->errors);
        }

        /** @var Schema $schema */
        $schema = $this->schema;
        $tableName = $this->finder->fullTableName($schema);

        if (!$where->hasClauses()) {
            return Result::new(new \Error("Can't update {$tableName} without WHERE clauses."));
        }

        $columns = $schema->columns();
        [$updateValues, $dataFormats, $missing] = $columns->columnsInfoForDataUpdate($data);

        static::assertNotMissing($missing, $tableName, true);

        if (!$updateValues) {
            return Result::new(new \Error("Can't update {$tableName} without data."));
        }

        $data = [];
        $formats = [];

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
            "UPDATE `{$tableName}` SET {$valuesSql} WHERE {$whereClause};",
            self::UPDATE
        );
    }

    /**
     * @param ColumnsData $data
     * @return array{array<string, mixed>, array<string, string>}
     */
    private function prepareInsertData(array $data): array
    {
        /** @var Schema $schema */
        $schema = $this->schema;
        $tableName = $this->finder->fullTableName($schema);
        $columns = $schema->columns();
        [$data, $formats, $missing] = $columns->columnsInfoForDataInsert($data);

        static::assertNotMissing($missing, $tableName, false);

        if (!$data) {
            throw new \Exception(
                sprintf('Error inserting row in %s table, no data.', esc_html($tableName))
            );
        }

        $toPrepare = [];

        foreach ($columns as $column) {
            $name = $column->name();
            if (array_key_exists($name, $data)) {
                $toPrepare[$name] = ColumnValueEncoder::for($column)->encode($data[$name]);
            }
        }

        return [$toPrepare, $formats];
    }

    /**
     * @param list<string>|null $missing
     * @param string $tableName
     * @param bool $isUpdate
     */
    protected static function assertNotMissing(
        ?array $missing,
        string $tableName,
        bool $isUpdate
    ): void {

        if (($missing === null) || ($missing === [])) {
            return;
        }

        $one = count($missing) === 1;
        $missingStr = $one ? "column: '" : "columns: '";
        $missingStr .= implode("', '", $missing) . "'";
        $be = $one ? 'is' : 'are';
        $task = $isUpdate ? 'updating' : 'inserting';
        $reason = $isUpdate
            ? "{$missingStr} can not be null"
            : "{$missingStr} {$be} missing or null";

        throw new \Exception(
            esc_html(sprintf('Error %s row in %s table, %s.', $task, $tableName, $reason))
        );
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $formats
     * @param bool $update
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

        return (string) (Dbal::wpdb()->prepare(implode(', ', $valuesSql), $escParams) ?? '');
    }

    /**
     * @param string $type
     * @param ColumnsData $data
     * @param array<string> $formats
     * @param ColumnsData $whereData
     * @param array<string> $whereFormats
     * @return Result
     */
    private function execute(
        string $type,
        array $data = [],
        array $formats = [],
        array $whereData = [],
        array $whereFormats = []
    ): Result {

        switch ($type) {
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

            return (int) $method($finder->fullTableName($schema), ...$args);
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
            return (int) $wpdb->query($query);
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
                case self::UPDATE:
                default:
                    $error = new Error("Failed updating row(s) of {$table}.");
                    break;
            }

            $result = $callback($wpdb, $schema);
            $error = Error::withMerged($error, "Errored query: {$wpdb->last_query}.");
            if ($this->cache !== null) {
                $this->cache->cleanCacheForTables($schema->name());
            }

            if ($result === false || $wpdb->last_error) {
                $value = $wpdb->last_error ? new Error($wpdb->last_error) : null;

                return Result::new($value)->mergeError($error);
            }

            $data = (object) ['rows' => (int) $result];
            if ($operation === self::CREATE) {
                $data->insertId = (int) $wpdb->insert_id;
            }

            return Result::new($data);
        } catch (\Throwable $throwable) {
            return Result::new(Error::withMergedThrowable($error, $throwable));
        } finally {
            $phpErrors->restoreHandler();
            $wpdb->suppress_errors($suppressErrors);
        }
    }
}
