<?php

declare(strict_types=1);

namespace Syde\Dbal\Schema;

final class WpSchema implements Schema
{
    private string $name;
    private bool $global;
    private Columns $columns;

    /**
     * @param string $name
     * @param bool $global
     * @param Columns $columns
     * @return WpSchema
     */
    public static function new(string $name, bool $global, Columns $columns): WpSchema
    {
        return new static($name, $global, $columns);
    }

    /**
     * @param string $name
     * @param bool $global
     * @param Columns $columns
     */
    private function __construct(string $name, bool $global, Columns $columns)
    {
        $this->name = $name;
        $this->global = $global;
        $this->columns = $columns;
    }

    /**
     * @return bool
     */
    public function isNetworkWide(): bool
    {
        return $this->global;
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return Columns
     */
    public function columns(): Columns
    {
        return $this->columns;
    }

    /**
     * @return Indexes|null
     */
    public function indexes(): ?Indexes
    {
        return null;
    }
}
