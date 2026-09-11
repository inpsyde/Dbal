<?php

declare(strict_types=1);

namespace Syde\Dbal\Tests;

class IntegrationTestCase extends TestCase
{
    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->resetDbal();
        parent::tearDown();
    }
}
