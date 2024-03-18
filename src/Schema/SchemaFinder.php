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
    private function __construct(WpSchemas $wpSchema, SchemasRegister $schemas)
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

        if (!preg_match($regex, $tableName, $matches)) {
            return $tableName;
        }

        if (($matches['siteid'] ?? '') === '') {
            return $matches['nobase'] ?? null;
        }

        $site = (int) ($matches['siteid'] ?? 0);
        $validId = $site === (int) $wpdb->siteid;
        $noPrefix = $matches['nopref'] ?? '';
        if (!$noPrefix) {
            return null;
        }

        if (!$validId && is_multisite() && ($site > 1)) {
            /** @psalm-suppress InvalidGlobal */
            global $_wp_switched_stack;
            $ids = is_array($_wp_switched_stack) ? $_wp_switched_stack : [];

            $validId = $ids && in_array($site, array_map('intval', $ids), true);
        }

        return $validId ? ($matches['nopref'] ?? null) : $matches['nobase'] ?? null;
    }
}
