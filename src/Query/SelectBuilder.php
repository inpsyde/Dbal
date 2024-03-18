<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Query;

use Inpsyde\Dbal\Dbal;
use Inpsyde\Dbal\Error;
use Inpsyde\Dbal\Result;

final class SelectBuilder
{
    private string $tableName;
    private ?string $alias;

    /** @var list<list{string, array}> */
    private array $record = [];

    /**
     * @param string $tableName
     * @param string|null $alias
     * @return SelectBuilder
     */
    public static function new(string $tableName, ?string $alias = null): SelectBuilder
    {
        return new self($tableName, $alias);
    }

    /**
     * @param string $tableName
     * @param string|null $alias
     */
    private function __construct(string $tableName, ?string $alias = null)
    {
        $this->tableName = $tableName;
        $this->alias = $alias;
    }

    /**
     * @return static
     */
    public function unfiltered(): SelectBuilder
    {
        $this->record[] = [__FUNCTION__, func_get_args()];

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
    ): SelectBuilder {

        $this->record[] = [__FUNCTION__, func_get_args()];

        return $this;
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
    ): SelectBuilder {

        $this->record[] = [__FUNCTION__, func_get_args()];

        return $this;
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
    ): SelectBuilder {

        $this->record[] = [__FUNCTION__, func_get_args()];

        return $this;
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
    ): SelectBuilder {

        $this->record[] = [__FUNCTION__, func_get_args()];

        return $this;
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
    ): SelectBuilder {

        $this->record[] = [__FUNCTION__, func_get_args()];

        return $this;
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
    ): SelectBuilder {

        $this->record[] = [__FUNCTION__, func_get_args()];

        return $this;
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
    ): SelectBuilder {

        $this->record[] = [__FUNCTION__, func_get_args()];

        return $this;
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
    ): SelectBuilder {

        $this->record[] = [__FUNCTION__, func_get_args()];

        return $this;
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
    ): SelectBuilder {

        $this->record[] = [__FUNCTION__, func_get_args()];

        return $this;
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
    ): SelectBuilder {

        $this->record[] = [__FUNCTION__, func_get_args()];

        return $this;
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
    ): SelectBuilder {

        $this->record[] = [__FUNCTION__, func_get_args()];

        return $this;
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
    ): SelectBuilder {

        $this->record[] = [__FUNCTION__, func_get_args()];

        return $this;
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
    ): SelectBuilder {

        $this->record[] = [__FUNCTION__, func_get_args()];

        return $this;
    }

    /**
     * @param string $column
     * @param string ...$columns
     * @return static
     */
    public function cols(string $column, string ...$columns): SelectBuilder
    {
        $this->record[] = [__FUNCTION__, func_get_args()];

        return $this;
    }

    /**
     * @param string ...$tables
     * @return static
     */
    public function allCols(string ...$tables): SelectBuilder
    {
        $this->record[] = [__FUNCTION__, func_get_args()];

        return $this;
    }

    /**
     * @param string $column
     * @param string|null $alias
     * @return static
     */
    public function andCol(string $column, ?string $alias = null): SelectBuilder
    {
        $this->record[] = [__FUNCTION__, func_get_args()];

        return $this;
    }

    /**
     * @param string $column
     * @param string $alias
     * @return static
     */
    public function rawCol(string $column, string $alias): SelectBuilder
    {
        $this->record[] = [__FUNCTION__, func_get_args()];

        return $this;
    }

