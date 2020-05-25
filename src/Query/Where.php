<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Query;

use Inpsyde\Dbal\Dbal;
use Inpsyde\Dbal\ErrorCollector;
use Inpsyde\Dbal\Schema\Column;
use Inpsyde\Dbal\Schema\ColumnValueEncoder;
use Inpsyde\Dbal\Schema\Columns;
use Inpsyde\Dbal\Schema\SchemaFinder;
use Inpsyde\Dbal\Schema\Schema;

class Where
{
    public const AND = 'AND';
    public const OR = 'OR';

    public const EQ = '=';
    public const NOT_EQ = '!=';
    public const GREATER = '>';
    public const GREATER_EQ = '>=';
    public const LESS = '<';
    public const LESS_EQ = '<=';
    public const IS = 'IS';
    public const IS_NOT = 'IS NOT';
    public const IN = 'IN';
    public const NOT_IN = 'NOT IN';
    public const LIKE = 'LIKE';
    public const NOT_LIKE = 'NOT LIKE';

    private const OPERATORS = [
        self::EQ => self::EQ,
        self::NOT_EQ => self::NOT_EQ,
        '<>' => self::NOT_EQ,
        self::GREATER => self::GREATER,
        self::GREATER_EQ => self::GREATER_EQ,
        self::LESS => self::LESS,
        self::LESS_EQ => self::LESS_EQ,
        self::IN => self::IN,
        self::NOT_IN => self::NOT_IN,
        self::IS => self::IS,
        self::IS_NOT => self::IS_NOT,
        self::LIKE => self::LIKE,
        self::NOT_LIKE => self::NOT_LIKE,
    ];

    /**
     * @var array<array>
     */
    private $clauses = [];

    /**
     * @var ErrorCollector
     */
    private $errors;

    /**
     * @param string $column
     * @return array{0:string, 1:string|null}
     */
    public static function maybeSplitTableName(string $column, ErrorCollector $collector): array
    {
        $origCol = $column;
        $colParts = explode('.', $column);
        $colPartsCount = count($colParts);
        $table = null;

        if ($colPartsCount === 2) {
            $column = $colParts[1];
            $table = $colParts[0] ?: null;
        }

        if (!$column || ($colPartsCount > 2)) {
            $collector->withError("Invalid column name '{$origCol}'.");

            return ['', null];
        }

        return [$column, $table];
    }

    /**
     * @return Where
     */
    public static function new(): Where
    {
        return new static();
    }

    private function __construct()
    {
        $this->errors = new ErrorCollector();
    }

    /**
     * @param string $column
     * @param $value
     * @param string $operator
     * @return Where
     *
     * @psalm-suppress MissingParamType
     * phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration
     */
    public function with(string $column, $value, ?string $operator = null): Where
    {
        // phpcs:enable Inpsyde.CodeQuality.ArgumentTypeDeclaration

        if (!$this->errors->isEmpty()) {
            return $this;
        }

        $this->checkOperator($operator, $value);
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        $this->clauses = [[false, self::AND, $column, $value, $operator]];

        return $this;
    }

    /**
     * @param string $clause
     * @param string|null $column
     * @param string|null $operator
     * @return Where
     */
    public function withRaw(string $clause, ?string $column = null, ?string $operator = null): Where
    {
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        [$operator, $column] = $this->checkRawParams($operator, $column);
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        $this->clauses = [
            [
                true,
                self::AND,
                $column,
                $clause,
                $operator,
            ],
        ];

        return $this;
    }

    /**
     * @param string $column
     * @param $value
     * @param string|null $operator
     * @return Where
     *
     * @psalm-suppress MissingParamType
     * phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration
     */
    public function and(string $column, $value, ?string $operator = null): Where
    {
        // phpcs:enable Inpsyde.CodeQuality.ArgumentTypeDeclaration
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        $this->checkOperator($operator, $value);
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        $this->clauses[] = [false, self::AND, $column, $value, $operator];

        return $this;
    }

