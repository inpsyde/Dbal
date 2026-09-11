<?php

declare(strict_types=1);

namespace Syde\Dbal\Query;

use Syde\Dbal\Dbal;
use Syde\Dbal\ErrorCollector;
use Syde\Dbal\Schema\Schema;
use Syde\Dbal\Schema\SchemaFinder;

abstract class BaseSelect
{
    public const FROM = 'from';
    public const WHERE = 'where';
    public const ORDER = 'order';
    public const LIMIT = 'limit';
    public const ASC = 'ASC';
    public const DESC = 'DESC';

    protected ?Schema $schema;
    protected SchemaFinder $finder;
    protected Aliases $aliases;
    protected ErrorCollector $errors;
    protected ?Where $where = null;
    protected bool $unfiltered = false;

    /** @var array<string, Join> */
    protected array $joins = [];

    /** @var list<list{string, string|null, bool}> */
    protected array $order = [];

    /** @var list{bool|null, int|null, int}  */
    protected array $limit = [null, null, 0];

    /**
     * @param string $tableName
     * @param SchemaFinder|null $finder
     */
    protected function __construct(string $tableName, ?SchemaFinder $finder = null)
    {
        $this->finder = $finder ?: Dbal::schemaFinder();
        $this->schema = $this->finder->findSchema($tableName);
        $this->aliases = Aliases::new($this->finder);
        $this->errors = new ErrorCollector();
        if ($this->schema === null) {
            $tableName
                ? $this->errors->withError("Table '{$tableName}' not found.")
                : $this->errors->withError("SELECT table name can't be empty.");
        }
    }

    /**
     * @return array<string, string>|null
     */
    abstract protected function buildQueryParts(): ?array;

    /**
     * @return static
     */
    public function unfiltered(): BaseSelect
    {
        $this->unfiltered = true;

        return $this;
    }

    /**
     * @param string $joinTable
     * @param string|null $columnNameOnMain
     * @param string|null $columnNameOnJoined
     * @param string|null $alias
     * @return static
     */
    public function leftJoin(
        string $joinTable,
        ?string $columnNameOnMain = null,
        ?string $columnNameOnJoined = null,
        ?string $alias = null
    ): BaseSelect {

        return $this->withJoin(
            Join::LEFT,
            $joinTable,
            null,
            $columnNameOnMain,
            $columnNameOnJoined,
            $alias
        );
    }

    /**
     * @param string $joinTable
     * @param Where $where
     * @param string|null $alias
     * @return static
     */
    public function leftJoinWhere(
        string $joinTable,
        Where $where,
        ?string $alias = null
    ): BaseSelect {

        return $this->withJoin(
            Join::LEFT,
            $joinTable,
            null,
            null,
            null,
            $alias,
            $where
        );
    }

    /**
     * @param string $joinTable
     * @param string $sourceTable
     * @param string|null $columnNameOnSource
     * @param string|null $columnNameOnJoined
     * @param string|null $alias
     * @return static
     */
    public function leftJoinWith(
        string $joinTable,
        string $sourceTable,
        ?string $columnNameOnSource = null,
        ?string $columnNameOnJoined = null,
        ?string $alias = null
    ): BaseSelect {

        return $this->withJoin(
            Join::LEFT,
            $joinTable,
            $sourceTable,
            $columnNameOnSource,
            $columnNameOnJoined,
            $alias
        );
    }

    /**
     * @param string $joinTable
     * @param string $sourceTable
     * @param Where $where
     * @param string|null $alias
     * @return static
     */
    public function leftJoinWhereWith(
        string $joinTable,
        string $sourceTable,
        Where $where,
        ?string $alias = null
    ): BaseSelect {

        return $this->withJoin(
            Join::LEFT,
            $joinTable,
            $sourceTable,
            null,
            null,
            $alias,
            $where
        );
    }

    /**
     * @param string $expression
     * @param string $alias
     * @param string|null $columnOnMain
     * @param string|null $columnOnExpression
     * @return static
     */
    public function leftJoinRaw(
        string $expression,
        string $alias,
        ?string $columnOnMain = null,
        ?string $columnOnExpression = null
    ): BaseSelect {

        return $this->withJoin(
            Join::LEFT,
            null,
            null,
            $columnOnMain,
            $columnOnExpression,
            $alias,
            null,
            $expression
        );
    }

