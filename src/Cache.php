<?php

declare(strict_types=1);

namespace Inpsyde\Dbal;

use Inpsyde\Dbal\Schema\SchemaFinder;

class Cache
{
    private const GROUP = 'dbal';
    private const TABLES_GROUP = 'dbal_t';
    private const OVERALL_KEY = '_dbal_cache';

    /**
     * @var array<string, mixed>
     */
    private static $fallback = [];

    /**
     * @var array<string, string>
     */
    private static $tableKeys = [];

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
            $this->addCleanCacheHooks();
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
     * @param string $tableName
     * @param string ...$tables
     * @return string
     */
    public function buildCacheKeyForTables(string $tableName, string ...$tables): string
    {
        $tableNames = [$tableName];
        if ($tables) {
            array_unshift($tables, $tableName);
            sort($tables, SORT_STRING);
            $tableNames = $tables;
        }

        [
            $netPrefix,
            $sitePrefix,
        ] = $this->siteCachePrefixes();

        [
            $tableNamesKey,
            $tableNamesKeySiteWide,
        ] = $this->siteCachePrefixesForTables(...$tableNames);

        $tableNamesCached = static::$tableKeys[$tableNamesKey] ?? '';
        $tableNamesKeySiteWideCached = static::$tableKeys[$tableNamesKeySiteWide] ?? '';
        if ($tableNamesCached || $tableNamesKeySiteWideCached) {
            return $tableNamesCached ?: $tableNamesKeySiteWideCached;
        }

        $keys = '';
        $done = [];
        $allNetwork = true;
        foreach ($tableNames as $tableName) {
            if (!$tableName || isset($done[$tableName])) {
                continue;
            }

            $done[$tableName] = 1;
            $schema = $this->finder->findSchema($tableName);
            if (!$schema) {
                continue;
            }

            $schema->isNetworkWide() or $allNetwork = false;

            $key = $this->finder->fullTableName($schema);
            $lastTableUpdate = wp_cache_get($key, self::TABLES_GROUP);
            if (!$lastTableUpdate) {
                $lastTableUpdate = microtime();
                wp_cache_set($key, $lastTableUpdate, self::TABLES_GROUP);
            }

            $keys .= (string)$lastTableUpdate;
        }

        if (!$keys) {
            return (string)microtime();
        }

        $key = $allNetwork ? md5($netPrefix . $keys) : md5($sitePrefix . $keys);
        $cacheKey = $allNetwork ? $tableNamesKeySiteWide : $tableNamesKey;
        static::$tableKeys[$cacheKey] = $key;

        return $key;
    }

    /**
     * @param string $tableName
     * @param string ...$tableNames
     * @return void
     */
    public function cleanCacheForTables(string $tableName, string ...$tableNames): void
    {
        array_unshift($tableNames, $tableName);

        $this->cleanTableKeysForTables(...$tableNames);

        foreach ($tableNames as $tableName) {
            $schema = $tableName ? $this->finder->findSchema($tableName) : '';
            if (!$schema) {
                continue;
            }

            wp_cache_delete($this->finder->fullTableName($schema), self::TABLES_GROUP);
        }
    }

    /**
     * @return void
     */
    public function cleanCacheForSite(): void
    {
        wp_cache_delete($this->siteKey(), self::GROUP);
    }

    /**
     * @return void
     */
    public function cleanCacheForNetwork(): void
    {
        wp_cache_delete($this->networkKey(), self::GROUP);
    }

    /**
     * @param string ...$tableNames
     */
    private function cleanTableKeysForTables(string ...$tableNames): void
    {
        [
            $tableNamesKey,
            $tableNamesKeySiteWide,
        ] = $this->siteCachePrefixesForTables(...$tableNames);

        if (count($tableNames) > 1) {
            unset(
                static::$tableKeys[$tableNamesKey],
                static::$tableKeys[$tableNamesKeySiteWide]
            );
            return;
        }

        $tableName = (string)array_shift($tableNames);
        foreach (array_keys(static::$tableKeys) as $key) {
            if (strpos($key, $tableName) !== false) {
                unset(static::$tableKeys[$key]);
            }
        }
    }

    /**
     * @param string ...$tableNames
     *
     * @return array<int, string>
     */
    private function siteCachePrefixesForTables(string ...$tableNames): array
    {
        [$netPrefix, $sitePrefix] = $this->siteCachePrefixes();
        $tableNamesKey = $sitePrefix . implode('', $tableNames);
        $tableNamesKeySiteWide = $netPrefix . implode('', $tableNames);

        return [
            $tableNamesKey,
            $tableNamesKeySiteWide,
        ];
    }

