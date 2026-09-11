<?php

declare(strict_types=1);

namespace Syde\Dbal\Tests;

use Syde\Dbal\Schema\Column;
use Syde\Dbal\Schema\Columns;
use Syde\Dbal\Schema\Index;
use Syde\Dbal\Schema\Indexes;
use Syde\Dbal\Schema\InstallableSchema;

class TablePivot implements InstallableSchema
{
    public const NAME = 'tests_sample_table_pivot';
    public const ID = 'id';
    public const ONE = 'one_id';
    public const TWO = 'two_id';

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
            Column::bigInt(self::ONE)->makeNotNull()->makeUnsigned(),
            Column::bigInt(self::TWO)->makeNotNull()->makeUnsigned()
        );
    }

    /**
     * @return Indexes|null
     */
    public function indexes(): ?Indexes
    {
        return Indexes::new(
            Index::primary(self::ID),
            Index::unique('one_two', self::ONE, self::TWO)
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
