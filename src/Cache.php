<?php

declare(strict_types=1);

namespace Inpsyde\Dbal;

use Inpsyde\Dbal\Schema\SchemaFinder;

class Cache
{
    private const GROUP = 'dbal';
    private const TABLES_GROUP = 'dbal_t';

    /**
     * @var array<string, mixed>
     */
    private static $fallback = [];

    /**
     * @var bool
     */
    private $initialized = false;

    /**
     * @var SchemaFinder
     */
    private $finder;

    /**
     * @return Cache
     */
    public static function new(SchemaFinder $finder): Cache
    {
        return new static($finder);
    }

    /**
     * @param SchemaFinder $finder
     */
    private function __construct(SchemaFinder $finder)
    {
        $this->finder = $finder;
    }

    /**
     * @return void
     */
    public function initialize(): void
    {
        if (!$this->initialized) {
            wp_cache_add_global_groups([self::GROUP, self::TABLES_GROUP]);
            $this->initialized = true;
        }
    }

    /**
     * @param string $key
     * @return mixed
     *
     * @psalm-suppress MissingReturnType
     * phpcs:disable Inpsyde.CodeQuality.ReturnTypeDeclaration
     */
    public function get(string $key)
    {
        // phpcs:enable Inpsyde.CodeQuality.ReturnTypeDeclaration

        if (!$key) {
            return null;
        }

        if (!empty(self::$fallback[$key])) {
            return self::$fallback[$key];
        }

        return wp_cache_get($key, self::GROUP) ?: null;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return void
     *
     * @psalm-suppress MissingParamType
     * phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration
     */
    public function set(string $key, $value): void
    {
        // phpcs:enable Inpsyde.CodeQuality.ArgumentTypeDeclaration

        /*
         * When using external object cache, do not store values bigger than 1Mb, and use a static
         * array as cache instead.
         */
        if (wp_using_ext_object_cache() && (strlen((string)maybe_serialize($value))) > 1024000) {
            self::$fallback[$key] = $value;

            return;
        }

        wp_cache_set($key, $value, self::GROUP);
    }

    /**
     * @param string $key
     * @return void
     */
    public function delete(string $key): void
    {
        unset(self::$fallback[$key]);
        wp_cache_delete($key, self::GROUP);
    }

    /**
     * Given same tables names (no matter the order), will return same value, until
     * Cache::cleanCacheForTables() is called next time.
     *
     * When a value is stored in cache, we can use this function to build a cache key, based on
     * all the tables used to retrieve the value.
     * In this way, when any of the tables changes (and Cache::cleanCacheForTables() is called)
     * all the cached value belonging, even partially, to that table are then invalidated.
     *
     * @param string $table
     * @param string ...$tables
     * @return string
     */
    public function buildCacheKeyForTables(string $table, string ...$tables): string
    {
        array_unshift($tables, $table);

        $values = [];
        foreach ($tables as $table) {
            if (isset($values[$table])) {
                continue;
            }
            $schema = $this->finder->findSchema($table);
            if (!$schema) {
                continue;
            }

            $key = $this->finder->fullTableName($schema);
            $lastTableUpdate = wp_cache_get($key, self::TABLES_GROUP);
            if (!$lastTableUpdate) {
                $lastTableUpdate = microtime();
                wp_cache_set($key, $lastTableUpdate, self::TABLES_GROUP);
            }

            $values[$table] = (string)$lastTableUpdate;
        }

        ksort($values);

        return md5(implode('|', $values));
    }

    /**
     * @param string $table
     * @param string ...$tables
     * @return void
     */
    public function cleanCacheForTables(string $table, string ...$tables): void
    {
        array_unshift($tables, $table);

        foreach ($tables as $table) {
            $schema = $this->finder->findSchema($table);
            if (!$schema) {
                continue;
            }

            wp_cache_delete($this->finder->fullTableName($schema), self::TABLES_GROUP);
        }
    }
}
