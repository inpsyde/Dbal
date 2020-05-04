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
        return new static($schemaFinder);
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

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ($exists) {
            $oldColumns = (array)($wpdb->get_results("SHOW COLUMNS FROM `{$fullName}`") ?: []);
            $colsToDelete = array_diff(array_column($oldColumns, 'Field'), $columns->allNames());
            foreach ($colsToDelete as $colToDelete) {
                $wpdb->query("ALTER TABLE `{$fullName}` DROP COLUMN `{$colToDelete}`");
            }
        }
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

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
        /** @psalm-suppress MixedArgument */
        if ($this->tableExists($wpdb, $name)) {
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $deleted = $wpdb->query("DROP TABLE {$name}") !== false;
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }

        $this->persistVersions($versions, $network);

        return $deleted;
    }

    /**
     * @param \wpdb $wpdb
     * @param string $tableName
     * @return bool
     *
     * @psalm-suppress PossiblyNullArgument
     */
    private function tableExists(\wpdb $wpdb, string $tableName): bool
    {
        return (bool)$wpdb->query($wpdb->prepare('SHOW TABLES LIKE %s', $tableName));
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

        /** @psalm-suppress MixedArgument */
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
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
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
     * @return array
     */
    private function persistVersions(array $versions, bool $network): array
    {
        $option = $network ? self::OPTION_VERSIONS_NETWORK : self::OPTION_VERSIONS;
        $this->versions[$option] = $versions;

        $network
            ? update_site_option($option, $versions)
            : update_option($option, $versions, false);

        $this->versions[$option] = $versions;

        return $versions;
    }

    /**
     * @param string $version
     * @return string|null
     */
    private function validateVersion(string $version): ?string
    {
        if (!$version) {
            return null;
        }

        $validated = '';
        $numbers = explode('.', $version);
        foreach ($numbers as $number) {
            if (!is_numeric($number)) {
                return null;
            }

            $validated and $validated .= '.';
            $validated .= (string)abs((int)$number);
        }

        return $validated ?: null;
    }
}
