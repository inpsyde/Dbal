<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Query;

use Inpsyde\Dbal\ErrorCollector;
use Inpsyde\Dbal\Schema\Schema;
use Inpsyde\Dbal\Schema\SchemaFinder;

final class Join
{
    public const LEFT = 'left';
    public const INNER = 'inner';

    private ErrorCollector $errors;
    private ?string $type = null;
    private ?Schema $targetSchema = null;
    private ?Schema $sourceSchema = null;
    private ?string $columnOnSource = null;
    private ?string $columnOnTarget = null;
    private ?Where $where = null;

    /** @var non-empty-string|null */
    private ?string $alias = null;

    /** @var non-empty-string|null */
    private ?string $raw = null;

    /**
     * @param Schema $sourceSchema
     * @param Schema $targetSchema
     * @param Aliases $aliases
     * @param string|null  $columnNameOnSource
     * @param string|null  $columnNameOnJoined
     * @param string|null $alias
     * @return Join
     */
    public static function left(
        Schema $sourceSchema,
        Schema $targetSchema,
        ?string $columnNameOnSource = null,
        ?string $columnNameOnJoined = null,
        ?string $alias = null
    ): Join {

        return new static(
            self::LEFT,
            $sourceSchema,
            $targetSchema,
            $columnNameOnSource,
            $columnNameOnJoined,
            $alias
        );
    }

    /**
     * @param Schema $sourceSchema
     * @param Schema $targetSchema
     * @param Where $where
     * @param string|null $alias
     * @return Join
     */
    public static function leftWhere(
        Schema $sourceSchema,
        Schema $targetSchema,
        Where $where,
        ?string $alias = null
    ): Join {

        return new static(self::LEFT, $sourceSchema, $targetSchema, null, null, $alias, $where);
    }

    /**
     * @param Schema $sourceSchema
     * @param string $clause
     * @param string $alias
     * @param string|null $columnNameOnSource
     * @param string|null $columnNameOnClause
     * @return Join
     */
    public static function leftRaw(
        Schema $sourceSchema,
        string $clause,
        string $alias,
        ?string $columnNameOnSource = null,
        ?string $columnNameOnClause = null
    ): Join {

        return new static(
            self::LEFT,
            $sourceSchema,
            null,
            $columnNameOnSource,
            $columnNameOnClause,
            $alias,
            null,
            $clause
        );
    }

    /**
     * @param Schema $sourceSchema
     * @param Schema $targetSchema
     * @param Aliases $aliases
     * @param string|null $columnNameOnSource
     * @param string|null $columnNameOnJoined
     * @param string|null $alias
     * @return Join
     */
    public static function inner(
        Schema $sourceSchema,
        Schema $targetSchema,
        ?string $columnNameOnSource = null,
        ?string $columnNameOnJoined = null,
        ?string $alias = null
    ): Join {

        return new static(
            self::INNER,
            $sourceSchema,
            $targetSchema,
            $columnNameOnSource,
            $columnNameOnJoined,
            $alias
        );
    }

    /**
     * @param Schema $sourceSchema
     * @param Schema $targetSchema
     * @param Where $where
     * @param string|null $alias
     * @return Join
     */
    public static function innerWhere(
        Schema $sourceSchema,
        Schema $targetSchema,
        Where $where,
        ?string $alias = null
    ): Join {

        return new static(self::INNER, $sourceSchema, $targetSchema, null, null, $alias, $where);
    }

    /**
     * @param Schema $sourceSchema
     * @param string $clause
     * @param string $alias
     * @param string|null $columnNameOnSource
     * @param string|null $columnNameOnClause
     * @return Join
     */
    public static function innerRaw(
        Schema $sourceSchema,
        string $clause,
        string $alias,
        ?string $columnNameOnSource = null,
        ?string $columnNameOnClause = null
    ): Join {

        return new static(
            self::INNER,
            $sourceSchema,
            null,
            $columnNameOnSource,
            $columnNameOnClause,
            $alias,
            null,
            $clause
        );
    }