    /**
     * @param string $expression
     * @param string $alias
     * @param string $sourceTable
     * @param string|null $columnOnMain
     * @param string|null $columnOnExpression
     * @return static
     */
    public function leftJoinRawWith(
        string $expression,
        string $alias,
        string $sourceTable,
        ?string $columnOnMain = null,
        ?string $columnOnExpression = null
    ): BaseSelect {

        return $this->withJoin(
            Join::LEFT,
            null,
            $sourceTable,
            $columnOnMain,
            $columnOnExpression,
            $alias,
            null,
            $expression
        );
    }

    /**
     * @param string $joinTable
     * @param string|null $columnNameOnMain
     * @param string|null $columnNameOnJoined
     * @param string|null $alias
     * @return static
     */
    public function innerJoin(
        string $joinTable,
        ?string $columnNameOnMain = null,
        ?string $columnNameOnJoined = null,
        ?string $alias = null
    ): BaseSelect {

        return $this->withJoin(
            Join::INNER,
            $joinTable,
            null,
            $columnNameOnMain,
            $columnNameOnJoined,
            $alias
        );
    }

    /**
     * @param string $joinTable
     * @param Where $where
     * @param string|null $alias
     * @return static
     */
    public function innerJoinWhere(
        string $joinTable,
        Where $where,
        ?string $alias = null
    ): BaseSelect {

        return $this->withJoin(
            Join::INNER,
            $joinTable,
            null,
            null,
            null,
            $alias,
            $where
        );
    }

    /**
     * @param string $joinTable
     * @param string $sourceTable
     * @param string|null $columnNameOnSource
     * @param string|null $columnNameOnJoined
     * @param string|null $alias
     * @return static
     */
    public function innerJoinWith(
        string $joinTable,
        string $sourceTable,
        ?string $columnNameOnSource = null,
        ?string $columnNameOnJoined = null,
        ?string $alias = null
    ): BaseSelect {

        return $this->withJoin(
            Join::INNER,
            $joinTable,
            $sourceTable,
            $columnNameOnSource,
            $columnNameOnJoined,
            $alias
        );
    }

    /**
     * @param string $joinTable
     * @param string $sourceTable
     * @param Where $where
     * @param string|null $alias
     * @return static
     */
    public function innerJoinWhereWith(
        string $joinTable,
        string $sourceTable,
        Where $where,
        ?string $alias = null
    ): BaseSelect {

        return $this->withJoin(
            Join::INNER,
            $joinTable,
            $sourceTable,
            null,
            null,
            $alias,
            $where
        );
    }

    /**
     * @param string $expression
     * @param string $alias
     * @param string|null $columnOnMain
     * @param string|null $columnOnExpression
     * @return static
     */
    public function innerJoinRaw(
        string $expression,
        string $alias,
        ?string $columnOnMain = null,
        ?string $columnOnExpression = null
    ): BaseSelect {

        return $this->withJoin(
            Join::INNER,
            null,
            null,
            $columnOnMain,
            $columnOnExpression,
            $alias,
            null,
            $expression
        );
    }

    /**
     * @param string $expression
     * @param string $alias
     * @param string $sourceTable
     * @param string|null $columnOnMain
     * @param string|null $columnOnExpression
     * @return static
     */
    public function innerJoinRawWith(
        string $expression,
        string $alias,
        string $sourceTable,
        ?string $columnOnMain = null,
        ?string $columnOnExpression = null
    ): BaseSelect {

        return $this->withJoin(
            Join::INNER,
            null,
            $sourceTable,
            $columnOnMain,
            $columnOnExpression,
            $alias,
            null,
            $expression
        );
    }

