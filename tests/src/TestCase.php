<?php

declare(strict_types=1);

namespace Syde\Dbal\Tests;

use Syde\Dbal\Dbal;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * @return void
     */
    protected function resetDbal(): void
    {
        /** @psalm-suppress PossiblyNullFunctionCall */
        \Closure::bind(
            static function (): void {
                /**  @psalm-suppress InaccessibleProperty */
                Dbal::$objects = null;
            },
            null,
            Dbal::class
        )();
    }
}
