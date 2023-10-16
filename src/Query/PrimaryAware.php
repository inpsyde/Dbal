<?php

/**
 * This file is part of the "Dbal" package.
 *
 * Copyright (C) 2023 Inpsyde GmbH
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

declare(strict_types=1);

namespace Inpsyde\Dbal\Query;

use Inpsyde\Dbal\Result;
use Inpsyde\Dbal\Schema\Index;
use Inpsyde\Dbal\Schema\Schema;
use Inpsyde\Dbal\Schema\SchemaFinder;

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
