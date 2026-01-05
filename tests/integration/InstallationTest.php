<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Tests\Integration;

use Inpsyde\Dbal\Dbal;
use Inpsyde\Dbal\Schema\Column;
use Inpsyde\Dbal\Schema\Schema;
use Inpsyde\Dbal\Schema\SchemasRegister;
use Inpsyde\Dbal\Schema\TableInstaller;
use Inpsyde\Dbal\Tests\IntegrationTestCase;
use Inpsyde\Dbal\Tests\TableOne;
use Inpsyde\Dbal\Tests\TablePivot;
use Inpsyde\Dbal\Tests\TableTwo;

/**
 * @runTestsInSeparateProcesses
 */
class InstallationTest extends IntegrationTestCase
{
    /**
     * @test
     */
    public function testRegisterForInstall(): void
    {
        /** @var SchemasRegister $theRegister */
        $theRegister = null;

        $schemaNames = [];

        add_action(
            TableInstaller::ACTION_INSTALLED,
            static function (Schema $schema) use (&$schemaNames) {
                $schemaNames[] = $schema->name();
            }
        );

        add_action(
            Dbal::ACTION_REGISTER_SCHEMA,
            static function (SchemasRegister $register) use (&$theRegister) {
                $theRegister = $register;
                $theRegister->registerForInstall(
                    new TableOne(),
                    new TableTwo(),
                    new TablePivot()
                );
            }
        );

        Dbal::initialize();

        static::assertSame($theRegister, Dbal::schemas());
        static::assertInstanceOf(TableOne::class, $theRegister->find(TableOne::NAME));
        static::assertInstanceOf(TableTwo::class, $theRegister->find(TableTwo::NAME));
        static::assertInstanceOf(TablePivot::class, $theRegister->find(TablePivot::NAME));

        static::assertContains(TableOne::NAME, $schemaNames);
        static::assertContains(TableTwo::NAME, $schemaNames);
        static::assertContains(TablePivot::NAME, $schemaNames);
    }

    /**
     * @test
     */
    public function testInstallationAndUpdate(): void
    {
        $version = '1.0.0';
        $countInstalled = 0;
        $countUpdated = 0;

        add_action(
            Dbal::ACTION_REGISTER_SCHEMA,
            static function (SchemasRegister $register) use (&$version) {
                $register->registerForInstall(new TableOne($version));
            }
        );

        add_action(
            TableInstaller::ACTION_INSTALLED,
            static function () use (&$countInstalled) {
                $countInstalled++;
            }
        );

        add_action(
            TableInstaller::ACTION_UPDATED,
            static function () use (&$countUpdated) {
                $countUpdated++;
            }
        );

        Dbal::initialize();

        $wpdb = Dbal::wpdb();

        $table = Dbal::schemaFinder()->wpdbTableName(TableOne::NAME);
        $actualColumns = (array) $wpdb->get_results("SHOW COLUMNS FROM {$table}", ARRAY_A);
        /** @var array<string, string> $actualTypes */
        $actualTypes = array_column($actualColumns, 'Type', 'Field');
        /** @var array<string, string> $actualNullable */
        $actualNullable = array_column($actualColumns, 'Null', 'Field');

        $table = Dbal::schemaFinder()->findSchema(TableOne::NAME);
        static::assertNotNull($table);
        $expectedColumns = $table->columns();

        static::assertSame(1, $countInstalled);
        static::assertSame(0, $countUpdated);

        /** @var Column $expectedColumn */
        foreach ($expectedColumns as $expectedColumn) {
            $name = $expectedColumn->name();
            $nullable = $expectedColumn->isNullable() ? 'YES' : 'NO';
            $type = strtolower($expectedColumn->type());

            static::assertArrayHasKey($name, $actualTypes);
            static::assertArrayHasKey($name, $actualNullable);
            static::assertStringStartsWith($type, strtolower($actualTypes[$name]));
            static::assertSame(strtoupper($actualNullable[$name]), $nullable);
        }

        /**
         * When version is different from "1.0.0" TableOne do not have an ENUM column
         * @see TableOne::columns()
         */
        $version = '1.1.0';

        $this->resetDbal();
        Dbal::initialize();

        $table = Dbal::schemaFinder()->wpdbTableName(TableOne::NAME);
        $actualColumns = (array) $wpdb->get_results("SHOW COLUMNS FROM {$table}", ARRAY_A);
        $actualTypes = array_column($actualColumns, 'Type', 'Field');

        static::assertArrayNotHasKey(TableOne::ENUM, $actualTypes);

        static::assertSame(1, $countInstalled);
        static::assertSame(1, $countUpdated);

        // Nothing should be done if version is the same

        $this->resetDbal();
        Dbal::initialize();

        $actualColumnsAgain = $wpdb->get_results("SHOW COLUMNS FROM {$table}", ARRAY_A);

        static::assertSame($actualColumns, $actualColumnsAgain);
        static::assertSame(1, $countUpdated);
        static::assertSame(1, $countInstalled);
    }
}
