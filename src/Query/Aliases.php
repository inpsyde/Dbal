<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Query;

use Inpsyde\Dbal\Dbal;
use Inpsyde\Dbal\ErrorCollector;
use Inpsyde\Dbal\Schema\Schema;
use Inpsyde\Dbal\Schema\SchemaFinder;
use Inpsyde\Dbal\Schema\Schemas;

/**
 * @psalm-consistent-constructor
 */
class Aliases
{
    private ErrorCollector $errors;
    private SchemaFinder $finder;

    /** @var array<string, string> */
    private array $aliasToName = [];

    /** @var array<string, string>  */
    private array $rawSchemaAliases = [];

    /** @var array<string, array{string, string|null, string|null}> */
    private array $columnsAliasData = [];

    /** @var array<string, string>  */
    private array $columnAliasToNames = [];

    /**
     * @param SchemaFinder|null $finder
     * @return static
     */
    public static function new(?SchemaFinder $finder = null): Aliases
    {
        return new static($finder);
    }

    /**
     * @param SchemaFinder|null $finder
     */
    protected function __construct(?SchemaFinder $finder)
    {
        $this->finder = $finder ?? Dbal::schemaFinder();
        $this->errors = new ErrorCollector();
    }

    /**
     * @param string $realName
     * @param string $alias
     * @return static
     */
    public function forSchema(string $realName, string $alias): Aliases
    {
        if ($realName === '') {
            $this->errors->withError("Schema name to alias can't be empty.");

            return $this;
        }

        if ($alias === '') {
            $this->errors->withError("Schema alias for table '{$realName}' can't be empty.");

            return $this;
        }

        if (!Schemas::validateIdentifierName($alias)) {
            $this->errors->withError("'{$alias}' is not a valid schema alias.");

            return $this;
        }

        $schema = $this->finder->findSchema($realName);
        if (!$schema) {
            $this->errors->withError("Schema '{$realName}' not found.");

            return $this;
        }

        if (
            !empty($this->aliasToName[$alias])
            && $this->aliasToName[$alias] !== $realName
        ) {
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
     * @param string $alias
     * @return static
     */
    public function forRawSchema(string $alias): Aliases
    {
        if (!$alias) {
            $this->errors->withError("Raw schema alias for table can't be empty.");

            return $this;
        }

        if (!Schemas::validateIdentifierName($alias)) {
            $this->errors->withError("'{$alias}' is not a valid schema alias.");

            return $this;
        }

        if (!empty($this->aliasToName[$alias])) {
            $already = $this->aliasToName[$alias];
            $this->errors->withError(
                "Schema aliases must be unique '{$alias}' already in use for '{$already}'."
            );

            return $this;
        }

        $this->aliasToName[$alias] = $alias;
        $this->rawSchemaAliases[$alias] = $alias;

        return $this;
    }

    /**
     * @param string $realName
     * @param string $alias
     * @param string $table
     * @return static
     */
    public function forColumn(string $realName, string $alias, string $table): Aliases
    {
        return $this->withColumn(false, $realName, $alias, $table);
    }

    /**
     * @param string $realName
     * @param string $alias
     * @param string|null $table
     * @return static
     */
    public function forRawColumn(string $realName, string $alias, ?string $table = null): Aliases
    {
        return $this->withColumn(true, $realName, $alias, $table);
    }

    /**
     * @param string $maybeAlias
     * @return list{string|null, Schema|null, string|null, list<string>}
     */
    public function resolveSchema(string $maybeAlias): array
    {
        if (!$maybeAlias) {
            $this->errors->withError("Could not resolve empty schema name.");

            return [null, null, null, []];
        }

        $aliased = $this->aliasToName[$maybeAlias] ?? null;
        $name = $aliased ?? $maybeAlias;
        $alias = ($aliased !== '') && ($aliased !== null) ? $maybeAlias : null;
        $schema = $name ? $this->finder->findSchema($name) : null;
        if (!$schema) {
            $this->errors->withError("Error resolving alias for '{$maybeAlias}': table not found.");

            return [null, null, null, []];
        }

        $aliases = array_keys($this->aliasToName, $name, true);
        if ((($aliased === '') || ($aliased === null)) && (count($aliases) === 1)) {
            $alias = reset($aliases);
        }

        return [$name, $schema, $alias, $aliases];
    }

    /**
     * @param string $maybeAlias
     * @return bool
     */
    public function isRawSchemaAlias(string $maybeAlias): bool
    {
        return $maybeAlias && ($this->rawSchemaAliases[$maybeAlias] ?? null) === $maybeAlias;
    }

    /**
     * @return bool
     */
    public function hasSchemaAliases(): bool
    {
        return $this->aliasToName !== [];
    }

    /**
     * @return bool
     */
    public function hasColumnAliases(): bool
    {
        return $this->columnAliasToNames !== [];
    }

    /**
     * @param string $name
     * @return list{non-empty-string|null, string|null, string|null, string|null}
     */
    public function resolveColumn(string $name): array
    {
        if ($name === '') {
            $this->errors->withError("Could not resolve empty column name.");

            return [null, null, null, null];
        }

        if (!$this->columnsAliasData) {
            return [$name, null, null, null];
        }

        $aliasData = $this->columnsAliasData[$name] ?? null;
        if ($aliasData === null) {
            $maybeRealName = $this->columnAliasToNames[$name] ?? null;
            if (($maybeRealName !== null) && ($maybeRealName !== '')) {
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
     * @param string $colName
     * @param string $alias
     * @param string|null $table
     * @return static
     */
    private function withColumn(bool $raw, string $colName, string $alias, ?string $table): Aliases
    {
        if (($alias === '') && ($colName === '')) {
            $this->errors->withError("Could not set empty alias for empty column name.");

            return $this;
        }

        if ($alias === '') {
            $this->errors->withError("Could not set empty alias for column '{$colName}'.");

            return $this;
        }

        if ($colName === '') {
            $this->errors->withError("Could not set alias '{$alias}' for empty column name.");

            return $this;
        }

        if (!Schemas::validateIdentifierName($alias)) {
            $this->errors->withError("'{$alias}' is not a valid column alias.");

            return $this;
        }

        $table ??= '';

        if (($table === '')) {
            if (!$raw) {
                $this->errors->withError("Could not set empty alias for column '{$alias}'.");

                return $this;
            }
            $this->columnsAliasData[$colName] = [$alias, null, null];
            $this->columnAliasToNames[$alias] = $colName;

            return $this;
        }

        $rawSchema = $this->rawSchemaAliases[$table] ?? null;
        $hasRawSchema = ($rawSchema !== null) && ($rawSchema !== '');

        [$schemaName, $schema] = $hasRawSchema ? [$rawSchema, null] : $this->resolveSchema($table);
        if (($schema === null) && !$hasRawSchema) {
            $this->errors->withError(
                "Could not set alias for column '{$colName}', table '{$table}' not found."
            );

            return $this;
        }

        $schemaFullName = $rawSchema ?? $this->finder->fullTableName($schema);
        $this->columnsAliasData[$colName] = [$alias, $schemaFullName, $schemaName];
        $this->columnAliasToNames[$alias] = $colName;

        return $this;
    }
}
