<?php

declare(strict_types=1);

namespace Syde\Dbal\Tests;

use Syde\Dbal\Dbal;
use Syde\Dbal\Schema\SchemasRegister;

class QueriesTestCase extends IntegrationTestCase
{
    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        add_action(
            Dbal::ACTION_REGISTER_SCHEMA,
            static function (SchemasRegister $register) {
                $register->registerForInstall(
                    new TableOne(),
                    new TableTwo(),
                    new TablePivot()
                );
            }
        );

        Dbal::initialize();
    }
}