    /**
     * @param string $clause
     * @param string|null $column
     * @param string|null $operator
     * @return Where
     */
    public function andRaw(string $clause, ?string $column = null, ?string $operator = null): Where
    {
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        [$operator, $column] = $this->checkRawParams($operator, $column);
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        if ($clause) {
            $this->clauses[] = [
                true,
                self::AND,
                $column,
                $clause,
                $operator,
            ];

            return $this;
        }

        return $this;
    }

    /**
     * @param string $column
     * @param $value
     * @param string|null $operator
     * @return Where
     *
     * @psalm-suppress MissingParamType
     * phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration
     */
    public function or(string $column, $value, ?string $operator = null): Where
    {
        // phpcs:enable Inpsyde.CodeQuality.ArgumentTypeDeclaration
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        $this->checkOperator($operator, $value);
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        $this->clauses[] = [false, self::OR, $column, $value, $operator];

        return $this;
    }

    /**
     * @param string $clause
     * @param string|null $column
     * @param string|null $operator
     * @return Where
     */
    public function orRaw(string $clause, ?string $column = null, ?string $operator = null): Where
    {
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        [$operator, $column] = $this->checkRawParams($operator, $column);
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        if ($clause) {
            $this->clauses[] = [
                true,
                self::OR,
                $column,
                $clause,
                $operator,
            ];
        }

        return $this;
    }

    /**
     * @param Compare $compare
     * @param Compare ...$compares
     * @return Where
     */
    public function withCompare(Compare $compare, Compare ...$compares): Where
    {
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        $this->clauses = [];

        return $this->andCompare($compare, ...$compares);
    }

    /**
     * @param Compare $compare
     * @param Compare ...$compares
     * @return Where
     */
    public function andCompare(Compare $compare, Compare ...$compares): Where
    {
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        array_unshift($compares, $compare);

        foreach ($compares as $compare) {
            $this->clauses[] = [false, self::AND, null, $compare, null, null];
        }

        return $this;
    }

    /**
     * @param Compare $compare
     * @param Compare ...$compares
     * @return Where
     */
    public function orCompare(Compare $compare, Compare ...$compares): Where
    {
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        array_unshift($compares, $compare);

        foreach ($compares as $compare) {
            $this->clauses[] = [false, self::OR, null, $compare, null, null];
        }

        return $this;
    }

    /**
     * @param Where $where
     * @param Where ...$wheres
     * @return Where
     */
    public function andWhere(Where $where, Where ...$wheres): Where
    {
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        array_unshift($wheres, $where);

        foreach ($wheres as $where) {
            if (!$where->hasClauses()) {
                continue;
            }

            $this->clauses[] = [false, self::AND, null, $where, null, null];
        }

        return $this;
    }

    /**
     * @param Where $where
     * @param Where ...$wheres
     * @return Where
     */
    public function orWhere(Where $where, Where ...$wheres): Where
    {
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        array_unshift($wheres, $where);

        foreach ($wheres as $where) {
            if (!$where->hasClauses()) {
                continue;
            }

            $this->clauses[] = [false, self::OR, null, $where, null, null];
        }

        return $this;
    }

    /**
     * @return bool
     */
    public function hasClauses(): bool
    {
        return (bool)$this->clauses;
    }

    /**
     * @param Schema $defaultSchema
     * @param SchemaFinder $finder
     * @param Aliases $aliases
     * @return string
     */
    public function clause(Schema $defaultSchema, SchemaFinder $finder, Aliases $aliases): string
    {
        if (!$this->errors->isEmpty()) {
            return '';
        }

        return $this->buildClause('', [], $defaultSchema, $finder, $aliases);
    }

    /**
     * @param ErrorCollector $collector
     * @return void
     */
    public function pushErrorTo(ErrorCollector $collector): void
    {
        $collector->pushFrom($this->errors);
    }

