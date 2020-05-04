<?php

namespace Inpsyde\Dbal\Schema;

interface Schema
{
    public function isNetworkWide(): bool;

    public function name(): string;

    public function columns(): Columns;

    public function indexes(): ?Indexes;
}
