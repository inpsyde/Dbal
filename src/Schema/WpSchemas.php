<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Schema;

use Inpsyde\Dbal\Dbal;

/**
 * @psalm-consistent-constructor
 */
final class WpSchemas
{
    /** @var array<string, Columns> */
    private static array $columns = [];

    /** @var array<string, string> $dbSchemas */
    private static array $dbSchemas = [];

    /** @var array<string, string> $dbCharsets */
    private static array $dbCharsets = [];

    /**
     * @return static
     */
    public static function new(): WpSchemas
    {
        return new static();
    }

    /**
     * Empty on purpose.
     */
    private function __construct()
    {
    }

    /**
     * @return Columns|null
     */
    public function postsColumns(): ?Columns
    {
        return $this->loadTableColumns(Dbal::wpdb()->posts);
    }

    /**
     * @return Columns|null
     */
    public function postMetaColumns(): ?Columns
    {
        return $this->loadTableColumns(Dbal::wpdb()->postmeta);
    }

    /**
     * @return Columns|null
     */
    public function termsColumns(): ?Columns
    {
        return $this->loadTableColumns(Dbal::wpdb()->terms);
    }

    /**
     * @return Columns|null
     */
    public function termMetaColumns(): ?Columns
    {
        return $this->loadTableColumns(Dbal::wpdb()->termmeta);
    }

    /**
     * @return Columns|null
     */
    public function commentsColumns(): ?Columns
    {
        return $this->loadTableColumns(Dbal::wpdb()->comments);
    }

    /**
     * @return Columns|null
     */
    public function commentMetaColumns(): ?Columns
    {
        return $this->loadTableColumns(Dbal::wpdb()->commentmeta);
    }

    /**
     * @return Columns|null
     */
    public function termTaxonomyColumns(): ?Columns
    {
        return $this->loadTableColumns(Dbal::wpdb()->term_taxonomy);
    }

    /**
     * @return Columns|null
     */
    public function termRelationshipsColumns(): ?Columns
    {
        return $this->loadTableColumns(Dbal::wpdb()->term_relationships);
    }

    /**
     * @return Columns|null
     */
    public function usersColumns(): ?Columns
    {
        return $this->loadTableColumns(Dbal::wpdb()->users);
    }

    /**
     * @return Columns|null
     */
    public function userMetaColumns(): ?Columns
    {
        return $this->loadTableColumns(Dbal::wpdb()->usermeta);
    }

    /**
     * @return Columns|null
     */
    public function optionsColumns(): ?Columns
    {
        return $this->loadTableColumns(Dbal::wpdb()->options);
    }

    /**
     * @return Columns|null
     */
    public function blogsColumns(): ?Columns
    {
        if (!is_multisite()) {
            return null;
        }

        return $this->loadTableColumns(Dbal::wpdb()->blogs);
    }

    /**
     * @return Columns|null
     */
    public function blogMetaColumns(): ?Columns
    {
        if (!is_multisite()) {
            return null;
        }

        return $this->loadTableColumns(Dbal::wpdb()->blogmeta);
    }

    /**
     * @return Columns|null
     */
    public function registrationLogColumns(): ?Columns
    {
        if (!is_multisite()) {
            return null;
        }

        return $this->loadTableColumns(Dbal::wpdb()->registration_log);
    }

    /**
     * @return Columns|null
     */
    public function siteColumns(): ?Columns
    {
        if (!is_multisite()) {
            return null;
        }

        return $this->loadTableColumns(Dbal::wpdb()->site);
    }

    /**
     * @return Columns|null
     */
    public function siteMetaColumns(): ?Columns
    {
        if (!is_multisite()) {
            return null;
        }

        return $this->loadTableColumns(Dbal::wpdb()->sitemeta);
    }

    /**
     * @return Columns|null
     */
    public function signupsColumns(): ?Columns
    {
        if (!is_multisite()) {
            return null;
        }

        return $this->loadTableColumns(Dbal::wpdb()->signups);
    }

    /**
     * @param string $table
     *
     * @return Columns|null
     *
     * phpcs:disable Syde.CodeQuality.NestingLevel.High
     */
    public function loadTableColumns(string $table): ?Columns
    {
        if (!empty(static::$columns[$table])) {
            return static::$columns[$table];
        }

        $charset = $this->loadCharsetForRegex('~');
        $sql = $this->loadWpSchema();

        preg_match(
            "~CREATE TABLE (?:IF NOT EXISTS )?{$table} ?\((.+?)\)(?: ?{$charset})?;~i",
            $sql,
            $matches
        );

        if (!isset($matches[1])) {
            return null;
        }

        $columns = [];
        $token = strtok($matches[1], ',');
        while ($token !== false) {
            $def = trim($token);
            $token = strtok(',');
            if (preg_match('~^(?:(?:PRIMARY )?KEY|INDEX|FULLTEXT|CONSTRAINT) ~i', $def) !== 1) {
                $column = Column::parseRawDefinition($def);
                if ($column !== null) {
                    $columns[] = $column;
                }
            }
        }

        if ($columns === []) {
            return null;
        }

        static::$columns[$table] = Columns::new(...$columns);

        return static::$columns[$table];
    }

    /**
     * @return string
     */
    private function loadWpSchema(): string
    {
        $key = (string) Dbal::wpdb()->prefix;

        if (!empty(static::$dbSchemas[$key])) {
            return static::$dbSchemas[$key];
        }

        if (!function_exists('wp_get_db_schema')) {
            // phpcs:disable Syde.CodeQuality.VariablesName.SnakeCaseVar

            /**
             * Requiring `schema.php` changes two globals as side effect, so we first back up and
             * then restore those, to make the whole operation transparent.
             */
            global $wp_queries, $charset_collate;
            $backup = [$wp_queries, $charset_collate];
            require_once ABSPATH . 'wp-admin/includes/schema.php';
            [$wp_queries, $charset_collate] = $backup;
            // phpcs:enable Inpsyde.CodeQuality.VariablesName.SnakeCaseVar
        }

        $schema = (string) wp_get_db_schema('all');
        static::$dbSchemas[$key] = preg_replace('~\s+~', ' ', trim($schema));

        return static::$dbSchemas[$key];
    }

    /**
     * @param string $delimiter
     *
     * @return string
     */
    private function loadCharsetForRegex(string $delimiter): string
    {
        $db = Dbal::wpdb();

        $key = $db->prefix . $delimiter;

        if (isset(static::$dbCharsets[$key])) {
            return static::$dbCharsets[$key];
        }

        $collate = preg_quote($db->get_charset_collate(), $delimiter);
        static::$dbCharsets[$key] = $collate;

        return $collate;
    }
}
