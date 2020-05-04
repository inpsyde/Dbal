<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Schema;

class SchemasRegister
{
    private const INSTALL = 1;
    private const UNINSTALL = 2;

    /**
     * @var array<string,array{0:InstallableSchema, 1:int}>
     */
    private $schemas = [];

    /**
     * @var array<int, int>
     */
    private $counts = [self::INSTALL => 0, self::UNINSTALL => 0];

    /**
     * @param string $name
     * @return bool
     */
    public static function validateColumnName(string $name): bool
    {
        return $name && (preg_match('~[^a-z0-9_]+~i', $name) === 0);
    }

    /**
     * @return SchemasRegister
     */
    public static function new(): SchemasRegister
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
     * @param InstallableSchema $schema
     * @return SchemasRegister
     */
    public function registerForInstall(InstallableSchema $schema): SchemasRegister
    {
        $name = $schema->name();

        if (!$name) {
            throw new \Exception("Schema name can't be empty.");
        }

        if (!empty($this->schemas[$name])) {
            throw new \Exception("Scheme with name {$name} already registered.");
        }

        if (!Schemas::validateSchemaName($name)) {
            throw new \Exception("'{$name}' is not a valid schema name.");
        }

        $this->schemas[$name] = [$schema, self::INSTALL];
        $this->counts[self::INSTALL]++;

        return $this;
    }

    /**
     * @param InstallableSchema $schema
     * @return SchemasRegister
     */
    public function registerForUninstall(InstallableSchema $schema): SchemasRegister
    {
        $name = $schema->name();
        if (!empty($this->schemas[$name])) {
            throw new \Exception("Scheme with name {$name} already registered.");
        }

        $this->schemas[$name] = [$schema, self::UNINSTALL];
        $this->counts[self::UNINSTALL]++;

        return $this;
    }

    /**
     * @param string $name
     * @return Schema|null
     */
    public function find(string $name): ?Schema
    {
        if (!empty($this->schemas[$name]) && ($this->schemas[$name][1] === self::INSTALL)) {
            return $this->schemas[$name][0];
        }

        return null;
    }

    /**
     * @param TableInstaller $installer
     * @return bool
     */
    public function install(TableInstaller $installer): bool
    {
        return $this->installOrUninstall($installer, self::INSTALL);
    }

    /**
     * @param TableInstaller $installer
     * @return bool
     */
    public function uninstall(TableInstaller $installer): bool
    {
        return $this->installOrUninstall($installer, self::UNINSTALL);
    }

    /**
     * @param TableInstaller $installer
     * @param int $targetOperation
     * @return bool
     */
    public function installOrUninstall(TableInstaller $installer, int $targetOperation): bool
    {
        if (empty($this->counts[$targetOperation])) {
            return true;
        }

        $all = 0;
        $ok = 0;

        $newSchemas = [];
        $isInstall = $targetOperation === self::INSTALL;
        $currentSchemas = $this->schemas;

        foreach ($currentSchemas as $name => [$schema, $operation]) {
            if ($operation !== $targetOperation) {
                continue;
            }

            $all++;
            $success = $isInstall
                ? $installer->installSchema($schema)
                : $installer->uninstallSchema($schema);

            $success and $ok++;
            if ($isInstall) {
                $success and $newSchemas[$name] = [$schema, $operation];
                continue;
            }

            unset($this->schemas[$name]);
        }

        $isInstall and $this->schemas = $newSchemas;

        return $ok === $all;
    }
}
