<?php

declare(strict_types=1);

namespace Inpsyde\Dbal;

class Dbal
{
    public const ACTION_REGISTER_SCHEMA = 'dbal.register-schema';
    public const ACTION_READY = 'dbal.ready';

    /** @var array<string, object>|null */
    protected static ?array $objects = null;

    /**
     * @return bool
     */
    public static function isReady(): bool
    {
        return did_action(self::ACTION_READY) && !doing_action(self::ACTION_READY);
    }

    /**
     * @return array<string, object>
     *
     * @psalm-assert array<string, object> static::$objects
     */
    public static function initialize(): array
    {
        if (static::$objects !== null) {
            return static::$objects;
        }

        $schemas = Schema\SchemasRegister::new();
        $wpSchema = Schema\WpSchemas::new();
        $schemaFinder = Schema\SchemaFinder::new($wpSchema, $schemas);
        $cache = Cache::new($schemaFinder);

        $cache->initialize();

        self::$objects = [
            Schema\SchemasRegister::class => $schemas,
            Schema\WpSchemas::class => $wpSchema,
            Schema\SchemaFinder::class => $schemaFinder,
            Cache::class => $cache,
        ];

        $initializer = static function () use ($schemas, $schemaFinder, &$initializer): void {

            remove_action('setup_theme', $initializer, 0);

            do_action(self::ACTION_REGISTER_SCHEMA, $schemas, $schemaFinder);

            $installer = Schema\TableInstaller::new($schemaFinder);
            $schemas->install($installer);
            $schemas->uninstall($installer);

            do_action(self::ACTION_READY);
        };

        (did_action('setup_theme') > 0)
            ? $initializer()
            : add_action('setup_theme', $initializer, 0);

        return self::$objects;
    }

    /**
     * @return \wpdb
     */
    public static function wpdb(): \wpdb
    {
        global $wpdb;

        /** @var \wpdb $wpdb */

        return $wpdb;
    }

    private function __construct()
    {
    }

    /**
     * @return Cache
     */
    public static function cache(): Cache
    {
        /** @var Cache $cache */
        $cache = static::initialize()[Cache::class];

        return $cache;
    }

    /**
     * @return Schema\SchemasRegister
     */
    public static function schemas(): Schema\SchemasRegister
    {
        /** @var Schema\SchemasRegister $schemas */
        $schemas = static::initialize()[Schema\SchemasRegister::class];

        return $schemas;
    }

    /**
     * @return Schema\WpSchemas
     */
    public static function wpSchema(): Schema\WpSchemas
    {
        /** @var Schema\WpSchemas $wpSchema */
        $wpSchema = static::initialize()[Schema\WpSchemas::class];

        return $wpSchema;
    }

    /**
     * @return Schema\SchemaFinder
     */
    public static function schemaFinder(): Schema\SchemaFinder
    {
        /** @var Schema\SchemaFinder $finder */
        $finder = static::initialize()[Schema\SchemaFinder::class];

        return $finder;
    }

    /**
     * @param string $tableName
     * @param string|null $alias
     * @return Query\Select
     */
    public static function select(string $tableName, ?string $alias = null): Query\Select
    {
        self::initialize();

        return Query\Select::from($tableName, $alias, static::schemaFinder(), static::cache());
    }

    /**
     * @param string $tableName
     * @return Query\Write
     */
    public static function writeOn(string $tableName): Query\Write
    {
        self::initialize();

        return Query\Write::on($tableName, static::schemaFinder(), static::cache());
    }

    /**
     * @param string $tableName
     * @return Query\Delete
     */
    public static function deleteFrom(string $tableName): Query\Delete
    {
        self::initialize();

        return Query\Delete::from($tableName, static::schemaFinder(), static::cache());
    }

    /**
     * @param int $flags
     * @return Transaction
     */
    public static function transaction(int $flags = Transaction::DEFAULT): Transaction
    {
        static::initialize();

        return Transaction::new($flags);
    }
}
