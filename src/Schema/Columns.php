<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Schema;

/**
 * @phpstan-type ColumnsData array<string, mixed>
 * @phpstan-type ColumnsFormats array<string, string>
 *
 * @template-implements \IteratorAggregate<int, Column>
 */
final class Columns implements \IteratorAggregate, \Countable
{
    /** @var array<string, Column> */
    private array $columns;

    /** @var array<string, ColumnValueEncoder> */
    private array $parsers = [];

    private ?Column $autoIncr = null;

    /**
     * @param Column $column
     * @param Column ...$columns
     * @return Columns
     */
    public static function new(Column $column, Column ...$columns): Columns
    {
        return new static($column, ...$columns);
    }

    /**
     * @param Column $column
     * @param Column ...$columns
     */
    private function __construct(Column $column, Column ...$columns)
    {
        array_unshift($columns, $column);

        $names = [];
        $this->columns = [];
        foreach ($columns as $column) {
            $name = $column->name();
            if (isset($names[$name])) {
                throw new \Exception(
                    sprintf(
                        'Column names must be unique, "%s" already in use.',
                        esc_html($name)
                    )
                );
            }

            if (!$column->isAutoIncrement()) {
                $names[$name] = 1;
                $this->columns[$name] = $column;
                continue;
            }

            if ($this->autoIncr) {
                throw new \Exception('There can be a single auto-increment column in each table.');
            }

            $names[$name] = 1;
            $this->columns[$name] = $column;
            $this->autoIncr = $column;
        }
    }

    /**
     * @return string
     */
    public function schemaSql(): string
    {
        $sql = '';
        foreach ($this->columns as $column) {
            $definition = $column->schemaDefinition();
            if ($sql !== '') {
                $sql .= ',';
            }
            $sql .= "\n\t{$definition}";
        }

        return $sql;
    }

    /**
     * @return string[]
     */
    public function allNames(): array
    {
        return array_keys($this->columns);
    }

    /**
     * @return Column|null
     */
    public function autoIncrement(): ?Column
    {
        return $this->autoIncr;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function hasColumn(string $name): bool
    {
        return array_key_exists($name, $this->columns);
    }

    /**
     * @param string $name
     * @return Column|null
     */
    public function findColumn(string $name): ?Column
    {
        return $this->columns[$name] ?? null;
    }

    /**
     * @param string $column
     * @param mixed $value
     * @return array{mixed, string|null}
     */
    public function selectColumnInfo(string $column, mixed $value): array
    {
        if (!$this->hasColumn($column)) {
            return [null, null];
        }

        [$parsed, $formats] = $this->columnInfoForData($column, $value);

        /** @var mixed $value */
        $value = $parsed[$column] ?? null;
        /** @var string $format */
        $format = $formats[$column] ?? '%s';

        return $value === null ? [null, null] : [$value, $format];
    }

    /**
     * @param ColumnsData $data
     * @return array{ColumnsData, ColumnsFormats, string[]|null}
     */
    public function columnsInfoForDataInsert(array $data): array
    {
        [$parsedData, $formats, $missing] = $this->columnsInfoForData($data, true, false);

        return [$parsedData, $formats, $missing];
    }

    /**
     * @param ColumnsData $data
     * @return array{ColumnsData, ColumnsFormats, string[]|null}
     */
    public function columnsInfoForDataUpdate(array $data): array
    {
        [$parsedData, $formats, $missing] = $this->columnsInfoForData($data, false, true);

        return [$parsedData, $formats, $missing];
    }

    /**
     * @param ColumnsData $data
     * @return array{ColumnsData, ColumnsFormats}
     */
    public function columnsInfoForDataRead(array $data): array
    {
        /**
         * @var ColumnsData $parsedData
         * @var ColumnsFormats $formats
         */
        [$parsedData, $formats] = $this->columnsInfoForData($data, false, false);

        return [$parsedData, $formats];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function parseQueryResultRow(array $row): array
    {
        $parsed = [];
        foreach ($this->columns as $name => $column) {
            if (!array_key_exists($name, $row)) {
                continue;
            }

            if (!isset($this->parsers[$name])) {
                $this->parsers[$name] = ColumnValueEncoder::for($column);
            }

            $parser = $this->parsers[$name];
            $parsed[$name] = $parser->decode($row[$name]);
        }

        return $parsed;
    }

    /**
     * @return \Traversable<int, Column>
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator(array_values($this->columns));
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return count($this->columns);
    }

    /**
     * @param ColumnsData $data
     * @param bool $forInsert
     * @param bool $forUpdate
     *
     * @return array{ColumnsData, ColumnsFormats, string[]|null}
     *
     * phpcs:disable SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh
     */
    private function columnsInfoForData(array $data, bool $forInsert, bool $forUpdate): array
    {
        $parsedData = [];
        $formats = [];
        $missing = $forInsert ? [] : null;
        $forEditing = $forInsert || $forUpdate;

        foreach ($this->columns as $column) {
            $name = $column->name();
            $required = $forInsert && $column->isRequiredOnInsert();

            if (!array_key_exists($name, $data)) {
                if ($required) {
                    $missing[] = $name;
                }
                continue;
            }

            $isValueNull = $data[$name] === null;
            if ($isValueNull && $forEditing && !$column->isNullable()) {
                $missing[] = $name;
                continue;
            }
            if ($isValueNull && $forEditing) {
                $parsedData[$name] = null;
                continue;
            }

            [$parsedData, $formats, $missing] = $this->columnInfoForData(
                $name,
                $data[$name],
                $required,
                $forEditing,
                $parsedData,
                $formats,
                $missing
            );
        }

        return $forEditing ? [$parsedData, $formats, $missing] : [$parsedData, $formats, null];
    }

    /**
     * @param string $name
     * @param mixed $value
     * @param bool $required
     * @param bool $forEdit
     * @param ColumnsData $parsedData
     * @param ColumnsFormats $formats
     * @param string[]|null $missing
     *
     * @return array{ColumnsData, ColumnsFormats, string[]|null}
     */
    private function columnInfoForData(
        string $name,
        mixed $value,
        bool $required = false,
        bool $forEdit = false,
        array $parsedData = [],
        array $formats = [],
        ?array $missing = null
    ): array {

        $column = $this->columns[$name] ?? null;
        if (!$column instanceof Column) {
            return [$parsedData, $formats, $missing];
        }

        switch (true) {
            case is_array($value):
                $error = !((!$forEdit && $this->isNumericArray($value)) || $column->isSerialized() || $column->isJson());
                break;
            case ($value instanceof \DateTimeInterface):
                $error = !$column->isDateOrTimeInfo();
                break;
            case ($value instanceof \stdClass):
                $error = !($column->isSerialized() || $column->isJson());
                break;
            default:
                $error = !is_scalar($value);
                break;
        }

        if (!$error) {
            $formats[$name] = $column->format();
            $parsedData[$name] = $value;
        } elseif ($required) {
            if ($missing === null) {
                $missing = [];
            }
            $missing[] = $name;
        }

        return [$parsedData, $formats, $missing];
    }

    /**
     * @param array<mixed> $data
     * @return bool
     */
    private function isNumericArray(array $data): bool
    {
        // 1049408: UNESCAPED_SLASHES|UNESCAPED_UNICODE|INVALID_UTF8_IGNORE|PARTIAL_OUTPUT_ON_ERROR
        $encoded = (string)@json_encode($data, 1049408); // phpcs:ignore

        return ($encoded[0] ?? null) === '[';
    }
}
