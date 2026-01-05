<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Schema;

/** @template-implements \IteratorAggregate<int, Schema>  */
class Schemas implements \Countable, \IteratorAggregate
{
    /**  @var list<Schema> */
    private array $schemas;
    private int $count = 0;

    /**
     * @param string $name
     * @return bool
     */
    public static function validateIdentifierName(string $name): bool
    {
        if (($name === '') || (rtrim($name) !== $name)) {
            return false;
        }

        return preg_match("~^[\u{0001}-\u{007F}\u{0080}-\u{FFFF}]+$~u", $name) === 1;
    }

    /**
     * @param string $name
     * @return bool
     * @deprecated
     */
    public static function validateSchemaName(string $name): bool
    {
        return static::validateIdentifierName($name);
    }

    /**
     * @param Schema ...$schemas
     *
     * @return Schemas
     */
    public static function new(Schema ...$schemas): Schemas
    {
        return new self(...$schemas);
    }

    /**
     * @param Schema ...$schemas
     */
    private function __construct(Schema ...$schemas)
    {
        $done = [];
        $this->schemas = [];

        foreach ($schemas as $schema) {
            $name = $schema->name();
            if (isset($done[$name])) {
                continue;
            }

            $done[$name] = 1;

            $this->count++;
            $this->schemas[] = $schema;
        }
    }

    /**
     * @return \Traversable<int, Schema>
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->schemas);
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return $this->count;
    }
}
