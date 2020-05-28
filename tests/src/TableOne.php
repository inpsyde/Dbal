<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Tests;

use Inpsyde\Dbal\Schema\Column;
use Inpsyde\Dbal\Schema\Columns;
use Inpsyde\Dbal\Schema\InstallableSchema;
use Inpsyde\Dbal\Schema\Index;
use Inpsyde\Dbal\Schema\Indexes;

class TableOne implements InstallableSchema
{
    // phpcs:ignore Inpsyde.CodeQuality.ForbiddenPublicProperty.Found
    public static $version = '1.0.0';

    public const NAME = 'tests_sample_table';
    public const NETWORK_WIDE = false;

    public const ID = 'id';
    public const POST_ID = 'post_id';
    public const TEXT = 'text';
    public const SERIALIZED = 'serialized';
    public const INTEGER = 'integer';
    public const DOUBLE = 'double';
    public const DATETIME = 'datetime';
    public const ENUM = 'enum';

    /**
     * @return string
     */
    public static function version(): string
    {
        return self::$version;
    }

    /**
     * @return bool
     */
    public static function isNetworkWide(): bool
    {
        return self::NETWORK_WIDE;
    }

    /**
     * @return string
     */
    public static function name(): string
    {
        return self::NAME;
    }

    /**
     * @return Columns
     */
    public function columns(): Columns
    {
        $columns = [
            Column::entityId(self::ID),
            Column::bigInt(self::POST_ID)->makeNotNull()->makeUnsigned(),
            Column::text(self::TEXT)->makeNotNull(),
            Column::text(self::SERIALIZED)->storeSerialized(),
            Column::smallInt(self::INTEGER, 1)->makeNotNull()->makeUnsigned(),
            Column::double(self::DOUBLE, 11, 8, 0.0)->makeNotNull(),
            Column::datetime(self::DATETIME)
                ->useCurrentTimestampAsDefault()
                ->retrieveAsDateTime(new \DateTimeZone('UTC')),
        ];

        if (self::$version === '1.0.0') {
            $columns[] = Column::enum(self::ENUM, 'yes', 'no');
        }

        return Columns::new(...$columns);
    }

    /**
     * @return Indexes|null
     */
    public function indexes(): ?Indexes
    {
        return Indexes::new(
            Index::primary(self::ID),
            Index::key(self::POST_ID),
            Index::key('multi', self::TEXT, self::DATETIME)->limitColSize(self::TEXT, 8)
        );
    }

    /**
     * @param \wpdb $wpdb
     * @param string $fullTableName
     * @return void
     */
    public function onInstall(\wpdb $wpdb, string $fullTableName): void
    {
        do_action(self::NAME . '.installed', $fullTableName);
    }

    /**
     * @param \wpdb $wpdb
     * @param string $fullTableName
     * @param string $previousVer
     * @return void
     */
    public function onUpdate(\wpdb $wpdb, string $fullTableName, string $previousVer): void
    {
        do_action(self::NAME . '.uninstalled', $fullTableName, $previousVer);
    }
}