    /**
     * @param string $clause
     * @param array<string, Columns> $columns
     * @param Schema $defaultSchema
     * @param SchemaFinder $finder
     * @param Aliases $aliases
     * @return string
     */
    private function buildClause(
        string $clause,
        array $columns,
        Schema $defaultSchema,
        SchemaFinder $finder,
        Aliases $aliases
    ): string {

        $args = [$defaultSchema, $finder, $aliases];

        /**
         * @var bool $raw
         * @var string $type
         * @var string|null $col
         * @var mixed|null $value
         * @var string|null $operator
         */
        foreach ($this->clauses as [$raw, $type, $col, $value, $operator]) {
            if ($raw && ($col === null)) {
                /** @var string $value */
                $clause = $this->buildRawClause($clause, $type, $value, $operator, null);
                continue;
            }

            if ($value instanceof Compare) {
                /** @psalm-suppress PossiblyInvalidArgument */
                $clause = $this->buildCompareClause($clause, $type, $value, ...$args);
                continue;
            }

            if ($value instanceof Where) {
                /** @psalm-suppress PossiblyInvalidArgument */
                $clause = $this->buildInnerClause($clause, $type, $value, $columns, ...$args);
                continue;
            }

            /**
             * @var string $col
             * @psalm-suppress PossiblyInvalidArgument
             * @psalm-suppress InvalidArgument
             */
            $info = $this->columnAndSchemaInfo($col, ...$args);

            if (!$info) {
                $this->errors->withError("Could not find column info for {$col}.");

                return '';
            }

            /**
             * @var string $column
             * @var Schema $schema
             * @var string $colName
             */
            [$column, $schema, $colName] = $info;

            if ($raw) {
                /** @var string $value */
                $clause = $this->buildRawClause($clause, $type, $value, $operator, $column);
                continue;
            }

            if ($value === null) {
                $clause = $this->buildNullClause($clause, $type, $column, $operator);
                continue;
            }

            $schemaName = $schema->name();
            if (empty($columns[$schemaName])) {
                $columns[$schemaName] = $schema->columns();
            }

            /** @var Columns $schemaColumns */
            $schemaColumns = $columns[$schemaName];
            $valCol = $schemaColumns->findColumn($colName);

            [$value, $format] = $valCol
                ? $schemaColumns->selectColumnInfo($colName, $value)
                : [null, null];

            if ($value === null) {
                $this->errors->withError("Could not parse column '{$colName}' in '{$schemaName}'.");

                return '';
            }

            $clause = $this->buildParsedClause($clause, $type, $column, $value, $format, $operator, $valCol);
        }

        return $clause;
    }

    /**
     * @param string $rawColumn
     * @param Schema $defaultSchema
     * @param SchemaFinder $finder
     * @param Aliases $aliases
     * @return array{0:string, 1:Schema, 2:string}|null
     */
    private function columnAndSchemaInfo(
        string $rawColumn,
        Schema $defaultSchema,
        SchemaFinder $finder,
        Aliases $aliases
    ): ?array {

        [$column, $table] = Where::maybeSplitTableName($rawColumn, $this->errors);
        if (!$this->errors->isEmpty()) {
            return null;
        }

        [$realColumn, , , $colTable] = $aliases->resolveColumn($column);
        $aliases->mergeErrors($this->errors);
        if (!$this->errors->isEmpty() || !$realColumn) {
            return null;
        }

        $tableName = $table ?? $defaultSchema->name();
        [$tableRealName, $schema, $tableAlias] = $aliases->resolveSchema($tableName);
        $aliases->mergeErrors($this->errors);
        if (!$this->errors->isEmpty() || !$schema) {
            return null;
        }

        if ($colTable && ($colTable !== $tableRealName)) {
            $this->errors->withError(
                "Table '{$colTable}' used in column alias for '{$realColumn}' does not match "
                . "{$tableRealName} used for same column in WHERE clause."
            );

            return null;
        }

        $tableNameOrAlias = $tableAlias ?? $finder->fullTableName($schema);

        return ["`{$tableNameOrAlias}`.`{$realColumn}`", $schema, $realColumn];
    }

