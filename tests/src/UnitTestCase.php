<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Tests;

use Brain\Monkey;
use Inpsyde\Dbal\Schema\SchemaFinder;
use Inpsyde\Dbal\Schema\SchemasRegister;
use Inpsyde\Dbal\Schema\WpSchemas;

class UnitTestCase extends TestCase
{
    /**
     * @return void
     */
    protected function setUp(): void
    {
        require_once ABSPATH . 'wp-includes/wp-db.php';

        parent::setUp();
        Monkey\setUp();
        global $wpdb;
        $wpdb = new DummyWpdb();

        Monkey\Functions\stubEscapeFunctions();
        Monkey\Functions\when('wp_timezone')->justReturn(new \DateTimeZone('Europe/Rome'));
        Monkey\Functions\when('esc_sql')->alias([$wpdb, '_real_escape']);

        Monkey\Functions\when('maybe_serialize')->alias(
            /**
             * @param mixed $value
             *
             * @return mixed
             *
             * @psalm-suppress MissingClosureParamType
             * phpcs:disable Syde.Functions.ReturnTypeDeclaration.NoReturnType
             */
            static function (mixed $value) {
                return is_scalar($value) ? $value : serialize($value);
            }
        );

        Monkey\Functions\when('is_serialized')->alias(
            /**
             * @param mixed $value
             * @return bool
             *
             * @psalm-suppress MissingClosureParamType
             */
            static function (mixed $value): bool {
                if (!is_string($value) || $value === "b:0;") {
                    return $value === 'b:0;';
                }

                return @unserialize($value) !== false;
            }
        );

        Monkey\Functions\when('maybe_unserialize')->alias(
            /**
             * @param mixed $value
             * @return mixed
             *
             * @psalm-suppress MissingClosureParamType
             * phpcs:disable Syde.Functions.ReturnTypeDeclaration.NoReturnType
             */
            static function (mixed $value) {
                return (is_string($value) && is_serialized($value)) ? unserialize($value) : $value;
            }
        );
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
        $this->resetDbal();
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * @param array ...$rows
     * @return void
     */
    protected function infixNextWpdbRowsResult(array ...$rows): void
    {
        global $wpdb;
        /** @var DummyWpdb $wpdb */

        $wpdb->nextResultRows = $rows;
    }

    /**
     * @param mixed $result
     * @return void
     */
    protected function infixNextWpdbQueryResult(mixed $result): void
    {
        global $wpdb;
        /** @var DummyWpdb $wpdb */

        $wpdb->nextQueryResult = $result;
    }

    /**
     * @param int $num
     * @param mixed $result
     * @return void
     */
    protected function infixNumWpdbQueryResult(int $num, mixed $result): void
    {
        global $wpdb;
        /** @var DummyWpdb $wpdb */

        $wpdb->nextNumQueryResult = [$num, $result];
    }

    /**
     * @param string $result
     * @return void
     */
    protected function infixNextWpdbGetVarResult(string $result): void
    {
        global $wpdb;
        /** @var DummyWpdb $wpdb */

        $wpdb->nextGetVarResult = $result;
    }

    /**
     * @return SchemaFinder
     */
    protected function initializeSampleTablesFinder(): SchemaFinder
    {
        $schemas = SchemasRegister::new();
        $schemas->registerForInstall(new TableOne(), new TableTwo(), new TablePivot());

        return SchemaFinder::new(WpSchemas::new(), $schemas);
    }

    /**
     * @param string $expectedRaw
     * @param string $actualRaw
     * @return void
     */
    protected function assertSameQuery(string $expectedRaw, string $actualRaw): void
    {
        $expected = trim(preg_replace('/\s+/', ' ', $expectedRaw));
        $actual = trim(preg_replace('/\s+/', ' ', $actualRaw));

        static::assertSame($expected, $actual);
    }
}
