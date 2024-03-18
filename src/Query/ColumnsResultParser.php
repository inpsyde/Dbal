<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Query;

use Inpsyde\Dbal\Schema\Columns;

class ColumnsResultParser
{
    private Columns $columns;
    private string $schema;
    private Aliases $aliases;

    /**
     * @param Columns $columns
     * @param string $schemaName
     * @param Aliases $aliases
     * @return ColumnsResultParser
     */
    public static function new(
        Columns $columns,
        string $schemaName,
        Aliases $aliases
    ): ColumnsResultParser {

        return new self($columns, $schemaName, $aliases);
    }

    /**
     * @param Columns $columns
     * @param string $schemaName
     * @param Aliases $aliases
     */
    private function __construct(Columns $columns, string $schemaName, Aliases $aliases)
    {
        $this->columns = $columns;
        $this->schema = $schemaName;
        $this->aliases = $aliases;

        if ($schemaName === '') {
            throw new \Exception("Empty schema name for ColumnsResultParser.");
        }
    }

    /**
     * @param array $data
     * @return array
     */
    public function parse(array $data): array
    {
        if (!$data) {
            return [];
        }

        $toParse = [];
        /** @var array<string, string> $columnsToAlias */
        $columnsToAlias = [];

        foreach ($data as $key => $value) {
            if (!$key || !is_string($key)) {
                continue;
            }

            [$column, $alias, , $tableRealName] = $this->aliases->resolveColumn($key);
            if (
                ($tableRealName !== null)
                && ($tableRealName !== '')
                && ($tableRealName !== $this->schema)
            ) {
                continue;
            }

            /** @var string $column */
            if (($alias !== null) && ($alias !== '')) {
                $columnsToAlias[$column] = $alias;
            }
            $toParse[$column] = $value;
        }

        if (!$toParse) {
            return $data;
        }

        $parsed = $this->columns->parseQueryResultRow($toParse);
        if (!$columnsToAlias) {
            return array_replace($data, $parsed);
        }

        $aliasRestoredData = [];
        foreach ($parsed as $column => $value) {
            $key = $columnsToAlias[$column] ?? $column;
            $aliasRestoredData[$key] = $value;
        }

        return array_replace($data, $aliasRestoredData);
    }
}
