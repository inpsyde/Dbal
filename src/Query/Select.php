<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Query;

use Inpsyde\Dbal\Cache;
use Inpsyde\Dbal\Dbal;
use Inpsyde\Dbal\Error;
use Inpsyde\Dbal\ErrorCollector;
use Inpsyde\Dbal\PhpErrors;
use Inpsyde\Dbal\Schema\SchemaFinder;
use Inpsyde\Dbal\Schema\Schemas;
use Inpsyde\Dbal\Schema\Schema;

/**
 * phpcs:disable Inpsyde.CodeQuality.PropertyPerClassLimit
 */
class Select
{
    public const SELECT = 'select';
    public const COLUMNS = 'columns';
    public const FROM = 'from';
    public const WHERE = 'where';
    public const GROUP_BY = 'groupBy';
    public const ORDER = 'order';
    public const LIMIT = 'limit';

    public const ASC = 'ASC';
    public const DESC = 'DESC';

    public const FILTER_QUERY_PART = 'dbal.select-query-part';

    private const RAW_COL_KEY = '生 ';

    /**
     * @var Schema|null
     */
    private $schema;

    /**
     * @var Cache|null
     */
    private $cache;

    /**
     * @var SchemaFinder
     */
    private $finder;

    /**
     * @var array<string, Join>
     */
    private $joins = [];

    /**
     * @var array<string, array<string, array{0:string, 1:bool}>>
     */
    private $columns = [];

    /**
     * @var Aliases
     */
    private $aliases;

    /**
     * @var Where|null
     */
    private $where;

    /**
     * @var array<array{0:string, 1:string|null, 2:bool}>
     */
    private $order = [];

    /**
     * @var string|null
     */
    private $groupBy;

    /**
     * @var array{0:bool|null, 1:int|null, 2:int}
     */
    private $limit = [null, null, 0];

    /**
     * @var bool
     */
    private $unfiltered = false;

    /**
     * @var ErrorCollector
     */
    private $errors;

    /**
     * @var array{0:int, 1:array<int, array>}|null
     */
    private $executed;

    /**
     * @var string|null
     */
    private $sql;

    /**
     * @var Schemas|null
     */
    private $allColSchemas;

    /**
     * @var ResultsParser|null
     */
    private $resultsParser;

    /**
     * @param string $tableName
     * @param string|null $alias
     * @param SchemaFinder|null $finder
     * @param Cache|null $cache
     * @return Select
     */
    public static function from(
        string $tableName,
        ?string $alias = null,
        SchemaFinder $finder = null,
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

        $this->finder = $finder ?: Dbal::schemaFinder();
        $this->schema = $this->finder->findSchema($tableName);
        $this->cache = $cache;

        $this->errors = new ErrorCollector();
        if (!$this->schema) {
            $tableName
                ? $this->errors->withError("Table '{$tableName}' not found.")
                : $this->errors->withError("SELECT table name can't be empty.");
        }

        $this->aliases = Aliases::new($this->finder);
        if ($alias && $this->schema) {
            $this->aliases = $this->aliases->forSchema($this->schema->name(), $alias);
            $this->aliases->mergeErrors($this->errors);
        }
    }

    /**
     * @return Select
     */
    public function unfiltered(): Select
    {
        $this->unfiltered = true;
        $this->resetMemoized();

        return $this;
    }

