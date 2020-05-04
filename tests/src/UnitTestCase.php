<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Tests;

use Inpsyde\Dbal\Schema\SchemaFinder;
use Inpsyde\Dbal\Schema\SchemasRegister;
use Inpsyde\Dbal\Schema\WpSchemas;
use Brain\Monkey;
use PHPUnit\Framework\TestCase;

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

        Monkey\Functions\when('wp_timezone')->justReturn(new \DateTimeZone('Europe/Rome'));
        Monkey\Functions\when('esc_sql')->alias([$wpdb, '_real_escape']);

        Monkey\Functions\when('maybe_serialize')->alias(
            static function ($value) { // phpcs:ignore
                return is_scalar($value) ? $value : serialize($value);
            }
        );

        Monkey\Functions\when('is_serialized')->alias(
            static function ($value) { // phpcs:ignore
                if (!is_string($value) || $value === "b:0;") {
                    return $value === "b:0;";
                }

                return @unserialize($value) !== false;
            }
        );

        Monkey\Functions\when('maybe_unserialize')->alias(
            static function ($value) { // phpcs:ignore
                return is_serialized($value) ? unserialize($value) : $value;
            }
        );
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
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
     * @param string $result
     * @return void
     *
     * phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration
     */
    protected function infixNextWpdbQueryResult($result): void
    {
        // phpcs:enable Inpsyde.CodeQuality.ArgumentTypeDeclaration

        global $wpdb;
        /** @var DummyWpdb $wpdb */

        $wpdb->nextQueryResult = $result;
    }

    /**
     * @param string $result
     * @return void
     *
     * phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration
     */
    protected function infixNumWpdbQueryResult(int $num, $result): void
    {
        // phpcs:enable Inpsyde.CodeQuality.ArgumentTypeDeclaration

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
        $schemas->registerForInstall(new TableOne());
        $schemas->registerForInstall(new TableTwo());
        $schemas->registerForInstall(new TablePivot());

        return SchemaFinder::new(WpSchemas::new(), $schemas);
    }
}
