<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Schema;

use Inpsyde\Dbal\Dbal;

class TableInstaller
{
    public const ACTION_INSTALL = 'dbal.table-install';
    public const ACTION_UPDATE = 'dbal.table-update';
    public const ACTION_INSTALLED = 'dbal.table-installed';
    public const ACTION_UPDATED = 'dbal.table-updated';
    public const FILTER_SKIP_TABLE_EXISTENCE_CHECK = 'dbal.skip-table-exists-check';

    private const OPTION_VERSIONS = 'dbal_table_versions';
    private const OPTION_VERSIONS_NETWORK = 'dbal_table_versions_net';

    /**
     * @var string[]
     */
    protected static array $dbTables = [];
    /** @var array<string, string> */
    private array $versions = [];
    private SchemaFinder $schemaFinder;

    /**
     * @param SchemaFinder $schemaFinder
     * @return TableInstaller
     */
    public static function new(SchemaFinder $schemaFinder): TableInstaller
    {
        return new self($schemaFinder);
    }

    /**
     * @param SchemaFinder $schemaFinder
     */
    private function __construct(SchemaFinder $schemaFinder)
    {
        $this->schemaFinder = $schemaFinder;
    }

    /**
     * @param InstallableSchema $schema
     * @return bool
     */
    public function installSchema(InstallableSchema $schema): bool
    {
        /**
         * @var bool $done
         * @var string $fullName
         * @var bool $exists
         * @var array<string, string> $versions
         * @var Version $newVer
         * @var Version $savedVer
         */
        [$done, $fullName, $exists, $versions, $newVer, $savedVer] = $this->installStatus($schema);
        if ($done || ($fullName === '')) {
            return $done;
        }

        $wpdb = Dbal::wpdb();
        $baseName = $schema->name();
        $versions[$baseName] = $newVer->value();
        $columns = $schema->columns();
        $keys = $schema->indexes();

        [$beforeHook, $afterHook] = $exists
            ? [self::ACTION_UPDATE, self::ACTION_UPDATED]
            : [self::ACTION_INSTALL, self::ACTION_INSTALLED];

        $hookArgs = $exists ? [$savedVer, $newVer] : [$newVer];
        do_action($beforeHook, $schema, $wpdb, ...$hookArgs);

        $columnsSql = $columns->schemaSql();
        $keysSql = $keys ? $keys->schemaSql($columns) : '';
        $dbCharsetCollate = $wpdb->get_charset_collate();
        $charsetCollate = $dbCharsetCollate ? " {$dbCharsetCollate}" : '';

        if ($exists) {
            $this->dropColumns($wpdb, $fullName, $columns);
        }
        dbDelta("CREATE TABLE `{$fullName}` ({$columnsSql}{$keysSql}){$charsetCollate}");

        if (!$exists && !$this->postInstallCheck($wpdb, $fullName)) {
            return false;
        }

        $this->persistVersions($versions, $schema->isNetworkWide());

        $exists
            ? $schema->onUpdate($wpdb, $fullName, $savedVer->value() ?? '')
            : $schema->onInstall($wpdb, $fullName);

        do_action($afterHook, $schema, $wpdb, ...$hookArgs);

        return true;
    }

    /**
     * @param InstallableSchema $table
     * @return bool
     */
    public function uninstallSchema(InstallableSchema $table): bool
    {
        $wpdb = Dbal::wpdb();

        $network = $table->isNetworkWide();
        $baseName = $table->name();
        $name = $this->schemaFinder->fullTableName($table);

        $versions = $this->loadVersions($network);
        unset($versions[$baseName]);

        $deleted = true;
        if ($this->tableExists($wpdb, $name)) {
            $deleted = $wpdb->query("DROP TABLE {$name}") !== false;
        }

        $this->persistVersions($versions, $network);

        return $deleted;
    }

    /**
     * @param \wpdb $wpdb
     * @param string $tableName
     * @return bool
     */
    private function postInstallCheck(\wpdb $wpdb, string $tableName): bool
    {

        $result = (bool) $wpdb->query(
            (string) ($wpdb->prepare('SHOW TABLES LIKE %s', $tableName) ?? '')
        );

        if (!in_array($tableName, static::$dbTables, true) && $result) {
            static::$dbTables[] = $tableName;
        }

        return $result;
    }

