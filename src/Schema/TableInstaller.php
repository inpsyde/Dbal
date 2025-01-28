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

    private const OPTION_VERSIONS = 'dbal_table_versions';
    private const OPTION_VERSIONS_NETWORK = 'dbal_table_versions_net';
    private const UPDATE_PHP_PATH = 'wp-admin/includes/upgrade.php';

    private const CACHE_KEY = 'dbal_tables_installer';

    /** @var array<string, bool>|null  */
    private static $proceedTables = null;

    /**
     * @var array<string,array>
     */
    private $versions = [];

    /**
     * @var SchemaFinder
     */
    private $schemaFinder;

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
         * @var array<string, array> $versions
         * @var string $newVer
         * @var string|null $savedVer
         */
        [$done, $fullName, $exists, $versions, $newVer, $savedVer] = $this->installStatus($schema);
        if ($done || !$fullName) {
            return $done;
        }

        $wpdb = Dbal::wpdb();
        $baseName = $schema->name();
        $versions[$baseName] = $newVer;
        $columns = $schema->columns();
        $keys = $schema->indexes();

        [$beforeHook, $afterHook] = $exists
            ? [self::ACTION_UPDATE, self::ACTION_UPDATED]
            : [self::ACTION_INSTALL, self::ACTION_INSTALLED];

        $hookArgs = $exists ? [$savedVer] : [];
        do_action($beforeHook, $schema, $wpdb, ...$hookArgs);

        $columnsSql = $columns->schemaSql();
        $keysSql = $keys ? $keys->schemaSql($columns) : '';
        $dbCharsetCollate = $wpdb->get_charset_collate();
        $charsetCollate = $dbCharsetCollate ? " {$dbCharsetCollate}" : '';

        if ($exists) {
            $oldColumns = (array)($wpdb->get_results("SHOW COLUMNS FROM `{$fullName}`") ?: []);
            $colsToDelete = array_diff(array_column($oldColumns, 'Field'), $columns->allNames());
            foreach ($colsToDelete as $colToDelete) {
                $wpdb->query("ALTER TABLE `{$fullName}` DROP COLUMN `{$colToDelete}`");
            }
        }

        dbDelta("CREATE TABLE `{$fullName}` ({$columnsSql}{$keysSql}){$charsetCollate}");

        if (!$exists && !$this->tableExists($wpdb, $fullName)) {
            return false;
        }

        $this->persistVersions($versions, $schema->isNetworkWide());

        $exists
            ? $schema->onUpdate($wpdb, $fullName, (string)$savedVer)
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
    private function tableExists(\wpdb $wpdb, string $tableName): bool
    {
        if (is_null(static::$proceedTables )) {
            /** @var array<string, bool>|false $value */
            $value = wp_cache_get(self::CACHE_KEY, 'dbal');
            static::$proceedTables = is_array($value) ? $value : [];
        }

        if (array_key_exists($tableName, static::$proceedTables)) {
            return (bool) static::$proceedTables[$tableName];
        }

        $result = (bool)$wpdb->query(
            (string)($wpdb->prepare('SHOW TABLES LIKE %s', $tableName) ?? '')
        );

        if (!$result) {
            return false;
        }

        self::$proceedTables[$tableName] = true;

        wp_cache_set(self::CACHE_KEY, static::$proceedTables, 'dbal', 300);

        return static::$proceedTables[$tableName];
    }

    /**
     * @param InstallableSchema $schema
     * @return array
     */
    private function installStatus(InstallableSchema $schema): array
    {
        $newVer = $this->prepareInstall($schema);
        if (!$newVer) {
            return [false, '', false, null, null, null];
        }

        $network = $schema->isNetworkWide();
        $baseName = $schema->name();
        $fullName = $this->schemaFinder->fullTableName($schema);

        $versions = $this->loadVersions($network);
        $savedVer = is_string($versions[$baseName] ?? null)
            ? $this->validateVersion($versions[$baseName])
            : null;

        $exists = $this->tableExists(Dbal::wpdb(), $fullName);
        if ($exists && ($savedVer === null)) {
            $savedVer = '0.0.0.0';
        }

        if (!$savedVer) {
            return [false, $fullName, $exists, $versions, $newVer, null];
        }

        if (version_compare($savedVer, $newVer, '>')) {
            throw new \Exception(
                "Can't downgrade table {$fullName} to version {$newVer} from version {$savedVer}."
            );
        }

        return [$savedVer === $newVer, $fullName, $exists, $versions, $newVer, $savedVer];
    }

    /**
     * @param InstallableSchema $schema
     * @return string|null
     */
    private function prepareInstall(InstallableSchema $schema): ?string
    {
        if (!function_exists('dbDelta')) {
            require_once ABSPATH . self::UPDATE_PHP_PATH;
        }

        return $this->validateVersion($schema->version());
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
        if (!$versions || !is_array($versions)) {
            $versions and delete_option($option);
            $versions = [];
        }

        /** @var array<string, string> $versions */
        $this->versions[$option] = $versions;

        return $versions;
    }

    /**
     * @param array $versions
     * @param bool $network
     * @return void
     */
    private function persistVersions(array $versions, bool $network)
    {
        $option = $network ? self::OPTION_VERSIONS_NETWORK : self::OPTION_VERSIONS;
        $this->versions[$option] = $versions;

        $network
            ? update_site_option($option, $versions)
            : update_option($option, $versions, false);

        $this->versions[$option] = $versions;
    }

    /**
     * @param string $version
     * @return string|null
     */
    private function validateVersion(string $version): ?string
    {
        $version = trim($version);
        if ($version === '') {
            return null;
        }

        // This is to address a bug where versions starting with "0" where saved
        // e.g. as "02.0" instead of "0.2.0".
        if (preg_match('~^0[0-9](?:\.[0-9]+|$)~', $version)) {
            $version = '0.' . (string)substr($version, 1);
        }

        $validated = [];
        $numbers = explode('.', $version);
        foreach ($numbers as $number) {
            if (!is_numeric($number)) {
                return null;
            }

            $validated[] = abs((int)$number);
        }

        return implode('.', $validated);
    }
}
