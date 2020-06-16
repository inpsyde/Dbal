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
     * @param string $table
     * @param string ...$tables
     * @return string
     */
    public function buildCacheKeyForTables(string $table, string ...$tables): string
    {
        array_unshift($tables, $table);

        $values = [];
        $allNetwork = true;
        foreach ($tables as $table) {
            if (isset($values[$table])) {
                continue;
            }
            $schema = $this->finder->findSchema($table);
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

            $values[$table] = (string)$lastTableUpdate;
        }

        $netId = (string)get_current_network_id();
        $overallNetKey = self::OVERALL_KEY . "_{$netId}";
        $overallNet = wp_cache_get($overallNetKey, self::GROUP);
        if (!$overallNet) {
            $overallNet = microtime();
            wp_cache_set($overallNetKey, $overallNet, self::GROUP);
        }

        $overallSiteKey = sprintf('%s_%s', $overallNetKey, (string)get_current_blog_id());
        $overallSite = $allNetwork ? '' : wp_cache_get($overallSiteKey, self::GROUP);
        if (!$overallSite && !$allNetwork) {
            $overallSite = microtime();
            wp_cache_set($overallSiteKey, $overallSite, self::GROUP);
        }

        ksort($values);

        $overallNet = (string)$overallNet;
        $overallSite = (string)$overallSite;

        return md5($overallNet . $overallSite . implode('|', $values));
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

    /**
     * @return void
     */
    public function flushForSite(): void
    {
        $netId = (string)get_current_network_id();
        $siteId = (string)get_current_network_id();
        $overallSiteKey = sprintf('%s_%s_%s', self::OVERALL_KEY, $netId, $siteId);
        wp_cache_delete($overallSiteKey, self::GROUP);
    }

    /**
     * @return void
     */
    public function flushForNetwork(): void
    {
        $overallNetKey = sprintf('%s_%s', self::OVERALL_KEY, (string)get_current_network_id());
        wp_cache_delete($overallNetKey, self::GROUP);
    }

    /**
     * @return void
     *
     * phpcs:disable Inpsyde.CodeQuality.FunctionLength
     */
    private function addCleanCacheHooks()
    {
        // phpcs:enable Inpsyde.CodeQuality.FunctionLength

        $wpdb = Dbal::wpdb();

        $cleanPosts = function () use ($wpdb): void {
            $this->cleanCacheForTables($wpdb->posts, $wpdb->postmeta);
        };

        $cleanTaxonomy = function () use ($wpdb): void {
            $this->cleanCacheForTables($wpdb->terms, $wpdb->term_taxonomy, $wpdb->termmeta);
        };

        $cleanUsers = function () use ($wpdb): void {
            $this->cleanCacheForTables($wpdb->users, $wpdb->signups, $wpdb->registration_log);
        };

        $cleanComments = function () use ($wpdb): void {
            $this->cleanCacheForTables($wpdb->comments, $wpdb->commentmeta);
        };

        $cleanBlogs = function () use ($wpdb): void {
            $this->cleanCacheForTables($wpdb->blogs, $wpdb->blogmeta);
        };

        $cleanSites = function () use ($wpdb): void {
            $this->cleanCacheForTables($wpdb->site, $wpdb->sitemeta, $wpdb->sitecategories);
        };

        $cleanOptions = function () use ($wpdb): void {
            $this->cleanCacheForTables($wpdb->options);
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
