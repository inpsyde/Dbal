<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Schema;

final class Index
{
    /**
     * @var bool
     */
    private $primary = false;

    /**
     * @var bool
     */
    private $unique = false;

    /**
     * @var string
     */
    private $name = '';

    /**
     * @var string[]
     */
    private $columns = [];

    /**
     * @var array<string, int>
     */
    private $limits = [];

    /**
     * @param string $name
     * @return Index
     */
    public static function primary(string $name): Index
    {
        return new static($name, true);
    }

    /**
     * @param string $name
     * @param string ...$columns
     * @return Index
     */
    public static function unique(string $name, string ...$columns): Index
    {
        return new static($name, false, true, ...$columns);
    }

    /**
     * @param string $name
     * @param string ...$columns
     * @return Index
     */
    public static function key(string $name, string ...$columns): Index
    {
        return new static($name, false, false, ...$columns);
    }

    /**
     * @param string $name
     * @param bool $primary
     * @param bool $unique
     * @param string ...$columns
     */
    private function __construct(
        string $name,
        bool $primary,
        bool $unique = false,
        string ...$columns
    ) {

        if (!SchemasRegister::validateColumnName($name)) {
            throw new \Exception("'{$name}' is not a valid index name.");
        }

        foreach ($columns as $column) {
            if (!SchemasRegister::validateColumnName($column)) {
                throw new \Exception("'{$column}' is not a valid column name in key {$name}.");
            }
        }

        $this->name = $name;
        $this->primary = $primary;
        $this->unique = $unique;
        $this->columns = $columns ?: [$name];
    }

    /**
     * @param string $column
     * @param int $size
     * @return Index
     */
    public function limitColSize(string $column, int $size): Index
    {
        if ($size < 1) {
            throw new \Exception("Invalid key size '{$size}'.");
        }

        if (!in_array($column, $this->columns, true)) {
            throw new \Exception("Unknown column '{$column}'.");
        }

        $this->limits[$column] = $size;

        return $this;
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return bool
     */
    public function isPrimary(): bool
    {
        return $this->primary;
    }

    /**
     * @param Columns $columns
     * @return string
     */
    public function schemaSql(Columns $columns): string
    {
        if ($this->primary) {
            $length = $this->limits[$this->name] ?? null;
            $lengthStr = $length ? "($length)" : '';
            // 2 spaces before parenthesis because of `dbDelta`.
            return "PRIMARY KEY  (`{$this->name}`{$lengthStr})";
        }

        $prefix = $this->unique ? 'UNIQUE ' : '';
        $sql = "{$prefix}KEY `{$this->name}` (";

        foreach ($this->columns as $column) {
            if (!$columns->hasColumn($column)) {
                throw new \Exception("'{$column}' is not a valid column name.");
            }
            $length = $this->limits[$column] ?? null;
            $lengthStr = $length ? sprintf('(%d)', $length) : '';
            $sql .= "`{$column}`{$lengthStr},";
        }

        return rtrim($sql, ',') . ')';
    }
}
