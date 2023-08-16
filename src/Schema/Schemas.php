<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Schema;

class Schemas implements \Countable, \IteratorAggregate
{
    /**
     * @var Schema[]
     */
    private $schemas;

    /**
     * @var int
     */
    private $count = 0;

    /**
     * @param string $name
     * @return bool
     */
    public static function validateSchemaName(string $name): bool
    {
        return (trim($name) === $name) && (esc_sql($name) === $name);
    }

    /**
     * @param Schema $schema
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
            if (!empty($done[$name])) {
                continue;
            }

            $done[$name] = 1;

            $this->count++;
            $this->schemas[] = $schema;
        }
    }

    /**
     * @return \Traversable<Schema>
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
