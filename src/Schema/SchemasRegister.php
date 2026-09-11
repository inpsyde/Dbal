<?php

declare(strict_types=1);

namespace Syde\Dbal\Schema;

/** @psalm-consistent-constructor */
class SchemasRegister
{
    private const INSTALL = 1;
    private const UNINSTALL = 2;

    /** @var array<string, list{InstallableSchema, int}> */
    private array $schemas = [];

    /** @var array<1|2, int<0,max>> */
    private array $counts = [self::INSTALL => 0, self::UNINSTALL => 0];

    /**
     * @param string $name
     * @return bool
     * @deprecated
     */
    public static function validateColumnName(string $name): bool
    {
        return Schemas::validateIdentifierName($name);
    }

    /**
     * @return static
     */
    public static function new(): SchemasRegister
    {
        return new static();
    }

    /**
     */
    protected function __construct()
    {
    }

    /**
     * @param InstallableSchema $schema
     * @param InstallableSchema ...$schemas
     * @return static
     */
    public function registerForInstall(
        InstallableSchema $schema,
        InstallableSchema ...$schemas
    ): SchemasRegister {

        array_unshift($schemas, $schema);

        foreach ($schemas as $schema) {
            $this->registerFor($schema, self::INSTALL);
        }

        return $this;
    }

    /**
     * @param InstallableSchema $schema
     * @param InstallableSchema ...$schemas
     * @return static
     */
    public function registerForUninstall(
        InstallableSchema $schema,
        InstallableSchema ...$schemas
    ): SchemasRegister {

        array_unshift($schemas, $schema);

        foreach ($schemas as $schema) {
            $this->registerFor($schema, self::UNINSTALL);
        }

        return $this;
    }

    /**
     * Register schema for a given action
     *
     * @param InstallableSchema $schema
     * @param 1|2 $for
     *
     * @throws \Exception
     */
    private function registerFor(InstallableSchema $schema, int $for): void
    {
        $name = $schema->name();

        if ($name === '') {
            throw new \Exception("Schema name can't be empty.");
        }

        if (!Schemas::validateIdentifierName($name)) {
            throw new \Exception(sprintf('"%s" is not a valid schema name.', esc_html($name)));
        }

        if (isset($this->schemas[$name])) {
            throw new \Exception(
                sprintf(
                    'Scheme with name "%s" already registered.',
                    esc_html($name)
                )
            );
        }

        $this->schemas[$name] = [$schema, $for];
        $this->counts[$for]++;
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
        if (($this->counts[$targetOperation] ?? 0) === 0) {
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

            if ($success) {
                $ok++;
            }
            if ($isInstall) {
                if ($success) {
                    $newSchemas[$name] = [$schema, $operation];
                }
                continue;
            }

            unset($this->schemas[$name]);
        }

        if ($isInstall) {
            $this->schemas = $newSchemas;
        }

        return $ok === $all;
    }
}
