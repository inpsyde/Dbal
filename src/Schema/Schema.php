<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Schema;

interface Schema
{
    public function isNetworkWide(): bool;

    public function name(): string;

    public function columns(): Columns;

    public function indexes(): ?Indexes;
}
