<?php

declare(strict_types=1);

namespace Syde\Dbal\Query;

class WpQueryUtils
{
    /**
     * @param array<string, mixed> $args
     * @return string
     */
    public static function buildSqlForArgs(array $args): string
    {
        $sql = '';
        $query = new \WP_Query();

        /**
         * @wp-hook posts_pre_query
         */
        $filter = static function ($null, \WP_Query $currentQuery) use (&$sql, $query) {
            if ($currentQuery === $query) {
                $sql = (string) $query->request;

                return [];
            }

            return $null;
        };

        // phpcs:disable Syde.WordPress.HookPriority
        add_filter('posts_pre_query', $filter, PHP_INT_MAX, 2);
        $query->query($args);
        remove_filter('posts_pre_query', $filter, PHP_INT_MAX);
        // phpcs:enable Syde.WordPress.HookPriority

        return $sql;
    }
}
