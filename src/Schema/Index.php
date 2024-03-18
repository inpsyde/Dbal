<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Schema;

final class Index
{
    private bool $primary = false;
    private bool $unique = false;
    private string $name = '';

    /** @var list<string>  */
    private array $columns = [];

    /** @var array<string, int> */
    private array $limits = [];

    /**
     * @param string $name
     * @return static
     */
    public static function primary(string $name): Index
    {
        return new static($name, true);
    }

    /**
     * @param string $name
     * @param string ...$columns
     * @return static
     */
    public static function unique(string $name, string ...$columns): Index
    {
        return new static($name, false, true, ...$columns);
    }

    /**
     * @param string $name
     * @param string ...$columns
     * @return static
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

        if (!Schemas::validateIdentifierName($name)) {
            throw new \Exception(
                sprintf(
                    '"%s" is not a valid index name.',
                    esc_html($name)
                )
            );
        }

        foreach ($columns as $column) {
            if (!Schemas::validateIdentifierName($column)) {
                throw new \Exception(
                    sprintf(
                        '"%s" is not a valid column name in key "%s".',
                        esc_html($column),
                        esc_html($name)
                    )
                );
            }
        }

        $this->name = $name;
        $this->primary = $primary;
        $this->unique = $unique;
        $this->columns = ($columns === []) ? [$name] : array_values($columns);
    }

    /**
     * @param string $column
     * @param int $size
     * @return static
     */
    public function limitColSize(string $column, int $size): Index
    {
        if ($size < 1) {
            throw new \Exception(sprintf('Invalid key size "%s".', esc_html((string) $size)));
        }

        if (!in_array($column, $this->columns, true)) {
            throw new \Exception(sprintf('Unknown column "%s".', esc_html($column)));
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
            $lengthStr = is_int($length) ? sprintf('(%d)', $length) : '';
            // 2 spaces before parenthesis because of `dbDelta`.
            return "PRIMARY KEY  (`{$this->name}`{$lengthStr})";
        }

        $prefix = $this->unique ? 'UNIQUE ' : '';
        $sql = "{$prefix}KEY `{$this->name}` (";

        foreach ($this->columns as $column) {
            if (!$columns->hasColumn($column)) {
                throw new \Exception(
                    sprintf(
                        '"%s" is not a valid column name.',
                        esc_html($column)
                    )
                );
            }
            $length = $this->limits[$column] ?? null;
            $lengthStr = is_int($length) ? sprintf('(%d)', $length) : '';
            $sql .= "`{$column}`{$lengthStr},";
        }

        return rtrim($sql, ',') . ')';
    }
}