    /**
     * @param string $type
     * @param Schema $sourceSchema
     * @param Schema $targetSchema
     * @param string|null $columnOnSource
     * @param string|null $columnOnJoined
     * @param string|null $alias
     * @param Where|null $where
     * @param string|null $raw
     */
    private function __construct(
        string $type,
        Schema $sourceSchema,
        ?Schema $targetSchema,
        ?string $columnOnSource = null,
        ?string $columnOnJoined = null,
        ?string $alias = null,
        ?Where $where = null,
        ?string $raw = null
    ) {

        $this->errors = new ErrorCollector();

        if ($where) {
            $this->type = $type;
            $this->sourceSchema = $sourceSchema;
            $this->targetSchema = $targetSchema;
            $this->where = $where;
            $this->alias = ($alias === '') ? null : $alias;

            return;
        }

        $hasRaw = ($raw !== null) && ($raw !== '');
        $hasNoAlias = ($alias === null) && ($raw === '');
        if ($hasRaw && ($hasNoAlias || $targetSchema)) {
            $hasNoAlias and $this->errors->withError('Alias is required for raw JOINs.');
            $targetSchema and $this->errors->withError('Target schema not allowed for raw JOINs.');

            return;
        }

        [$sourceCol, $targetCol] = $this->resolveColumns(
            $sourceSchema,
            $targetSchema,
            $columnOnSource,
            $columnOnJoined
        );

        if (!$this->checkColumns($sourceCol, $targetCol, $sourceSchema, $targetSchema)) {
            return;
        }

        $this->type = $type;
        $this->sourceSchema = $sourceSchema;
        $this->targetSchema = $targetSchema;
        $this->columnOnSource = $sourceCol;
        $this->columnOnTarget = $targetCol;
        $this->alias = ($alias === '') ? null : $alias;
        $this->raw = ($raw === '') ? null : $raw;
    }

    /**
     * @return Schema|null
     */
    public function targetSchema(): ?Schema
    {
        return $this->targetSchema;
    }

    /**
     * @param SchemaFinder $finder
     * @param Aliases $aliases
     * @return string
     */
    public function clause(SchemaFinder $finder, Aliases $aliases): string
    {
        if (!$this->errors->isEmpty()) {
            return '';
        }

        $on = $this->where
            ? $this->onClauseByWhere($finder, $aliases)
            : $this->onClauseByColumns($finder, $aliases);

        if (!$on) {
            return '';
        }

        $clause = $this->type === self::INNER ? 'INNER JOIN ' : 'LEFT JOIN ';
        if ($this->raw !== null) {
            return $clause . "({$this->raw}) AS `{$this->alias}` {$on}";
        }

        /** @var Schema $targetSchema */
        $targetSchema = $this->targetSchema;
        $targetName = $finder->fullTableName($targetSchema);

        $clause .= "`{$targetName}`";
        if ($this->alias !== null) {
            $clause .= " AS `{$this->alias}`";
        }

        return "{$clause} {$on}";
    }

