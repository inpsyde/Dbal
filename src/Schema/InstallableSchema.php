<?php

namespace Inpsyde\Dbal\Schema;

interface InstallableSchema extends Schema
{
    public function version(): string;

    public function onInstall(\wpdb $wpdb, string $fullTableName): void;

    public function onUpdate(\wpdb $wpdb, string $fullTableName, string $previousVer): void;
}
