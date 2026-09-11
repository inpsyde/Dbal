<?php

declare(strict_types=1);

namespace Syde\Dbal\Schema;

interface Schema
{
    public function isNetworkWide(): bool;

    public function name(): string;

    public function columns(): Columns;

    public function indexes(): ?Indexes;
}