    /**
     * @param string $joinTable
     * @param string|null $columnNameOnMain
     * @param string|null $columnNameOnJoined
     * @param string|null $alias
     * @return Select
     */
    public function leftJoin(
        string $joinTable,
        string $columnNameOnMain = null,
        ?string $columnNameOnJoined = null,
        ?string $alias = null
    ): Select {

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
     * @return Select
     */
    public function leftJoinWhere(string $joinTable, Where $where, ?string $alias = null): Select
    {
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
     * @return Select
     */
    public function leftJoinWith(
        string $joinTable,
        string $sourceTable,
        ?string $columnNameOnSource = null,
        ?string $columnNameOnJoined = null,
        ?string $alias = null
    ): Select {

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
     * @return Select
     */
    public function leftJoinWhereWith(
        string $joinTable,
        string $sourceTable,
        Where $where,
        ?string $alias = null
    ): Select {

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
     * @return Select
     */
    public function leftJoinRaw(
        string $expression,
        string $alias,
        ?string $columnOnMain = null,
        ?string $columnOnExpression = null
    ): Select {

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
     * @return Select
     */
    public function leftJoinRawWith(
        string $expression,
        string $alias,
        string $sourceTable,
        ?string $columnOnMain = null,
        ?string $columnOnExpression = null
    ): Select {

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
     * @return Select
     */
    public function innerJoin(
        string $joinTable,
        ?string $columnNameOnMain = null,
        ?string $columnNameOnJoined = null,
        ?string $alias = null
    ): Select {

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
     * @return Select
     */
    public function innerJoinWhere(string $joinTable, Where $where, ?string $alias = null): Select
    {
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
     * @return Select
     */
    public function innerJoinWith(
        string $joinTable,
        string $sourceTable,
        ?string $columnNameOnSource = null,
        ?string $columnNameOnJoined = null,
        ?string $alias = null
    ): Select {

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
     * @return Select
     */
    public function innerJoinWhereWith(
        string $joinTable,
        string $sourceTable,
        Where $where,
        ?string $alias = null
    ): Select {

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
     * @return Select
     */
    public function innerJoinRaw(
        string $expression,
        string $alias,
        ?string $columnOnMain = null,
        ?string $columnOnExpression = null
    ): Select {

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
     * @return Select
     */
    public function innerJoinRawWith(
        string $expression,
        string $alias,
        string $sourceTable,
        ?string $columnOnMain = null,
        ?string $columnOnExpression = null
    ): Select {

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
     * @return Select
     */
    public function joinViaPivot(
        string $joinTable,
        string $pivotTable,
        string $pivotColumnForMain,
        string $pivotColumnForJoined,
        ?string $columnOnMain = null,
        ?string $columnOnJoined = null
    ): Select {

        if (!$this->errors->isEmpty()) {
            return $this;
        }

        return $this
            ->innerJoin($pivotTable, $columnOnMain, $pivotColumnForMain)
            ->innerJoinWith($joinTable, $pivotTable, $pivotColumnForJoined, $columnOnJoined);
    }

    /**
     * @param string $column
     * @param string ...$columns
     * @return Select
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
     * @return Select
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
     * @return Select
     */
    public function andCol(string $column, ?string $alias = null): Select
    {
        return $this->withColumn($column, $alias, false);
    }

    /**
     * @param string $column
     * @param string $alias
     * @return Select
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
     * @return Select
     */
    public function andRawCol(string $column, string $alias): Select
    {
        return $this->withColumn($column, $alias, true);
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param string|null $operator
     * @return Select
     *
     * @psalm-suppress MissingParamType
     * phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration
     */
    public function where(string $column, $value, ?string $operator = null): Select
    {
        // phpcs:enable Inpsyde.CodeQuality.ArgumentTypeDeclaration

        if (!$this->errors->isEmpty()) {
            return $this;
        }

        $this->resetMemoized();
        $this->where = Where::new()->with($column, $value, $operator);
        $this->where->pushErrorTo($this->errors);

        return $this;
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param string|null $operator
     * @return Select
     *
     * @psalm-suppress MissingParamType
     * phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration
     */
    public function andWhere(string $column, $value, ?string $operator = null): Select
    {
        // phpcs:enable Inpsyde.CodeQuality.ArgumentTypeDeclaration

        if (!$this->errors->isEmpty()) {
            return $this;
        }

        $this->resetMemoized();
        if (!$this->where) {
            $this->where = Where::new();
        }
        $this->where->and($column, $value, $operator);
        $this->where->pushErrorTo($this->errors);

        return $this;
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param string|null $operator
     * @return Select
     *
     * @psalm-suppress MissingParamType
     * phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration
     */
    public function orWhere(string $column, $value, ?string $operator = null): Select
    {
        // phpcs:enable Inpsyde.CodeQuality.ArgumentTypeDeclaration

        if (!$this->errors->isEmpty()) {
            return $this;
        }

        $this->resetMemoized();
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
     * @return Select
     */
    public function whereRaw(
        string $clause,
        ?string $column = null,
        ?string $operator = null
    ): Select {

        if (!$this->errors->isEmpty()) {
            return $this;
        }

        $this->resetMemoized();
        $this->where = Where::new()->withRaw($clause, $column, $operator);
        $this->where->pushErrorTo($this->errors);

        return $this;
    }

    /**
     * @param string $clause
     * @param string|null $column
     * @param string|null $operator
     * @param string|null $table
     * @return Select
     */
    public function andWhereRaw(
        string $clause,
        ?string $column = null,
        ?string $operator = null
    ): Select {

        if (!$this->errors->isEmpty()) {
            return $this;
        }

        $this->resetMemoized();
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
     * @return Select
     */
    public function orWhereRaw(
        string $clause,
        ?string $column = null,
        ?string $operator = null
    ): Select {

        if (!$this->errors->isEmpty()) {
            return $this;
        }

        $this->resetMemoized();
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
     * @return Select
     */
    public function whereUsing(Where $where, Where ...$wheres): Select
    {
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        $this->resetMemoized();
        $this->where = Where::new()->andWhere($where, ...$wheres);

        return $this;
    }

    /**
     * @param Where $where
     * @param Where ...$wheres
     * @return Select
     */
    public function andWhereUsing(Where $where, Where ...$wheres): Select
    {
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        $this->resetMemoized();
        if (!$this->where) {
            $this->where = Where::new();
        }
        $this->where->andWhere($where, ...$wheres);

        return $this;
    }

    /**
     * @param Where $where
     * @param Where ...$wheres
     * @return Select
     */
    public function orWhereUsing(Where $where, Where ...$wheres): Select
    {
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        $this->resetMemoized();
        if (!$this->where) {
            $this->where = Where::new();
        }
        $this->where->orWhere($where, ...$wheres);

        return $this;
    }

    /**
     * @param Compare $compare
     * @param Compare ...$compares
     * @return Select
     */
    public function whereCompare(Compare $compare, Compare ...$compares): Select
    {
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        $this->resetMemoized();
        $this->where = Where::new()->andCompare($compare, ...$compares);

        return $this;
    }

    /**
     * @param Compare $compare
     * @param Compare ...$compares
     * @return Select
     */
    public function andWhereCompare(Compare $compare, Compare ...$compares): Select
    {
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        $this->resetMemoized();
        if (!$this->where) {
            $this->where = Where::new();
        }
        $this->where->andCompare($compare, ...$compares);

        return $this;
    }

    /**
     * @param Compare $compare
     * @param Compare ...$compares
     * @return Select
     */
    public function orWhereCompare(Compare $compare, Compare ...$compares): Select
    {
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        $this->resetMemoized();
        if (!$this->where) {
            $this->where = Where::new();
        }
        $this->where->orCompare($compare, ...$compares);

        return $this;
    }

    /**
     * @param string $column
     * @param string $dir
     * @return Select
     */
    public function orderBy(string $column, string $dir = self::ASC): Select
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
     * @return Select
     */
    public function orderByRaw(string $clause, ?string $dir = self::ASC): Select
    {
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        $this->order = [];

        return $this->withOrder($clause, true, $dir);
    }

    /**
     * @param string $column
     * @param string $dir
     * @return Select
     */
    public function orderByRand(): Select
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
     * @return Select
     */
    public function thenOrderBy(string $clause, string $dir = self::ASC): Select
    {
        return $this->withOrder($clause, false, $dir);
    }

    /**
     * @param string $clause
     * @param string|null $dir
     * @return Select
     */
    public function thenOrderByRaw(string $clause, ?string $dir = self::ASC): Select
    {
        return $this->withOrder($clause, true, $dir);
    }

    /**
     * @param int $limit
     * @param int $offset
     * @return Select
     */
    public function limit(int $limit, int $offset = 0): Select
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
        $this->resetMemoized();

        return $this;
    }

    /**
     * @param string $column
     * @return Select
     */
    public function groupBy(string $column): Select
    {
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        $column = $this->fullyQualifiedColName($column);
        if ($column === null) {
            return $this;
        }

        $this->resetMemoized();

        $this->groupBy = $column;

        return $this;
    }

    /**
     * @param int $page
     * @param int $perPage
     * @return Select
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
     * @param int $page
     * @param int $perPage
     * @return Select
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
     * @return Select
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
            /** @psalm-suppress PossiblyNullArgument */
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
            /** @psalm-suppress PossiblyNullArgument */
            return ResultSet::errored($this->errors->error());
        }

        if ($backup !== null) {
            $this->limit = $backup;
        }

        /** @var array|null $row */
        $row = $rows ? reset($rows) : null;
        if (!$row) {
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
            /** @psalm-suppress PossiblyNullArgument */
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

        $maybeError = $this->errors->isEmpty() ? null : $this->errors->error();
        if ($maybeError) {
            return ResultSet::errored($maybeError);
        }

        [$foundRows, $rows] = $this->execute();

        if (!$this->errors->isEmpty()) {
            /** @psalm-suppress PossiblyNullArgument */
            return ResultSet::errored($this->errors->error());
        }

        [$hardLimit, $perPage, $page] = $this->limit;
        $pagination = (($hardLimit === false) && $perPage && $page)
            ? Pagination::byTotalRows($foundRows, $perPage, $page)
            : Pagination::notPaginated();

        return ResultSet::new($this, $parser, $pagination, ...$rows);
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
        if (!$parts) {
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
        $valid = $this->errors->isEmpty();
        if (!$valid || $this->sql) {
            return $valid ? $this->sql : '';
        }

        $parts = $this->buildQueryParts();
        if (!$parts) {
            return '';
        }

        $this->sql = implode(' ', array_filter($parts));

        return $this->sql;
    }

    /**
     * @return array<string, string>|null
     */
    public function buildQueryParts(): ?array
    {
        if (!$this->errors->isEmpty()) {
            return null;
        }

        /** @var Schema $schema */
        $schema = $this->schema;
        /** @var SchemaFinder $finder */
        $finder = $this->finder;

        $whereClause = '';

        if ($this->where) {
            $whereClause = $this->where->clause($schema, $finder, $this->aliases);
            $this->where->pushErrorTo($this->errors);
            if (!$this->errors->isEmpty()) {
                return null;
            }
        }

        $parts = [
            self::SELECT => $this->limit[0] === false ? 'SELECT SQL_CALC_FOUND_ROWS' : 'SELECT',
            self::COLUMNS => $this->buildColumnsSql(),
            self::FROM => $this->buildFromSql(),
            self::WHERE => $whereClause ? "WHERE {$whereClause}" : '',
            self::GROUP_BY => $this->groupBy ? "GROUP BY {$this->groupBy}" : '',
            self::ORDER => $this->buildOrderSql(),
            self::LIMIT => $this->buildLimitSql(),
        ];

        if (!$this->errors->isEmpty()) {
            return null;
        }

        if ($this->unfiltered || !has_filter(self::FILTER_QUERY_PART)) {
            return $parts;
        }

        foreach ($parts as $name => $value) {
            $filtered = apply_filters(self::FILTER_QUERY_PART, $value, $name, $parts);
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
    private function hasJoinFor(string $joinTableName): bool
    {
        [$name, , , $tableAliases] = $this->aliases->resolveSchema($joinTableName);
        $this->aliases->mergeErrors($this->errors);
        if (!$name || !$this->errors->isEmpty()) {
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
     * @return Select
     *
     * phpcs:disable Generic.Metrics.CyclomaticComplexity.TooHigh
     * phpcs:disable Inpsyde.CodeQuality.FunctionLength
     */
    private function withJoin(
        string $type,
        ?string $targetTable,
        ?string $sourceTable,
        ?string $columnOnSource = null,
        ?string $columnOnJoined = null,
        ?string $alias = null,
        ?Where $where = null,
        ?string $raw = null
    ): Select {

        // phpcs:enable Generic.Metrics.CyclomaticComplexity.TooHigh
        // phpcs:enable Inpsyde.CodeQuality.FunctionLength

        if (!$this->errors->isEmpty()) {
            return $this;
        }

        $isRaw = $raw !== null;

        /** @psalm-suppress PossiblyNullArgument */
        $target = $isRaw ? null : $this->finder->findSchema($targetTable);
        if (!$target && !$isRaw) {
            return $this->pushError("Could not find table '{$targetTable}' to join.");
        }

        if ($isRaw) {
            if ($raw === '') {
                return $this->pushError("Raw JOIN expression can't be empty.");
            }

            if (!$alias) {
                return $this->pushError("Alias is required for raw JOINs.");
            }
        }

        $sourceIsMain = $sourceTable === null;
        $source = $sourceIsMain ? $this->schema : null;

        if (!$sourceIsMain) {
            [, $source] = $this->aliases->resolveSchema($sourceTable ?? '');
            $this->aliases->mergeErrors($this->errors);
            if (!$this->errors->isEmpty() || !$source) {
                return $this;
            }
        }

        if (!$source) {
            return $this;
        }

        // If source table is not the main, we need to be sure source table is already joined
        if (!$sourceIsMain && !$this->hasJoinFor($source->name())) {
            return $this->pushError(
                "Table '{$sourceTable}' is not in joined table list, "
                . "can't use as source for another join."
            );
        }

        $join = null;

        if ($isRaw) {
            /** @psalm-suppress PossiblyNullArgument */
            $join = $type === Join::LEFT
                ? Join::leftRaw($source, $raw, $alias, $columnOnSource, $columnOnJoined)
                : Join::innerRaw($source, $raw, $alias, $columnOnSource, $columnOnJoined);
        } elseif ($where) {
            /** @psalm-suppress PossiblyNullArgument */
            $join = $type === Join::LEFT
                ? Join::leftWhere($source, $target, $where, $alias)
                : Join::innerWhere($source, $target, $where, $alias);
        }

        if (!$join) {
            /** @psalm-suppress PossiblyNullArgument */
            $join = $type === Join::LEFT
                ? Join::left($source, $target, $columnOnSource, $columnOnJoined, $alias)
                : Join::inner($source, $target, $columnOnSource, $columnOnJoined, $alias);
        }

        $join->mergeErrors($this->errors);
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        $this->resetMemoized();

        if ($alias) {
            /** @psalm-suppress PossiblyNullReference */
            $isRaw
                ? $this->aliases->forRawSchema($alias)
                : $this->aliases->forSchema($target->name(), $alias);
            $this->aliases->mergeErrors($this->errors);
        }

        if ($this->errors->isEmpty()) {
            /** @psalm-suppress PossiblyNullReference */
            $joinName = $alias ?? $target->name();
            $this->joins[$joinName] = $join;
        }

        return $this;
    }

    /**
     * @param string $column
     * @param string|null $table
     * @param string|null $alias
     * @param bool $raw
     * @return Select
     */
    private function withColumn(string $column, ?string $alias = null, bool $raw = false): Select
    {
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        /** @var Schema $mainSchema */
        $mainSchema = $this->schema;

        [$column, $table] = $raw && substr_count($column, '(')
            ? [$column, null]
            : Where::maybeSplitTableName($column, $this->errors);

        if (!$this->errors->isEmpty()) {
            return $this;
        }

        $tableName = null;
        if ($table || !$raw) {
            $tableName = $table === null ? $mainSchema->name() : $table;
            $isRawAlias = $this->aliases->isRawSchemaAlias($tableName);

            /** @var Schema|null $schema */
            [$schemaName, $schema, $schemaAlias] = $isRawAlias
                ? [$tableName, null, $tableName]
                : $this->aliases->resolveSchema($tableName);

            $this->aliases->mergeErrors($this->errors);
            if (!$this->errors->isEmpty() || (!$schema && !$isRawAlias)) {
                return $this;
            }

            /** @psalm-suppress PossiblyNullReference */
            if (!$raw && ($column !== '*' && !$schema->columns()->hasColumn($column))) {
                return $this->pushError(
                    "Column '{$column}' not found in '{$schemaName}' table. "
                    . 'Use a "raw" column to make use of MySQL functions. '
                    . 'To use column from joined tables, make sure to declare the JOIN before '
                    . 'the columns.'
                );
            }
        }

        if ($raw && !$alias && $column !== '*') {
            return $this->pushError(
                "Please provide a non-empty alias for raw column (like MySQL functions)."
            );
        }

        $this->resultsParser = null;
        $this->allColSchemas = null;
        $this->resetMemoized();

        /** @var string $columnsKey */
        $columnsKey = ($raw && !$table) ? self::RAW_COL_KEY : ($schemaAlias ?? $tableName);
        $schemaColumns = $this->columns[$columnsKey] ?? [];
        $schemaColumns[$column] = [$column, $raw];
        $this->columns[$columnsKey] = $schemaColumns;

        if ($alias) {
            $this->aliases = $raw
                ? $this->aliases->forRawColumn($column, $alias, $table ? $tableName : null)
                : $this->aliases->forColumn($column, $alias, $tableName ?? '');

            $this->aliases->mergeErrors($this->errors);
        }

        return $this;
    }

    /**
     * @param string $column
     * @param bool $raw
     * @param string|null $dir
     * @return Select
     */
    private function withOrder(string $column, bool $raw, ?string $dir): Select
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
            $this->resetMemoized();
            $this->order[] = [$column, $dir, true];

            return $this;
        }

        $column = $this->fullyQualifiedColName($column);
        if ($column === null) {
            return $this;
        }

        $this->resetMemoized();

        $this->order[] = [$column, $dir, false];

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
        foreach ($this->columns as $tableNameOrAlias => $data) {
            if (
                $tableNameOrAlias === self::RAW_COL_KEY
                || $this->aliases->isRawSchemaAlias($tableNameOrAlias)
            ) {
                continue;
            }

            /** @var Schema|null $schema */
            [, $schema] = $this->aliases->resolveSchema($tableNameOrAlias);
            $this->aliases->mergeErrors($this->errors);
            if (!$schema || !$this->errors->isEmpty()) {
                break;
            }

            $schemas[] = $schema;
            continue;
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
     * @return array{0:int, 1:array<int, array>}
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
            if (!$rows || !is_array($rows)) {
                $rows = [];
            }

            if ($wpdb->last_error) {
                $this->pushError($wpdb->last_error);

                return [0, []];
            }

            $foundRows = ($this->limit[0] === false)
                ? (int)$wpdb->get_var('SELECT FOUND_ROWS()')
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

            $this->columns[$theMainAlias ?? $mainSchema->name()] = ['*' => ['*', true]];
        }

        ksort($this->columns);

        /** @var string $tableName */
        foreach ($this->columns as $tableName => $columnsData) {
            $sql = $this->buildColumnsSqlForTable($sql, $tableName, $columnsData);
        }

        return $sql;
    }

    /**
     * @param string $sql
     * @param string $table
     * @param array<string, array{0:string, 1:bool}> $columns
     * @return string
     */
    private function buildColumnsSqlForTable(string $sql, string $table, array $columns): string
    {
        $isRawAll = $table === self::RAW_COL_KEY;
        $hasAll = !empty($columns['*']);
        foreach ($columns as [$colName, $isRaw]) {
            // If we're getting all columns, we don't need more.
            if ($colName !== '*' && $hasAll && !$isRaw) {
                continue;
            }

            [$colRealName, $colAlias] = $this->aliases->resolveColumn($colName);
            $this->aliases->mergeErrors($this->errors);
            if (!$this->errors->isEmpty() || !$colRealName) {
                return '';
            }

            $isColumnAll = $colName === '*';
            if ($isColumnAll && $isRawAll) {
                continue;
            }

            if ($isRawAll && $colAlias) {
                $sql and $sql .= ', ';
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

            /** @psalm-suppress PossiblyNullArgument */
            $tableRef = $schemaAlias ?? $this->finder->fullTableName($schema);

            $sql and $sql .= ', ';
            if ($isColumnAll) {
                $sql .= "`{$tableRef}`.*";
                continue;
            }

            $sql .= ($isRaw && !$isRawSchema) ? $colRealName : "`{$tableRef}`.`{$colRealName}`";
            $colAlias and $sql .= " AS `{$colAlias}`";
        }

        return $sql;
    }

    /**
     * @return string
     */
    private function buildFromSql(): string
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
        $sql .= $mainAlias ? "`{$fullMainName}` AS `{$mainAlias}`" : "`{$fullMainName}`";

        /** @var Join $join */
        foreach ($this->joins as $join) {
            $sql .= ' ' . $join->clause($this->finder, $this->aliases);
        }

        return $sql;
    }

    /**
     * @return string
     */
    private function buildOrderSql(): string
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
    private function buildLimitSql(): string
    {
        [$hardLimit, $perPage, $pageOrOffset] = $this->limit;
        if (($hardLimit === null) || ($perPage === null)) {
            return '';
        }

        $offset = $hardLimit ? $pageOrOffset : (($pageOrOffset - 1) * $perPage);

        return sprintf('LIMIT %d, %d', $offset, $perPage);
    }

    /**
     * @param string $column
     * @return string
     */
    private function fullyQualifiedColName(string $rawColumn): ?string
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
        if (!$this->errors->isEmpty() || !$realColumn) {
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

        if (!$schema || $colTable && ($colTable !== $tableRealName)) {
            $this->errors->withError("Could not correctly resolve column '{$rawColumn}'.");

            return null;
        }

        $tableNameOrAlias = $tableAlias ?? $this->finder->fullTableName($schema);

        return "`{$tableNameOrAlias}`.`{$realColumn}`";
    }

    /**
     * @param string $sql
     * @return array{0:string, 1:int|null, 2: array<int, array>|null}
     */
    private function cacheFor(string $sql): array
    {
        if (!$this->cache) {
            return ['', null, null];
        }

        /** @var Schema $mainSchema */
        $mainSchema = $this->schema;
        $tables = [$mainSchema->name()];

        /** @var Join $join */
        foreach ($this->joins as $join) {
            $schema = $join->targetSchema();
            if ($schema) {
                $tables[] = $schema->name();
            }
        }

        $tableKeys = $this->cache->buildCacheKeyForTables(...$tables);
        $fullKey = substr(md5("{$tableKeys}|{$sql}"), -18, 16) ?: '';

        $cached = $this->cache->get($fullKey);
        if ($cached && is_array($cached) && isset($cached[0]) && isset($cached[1])) {
            /** @var array<int, array> $rows */
            $rows = $cached[1];

            return [$fullKey, (int)$cached[0], $rows];
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

    /**
     * @param string $string
     * @return Select
     */
    private function pushError(string $string): Select
    {
        $this->errors->withError($string);

        return $this;
    }
}
