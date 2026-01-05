<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Query;

use Inpsyde\Dbal\Cache;
use Inpsyde\Dbal\Dbal;
use Inpsyde\Dbal\Error;
use Inpsyde\Dbal\PhpErrors;
use Inpsyde\Dbal\Schema\Schema;
use Inpsyde\Dbal\Schema\SchemaFinder;
use Inpsyde\Dbal\Schema\Schemas;

class Select extends BaseSelect
{
    public const SELECT = 'select';
    public const COLUMNS = 'columns';
    public const GROUP_BY = 'groupBy';
    public const ORDER = 'order';

    public const FILTER_QUERY_PART = 'dbal.select-query-part';

    private const RAW_COL_KEY = '*raw* ';

    private ?Cache $cache;
    private ?string $sql = null;
    private ?Schemas $allColSchemas = null;
    private ?ResultsParser $resultsParser = null;

    /** @var array<string, array<string, array{string, bool, string|null}>> */
    private array $columns = [];

    /**  @var list<string> */
    private array $groupBy = [];

    /** @var list{int, array<int, array<mixed>>}|null */
    private ?array $executed = null;

    /**
     * @param string $tableName
     * @param string|null $alias
     * @param SchemaFinder|null $finder
     * @param Cache|null $cache
     *
     * @return Select
     */
    public static function from(
        string $tableName,
        ?string $alias = null,
        ?SchemaFinder $finder = null,
        ?Cache $cache = null
    ): Select {

        return new self($tableName, $alias, $finder, $cache);
    }

    /**
     * @param string $tableName
     * @param string|null $alias
     * @param SchemaFinder|null $finder
     * @param Cache|null $cache
     */
    private function __construct(
        string $tableName,
        ?string $alias = null,
        ?SchemaFinder $finder = null,
        ?Cache $cache = null
    ) {

        parent::__construct($tableName, $finder);
        $this->cache = $cache;

        if (($alias !== null) && ($alias !== '') && $this->schema) {
            $this->aliases = $this->aliases->forSchema($this->schema->name(), $alias);
            $this->aliases->mergeErrors($this->errors);
        }
    }

    /**
     * @return static
     */
    public function unfiltered(): BaseSelect
    {
        parent::unfiltered();
        $this->resetMemoized();

        return $this;
    }

    /**
     * @param string $column
     * @param string ...$columns
     *
     * @return static
     */
    public function cols(string $column, string ...$columns): Select
    {
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        $this->columns = [];
        $this->andCol($column);
        foreach ($columns as $column) {
            $this->andCol($column);
        }

        return $this;
    }

    /**
     * @param string ...$tables
     *
     * @return static
     */
    public function allCols(string ...$tables): Select
    {
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        $this->columns = [];
        if (!$tables) {
            /** @var Schema $schema */
            $schema = $this->schema;

            return $this->andRawCol($schema->name() . '.*', '');
        }

        foreach ($tables as $table) {
            $this->andRawCol("{$table}.*", '');
            if (!$this->errors->isEmpty()) {
                break;
            }
        }

        return $this;
    }

    /**
     * @param string $column
     * @param string|null $alias
     *
     * @return static
     */
    public function andCol(string $column, ?string $alias = null): Select
    {
        return $this->withColumn($column, $alias, false);
    }

    /**
     * @param string $column
     * @param string $alias
     *
     * @return static
     */
    public function rawCol(string $column, string $alias): Select
    {
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        $this->columns = [];

        return $this->withColumn($column, $alias, true);
    }

