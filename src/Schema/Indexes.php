<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Schema;

final class Indexes
{
    /**
     * @var array<int,Index>
     */
    private $keys;

    /**
     * @var Index|null
     */
    private $primary;

    /**
     * @param Index $key
     * @param Index ...$keys
     * @return Indexes
     */
    public static function new(Index $key, Index ...$keys): Indexes
    {
        return new static($key, ...$keys);
    }

    /**
     * @param Index $key
     * @param Index ...$keys
     */
    private function __construct(Index $key, Index ...$keys)
    {
        array_unshift($keys, $key);

        foreach ($keys as $key) {
            if (!$key->isPrimary()) {
                continue;
            }

            if ($this->primary) {
                throw new \Exception("A table can only have a single primary key.");
            }

            $this->primary = $key;
        }

        $this->keys = $keys;
    }

    /**
     * @return Index|null
     */
    public function primary(): ?Index
    {
        return $this->primary;
    }

    /**
     * @param Columns $columns
     * @return string|null
     */
    public function schemaSql(Columns $columns): ?string
    {
        if (!$this->keys) {
            return null;
        }

        $keys = '';
        foreach ($this->keys as $key) {
            $keys .= ",\n\t" . $key->schemaSql($columns);
        }

        return "{$keys}\n";
    }
}
