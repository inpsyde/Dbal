<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Tests;

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
        WpInstallExtension::resetDb();
        parent::tearDown();
    }
}
