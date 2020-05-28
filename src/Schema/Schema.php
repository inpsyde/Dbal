<?php

namespace Inpsyde\Dbal\Schema;

interface Schema
{
    public static function isNetworkWide(): bool;

    public static function name(): string;

    public function columns(): Columns;

    public function indexes(): ?Indexes;
}
