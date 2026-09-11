<?php

declare(strict_types=1);

namespace Syde\Dbal\Schema;

interface InstallableSchema extends Schema
{
    public function version(): string;

    public function onInstall(\wpdb $wpdb, string $fullTableName): void;

    public function onUpdate(\wpdb $wpdb, string $fullTableName, string $previousVer): void;
}