    /**
     * @param string $column
     * @param Schema $schema
     * @param Aliases $aliases
     * @return string
     */
    private function resolveColumn(string $column, Schema $schema, Aliases $aliases): string
    {
        [$column, $table] = Where::maybeSplitTableName($column, $this->errors);
        if (!$this->errors->isEmpty()) {
            return '';
        }

        [$columnName, , , $columnTableName] = $aliases->resolveColumn($column);
        $aliases->mergeErrors($this->errors);
        if (!$this->errors->isEmpty() || ($columnName === null)) {
            return '';
        }

        $tableName = $table ?? $columnTableName ?? $schema->name();
        [, , $tableAlias] = $aliases->resolveSchema($tableName);
        $tableAlias ??= '';

        /** @psalm-suppress RedundantCondition */
        if (
            ($table !== null)
            && (($columnTableName !== null) || ($tableAlias !== ''))
            && !in_array($table, [$columnTableName, $tableAlias], true)
        ) {
            $this->errors->withError("Table '{$table}' is unknown alias.");

            return '';
        }

        if (!$schema->columns()->hasColumn($columnName)) {
            $this->errors->withError(
                "Column '{$column}' seen in JOIN clause not found in '{$tableName}'."
            );

            return '';
        }

        return $columnName;
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
     * @param non-empty-string|null $sourceCol
     * @param non-empty-string|null $targetCol
     * @param Schema $sourceSchema
     * @param Schema|null $targetSchema
     * @return bool
     */
    private function checkColumns(
        ?string $sourceCol,
        ?string $targetCol,
        Schema $sourceSchema,
        ?Schema $targetSchema
    ): bool {

        if (($sourceCol !== null) && ($targetCol !== null)) {
            return true;
        }

        $targetName = $targetSchema ? sprintf("'%s'", $targetSchema->name()) : 'target expression';
        $sourceName = $sourceSchema->name();

        ($sourceCol === null) and $this->errors->withError(
            sprintf(
                "Could not find a column on '%s' to be used to JOIN %s.",
                $sourceName,
                $targetName
            )
        );

        ($targetCol === null) and $this->errors->withError(
            sprintf(
                "Could not find a column on %s to be used to JOIN '%s'.",
                $targetName,
                $sourceName
            )
        );

        return false;
    }

    /**
     * @param Schema $sourceSchema
     * @param Schema|null $targetSchema
     * @param string|null $columnNameOnSource
     * @param string|null $columnNameOnJoined
     * @return list{non-empty-string|null, non-empty-string|null}
     */
    private function resolveColumns(
        Schema $sourceSchema,
        ?Schema $targetSchema,
        ?string $columnNameOnSource = null,
        ?string $columnNameOnJoined = null
    ): array {

        $sourceUsePrimary = false;
        $joinedUsePrimary = false;

        if (($columnNameOnSource === '') || ($columnNameOnSource === null)) {
            $columnNameOnSource = $this->findPrimaryColName($sourceSchema);
            $sourceUsePrimary = (bool) $columnNameOnSource;
        }

        if ($targetSchema === null) {
            ($columnNameOnSource === '') and $columnNameOnSource = null;
            ($columnNameOnJoined === '') and $columnNameOnJoined = null;

            return [$columnNameOnSource, $columnNameOnJoined ?? $columnNameOnSource];
        }

        if (($columnNameOnJoined === '') || ($columnNameOnJoined === null)) {
            $columnNameOnJoined = $this->findPrimaryColName($targetSchema);
            $joinedUsePrimary = (bool) $columnNameOnJoined;
        }

        $columnNameOnSource ??= '';
        $columnNameOnJoined ??= '';

        if (($columnNameOnSource === '') && ($columnNameOnJoined === '')) {
            return [null, null];
        }

        if (($columnNameOnJoined === '') && !$sourceUsePrimary) {
            return [$columnNameOnSource, $columnNameOnSource];
        }

        if (($columnNameOnSource === '') && !$joinedUsePrimary) {
            return [$columnNameOnJoined, $columnNameOnJoined];
        }

        ($columnNameOnSource === '') and $columnNameOnSource = null;
        ($columnNameOnJoined === '') and $columnNameOnJoined = null;

        return [$columnNameOnSource, $columnNameOnJoined];
    }

    /**
     * @param Schema $schema
     * @return string|null
     */
    private function findPrimaryColName(Schema $schema): ?string
    {
        $keys = $schema->indexes();
        $primary = $keys ? $keys->primary() : null;

        return $primary ? $primary->name() : null;
    }

    /**
     * @param SchemaFinder $finder
     * @param Aliases $aliases
     * @return string
     */
    private function onClauseByColumns(SchemaFinder $finder, Aliases $aliases): string
    {
        /** @var Schema $sourceSchema */
        $sourceSchema = $this->sourceSchema;

        [, , $sourceAlias] = $aliases->resolveSchema($sourceSchema->name());
        $aliases->mergeErrors($this->errors);
        if (!$this->errors->isEmpty()) {
            return '';
        }

        $targetSchema = $this->targetSchema;
        $sourceRef = $sourceAlias ?? $finder->fullTableName($sourceSchema);
        $sourceColName = $this->resolveColumn($this->columnOnSource ?? '', $sourceSchema, $aliases);

        $targetColName = '';
        if ($this->raw === null) {
            $targetSearch = $this->columnOnTarget ?? '';
            /** @var Schema $targetSchema */
            $targetColName = $this->resolveColumn($targetSearch, $targetSchema, $aliases);
        }

        if (($sourceColName === '') || (($targetColName === '') && ($this->raw === null))) {
            return '';
        }

        $clause = "ON `{$sourceRef}`.`{$sourceColName}` = ";
        /** @var Schema $targetSchema */
        $targetRef = $this->alias ?? $finder->fullTableName($targetSchema);
        $targetColName = ($this->raw !== null) ? $this->columnOnTarget : $targetColName;
        $clause .= "`{$targetRef}`.`{$targetColName}`";

        return $clause;
    }

    /**
     * @param SchemaFinder $finder
     * @param Aliases $aliases
     * @return string
     */
    private function onClauseByWhere(SchemaFinder $finder, Aliases $aliases): string
    {
        /** @var Where $where */
        $where = $this->where;

        /** @var Schema $sourceSchema */
        $sourceSchema = $this->sourceSchema;

        /** @var Schema $targetSchema */
        $targetSchema = $this->targetSchema;

        $whereAliases = $aliases;
        if ($this->alias !== null) {
            $whereAliases = clone $aliases;
            $whereAliases = $whereAliases->forSchema($targetSchema->name(), $this->alias);
        }

        $clause = $where->clause($sourceSchema, $finder, $whereAliases);
        $where->pushErrorTo($this->errors);
        if (!$this->errors->isEmpty()) {
            return '';
        }

        return "ON {$clause}";
    }
}
