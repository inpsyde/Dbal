<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Query;

use Inpsyde\Dbal\Cache;
use Inpsyde\Dbal\Dbal;
use Inpsyde\Dbal\PhpErrors;
use Inpsyde\Dbal\Result;
use Inpsyde\Dbal\Schema\Index;
use Inpsyde\Dbal\Schema\Schema;
use Inpsyde\Dbal\Schema\SchemaFinder;

class Delete extends BaseSelect
{
    use PrimaryAware;

    public const DELETE = 'delete';
    public const FILTER_QUERY_PART = 'dbal.delete-query-part';

    private ?Cache $cache;

    /** @var list<Schema> */
    private array $tablesOnly = [];

    /**
     * @param string $tableName
     * @param SchemaFinder|null $finder
     * @param Cache|null $cache
     * @return Delete
     */
    public static function from(
        string $tableName,
        ?SchemaFinder $finder = null,
        ?Cache $cache = null
    ): Delete {

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

        parent::__construct($tableName, $finder);
        $this->cache = $cache;
    }

    /**
     * @param string $tableName
     * @param string ...$tableNames
     * @return static
     */
    public function deleteOnly(string $tableName, string ...$tableNames): Delete
    {
        if (!$this->errors->isEmpty()) {
            return $this;
        }

        array_unshift($tableNames, $tableName);
        $only = [];
        foreach ($tableNames as $tableName) {
            $schema = $this->finder->findSchema($tableName);
            if (!$schema) {
                $this->pushError("Could not find a defined schema for '{$tableName}'.");
                continue;
            }
            $only[] = $schema;
        }

        if (!$this->errors->isEmpty()) {
            return $this;
        }

        $this->tablesOnly = $only;

        return $this;
    }

    /**
     * @param int $limit
     * @param int $offset
     * @return static
     */
    public function limit(int $limit, int $offset = 0): BaseSelect
    {
        if ($this->joins || ($offset !== 0)) {
            if ($this->joins) {
                $this->pushError('JOIN and LIMIT clauses can not be mixed in DELETE queries.');
            }

            if ($offset !== 0) {
                $this->pushError('LIMIT clause in DELETE queries can not use offset.');
            }

            return $this;
        }

        return parent::limit($limit, $offset);
    }

    /**
     * @param mixed $primaryValue
     * @param string|null $operator
     * @return Result
     */
    public function delOnPrimary($primaryValue, ?string $operator = null): Result
    {
        if (!$this->errors->isEmpty()) {
            return Result::new($this->errors);
        }

        return $this->findPrimary($this->schema, $this->finder)
            ->bind(
                function (Index $index) use ($primaryValue, $operator): Result {
                    if (($operator === null) && wp_is_numeric_array($primaryValue)) {
                        $operator = Where::IN;
                    }

                    $instance = $this->where
                        ? $this->andWhere($index->name(), $primaryValue, $operator)
                        : $this->where($index->name(), $primaryValue, $operator);

                    return $instance->exec();
                }
            );
    }

    /**
     * @return Result
     */
    public function exec(): Result
    {
        if (!$this->where && ($this->limit[0] === null)) {
            $this->pushError('Can not execute a DELETE query without WHERE or LIMIT clauses.');
        }

        $allSchemas = $this->findAllSchemas();
        if ($allSchemas === null) {
            $this->pushError('Can not find target schemas for DELETE query.');
        }

        if (!$this->errors->isEmpty()) {
            return Result::new($this->errors);
        }

        $wpdb = Dbal::wpdb();

        $phpErrors = PhpErrors::convertToExceptions();
        $suppressErrors = $wpdb->suppress_errors(true);

        try {
            $sql = $this->buildSql();
            if (!$sql) {
                $this->errors->withError('Could not build SQL for DELETE query');

                return Result::new($this->errors);
            }

            $rows = $wpdb->query($sql);
            if (is_numeric($rows)) {
                $this->flushCache(...$allSchemas);

                return Result::new((int) $rows);
            }

            $this->errors->withError('Failed deleting rows');

            return Result::new($this->errors);
        } catch (\Throwable $throwable) {
            return Result::new($throwable);
        } finally {
            $phpErrors->restoreHandler();
            $wpdb->suppress_errors($suppressErrors);
        }
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

        $allSchemas = $this->findAllSchemas();
        if ($allSchemas === null) {
            return null;
        }

        $delete = 'DELETE';

        if (count($allSchemas) > 1 || ($this->tablesOnly !== [])) {
            $delete .= sprintf(' `%s`', implode('`, `', $allSchemas));
        }

        $parts = array_merge([self::DELETE => $delete], $base);

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

        $alias ??= '';
        if (($alias !== '') || ($this->limit[0] !== null)) {
            if (($alias !== '')) {
                $this->pushError('Aliases are not allowed in DELETE queries');
            }

            if ($this->limit[0] !== null) {
                $this->pushError('JOIN and LIMIT clauses can not be mixed in DELETE queries.');
            }

            return $this;
        }

        return parent::withJoin(
            $type,
            $targetTable,
            $sourceTable,
            $columnOnSource,
            $columnOnJoined,
            null,
            $where,
            $raw
        );
    }

    /**
     * @return list<string>|null
     */
    private function findAllSchemas(): ?array
    {
        /** @var Schema $mainSchema */
        $mainSchema = $this->schema;
        $allSchemas = [$this->finder->fullTableName($mainSchema)];
        foreach ($this->joins as $joined) {
            $joinedSchema = $joined->targetSchema();
            if (!$joinedSchema) {
                return null;
            }
            $allSchemas[] = $this->finder->fullTableName($joinedSchema);
        }

        if ($this->tablesOnly === []) {
            return $allSchemas;
        }

        $allowed = [];
        foreach ($this->tablesOnly as $table) {
            $tableName = $this->finder->fullTableName($table);
            if (!in_array($tableName, $allSchemas, true)) {
                return null;
            }

            $allowed[] = $tableName;
        }

        return $allowed;
    }

    /**
     * @param string ...$allSchemas
     * @return void
     */
    private function flushCache(string ...$allSchemas): void
    {
        if (!$this->cache) {
            return;
        }

        foreach ($allSchemas as $schema) {
            $this->cache->cleanCacheForTables($schema);
        }
    }
}
