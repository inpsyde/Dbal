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

use Inpsyde\Dbal\Dbal;
use Inpsyde\Dbal\PhpErrors;
use Inpsyde\Dbal\Result;
use Inpsyde\Dbal\Schema\Index;
use Inpsyde\Dbal\Schema\Schema;
use Inpsyde\Dbal\Schema\SchemaFinder;

class Delete extends BaseSelect
{
    use PrimaryAware;

    public const DELETE = 'delete';
    public const FILTER_QUERY_PART = 'dbal.delete-query-part';

    /**
     * @param string $tableName
     * @param SchemaFinder|null $finder
     * @return Delete
     */
    public static function from(string $tableName, ?SchemaFinder $finder = null): Delete
    {
        return new self($tableName, $finder);
    }

    /**
     * @param mixed $primaryValue
     * @param string|null $operator
     * @return Result
     */
    public function delOnPrimary($primaryValue, ?string $operator = null): Result
    {
        if (!$this->errors->isEmpty()) {
            return Result::new($this->errors);
        }

        return $this->findPrimary($this->schema, $this->finder)
            ->bind(
                function (Index $index) use ($primaryValue, $operator): Result {
                    if (($operator === null) && is_array($primaryValue)) {
                        $operator = Where::IN;
                    }

                    $instance = $this->where
                        ? $this->andWhere($index->name(), $primaryValue, $operator)
                        : $this->where($index->name(), $primaryValue, $operator);

                    return $instance->exec();
                }
            );
    }

    /**
     * @return Result
     */
    public function exec(): Result
    {
        if (!$this->where) {
            $this->pushError('Can not execute a DELETE query without a WHERE clause');
        }

        if (!$this->errors->isEmpty()) {
            return Result::new($this->errors);
        }

        $wpdb = Dbal::wpdb();

        $phpErrors = PhpErrors::convertToExceptions();
        $suppressErrors = $wpdb->suppress_errors(true);

        try {
            $sql = $this->buildSql();
            if (!$sql) {
                $this->errors->withError('Could not build SQL for DELETE query');

                return Result::new($this->errors);
            }

            $rows = $wpdb->query($sql);
            if (is_numeric($rows)) {
                return Result::new((int)$rows);
            }

            $this->errors->withError('Failed deleting rows');

            return Result::new($this->errors);
        } catch (\Throwable $throwable) {
            return Result::new($throwable);
        } finally {
            $phpErrors->restoreHandler();
            $wpdb->suppress_errors($suppressErrors);
        }
    }

    /**
     * @return array<string, string>|null
     */
    protected function buildQueryParts(): ?array
    {
        $base = $this->buildBaseQueryParts();
        if ($base === null) {
            return null;
        }

        $delete = 'DELETE';

        $allJoined = [];
        foreach ($this->joins as $joined) {
            $joinedSchema = $joined->targetSchema();
            if (!$joinedSchema) {
                return null;
            }
            $allJoined[] = $this->finder->fullTableName($joinedSchema);
        }
        if ($allJoined) {
            /** @var Schema $mainSchema */
            $mainSchema = $this->schema;
            array_unshift($allJoined, $this->finder->fullTableName($mainSchema));
            $delete .= sprintf(' `%s`', implode('`, `', $allJoined));
        }

        $parts = array_merge([self::DELETE => $delete], $base);

        return $this->filterQueryParts($parts, self::FILTER_QUERY_PART);
    }

    /**
     * @param string $type
     * @param string|null $targetTable
     * @param string|null $sourceTable
     * @param string|null $columnOnSource
     * @param string|null $columnOnJoined
     * @param string|null $alias
     * @param Where|null $where
     * @param string|null $raw
     * @return static
     */
    protected function withJoin(
        string $type,
        ?string $targetTable,
        ?string $sourceTable,
        ?string $columnOnSource = null,
        ?string $columnOnJoined = null,
        ?string $alias = null,
        ?Where $where = null,
        ?string $raw = null
    ): BaseSelect {

        if ($alias) {
            $this->pushError('Aliases are not allowed in DELETE queries');

            return $this;
        }

        return parent::withJoin(
            $type,
            $targetTable,
            $sourceTable,
            $columnOnSource,
            $columnOnJoined,
            null,
            $where,
            $raw
        );
    }
}
