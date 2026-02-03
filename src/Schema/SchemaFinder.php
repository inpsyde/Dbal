<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Schema;

use Inpsyde\Dbal\Dbal;

/**
 * @psalm-consistent-constructor
 */
class SchemaFinder
{
    private WpSchemas $wpSchema;
    private SchemasRegister $register;

    /**
     * @param WpSchemas $wpSchema
     * @param SchemasRegister $schemas
     * @return static
     */
    public static function new(WpSchemas $wpSchema, SchemasRegister $schemas): SchemaFinder
    {
        return new static($wpSchema, $schemas);
    }

    /**
     * @param WpSchemas $wpSchema
     * @param SchemasRegister $schemas
     */
    protected function __construct(WpSchemas $wpSchema, SchemasRegister $schemas)
    {
        $this->wpSchema = $wpSchema;
        $this->register = $schemas;
    }

    /**
     * @param Schema $schema
     * @return string
     */
    public function fullTableName(Schema $schema): string
    {
        $prefix = $schema->isNetworkWide() ? Dbal::wpdb()->base_prefix : Dbal::wpdb()->prefix;

        return $prefix . $schema->name();
    }

    /**
     * @param string $schemaName
     * @return string
     */
    public function wpdbTableName(string $schemaName): ?string
    {
        $schema = $this->findSchema($schemaName);

        return $schema ? $this->fullTableName($schema) : null;
    }

    /**
     * @param string $tableName
     * @return Schema|null
     */
    public function findSchema(string $tableName): ?Schema
    {
        $core = $this->findCoreSchema($tableName);
        if ($core !== null) {
            return $core;
        }

        $noPrefixName = $this->stripPrefix($tableName);
        if (($noPrefixName === '') || ($noPrefixName === null)) {
            return null;
        }

        return $this->register->find($noPrefixName);
    }

    /**
     * @param string $tableName
     * @return WpSchema|null
     */
    public function findCoreSchema(string $tableName): ?WpSchema
    {
        $wpdb = Dbal::wpdb();

        $noPrefixName = $this->stripPrefix($tableName) ?? '';
        if ($noPrefixName === '') {
            return null;
        }

        $fullTableName = $wpdb->{$noPrefixName} ?? null;

        $columns = (($fullTableName !== '') && is_string($fullTableName))
            ? $this->wpSchema->loadTableColumns($fullTableName)
            : null;

        if (!$columns) {
            return null;
        }

        $global = in_array($noPrefixName, $wpdb->global_tables, true)
            || in_array($noPrefixName, $wpdb->ms_global_tables, true);

        return WpSchema::new($noPrefixName, $global, $columns);
    }

    /**
     * @param string $tableName
     * @return string|null
     */
    public function stripPrefix(string $tableName): ?string
    {
        $wpdb = Dbal::wpdb();

        $regex = "~^{$wpdb->base_prefix}(?<nobase>(?:(?<siteid>[0-9]+)_)?(?<nopref>.+))$~";
        /** @var array{siteid?: string, nopref?: string} $matches */
        $matches = [];

        if (!preg_match($regex, $tableName, $matches)) {
            return $tableName;
        }

        $noBase = $matches['nobase'] ?? null;

        if (($matches['siteid'] ?? '') === '') {
            return $noBase;
        }

        /** @phpstan-ignore nullCoalesce.offset */
        $site = (int) ($matches['siteid'] ?? 0);
        $validId = $site === (int) $wpdb->siteid;
        $noPrefix = $matches['nopref'] ?? null;

        if (!$noPrefix) {
            return null;
        }

        if (!$validId && is_multisite() && ($site > 1)) {
            $validId = get_site($site) !== null;
        }

        return $validId ? $noPrefix : $noBase;
    }
}