    /**
     * @return array{string, string}
     */
    private function siteCachePrefixes(): array
    {
        $overallNetKey = $this->networkKey();
        $overallNet = (string)(wp_cache_get($overallNetKey, self::GROUP) ?: '');
        if (!$overallNet) {
            $overallNet = (string)microtime();
            wp_cache_set($overallNetKey, $overallNet, self::GROUP);
        }

        $overallSiteKey = $this->siteKey();
        $overallSite = (string)(wp_cache_get($overallSiteKey, self::GROUP) ? : '');
        if (!$overallSite) {
            $overallSite = (string)microtime();
            wp_cache_set($overallSiteKey, $overallSite, self::GROUP);
        }

        return [$overallNet, $overallSite];
    }

    /**
     * @return string
     */
    private function networkKey(): string
    {
        return sprintf('%s_%s', self::OVERALL_KEY, (string)get_current_network_id());
    }

    /**
     * @return string
     */
    private function siteKey(): string
    {
        return sprintf('%s_%s', $this->networkKey(), (string)get_current_blog_id());
    }

    /**
     * @return void
     *
     * phpcs:disable Inpsyde.CodeQuality.FunctionLength
     *
     * @psalm-suppress DocblockTypeContradiction
     */
    private function addCleanCacheHooks()
    {
        // phpcs:enable Inpsyde.CodeQuality.FunctionLength

        $wpdb = Dbal::wpdb();

        $cleanPosts = function () use ($wpdb): void {
            $this->cleanCacheForTables($wpdb->posts ?? '', $wpdb->postmeta ?? '');
        };

        $cleanTaxonomy = function () use ($wpdb): void {
            $this->cleanCacheForTables(
                $wpdb->terms ?? '',
                $wpdb->term_taxonomy ?? '',
                $wpdb->termmeta ?? ''
            );
        };

        $cleanUsers = function () use ($wpdb): void {
            $this->cleanCacheForTables(
                $wpdb->users ?? '',
                $wpdb->signups ?? '',
                $wpdb->registration_log ?? ''
            );
        };

        $cleanComments = function () use ($wpdb): void {
            $this->cleanCacheForTables($wpdb->comments ?? '', $wpdb->commentmeta ?? '');
        };

        $cleanBlogs = function () use ($wpdb): void {
            $this->cleanCacheForTables($wpdb->blogs ?? '', $wpdb->blogmeta ?? '');
        };

        $cleanSites = function () use ($wpdb): void {
            $this->cleanCacheForTables(
                $wpdb->site ?? '',
                $wpdb->sitemeta ?? '',
                $wpdb->sitecategories ?? ''
            );
        };

        $cleanOptions = function () use ($wpdb): void {
            $this->cleanCacheForTables($wpdb->options ?? '');
        };

        $cleanMeta = function (string $type) use ($wpdb): callable {
            return function () use ($type, $wpdb): void {
                $table = "{$type}meta";
                $this->cleanCacheForTables((string)$wpdb->{$table});
            };
        };

        add_action('clean_page_cache', $cleanPosts);
        add_action('clean_post_cache', $cleanPosts);
        add_action('clean_attachment_cache', $cleanPosts);
        add_action('clean_term_cache', $cleanTaxonomy);
        add_action('clean_taxonomy_cache', $cleanTaxonomy);
        add_action('clean_user_cache', $cleanUsers);
        add_action('clean_comment_cache', $cleanComments);
        add_action('clean_object_term_cache', $cleanTaxonomy);
        add_action('clean_object_term_cache', $cleanPosts);
        add_action('clean_site_cache', $cleanBlogs);
        add_action('clean_site_cache', $cleanUsers);
        add_action('clean_network_cache', $cleanBlogs);
        add_action('clean_network_cache', $cleanUsers);
        add_action('clean_network_cache', $cleanSites);
        add_action('added_option', $cleanOptions);
        add_action('updated_option', $cleanOptions);
        add_action('deleted_option', $cleanOptions);
        add_action('update_post_metadata_cache', $cleanMeta('post'));
        add_action('update_term_metadata_cache', $cleanMeta('term'));
        add_action('update_user_metadata_cache', $cleanMeta('user'));
        add_action('update_blog_metadata_cache', $cleanMeta('blog'));
        add_action('update_site_metadata_cache', $cleanMeta('site'));
        add_action('update_comment_metadata_cache', $cleanMeta('comment'));
    }
}