    /**
     * @param string $joinTable
     * @param string $pivotTable
     * @param string $pivotColumnForMain
     * @param string $pivotColumnForJoined
     * @param string|null $columnOnMain
     * @param string|null $columnOnJoined
     * @return static
     */
    public function joinViaPivot(
        string $joinTable,
        string $pivotTable,
        string $pivotColumnForMain,
        string $pivotColumnForJoined,
        ?string $columnOnMain = null,
        ?string $columnOnJoined = null
    ): BaseSelect {

        if (!$this->errors->isEmpty()) {
            return $this;
        }

        return $this
            ->innerJoin($pivotTable, $columnOnMain, $pivotColumnForMain)
            ->innerJoinWith($joinTable, $pivotTable, $pivotColumnForJoined, $columnOnJoined);
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param string|null $operator
     * @return static
     */
    public function where(string $column, mixed $value, ?string $operator = null): BaseSelect
    {
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        $this->where = Where::new()->with($column, $value, $operator);
        $this->where->pushErrorTo($this->errors);

        return $this;
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param string|null $operator
     * @return static
     */
    public function andWhere(string $column, mixed $value, ?string $operator = null): BaseSelect
    {
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        $this->where ??= Where::new();
        $this->where->and($column, $value, $operator);
        $this->where->pushErrorTo($this->errors);

        return $this;
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param string|null $operator
     * @return static
     */
    public function orWhere(string $column, mixed $value, ?string $operator = null): BaseSelect
    {
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        if (!$this->where) {
            $this->where = Where::new();
        }
        $this->where->or($column, $value, $operator);
        $this->where->pushErrorTo($this->errors);

        return $this;
    }

    /**
     * @param string $clause
     * @param string|null $column
     * @param string|null $operator
     * @return static
     */
    public function whereRaw(
        string $clause,
        ?string $column = null,
        ?string $operator = null
    ): BaseSelect {

        if (!$this->errors->isEmpty()) {
            return $this;
        }

        $this->where = Where::new()->withRaw($clause, $column, $operator);
        $this->where->pushErrorTo($this->errors);

        return $this;
    }

    /**
     * @param string $clause
     * @param string|null $column
     * @param string|null $operator
     * @return static
     */
    public function andWhereRaw(
        string $clause,
        ?string $column = null,
        ?string $operator = null
    ): BaseSelect {

        if (!$this->errors->isEmpty()) {
            return $this;
        }

        if (!$this->where) {
            $this->where = Where::new();
        }

        $this->where = $this->where->andRaw($clause, $column, $operator);
        $this->where->pushErrorTo($this->errors);

        return $this;
    }

    /**
     * @param string $clause
     * @param string|null $column
     * @param string|null $operator
     * @return static
     */
    public function orWhereRaw(
        string $clause,
        ?string $column = null,
        ?string $operator = null
    ): BaseSelect {

        if (!$this->errors->isEmpty()) {
            return $this;
        }

        if (!$this->where) {
            $this->where = Where::new();
        }
        $this->where = $this->where->orRaw($clause, $column, $operator);
        $this->where->pushErrorTo($this->errors);

        return $this;
    }

    /**
     * @param Where $where
     * @param Where ...$wheres
     * @return static
     */
    public function whereUsing(Where $where, Where ...$wheres): BaseSelect
    {
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        $this->where = Where::new()->andWhere($where, ...$wheres);

        return $this;
    }

    /**
     * @param Where $where
     * @param Where ...$wheres
     * @return static
     */
    public function andWhereUsing(Where $where, Where ...$wheres): BaseSelect
    {
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        if (!$this->where) {
            $this->where = Where::new();
        }
        $this->where->andWhere($where, ...$wheres);

        return $this;
    }

    /**
     * @param Where $where
     * @param Where ...$wheres
     * @return static
     */
    public function orWhereUsing(Where $where, Where ...$wheres): BaseSelect
    {
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        if (!$this->where) {
            $this->where = Where::new();
        }
        $this->where->orWhere($where, ...$wheres);

        return $this;
    }

    /**
     * @param Compare $compare
     * @param Compare ...$compares
     * @return static
     */
    public function whereCompare(Compare $compare, Compare ...$compares): BaseSelect
    {
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        $this->where = Where::new()->andCompare($compare, ...$compares);

        return $this;
    }

    /**
     * @param Compare $compare
     * @param Compare ...$compares
     * @return static
     */
    public function andWhereCompare(Compare $compare, Compare ...$compares): BaseSelect
    {
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        if (!$this->where) {
            $this->where = Where::new();
        }
        $this->where->andCompare($compare, ...$compares);

        return $this;
    }

    /**
     * @param Compare $compare
     * @param Compare ...$compares
     * @return static
     */
    public function orWhereCompare(Compare $compare, Compare ...$compares): BaseSelect
    {
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        if (!$this->where) {
            $this->where = Where::new();
        }
        $this->where->orCompare($compare, ...$compares);

        return $this;
    }

    /**
     * @param string $column
     * @param string $dir
     * @return static
     */
    public function orderBy(string $column, string $dir = self::ASC): BaseSelect
    {
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        $this->order = [];

        return $this->withOrder($column, false, $dir);
    }

    /**
     * @param string $clause
     * @param string|null $dir
     * @return static
     */
    public function orderByRaw(string $clause, ?string $dir = self::ASC): BaseSelect
    {
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        $this->order = [];

        return $this->withOrder($clause, true, $dir);
    }

    /**
     * @return static
     */
    public function orderByRand(): BaseSelect
    {
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        $this->order = [];

        return $this->withOrder('RAND()', true, null);
    }

    /**
     * @param string $clause
     * @param string $dir
     * @return static
     */
    public function thenOrderBy(string $clause, string $dir = self::ASC): BaseSelect
    {
        return $this->withOrder($clause, false, $dir);
    }

    /**
     * @param string $clause
     * @param string|null $dir
     * @return static
     */
    public function thenOrderByRaw(string $clause, ?string $dir = self::ASC): BaseSelect
    {
        return $this->withOrder($clause, true, $dir);
    }

    /**
     * @param int $limit
     * @param int $offset
     * @return static
     */
    public function limit(int $limit, int $offset = 0): BaseSelect
    {
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        if ($limit < 1) {
            return $this->pushError('Limit must be greater than 1.');
        }

        if ($offset < 0) {
            return $this->pushError('Offset bust be greater than 0.');
        }

        $this->limit = [true, $limit, $offset];

        return $this;
    }

    /**
     * @return bool
     */
    public function hasErrors(): bool
    {
        return !$this->errors->isEmpty();
    }

    /**
     * @return string
     */
    public function error(): string
    {
        $error = $this->errors->error();
        if (!$error) {
            return '';
        }

        return $error->serialize();
    }

    /**
     * @return void
     */
    public function assertValid(): void
    {
        $error = $this->errors->error();
        if ($error) {
            throw $error;
        }
    }

    /**
     * @return string
     */
    public function buildSql(): string
    {
        if (!$this->errors->isEmpty()) {
            return '';
        }

        $parts = $this->buildQueryParts() ?? [];
        if ($parts === []) {
            return '';
        }

        return implode(' ', array_filter($parts));
    }

    /**
     * @return string
     */
    public function buildSqlNoEscape(): string
    {
        $sql = $this->buildSql();
        if (!$sql) {
            return '';
        }

        $wpdb = Dbal::wpdb();

        return $wpdb->remove_placeholder_escape($sql);
    }

    /**
     * @return array<string, string>|null
     */
    public function buildQueryPartsNoEscape(): ?array
    {
        $parts = $this->buildQueryParts();
        if (($parts === null) || ($parts === [])) {
            return $parts;
        }

        $wpdb = Dbal::wpdb();

        $clean = [];
        foreach ($parts as $key => $part) {
            $clean[$key] = $wpdb->remove_placeholder_escape($part);
        }

        return $clean;
    }

    /**
     * @return array<string, string>|null
     */
    protected function buildBaseQueryParts(): ?array
    {
        if (!$this->errors->isEmpty()) {
            return null;
        }

        /** @var Schema $schema */
        $schema = $this->schema;
        $finder = $this->finder;

        $whereClause = '';

        if ($this->where) {
            $whereClause = $this->where->clause($schema, $finder, $this->aliases);
            $this->where->pushErrorTo($this->errors);
            if (!$this->errors->isEmpty()) {
                return null;
            }
        }

        return [
            self::FROM => $this->buildFromSql(),
            self::WHERE => $whereClause ? "WHERE {$whereClause}" : '',
            self::ORDER => $this->buildOrderSql(),
            self::LIMIT => $this->buildLimitSql(),
        ];
    }

    /**
     * @param array<string, string> $parts
     * @param string $filterName
     * @return array<string, string>|null
     */
    protected function filterQueryParts(array $parts, string $filterName): ?array
    {
        if (!$this->errors->isEmpty()) {
            return null;
        }

        if ($this->unfiltered || (has_filter($filterName) === false)) {
            return $parts;
        }

        foreach ($parts as $name => $value) {
            $filtered = apply_filters($filterName, $value, $name, $parts);
            if (($filtered !== $value) && ($filtered === null || is_string($filtered))) {
                $parts[$name] = $filtered ?? '';
            }
        }

        return $parts;
    }

    /**
     * @param string $joinTableName
     * @return bool
     */
    protected function hasJoinFor(string $joinTableName): bool
    {
        [$name, , , $tableAliases] = $this->aliases->resolveSchema($joinTableName);
        $this->aliases->mergeErrors($this->errors);
        if (($name === '') || ($name === null) || !$this->errors->isEmpty()) {
            return false;
        }

        if (!empty($this->joins[$name])) {
            return true;
        }

        foreach ($tableAliases as $tableAlias) {
            if (!empty($this->joins[$tableAlias])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $type
     * @param string|null $targetTable
     * @param string|null $sourceTable
     * @param string|null $columnOnSource
     * @param string|null $columnOnJoined
     * @param string|null $alias
     * @param Where|null $where
     * @param string|null $raw
     * @return static
     *
     * phpcs:disable SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh
     */
    protected function withJoin(
        string $type,
        ?string $targetTable,
        ?string $sourceTable,
        ?string $columnOnSource = null,
        ?string $columnOnJoined = null,
        ?string $alias = null,
        ?Where $where = null,
        ?string $raw = null
    ): BaseSelect {

        $schemas = $this->determineJoinSchema($sourceTable, $targetTable, $alias, $raw);
        if ($schemas === null) {
            return $this;
        }

        [$source, $target] = $schemas;
        $join = null;

        if ($raw !== null) {
            /** @var string $alias */
            $join = $type === Join::LEFT
                ? Join::leftRaw($source, $raw, $alias, $columnOnSource, $columnOnJoined)
                : Join::innerRaw($source, $raw, $alias, $columnOnSource, $columnOnJoined);
        } elseif ($where) {
            /** @var Schema $target */
            $join = $type === Join::LEFT
                ? Join::leftWhere($source, $target, $where, $alias)
                : Join::innerWhere($source, $target, $where, $alias);
        }

        if (!$join) {
            /** @var Schema $target */
            $join = $type === Join::LEFT
                ? Join::left($source, $target, $columnOnSource, $columnOnJoined, $alias)
                : Join::inner($source, $target, $columnOnSource, $columnOnJoined, $alias);
        }

        $join->mergeErrors($this->errors);
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        if (($alias !== null) && ($alias !== '')) {
            ($raw !== null)
                ? $this->aliases->forRawSchema($alias)
                : $this->aliases->forSchema($target->name(), $alias);
            $this->aliases->mergeErrors($this->errors);
        }

        if ($this->errors->isEmpty()) {
            /** @var Schema $target */
            $joinName = $alias ?? $target->name();
            $this->joins[$joinName] = $join;
        }

        return $this;
    }

    /**
     * @param string $column
     * @param bool $raw
     * @param string|null $dir
     * @return static
     */
    protected function withOrder(string $column, bool $raw, ?string $dir): BaseSelect
    {
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        if (!$column) {
            return $this->pushError("Ordering column name can't be empty.");
        }

        $dir = ($dir === null) ? null : strtoupper($dir);
        if (!in_array($dir, [null, self::ASC, self::DESC], true)) {
            return $this->pushError("Invalid ORDER direction '{$dir}'.");
        }

        $firstClause = $this->order[0][0] ?? '';
        if ($firstClause && (stripos($firstClause, 'RAND()') !== false)) {
            return $this->pushError("Can't order by '{$column}' when already ordering randomly.");
        }

        if ($raw) {
            $this->order[] = [$column, $dir, true];

            return $this;
        }

        $column = $this->fullyQualifiedColName($column);
        if ($column === null) {
            return $this;
        }

        $this->order[] = [$column, $dir, false];

        return $this;
    }

    /**
     * @param string|null $sourceTable
     * @param string|null $targetTable
     * @param string|null $alias
     * @param string|null $raw
     * @return array{Schema, Schema|null}|null
     *
     * phpcs:disable SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh
     */
    protected function determineJoinSchema(
        ?string $sourceTable,
        ?string $targetTable,
        ?string $alias,
        ?string $raw
    ): ?array {

        if (!$this->errors->isEmpty()) {
            return null;
        }

        $isRaw = $raw !== null;

        $target = ($isRaw || ($targetTable === null) || ($targetTable === ''))
            ? null
            : $this->finder->findSchema($targetTable);
        if (!$target && !$isRaw) {
            $this->pushError("Could not find table '{$targetTable}' to join.");

            return null;
        }

        if ($isRaw) {
            if ($raw === '') {
                $this->pushError("Raw JOIN expression can't be empty.");

                return null;
            }

            if (($alias === null) || ($alias === '')) {
                $this->pushError("Alias is required for raw JOINs.");

                return null;
            }
        }

        $sourceIsMain = $sourceTable === null;
        $source = $sourceIsMain ? $this->schema : null;

        if (!$sourceIsMain) {
            [, $source] = $this->aliases->resolveSchema($sourceTable ?? '');
            $this->aliases->mergeErrors($this->errors);
            if (!$this->errors->isEmpty() || !$source) {
                return null;
            }
        }

        if (!$source) {
            return null;
        }

        // If source table is not the main, we need to be sure source table is already joined
        if (!$sourceIsMain && !$this->hasJoinFor($source->name())) {
            $this->pushError(
                "Table '{$sourceTable}' is not in joined table list, "
                . "can't use as source for another join."
            );

            return null;
        }

        return [$source, $target];
    }

    /**
     * @return string
     */
    protected function buildFromSql(): string
    {
        $sql = 'FROM ';

        /** @var Schema $mainSchema */
        $mainSchema = $this->schema;

        [, , $mainAlias] = $this->aliases->resolveSchema($mainSchema->name());
        $this->aliases->mergeErrors($this->errors);
        if (!$this->errors->isEmpty()) {
            return '';
        }

        $fullMainName = $this->finder->fullTableName($mainSchema);
        $sql .= (($mainAlias !== null) && ($mainAlias !== ''))
            ? "`{$fullMainName}` AS `{$mainAlias}`"
            : "`{$fullMainName}`";

        foreach ($this->joins as $join) {
            $sql .= ' ' . $join->clause($this->finder, $this->aliases);
        }

        return $sql;
    }

    /**
     * @return string
     */
    protected function buildOrderSql(): string
    {
        $orderParts = [];
        foreach ($this->order as [$col, $dir, $raw]) {
            $orderParts[] = ($raw && ($dir === null)) ? $col : "{$col} {$dir}";
        }

        if ($orderParts) {
            return 'ORDER BY ' . implode(', ', $orderParts);
        }

        return '';
    }

    /**
     * @return string
     */
    protected function buildLimitSql(): string
    {
        [$hardLimit, $perPage, $pageOrOffset] = $this->limit;
        if (($hardLimit === null) || ($perPage === null)) {
            return '';
        }

        $offset = $hardLimit ? $pageOrOffset : (($pageOrOffset - 1) * $perPage);

        if ($offset === 0) {
            return sprintf('LIMIT %d', $perPage);
        }

        return sprintf('LIMIT %d, %d', $offset, $perPage);
    }

    /**
     * @param string $rawColumn
     * @return string|null
     */
    protected function fullyQualifiedColName(string $rawColumn): ?string
    {
        if (!$this->errors->isEmpty()) {
            return null;
        }

        [$column, $table] = Where::maybeSplitTableName($rawColumn, $this->errors);
        if (!$this->errors->isEmpty()) {
            return null;
        }

        [$realColumn, , , $colTable] = $this->aliases->resolveColumn($column);
        $this->aliases->mergeErrors($this->errors);
        if (!$this->errors->isEmpty() || ($realColumn === null)) {
            return null;
        }

        /** @var Schema $mainSchema */
        $mainSchema = $this->schema;
        $tableName = $table ?? $mainSchema->name();

        [$tableRealName, $schema, $tableAlias] = $this->aliases->resolveSchema($tableName);
        $this->aliases->mergeErrors($this->errors);
        if (!$this->errors->isEmpty()) {
            return null;
        }

        if (
            !$schema
            || (($colTable !== '') && ($colTable !== null) && ($colTable !== $tableRealName))
        ) {
            $this->errors->withError("Could not correctly resolve column '{$rawColumn}'.");

            return null;
        }

        $tableNameOrAlias = $tableAlias ?? $this->finder->fullTableName($schema);

        return "`{$tableNameOrAlias}`.`{$realColumn}`";
    }

    /**
     * @param string $string
     * @return static
     */
    protected function pushError(string $string): BaseSelect
    {
        $this->errors->withError($string);

        return $this;
    }
}
