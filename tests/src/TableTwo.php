<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Tests;

use Inpsyde\Dbal\Schema\Column;
use Inpsyde\Dbal\Schema\Columns;
use Inpsyde\Dbal\Schema\InstallableSchema;
use Inpsyde\Dbal\Schema\Index;
use Inpsyde\Dbal\Schema\Indexes;

class TableTwo implements InstallableSchema
{
    public const NAME = 'tests_sample_table_two';
    public const ID = 'id';
    public const VARCHAR = 'varchar';
    public const DECIMAL = 'decimal';

    /**
     * @return string
     */
    public function version(): string
    {
        return '1.0.0';
    }

    /**
     * @return bool
     */
    public function isNetworkWide(): bool
    {
        return false;
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return self::NAME;
    }

    /**
     * @return Columns
     */
    public function columns(): Columns
    {
        return Columns::new(
            Column::entityId(self::ID),
            Column::varChar(self::VARCHAR, 16, 'foo')->makeNotNull(),
            Column::decimal(self::DECIMAL)->makeNotNull()
        );
    }

    /**
     * @return Indexes|null
     */
    public function indexes(): ?Indexes
    {
        return Indexes::new(Index::primary(self::ID));
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
