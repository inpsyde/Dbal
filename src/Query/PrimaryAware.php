<?php

declare(strict_types=1);

namespace Syde\Dbal\Query;

use Syde\Dbal\Result;
use Syde\Dbal\Schema\Schema;
use Syde\Dbal\Schema\SchemaFinder;

trait PrimaryAware
{
    /**
     * @param Schema|null $schema
     * @param SchemaFinder $finder
     * @return Result
     */
    private function findPrimary(?Schema $schema, SchemaFinder $finder): Result
    {
        if (!$schema) {
            return Result::new(new \Error('Can not find a primary column on a null schema'));
        }

        $indexes = $schema->indexes();
        $primary = $indexes ? $indexes->primary() : null;
        if (!$primary) {
            $name = $finder->fullTableName($schema);

            return Result::new(new \Error("Table {$name} doesn't have a primary column."));
        }

        return Result::new($primary);
    }
}
