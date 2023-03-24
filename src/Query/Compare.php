<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Query;

use Inpsyde\Dbal\ErrorCollector;
use Inpsyde\Dbal\Schema\SchemaFinder;
use Inpsyde\Dbal\Schema\Schema;

final class Compare
{
    public const CAST_DATE = 'DATE';
    public const CAST_DATETIME = 'DATETIME';
    public const CAST_TIME = 'TIME';
    public const CAST_CHAR = 'CHAR';
    public const CAST_SIGNED = 'SIGNED';
    public const CAST_UNSIGNED = 'UNSIGNED';
    public const CAST_BINARY = 'BINARY';

    private const CASTS = [
        self::CAST_DATE => '%s',
        self::CAST_DATETIME => '%s',
        self::CAST_TIME => '%s',
        self::CAST_CHAR => '%s',
        self::CAST_SIGNED => '%d',
        self::CAST_UNSIGNED => '%d',
        self::CAST_BINARY => '%s',
    ];
    private const LEFT = 'LX';
    private const RIGHT = 'RX';

    /**
     * @var string|null
     */
    private $leftCol;

    /**
     * @var string|null
     */
    private $rightCol;

    /**
     * @var string
     */
    private $operator;

    /**
     * @var array{string|null, string|null}
     */
    private $casts = [null, null];

    /**
     * @var array{mixed, bool}
     */
    private $value = [null, false];

    /**
     * @var ErrorCollector
     */
    private $errors;

