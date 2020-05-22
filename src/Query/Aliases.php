<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Query;

use Inpsyde\Dbal\Dbal;
use Inpsyde\Dbal\ErrorCollector;
use Inpsyde\Dbal\Schema\SchemaFinder;
use Inpsyde\Dbal\Schema\Schemas;

class Aliases
{
    /**
     * @var SchemaFinder
     */
    private $finder;

    /**
     * @var array<string, string>
     */
    private $aliasToName = [];

    /**
     * @var array<string, array{0:string, 1:string|null, 2:string|null}>
     */
    private $columnsAliasData = [];

    /**
     * @var array<string, string>
     */
    private $columnAliasToNames = [];

    /**
     * @var ErrorCollector
     */
    private $errors;

    /**
     * @param SchemaFinder|null $finder
     * @return Aliases
     */
    public static function new(?SchemaFinder $finder = null): Aliases
    {
        return new static($finder);
    }

    /**
     * @param SchemaFinder $finder
     */
    private function __construct(?SchemaFinder $finder)
    {
        $this->finder = $finder ?? Dbal::schemaFinder();
        $this->errors = new ErrorCollector();
    }

    /**
     * @param string $realName
     * @param string $alias
     * @return Aliases
     */
    public function forSchema(string $realName, string $alias): Aliases
    {
        if (!$realName) {
            $this->errors->withError("Schema name to alias can't be empty.");

            return $this;
        }

        if (!$alias) {
            $this->errors->withError("Schema alias for table '{$realName}' can't be empty.");

            return $this;
        }

        if (!Schemas::validateSchemaName($alias)) {
            $this->errors->withError("'{$alias}' is not a valid schema alias.");

            return $this;
        }

        $schema = $this->finder->findSchema($realName);
        if (!$schema) {
            $this->errors->withError("Schema '{$realName}' not found.");

            return $this;
        }

        if (!empty($this->aliasToName[$alias])) {
            $already = $this->aliasToName[$alias];
            $this->errors->withError(
                "Schema aliases must be unique '{$alias}' already in use for '{$already}'."
            );

            return $this;
        }

        $this->aliasToName[$alias] = $realName;

        return $this;
    }

    /**
     * @param string $realName
     * @param string $alias
     * @param string $table
     * @return Aliases
     */
    public function forColumn(string $realName, string $alias, string $table): Aliases
    {
        return $this->withColumn(false, $realName, $alias, $table);
    }

    /**
     * @param string $realName
     * @param string $alias
     * @param string|null $table
     * @return Aliases
     */
    public function forRawColumn(string $realName, string $alias, ?string $table = null): Aliases
    {
        return $this->withColumn(true, $realName, $alias, $table);
    }

    /**
     * @param string $maybeAlias
     * @return array{0:string|null, 1:\Inpsyde\Dbal\Schema\Schema|null, 2:string|null, 3:array<string>}
     */
    public function resolveSchema(string $maybeAlias): array
    {
        if (!$maybeAlias) {
            $this->errors->withError("Could not resolve empty schema name.");

            return [null, null, null, []];
        }

        $aliased = $this->aliasToName[$maybeAlias] ?? null;
        $name = $aliased ?? $maybeAlias;
        $alias = $aliased ? $maybeAlias : null;
        $schema = $name ? $this->finder->findSchema($name) : null;
        if (!$schema) {
            $this->errors->withError("Error resolving alias for '{$maybeAlias}': table not found.");

            return [null, null, null, []];
        }

        $aliases = array_keys($this->aliasToName, $name, true);
        if (!$aliased && count($aliases) === 1) {
            /** @var string $alias */
            $alias = reset($aliases);
        }

        return [$name, $schema, $alias, $aliases];
    }

    /**
     * @param string $table
     * @param string $name
     * @return array{0:string|null, 1:string|null, 2:string|null, 3:string|null}
     */
    public function resolveColumn(string $name): array
    {
        if (!$name) {
            $this->errors->withError("Could not resolve empty column name.");

            return [null, null, null, null];
        }

        if (!$this->columnsAliasData) {
            return [$name, null, null, null];
        }

        $aliasData = $this->columnsAliasData[$name] ?? null;
        if ($aliasData === null) {
            $maybeRealName = $this->columnAliasToNames[$name] ?? null;
            if ($maybeRealName) {
                $name = $maybeRealName;
                $aliasData = $this->columnsAliasData[$name] ?? null;
            }
        }

        if ($aliasData === null) {
            return [$name, null, null, null];
        }

        [$alias, $tableFullName, $tableName] = $aliasData;

        return [$name, $alias, $tableFullName, $tableName];
    }

    /**
     * @param ErrorCollector $collector
     * @return void
     */
    public function mergeErrors(ErrorCollector $collector): void
    {
        $collector->pushFrom($this->errors);
    }

    /**
     * @param bool $raw
     * @param string $name
     * @param string $alias
     * @param string|null $table
     * @return Aliases
     */
    private function withColumn(bool $raw, string $name, string $alias, ?string $table): Aliases
    {
        if (!$name) {
            $this->errors->withError("Could not set alias '{$alias}' for empty column name.");

            return $this;
        }

        if (!$alias) {
            $this->errors->withError("Could not set empty alias for column '{$alias}'.");

            return $this;
        }

        if (!Schemas::validateSchemaName($alias)) {
            $this->errors->withError("'{$alias}' is not a valid column alias.");

            return $this;
        }

        if (!$table && !$raw) {
            $this->errors->withError("Could not set empty alias for column '{$alias}'.");

            return $this;
        }

        if (!$table) {
            $this->columnsAliasData[$name] = [$alias, null, null];
            $this->columnAliasToNames[$alias] = $name;

            return $this;
        }

        [$schemaName, $schema] = $this->resolveSchema($table);
        if (!$schema) {
            $this->errors->withError(
                "Could not set alias for column '{$raw}', table '{$table}' not found."
            );

            return $this;
        }

        $schemaFullName = $this->finder->fullTableName($schema);

        $this->columnsAliasData[$name] = [$alias, $schemaFullName, $schemaName];
        $this->columnAliasToNames[$alias] = $name;

        return $this;
    }
}