    /**
     * @param string $clause
     * @param string $type
     * @param string $value
     * @param string|null $operator
     * @param string|null $columnName
     * @return string
     */
    private function buildRawClause(
        string $clause,
        string $type,
        string $value,
        ?string $operator = null,
        ?string $columnName = null
    ): string {

        $clause and $clause .= " {$type} ";
        $columnName and $clause .= "{$columnName} ";
        $operator and $clause .= "{$operator} ";
        $clause .= $value;

        return $clause;
    }

    /**
     * @param string $clause
     * @param string $type
     * @param string $columnName
     * @param string|null $operator
     * @return string
     */
    private function buildNullClause(
        string $clause,
        string $type,
        string $columnName,
        ?string $operator
    ): string {

        $is = $operator === self::IS_NOT ? 'IS NOT NULL' : 'IS NULL';
        $clause and $clause .= " {$type} ";
        $clause .= "{$columnName} {$is}";

        return $clause;
    }

    /**
     * @param string $clause
     * @param string $type
     * @param string $column
     * @param mixed $columnValue
     * @param string|null $format
     * @param string|null $operator
     * @param Column|null $valueColumnObject
     * @return string
     *
     * @psalm-suppress MissingParamType
     * phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration
     */
    private function buildParsedClause(
        string $clause,
        string $type,
        string $column,
        $columnValue,
        ?string $format,
        ?string $operator,
        ?Column $valueColumnObject = null
    ): string {

        // phpcs:enable Inpsyde.CodeQuality.ArgumentTypeDeclaration

        $wpdb = Dbal::wpdb();

        if (!$format) {
            $format = '%s';
        }

        $multiValue = $columnValue
            && is_array($columnValue)
            && in_array($operator, [self::IN, self::NOT_IN], true);

        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared

        if (!$multiValue) {
            $operator = $operator ?? self::EQ;
            $clause and $clause .= " {$type} ";
            $columnClause = "{$column} {$operator} {$format}";
            $columnValue = $this->encodeValue($columnValue, $valueColumnObject);

            return $clause . (string)$wpdb->prepare($columnClause, $columnValue);
        }

        if ($multiValue) {
            $parsedValue = [];
            foreach ($columnValue as $columnSingleValue) {
                $parsedValue[] = $this->encodeValue($columnSingleValue, $valueColumnObject);
            }

            $inFormat = rtrim(str_repeat("{$format},", count($parsedValue)), ',');
            $clause and $clause .= " {$type} ";
            $columnClause = "{$column} {$operator} ({$inFormat}) ";

            return $clause . (string)$wpdb->prepare($columnClause, ...$parsedValue);
        }

        // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

        if ($operator === null) {
            $operator = 'NULL';
        }

        $type = gettype($columnValue);

        $this->errors->withError(
            "Error building WHERE clause for {$column}: operator {$operator} is not compatible "
            . "with given column value of type {$type}."
        );

        return $clause;
    }

    /**
     * @param string $clause
     * @param string $type
     * @param Compare $compare
     * @param Schema $defaultSchema
     * @param SchemaFinder $finder
     * @param Aliases $aliases
     * @return string
     */
    private function buildCompareClause(
        string $clause,
        string $type,
        Compare $compare,
        Schema $defaultSchema,
        SchemaFinder $finder,
        Aliases $aliases
    ): string {

        [$leftCol, $rightColOrValue, $valueFormat, $operator] = $compare->clauseParams(
            $defaultSchema,
            $finder,
            $aliases
        );

        $compare->mergeError($this->errors);
        if (!$this->errors->isEmpty()) {
            return $clause;
        }

        if (!$leftCol || ($rightColOrValue === null) || !$operator) {
            return $clause;
        }

        if ($compare->valueIsRaw()) {
            $this->checkOperator($operator, '');
            if (!$this->errors->isEmpty()) {
                return $clause;
            }

            $clause and $clause .= " {$type} ";
            $clause .= "{$leftCol} {$operator} {$rightColOrValue}";

            return $clause;
        }

        if ($compare->hasValue()) {
            return $this->buildParsedClause(
                $clause,
                $type,
                $leftCol,
                $rightColOrValue,
                $valueFormat,
                $operator
            );
        }

        $this->checkOperator($operator, '');
        if (!$this->errors->isEmpty()) {
            return $clause;
        }

        $clause and $clause .= " {$type} ";
        $clause .= "{$leftCol} {$operator} {$rightColOrValue}";

        return $clause;
    }