    /**
     * @param string $leftColumn
     * @param string $rightColumn
     * @param string|null $operator
     * @return Compare
     */
    public static function columns(
        string $leftColumn,
        string $rightColumn,
        ?string $operator = null
    ): Compare {

        return new static($leftColumn, $rightColumn, $operator);
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param string|null $operator
     * @return Compare
     */
    public static function columnValue(string $column, $value, ?string $operator = null): Compare
    {
        $instance = new static($column, null, $operator);

        return $instance->checkValue($value, false);
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param string|null $operator
     * @return Compare
     */
    public static function columnRawValue(string $column, $value, ?string $operator = null): Compare
    {
        $instance = new static($column, null, $operator);

        return $instance->checkValue($value, true);
    }

    /**
     * @param string|null $leftCol
     * @param string|null $rightCol
     * @param string $operator
     * @param string|null $tableLeft
     * @param string|null $tableRight
     */
    private function __construct(?string $leftCol, ?string $rightCol, ?string $operator)
    {
        $this->leftCol = $leftCol;
        $this->rightCol = $rightCol;
        $this->operator = $operator ?? '=';
        $this->errors = new ErrorCollector();
    }

    /**
     * @param string $type
     * @return Compare
     */
    public function castLeft(string $type): Compare
    {
        $this->casts[0] = $this->checkCastType(self::LEFT, $type);

        return $this;
    }

    /**
     * @param string $type
     * @return Compare
     */
    public function castRight(string $type): Compare
    {
        $this->casts[1] = $this->checkCastType(self::RIGHT, $type);

        return $this;
    }

    /**
     * @return bool
     */
    public function hasValue(): bool
    {
        return $this->value[0] !== null;
    }

    /**
     * @return bool
     */
    public function valueIsRaw(): bool
    {
        return $this->hasValue() && !empty($this->value[1]);
    }

    /**
     * @param Schema $defaultSchema
     * @param SchemaFinder $finder
     * @param Aliases $aliases
     * @return array{string|null, mixed, string|null, string|null}
     */
    public function clauseParams(
        Schema $defaultSchema,
        SchemaFinder $finder,
        Aliases $aliases
    ): array {

        if (!$this->errors->isEmpty()) {
            return [null, null, null, null, null];
        }

        [$castLeft, $castRight] = $this->casts;

        $hasValue = $this->hasValue();
        $valueIsRaw = $this->valueIsRaw();

        $leftCol = $this->colName(self::LEFT, $aliases, $defaultSchema, $finder);

        $rightColOrValue = $hasValue
            ? $this->value[0]
            : $this->colName(self::RIGHT, $aliases, $defaultSchema, $finder);

        if ($leftCol === null || $rightColOrValue === null) {
            $this->errors->withError('Could not resolve compare column names.');

            return [null, null, null, null, null];
        }

        if ($castLeft) {
            $leftCol = "CAST({$leftCol} AS {$castLeft})";
        }

        if ($castRight) {
            $rightColOrValue = "CAST({$rightColOrValue} AS {$castRight})";
        }

        $valueFormat = null;
        if ($hasValue && !$valueIsRaw) {
            [$rightColOrValue, $valueFormat] = $this->colValue(
                $rightColOrValue,
                $defaultSchema,
                $finder,
                $aliases
            );
        }

        return [$leftCol, $rightColOrValue, $valueFormat, $this->operator];
    }

    /**
     * @param ErrorCollector $collector
     * @return void
     */
    public function mergeError(ErrorCollector $collector)
    {
        $collector->pushFrom($this->errors);
    }

    /**
     * @param string $which
     * @param Aliases $aliases
     * @param Schema $defaultSchema
     * @param SchemaFinder $finder
     * @return string|null
     */
    private function colName(
        string $which,
        Aliases $aliases,
        Schema $defaultSchema,
        SchemaFinder $finder
    ): ?string {

        /**
         * @var string $colName
         */
        $colName = $which === self::LEFT ? $this->leftCol : $this->rightCol;
        [$column, $table] = Where::maybeSplitTableName($colName, $this->errors);
        if (!$this->errors->isEmpty()) {
            return null;
        }

        [$columnName, , , $colTableName] = $aliases->resolveColumn($column);
        $aliases->mergeErrors($this->errors);
        if (!$this->errors->isEmpty()) {
            return null;
        }

        [, $schema, $tableAlias] = $aliases->resolveSchema(
            $table ?? $colTableName ?? $defaultSchema->name()
        );

        if (
            $table
            && ($colTableName || $tableAlias)
            && !in_array($table, [$colTableName, $tableAlias], true)
        ) {
            $this->errors->withError(
                "Table '{$table}' is unknown alias."
            );

            return null;
        }

        $aliases->mergeErrors($this->errors);
        if (!$schema) {
            return null;
        }

        $colTableAlias = $tableAlias ?? $finder->fullTableName($schema);

        return "`{$colTableAlias}`.`{$columnName}`";
    }

    /**
     * @param mixed $rawValue
     * @param Schema $defaultSchema
     * @param SchemaFinder $finder
     * @param Aliases $aliases
     * @return array{mixed, string|null}
     */
    private function colValue(
        $rawValue,
        Schema $defaultSchema,
        SchemaFinder $finder,
        Aliases $aliases
    ): array {

        [$colName, $schemaName] = Where::maybeSplitTableName($this->leftCol ?? '', $this->errors);
        if (!$this->errors->isEmpty()) {
            return [null, null];
        }

        $realSchemaName = null;
        if ($schemaName) {
            [$realSchemaName] = $aliases->resolveSchema($schemaName);
            $aliases->mergeErrors($this->errors);
            if (!$this->errors->isEmpty()) {
                return [null, null];
            }
        }

        $realSchemaName or $realSchemaName = $defaultSchema->name();
        $schema = $realSchemaName ? $finder->findSchema($realSchemaName) : null;
        if (!$schema) {
            $this->errors->withError("Table '{$schemaName}' not found.");

            return [null, null];
        }

        [$value, $format] = $schema->columns()->selectColumnInfo($colName, $rawValue);
        if ($value === null || $format === null) {
            $this->errors->withError(
                "Could not find column info for {$colName} in {$realSchemaName} table."
            );

            return [null, null];
        }

        if ($this->casts[0]) {
            $format = self::CASTS[$this->casts[0]];
        }

        return [$value, $format];
    }

    /**
     * @param string $which
     * @param string $type
     * @return string
     */
    private function checkCastType(string $which, string $type): string
    {
        if ($which === self::RIGHT && $this->hasValue()) {
            $this->errors->withError("It is only possible to cast column, not values.");

            return '';
        }

        $type = strtoupper($type);
        if (empty(self::CASTS[$type])) {
            $this->errors->withError("Invalid cast '{$type}'.");

            return '';
        }

        return $type;
    }

    /**
     * @param mixed $value
     * @param bool $raw
     * @return Compare
     */
    private function checkValue($value, bool $raw): Compare
    {
        if ($value === null) {
            $this->errors->withError("Can't use null values as compare argument.");

            return $this;
        }

        $this->value = [$value, $raw];

        return $this;
    }
}