    /**
     * @param \wpdb $wpdb
     * @param string $tableName
     * @param string|null $currentVersion
     * @return bool
     */
    private function tableExists(\wpdb $wpdb, string $tableName, ?string $currentVersion = null): bool
    {
        if (apply_filters(self::FILTER_SKIP_TABLE_EXISTENCE_CHECK, false, $tableName, $currentVersion)) {
            return true;
        }

        if (count(static::$dbTables) < 1) {
            /** @var string[] $results */
            $results = $wpdb->get_col('SHOW TABLES');
            static::$dbTables = $results;
        }

        return in_array($tableName, static::$dbTables, true);
    }

    /**
     * @param InstallableSchema $schema
     * @return array{bool, string, bool, array<string, string>, Version, Version}
     */
    private function installStatus(InstallableSchema $schema): array
    {
        $newVer = $this->prepareInstall($schema);
        if (!$newVer->isValid()) {
            return [false, '', false, [], Version::newEmpty(), Version::newEmpty()];
        }

        $network = $schema->isNetworkWide();
        $baseName = $schema->name();
        $fullName = $this->schemaFinder->fullTableName($schema);

        $versions = $this->loadVersions($network);
        $savedVer = is_string($versions[$baseName] ?? null)
            ? Version::new($versions[$baseName])
            : Version::newEmpty();

        $exists = $this->tableExists(Dbal::wpdb(), $fullName, $savedVer->value());
        if ($exists && !$savedVer->isValid()) {
            $savedVer = Version::new('0.0.0.0');
        }

        if (!$savedVer->isValid()) {
            return [false, $fullName, $exists, $versions, $newVer, Version::newEmpty()];
        }

        return [$savedVer->equals($newVer), $fullName, $exists, $versions, $newVer, $savedVer];
    }

    /**
     * @param InstallableSchema $schema
     * @return Version
     */
    private function prepareInstall(InstallableSchema $schema): Version
    {
        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        return Version::new($schema->version());
    }

    /**
     * @param bool $network
     * @return array<string, string>
     */
    private function loadVersions(bool $network): array
    {
        $option = $network ? self::OPTION_VERSIONS_NETWORK : self::OPTION_VERSIONS;

        /** @var array<string, string>|null $versions */
        $versions = $this->versions[$option] ?? null;

        if (is_array($versions)) {
            return $versions;
        }

        $versions = $network ? get_site_option($option) : get_option($option);
        if (($versions === []) || !is_array($versions)) {
            if ($versions !== []) {
                delete_option($option);
            }
            $versions = [];
        }

        /** @var array<string, string> $versions */
        $this->versions[$option] = $versions;

        return $versions;
    }

    /**
     * @param array<string, string> $versions
     * @param bool $network
     * @return void
     */
    private function persistVersions(array $versions, bool $network): void
    {
        $option = $network ? self::OPTION_VERSIONS_NETWORK : self::OPTION_VERSIONS;
        $this->versions[$option] = $versions;

        $network
            ? update_site_option($option, $versions)
            : update_option($option, $versions, false);

        $this->versions[$option] = $versions;
    }

    /**
     * @param \wpdb $wpdb
     * @param string $schemaName
     * @param Columns $columns
     * @return void
     */
    private function dropColumns(\wpdb $wpdb, string $schemaName, Columns $columns): void
    {
        $currentColumns = $wpdb->get_results(
            (string) $wpdb->prepare(
                "SHOW COLUMNS FROM %i",
                $schemaName
            )
        );

        if (($currentColumns === []) || !is_array($currentColumns)) {
            return;
        }

        $dropColsSql = '';
        $colsToDelete = [];

        $targetNames = $columns->allNames();
        foreach ($currentColumns as $currentColumn) {
            $currentColName = is_object($currentColumn) ? ($currentColumn->Field ?? null) : null;
            if (($currentColName !== null) && !in_array($currentColName, $targetNames, true)) {
                if ($dropColsSql !== '') {
                    $dropColsSql .= ', ';
                }
                $dropColsSql .= 'DROP COLUMN %i';
                $colsToDelete[] = $currentColName;
            }
        }

        if ($colsToDelete !== []) {
            $sql = "ALTER TABLE %i {$dropColsSql};";
            $wpdb->query((string) $wpdb->prepare($sql, $schemaName, ...$colsToDelete));
        }
    }
}
