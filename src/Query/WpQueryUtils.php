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
        $postsRequest = '';
        $query = new \WP_Query();
        $filter = static function ($null, \WP_Query $currentQuery) use (&$postsRequest, &$query) {
            if ($currentQuery === $query) {
                $postsRequest = $query->request;

                return [];
            }

            return $null;
        };

        add_filter('posts_pre_query', $filter, PHP_INT_MAX, 2);
        $query->query($args);
        remove_filter('posts_pre_query', $filter, PHP_INT_MAX);

        return $postsRequest;
    }
}
