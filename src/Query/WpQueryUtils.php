<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Query;

class WpQueryUtils
{
    /**
     * @param array $args
     * @return string
     */
    public static function buildSqlForArgs(array $args): string
    {
        $sql = '';
        $query = new \WP_Query();

        /**
         * @wp-hook posts_pre_query
         *
         * @psalm-suppress MissingClosureParamType
         * @psalm-suppress MissingClosureReturnType
         */
        $filter = static function ($null, $currentQuery) use (&$sql, $query) {
            if ($currentQuery === $query) {
                $sql = $query->request;

                return [];
            }

            return $null;
        };

        add_filter('posts_pre_query', $filter, PHP_INT_MAX, 2);
        $query->query($args);
        remove_filter('posts_pre_query', $filter, PHP_INT_MAX);

        return (string)$sql;
    }
}