    /**
     * @param string $column
     * @param string $alias
     *
     * @return static
     */
    public function andRawCol(string $column, string $alias): Select
    {
        return $this->withColumn($column, $alias, true);
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param string|null $operator
     *
     * @return static
     */
    public function where(string $column, mixed $value, ?string $operator = null): BaseSelect
    {
        if ($this->errors->isEmpty()) {
            $this->resetMemoized();
        }

        return parent::where($column, $value, $operator);
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param string|null $operator
     *
     * @return static
     */
    public function andWhere(string $column, mixed $value, ?string $operator = null): BaseSelect
    {
        if ($this->errors->isEmpty()) {
            $this->resetMemoized();
        }

        return parent::andWhere($column, $value, $operator);
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param string|null $operator
     *
     * @return static
     */
    public function orWhere(string $column, mixed $value, ?string $operator = null): BaseSelect
    {
        if ($this->errors->isEmpty()) {
            $this->resetMemoized();
        }

        return parent::orWhere($column, $value, $operator);
    }

    /**
     * @param string $clause
     * @param string|null $column
     * @param string|null $operator
     *
     * @return static
     */
    public function whereRaw(
        string $clause,
        ?string $column = null,
        ?string $operator = null
    ): BaseSelect {

        if ($this->errors->isEmpty()) {
            $this->resetMemoized();
        }

        return parent::whereRaw($clause, $column, $operator);
    }

    /**
     * @param string $clause
     * @param string|null $column
     * @param string|null $operator
     *
     * @return static
     */
    public function andWhereRaw(
        string $clause,
        ?string $column = null,
        ?string $operator = null
    ): BaseSelect {

        if ($this->errors->isEmpty()) {
            $this->resetMemoized();
        }

        return parent::andWhereRaw($clause, $column, $operator);
    }

    /**
     * @param string $clause
     * @param string|null $column
     * @param string|null $operator
     *
     * @return static
     */
    public function orWhereRaw(
        string $clause,
        ?string $column = null,
        ?string $operator = null
    ): BaseSelect {

        if ($this->errors->isEmpty()) {
            $this->resetMemoized();
        }

        return parent::orWhereRaw($clause, $column, $operator);
    }

    /**
     * @param Where $where
     * @param Where ...$wheres
     *
     * @return static
     */
    public function whereUsing(Where $where, Where ...$wheres): BaseSelect
    {
        if ($this->errors->isEmpty()) {
            $this->resetMemoized();
        }

        return parent::whereUsing($where, ...$wheres);
    }

    /**
     * @param Where $where
     * @param Where ...$wheres
     *
     * @return static
     */
    public function andWhereUsing(Where $where, Where ...$wheres): BaseSelect
    {
        if ($this->errors->isEmpty()) {
            $this->resetMemoized();
        }

        return parent::andWhereUsing($where, ...$wheres);
    }

    /**
     * @param Where $where
     * @param Where ...$wheres
     *
     * @return static
     */
    public function orWhereUsing(Where $where, Where ...$wheres): BaseSelect
    {
        if ($this->errors->isEmpty()) {
            $this->resetMemoized();
        }

        return parent::orWhereUsing($where, ...$wheres);
    }

    /**
     * @param Compare $compare
     * @param Compare ...$compares
     *
     * @return static
     */
    public function whereCompare(Compare $compare, Compare ...$compares): BaseSelect
    {
        if ($this->errors->isEmpty()) {
            $this->resetMemoized();
        }

        return parent::whereCompare($compare, ...$compares);
    }

    /**
     * @param Compare $compare
     * @param Compare ...$compares
     *
     * @return static
     */
    public function andWhereCompare(Compare $compare, Compare ...$compares): BaseSelect
    {
        if ($this->errors->isEmpty()) {
            $this->resetMemoized();
        }

        return parent::andWhereCompare($compare, ...$compares);
    }

    /**
     * @param Compare $compare
     * @param Compare ...$compares
     *
     * @return static
     */
    public function orWhereCompare(Compare $compare, Compare ...$compares): BaseSelect
    {
        if ($this->errors->isEmpty()) {
            $this->resetMemoized();
        }

        return parent::orWhereCompare($compare, ...$compares);
    }

    /**
     * @param int $limit
     * @param int $offset
     *
     * @return static
     */
    public function limit(int $limit, int $offset = 0): BaseSelect
    {
        $current = $this->limit;
        $return = parent::limit($limit, $offset);
        if ($current !== $this->limit) {
            $return->resetMemoized();
        }

        return $return;
    }

    /**
     * @param string $column
     *
     * @return static
     */
    public function groupBy(string $column): Select
    {
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        $this->groupBy = [];

        return $this->withGroupBy($column);
    }

    /**
     * @param string $column
     *
     * @return static
     */
    public function thenGroupBy(string $column): Select
    {
        return $this->withGroupBy($column);
    }

    /**
     * @param int $page
     * @param int $perPage
     *
     * @return static
     */
    public function paginated(int $page = 1, int $perPage = 100): Select
    {
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        if ($page < 1 || $perPage < 1) {
            $this->pushError('$page and $perPage parameters must be bigger than 1.');

            return $this;
        }

        $this->resetMemoized();

        $this->limit = [false, $perPage, $page];

        return $this;
    }

    /**
     * @return static
     */
    public function unPaginated(): Select
    {
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        $this->resetMemoized();
        $this->limit = [null, null, 0];

        return $this;
    }

    /**
     * @param Pagination $pagination
     *
     * @return static
     */
    public function paginatedWith(Pagination $pagination): Select
    {
        $perPage = $pagination->perPage();
        if ($perPage === null) {
            $this->pushError('Invalid pagination object.');

            return $this;
        }

        return $this->paginated($pagination->page(), $perPage);
    }

    /**
     * @return ResultSet
     */
    public function pickFirst(): ResultSet
    {
        if (!$this->errors->isEmpty()) {
            return ResultSet::errored($this->errors->error());
        }

        $parser = $this->createResultsParser();
        $backup = null;

        if ($this->executed === null) {
            $backup = $this->limit;
            $this->limit = [true, 1, 0];
        }

        [, $rows] = $this->execute();

        if (!$this->errors->isEmpty()) {
            return ResultSet::errored($this->errors->error());
        }

        if ($backup !== null) {
            $this->limit = $backup;
        }

        $row = $rows
            ? reset($rows)
            : null;
        if (($row === null) || ($row === [])) {
            return ResultSet::empty();
        }

        return ResultSet::singleRow($this, $parser, $row);
    }

    /**
     * @return ResultSet
     */
    public function all(): ResultSet
    {
        if (!$this->errors->isEmpty()) {
            return ResultSet::errored($this->errors->error());
        }

        if (($this->limit[0] === null) && !$this->where) {
            $this->errors->withError(
                'Can\'t execute an unlimited and not paginated query without a WHERE clause.'
            );

            /** @var Error $error */
            $error = $this->errors->error();

            return ResultSet::errored($error);
        }

        $parser = $this->createResultsParser();

        $maybeError = $this->errors->isEmpty()
            ? null
            : $this->errors->error();
        if ($maybeError) {
            return ResultSet::errored($maybeError);
        }

        [$foundRows, $rows] = $this->execute();

        if (!$this->errors->isEmpty()) {
            return ResultSet::errored($this->errors->error());
        }

        [$hardLimit, $perPage, $page] = $this->limit;
        $pagination = (($hardLimit === false) && ($perPage !== null) && ($page > 0))
            ? Pagination::byTotalRows($foundRows, $perPage, $page)
            : Pagination::notPaginated();

        return ResultSet::new($this, $parser, $pagination, ...$rows);
    }

    /**
     * @return string
     */
    public function buildSql(): string
    {
        if (($this->sql === null) || ($this->sql === '')) {
            $sql = parent::buildSql();
            if ($sql !== '') {
                $this->sql = $sql;
            }
        }

        return $this->sql ?? '';
    }

    /**
     * @return array<string, string>|null
     */
    protected function buildQueryParts(): ?array
    {
        $base = $this->buildBaseQueryParts();
        if ($base === null) {
            return null;
        }

        $parts = array_merge(
            [
                self::SELECT => $this->limit[0] === false
                    ? 'SELECT SQL_CALC_FOUND_ROWS'
                    : 'SELECT',
                self::COLUMNS => $this->buildColumnsSql(),
            ],
            $base
        );

        if ($this->groupBy) {
            $groupBy = 'GROUP BY ' . implode(', ', $this->groupBy);
            $newParts = [];
            foreach ($parts as $key => $val) {
                $newParts[$key] = $val;
                if ($key === self::WHERE) {
                    $newParts[self::GROUP_BY] = $groupBy;
                }
            }
            $parts = $newParts;
        }

        return $this->filterQueryParts($parts, self::FILTER_QUERY_PART);
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
     *
     * @return static
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

        $current = $this->joins;
        $return = parent::withJoin(
            $type,
            $targetTable,
            $sourceTable,
            $columnOnSource,
            $columnOnJoined,
            $alias,
            $where,
            $raw
        );

        if ($current !== $return->joins) {
            $return->resetMemoized();
        }

        return $return;
    }

    /**
     * @param string $column
     * @param bool $raw
     * @param string|null $dir
     *
     * @return static
     */
    protected function withOrder(string $column, bool $raw, ?string $dir): BaseSelect
    {
        $current = $this->order;
        $return = parent::withOrder($column, $raw, $dir);

        if ($current !== $return->order) {
            $return->resetMemoized();
        }

        return $return;
    }

    /**
     * @param string $column
     *
     * @return static
     */
    private function withGroupBy(string $column): Select
    {
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        if (!$column) {
            return $this->pushError("Ordering column name can't be empty.");
        }

        $column = $this->fullyQualifiedColName($column);
        if ($column === null) {
            return $this;
        }

        $this->resetMemoized();

        $this->groupBy[] = $column;

        return $this;
    }

    /**
     * @param string $column
     * @param string|null $alias
     * @param bool $raw
     *
     * @return static
     *
     * phpcs:disable Syde.Functions.FunctionLength.TooLong
     * phpcs:disable SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh
     */
    private function withColumn(string $column, ?string $alias = null, bool $raw = false): Select
    {
        // phpcs:enable Inpsyde.CodeQuality.FunctionLength
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        /** @var Schema $mainSchema */
        $mainSchema = $this->schema;

        [$column, $table] = ($raw && substr_count($column, '('))
            ? [$column, null]
            : Where::maybeSplitTableName($column, $this->errors);

        if (!$this->errors->isEmpty()) {
            return $this;
        }

        $tableName = null;
        if (($table !== null) || !$raw) {
            $tableName = ($table === null)
                ? $mainSchema->name()
                : $table;
            $isRawAlias = $this->aliases->isRawSchemaAlias($tableName);

            [$schemaName, $schema, $schemaAlias] = $isRawAlias
                ? [$tableName, null, $tableName]
                : $this->aliases->resolveSchema($tableName);

            $this->aliases->mergeErrors($this->errors);
            if (!$this->errors->isEmpty() || (!$schema && !$isRawAlias)) {
                return $this;
            }

            if (
                !$raw
                && !$isRawAlias
                && ($column !== '*' && !$schema->columns()->hasColumn($column))
            ) {
                return $this->pushError(
                    "Column '{$column}' not found in '{$schemaName}' table. "
                    . 'Use a "raw" column to make use of MySQL functions. '
                    . 'To use column from joined tables, make sure to declare the JOIN before '
                    . 'the columns.'
                );
            }
        }

        if ($raw && (($alias === null) || ($alias === '')) && ($column !== '*')) {
            return $this->pushError(
                "Please provide a non-empty alias for raw column (like MySQL functions)."
            );
        }

        $this->resultsParser = null;
        $this->allColSchemas = null;
        $this->resetMemoized();

        /** @var string $columnsKey */
        $columnsKey = ($raw && ($table === null))
            ? self::RAW_COL_KEY
            : ($schemaAlias ?? $tableName);
        $schemaColumns = $this->columns[$columnsKey] ?? [];
        $schemaColumns[$column] = [$column, $raw, $alias];
        $this->columns[$columnsKey] = $schemaColumns;

        if (($alias !== null) && ($alias !== '')) {
            $table ??= '';
            $this->aliases = $raw
                ? $this->aliases->forRawColumn(
                    $column,
                    $alias,
                    ($table !== '')
                        ? $tableName
                        : null
                )
                : $this->aliases->forColumn($column, $alias, $tableName ?? '');

            $this->aliases->mergeErrors($this->errors);
        }

        return $this;
    }

    /**
     * @return ResultsParser
     */
    private function createResultsParser(): ResultsParser
    {
        if ($this->resultsParser) {
            return $this->resultsParser;
        }

        $this->resultsParser = ResultsParser::forTables($this->allColumnsSchemas(), $this->aliases);

        return $this->resultsParser;
    }

    /**
     * @return Schemas
     */
    private function allColumnsSchemas(): Schemas
    {
        if ($this->allColSchemas) {
            return $this->allColSchemas;
        }

        $schemas = [];
        foreach (array_keys($this->columns) as $tableNameOrAlias) {
            if (
                ($tableNameOrAlias === self::RAW_COL_KEY)
                || $this->aliases->isRawSchemaAlias($tableNameOrAlias)
            ) {
                continue;
            }

            [, $schema] = $this->aliases->resolveSchema($tableNameOrAlias);
            $this->aliases->mergeErrors($this->errors);
            if (!$schema || !$this->errors->isEmpty()) {
                break;
            }

            $schemas[] = $schema;
        }

        if (!$schemas) {
            /** @var Schema $mainSchema */
            $mainSchema = $this->schema;
            $schemas = [$mainSchema];
        }

        $this->allColSchemas = Schemas::new(...$schemas);

        return $this->allColSchemas;
    }

    /**
     * @return array{int, array<int, array<mixed>>}
     */
    private function execute(): array
    {
        if ($this->executed) {
            return $this->executed;
        }

        [$fullKey, $cachedFound, $cachedRows] = $this->cacheFor($this->buildSqlNoEscape());
        if ($cachedFound !== null && $cachedRows !== null) {
            $this->executed = [$cachedFound, $cachedRows];

            return $this->executed;
        }

        $wpdb = Dbal::wpdb();

        $phpErrors = PhpErrors::convertToExceptions();
        $suppressErrors = $wpdb->suppress_errors(true);

        try {
            $sql = $this->buildSql();

            $rows = $wpdb->get_results($sql, ARRAY_A);
            if (!is_array($rows)) {
                $rows = [];
            }

            if ($wpdb->last_error) {
                $this->pushError($wpdb->last_error);

                return [0, []];
            }

            $foundRows = ($this->limit[0] === false)
                ? (int) $wpdb->get_var('SELECT FOUND_ROWS()')
                : count($rows);

            $this->executed = [$foundRows, array_values(array_filter($rows, 'is_array'))];

            if ($this->cache) {
                $this->cache->set($fullKey, $this->executed);
            }

            return $this->executed;
        } catch (\Throwable $throwable) {
            $this->pushError($throwable->getMessage());

            return [0, []];
        } finally {
            $phpErrors->restoreHandler();
            $wpdb->suppress_errors($suppressErrors);
        }
    }

    /**
     * @return string
     */
    private function buildColumnsSql(): string
    {
        $sql = '';

        if (!$this->columns) {
            /** @var Schema $mainSchema */
            $mainSchema = $this->schema;
            [, , $theMainAlias] = $this->aliases->resolveSchema($mainSchema->name());
            $this->aliases->mergeErrors($this->errors);
            if (!$this->errors->isEmpty()) {
                return '';
            }

            $this->columns[$theMainAlias ?? $mainSchema->name()] = ['*' => ['*', true, null]];
        }

        ksort($this->columns);
        foreach ($this->columns as $tableName => $columnsData) {
            $sql = $this->buildColumnsSqlForTable($sql, $tableName, $columnsData);
        }

        return $sql;
    }

    /**
     * @param string $sql
     * @param string $table
     * @param array<string, array{string, bool, string|null}> $columns
     *
     * @return string
     *
     * phpcs:disable SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh
     */
    private function buildColumnsSqlForTable(string $sql, string $table, array $columns): string
    {
        $isRawAll = $table === self::RAW_COL_KEY;
        $hasAll = !empty($columns['*']);
        foreach ($columns as [$colName, $isRaw, $alias]) {
            // If we're getting all columns, we don't need more.
            if (($colName !== '*') && $hasAll && !$isRaw) {
                continue;
            }

            [$colRealName, $colAlias] = $this->aliases->resolveColumn($colName);
            $this->aliases->mergeErrors($this->errors);
            if (!$this->errors->isEmpty() || ($colRealName === null)) {
                return '';
            }

            $isColumnAll = $colName === '*';
            if ($isColumnAll && $isRawAll) {
                continue;
            }

            if ($isRawAll && ($colAlias !== null) && ($colAlias !== '')) {
                if ($sql !== '') {
                    $sql .= ', ';
                }
                $sql .= "{$colRealName} AS `{$colAlias}`";
                continue;
            }

            $isRawSchema = $this->aliases->isRawSchemaAlias($table);

            [, $schema, $schemaAlias] = $isRawSchema
                ? [null, null, $table]
                : $this->aliases->resolveSchema($table);

            $this->aliases->mergeErrors($this->errors);
            if (!$this->errors->isEmpty() || (!$schema && !$isRawSchema)) {
                return '';
            }

            $tableRef = $schemaAlias ?? $this->finder->fullTableName($schema);

            if ($sql !== '') {
                $sql .= ', ';
            }
            if ($isColumnAll) {
                $sql .= "`{$tableRef}`.*";
                continue;
            }

            $sql .= ($isRaw && !$isRawSchema)
                ? $colRealName
                : "`{$tableRef}`.`{$colRealName}`";
            $colAlias = $alias ?? $colAlias;
            if (($colAlias !== null) && ($colAlias !== '')) {
                $sql .= " AS `{$colAlias}`";
            }
        }

        return $sql;
    }

    /**
     * @param string $sql
     *
     * @return array{string, int|null, array<int, array<mixed>>|null}
     */
    private function cacheFor(string $sql): array
    {
        if (!$this->cache) {
            return ['', null, null];
        }

        /** @var Schema $mainSchema */
        $mainSchema = $this->schema;
        $tables = [$mainSchema->name()];

        foreach ($this->joins as $join) {
            $schema = $join->targetSchema();
            if ($schema) {
                $tables[] = $schema->name();
            }
        }

        $tableKeys = $this->cache->buildCacheKeyForTables(...$tables);
        $fullKey = (string) substr(md5("{$tableKeys}|{$sql}"), -18, 16);

        $cached = $this->cache->get($fullKey);
        if (is_array($cached) && isset($cached[0]) && isset($cached[1])) {
            /** @var array<int, array<mixed>> $rows */
            $rows = $cached[1];

            return [$fullKey, (int) $cached[0], $rows];
        }

        return [$fullKey, null, null];
    }

    /**
     * @return void
     */
    private function resetMemoized(): void
    {
        $this->executed = null;
        $this->sql = null;
    }
}