    /**
     * @param string $column
     * @param string $alias
     * @return static
     */
    public function andRawCol(string $column, string $alias): SelectBuilder
    {
        $this->record[] = [__FUNCTION__, func_get_args()];

        return $this;
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param string|null $operator
     * @return static
     */
    public function where(string $column, $value, ?string $operator = null): SelectBuilder
    {
        $this->record[] = [__FUNCTION__, func_get_args()];

        return $this;
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param string|null $operator
     * @return static
     */
    public function andWhere(string $column, $value, ?string $operator = null): SelectBuilder
    {
        $this->record[] = [__FUNCTION__, func_get_args()];

        return $this;
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param string|null $operator
     * @return static
     */
    public function orWhere(string $column, $value, ?string $operator = null): SelectBuilder
    {
        $this->record[] = [__FUNCTION__, func_get_args()];

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
    ): SelectBuilder {

        $this->record[] = [__FUNCTION__, func_get_args()];

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
    ): SelectBuilder {

        $this->record[] = [__FUNCTION__, func_get_args()];

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
    ): SelectBuilder {

        $this->record[] = [__FUNCTION__, func_get_args()];

        return $this;
    }

    /**
     * @param Where $where
     * @param Where ...$wheres
     * @return static
     */
    public function whereUsing(Where $where, Where ...$wheres): SelectBuilder
    {
        $this->record[] = [__FUNCTION__, func_get_args()];

        return $this;
    }

    /**
     * @param Where $where
     * @param Where ...$wheres
     * @return static
     */
    public function andWhereUsing(Where $where, Where ...$wheres): SelectBuilder
    {
        $this->record[] = [__FUNCTION__, func_get_args()];

        return $this;
    }

    /**
     * @param Where $where
     * @param Where ...$wheres
     * @return static
     */
    public function orWhereUsing(Where $where, Where ...$wheres): SelectBuilder
    {
        $this->record[] = [__FUNCTION__, func_get_args()];

        return $this;
    }

    /**
     * @param Compare $compare
     * @param Compare ...$compares
     * @return static
     */
    public function whereCompare(Compare $compare, Compare ...$compares): SelectBuilder
    {
        $this->record[] = [__FUNCTION__, func_get_args()];

        return $this;
    }

    /**
     * @param Compare $compare
     * @param Compare ...$compares
     * @return static
     */
    public function andWhereCompare(Compare $compare, Compare ...$compares): SelectBuilder
    {
        $this->record[] = [__FUNCTION__, func_get_args()];

        return $this;
    }

    /**
     * @param Compare $compare
     * @param Compare ...$compares
     * @return static
     */
    public function orWhereCompare(Compare $compare, Compare ...$compares): SelectBuilder
    {
        $this->record[] = [__FUNCTION__, func_get_args()];

        return $this;
    }

    /**
     * @param string $column
     * @param string $dir
     * @return static
     */
    public function orderBy(string $column, string $dir = Select::ASC): SelectBuilder
    {
        $this->record[] = [__FUNCTION__, func_get_args()];

        return $this;
    }

    /**
     * @param string $clause
     * @param string|null $dir
     * @return static
     */
    public function orderByRaw(string $clause, ?string $dir = Select::ASC): SelectBuilder
    {
        $this->record[] = [__FUNCTION__, func_get_args()];

        return $this;
    }

    /**
     * @return static
     */
    public function orderByRand(): SelectBuilder
    {
        $this->record[] = [__FUNCTION__, func_get_args()];

        return $this;
    }

    /**
     * @param string $clause
     * @param string $dir
     * @return static
     */
    public function thenOrderBy(string $clause, string $dir = Select::ASC): SelectBuilder
    {
        $this->record[] = [__FUNCTION__, func_get_args()];

        return $this;
    }

    /**
     * @param string $clause
     * @param string|null $dir
     * @return static
     */
    public function thenOrderByRaw(string $clause, ?string $dir = Select::ASC): SelectBuilder
    {
        $this->record[] = [__FUNCTION__, func_get_args()];

        return $this;
    }

    /**
     * @param int $limit
     * @param int $offset
     * @return static
     */
    public function limit(int $limit, int $offset = 0): SelectBuilder
    {
        $this->record[] = [__FUNCTION__, func_get_args()];

        return $this;
    }

    /**
     * @param string $column
     * @return static
     */
    public function groupBy(string $column): SelectBuilder
    {
        $this->record[] = [__FUNCTION__, func_get_args()];

        return $this;
    }

    /**
     * @param string $column
     * @return static
     */
    public function thenGroupBy(string $column): SelectBuilder
    {
        $this->record[] = [__FUNCTION__, func_get_args()];

        return $this;
    }

    /**
     * @param int $page
     * @param int $perPage
     * @return static
     */
    public function paginated(int $page = 1, int $perPage = 100): SelectBuilder
    {
        $this->record[] = [__FUNCTION__, func_get_args()];

        return $this;
    }

    /**
     * @return static
     */
    public function unPaginated(): SelectBuilder
    {
        $this->record[] = [__FUNCTION__, func_get_args()];

        return $this;
    }

    /**
     * @param Pagination $pagination
     * @return static
     */
    public function paginatedWith(Pagination $pagination): SelectBuilder
    {
        $this->record[] = [__FUNCTION__, func_get_args()];

        return $this;
    }

    /**
     * @return Result
     */
    public function build(): Result
    {
        $select = Dbal::select($this->tableName, $this->alias);

        if (!Dbal::isReady()) {
            return Result::new(
                new Error(sprintf('Invalid call to %s() before DBAL is ready.', __METHOD__))
            );
        }

        foreach ($this->record as [$methodName, $args]) {
            /** @var Select $select */
            $select = $select->{$methodName}(...$args);
        }

        return Result::new($select);
    }

    /**
     * @return ResultSet
     */
    public function pickFirst(): ResultSet
    {
        return $this->execute(true);
    }

    /**
     * @return ResultSet
     */
    public function all(): ResultSet
    {
        return $this->execute(false);
    }

    /**
     * @param bool $pickFirst
     * @return ResultSet
     */
    private function execute(bool $pickFirst): ResultSet
    {
        $result = $this->build()->bind(
            static function (Select $select) use ($pickFirst): ResultSet {
                return $pickFirst ? $select->pickFirst() : $select->all();
            },
            static function (Error $error) use ($pickFirst): Error {
                $method = $pickFirst ? 'pickFirst' : 'all';

                return $error->merge(new Error("Could not execute Select::{$method}()"));
            }
        );

        /** @var ResultSet $resultSet */
        $resultSet = $result->isErrored()
            ? ResultSet::errored($result->error())
            : $result->extract();

        return $resultSet;
    }
}