    /**
     * @param string $clause
     * @param string $type
     * @param Where $value
     * @param array<string, Columns> $columns
     * @param Schema $defaultSchema
     * @param SchemaFinder $finder
     * @param Aliases $aliases
     * @return string
     */
    private function buildInnerClause(
        string $clause,
        string $type,
        Where $value,
        array $columns,
        Schema $defaultSchema,
        SchemaFinder $finder,
        Aliases $aliases
    ): string {

        $inner = $value->buildClause('', $columns, $defaultSchema, $finder, $aliases);
        $clause .= $clause ? " {$type} ({$inner})" : $inner;

        return $clause;
    }

    /**
     * @param string $operator
     * @param mixed|null $value
     * @return string
     *
     * @psalm-suppress MissingParamType
     * phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration
     */
    private function checkOperator(?string $operator, $value): string
    {
        // phpcs:enable Inpsyde.CodeQuality.ArgumentTypeDeclaration

        if ($operator === null) {
            $operator = self::EQ;
            if ($value === null || is_array($value)) {
                $operator = $value === null ? self::IS : self::IN;
            }

            return $operator;
        }

        $operator = strtoupper($operator);

        if ($value === null) {
            if ($operator !== self::IS && $operator !== self::IS_NOT) {
                $this->errors->withError('Only "IS" or "IS NOT" operator is allowed for null.');
            }

            return $operator;
        }

        if ($operator === self::IS || $operator === self::IS_NOT) {
            $this->errors->withError('"IS" and "IS NOT" operators are allowed only for null.');
        }

        if ($operator === self::IN || $operator === self::NOT_IN) {
            if (!is_array($value)) {
                $this->errors->withError('"IN" and "NOT IN" operators are allowed only for arrays.');
            }

            return $operator;
        }

        if (!in_array($operator, self::OPERATORS, true)) {
            $this->errors->withError("Invalid operator \"{$operator}\".");
        }

        return $operator;
    }

    /**
     * @param string|null $operator
     * @param string|null $column
     * @return array{0:string|null, 1:string|null}
     */
    private function checkRawParams(?string $operator, ?string $column): array
    {
        if ($operator !== null) {
            $operator = strtoupper($operator);
            if (empty(self::OPERATORS[$operator])) {
                $this->errors->withError("Invalid WHERE operator '{$operator}'.");

                return [null, null];
            }
        }

        return [$operator, $column];
    }

    /**
     * @param mixed $value
     * @param \Inpsyde\Dbal\Schema\Column|null $column
     * @return bool|float|int|string
     *
     * @psalm-suppress MissingParamType
     * @psalm-suppress MissingReturnType
     * phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration
     * phpcs:disable Inpsyde.CodeQuality.ReturnTypeDeclaration
     */
    private function encodeValue($value, ?Column $column)
    {
        // phpcs:enable Inpsyde.CodeQuality.ArgumentTypeDeclaration
        // phpcs:enable Inpsyde.CodeQuality.ReturnTypeDeclaration

        if ($column) {
            $value = ColumnValueEncoder::for($column)->encode($value);
        }

        if (is_array($value) || $value instanceof \stdClass) {
            $value = serialize((array)$value);
        } elseif ($value instanceof \DateTimeInterface) {
            $value = $value->format('Y-m-d H:i:s');
        }

        if (!is_scalar($value)) {
            $value = '';
        }

        return $value;
    }
}
