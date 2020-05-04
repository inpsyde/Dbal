<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Tests;

use Inpsyde\Dbal\Dbal;
use PHPUnit\Framework\TestCase;

class IntegrationTestCase extends TestCase
{
    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        require_once ABSPATH . 'wp-config.php';
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->resetDbal();
        parent::tearDown();
    }

    /**
     * @return void
     */
    protected function resetDbal(): void
    {
        \Closure::bind(
            static function (): void {
                /** @noinspection PhpUndefinedFieldInspection */
                static::$objects = null;
            },
            null,
            Dbal::class
        )();
    }

    /**
     * @return void
     */
    protected function resetDb(): void
    {
        $this->resetDbal();
        WpInstallExtension::resetDb();
    }
}
