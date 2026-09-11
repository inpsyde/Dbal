<?php

declare(strict_types=1);

namespace Syde\Dbal\Tests\Unit\Query;

use Brain\Monkey;
use Syde\Dbal\Dbal;
use Syde\Dbal\Query\SelectBuilder;
use Syde\Dbal\Tests\TableOne;
use Syde\Dbal\Tests\TablePivot;
use Syde\Dbal\Tests\TableTwo;
use Syde\Dbal\Tests\UnitTestCase;

class SelectBuilderTest extends UnitTestCase
{
    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\Functions\when('wp_cache_add_global_groups')->justReturn();
    }

    /**
     * @test
     */
    public function testBuild(): void
    {
        $select = SelectBuilder::new(TableOne::NAME, 'one')
            ->joinViaPivot(TableTwo::NAME, TablePivot::NAME, TablePivot::ONE, TablePivot::TWO)
            ->cols(TableOne::INTEGER, TableOne::TEXT, TableTwo::NAME . '.' . TableTwo::VARCHAR)
            ->where(TableOne::ENUM, 'yes')
            ->andWhere(TableTwo::NAME . '.' . TableTwo::DECIMAL, 10.123, '>=');

        static::assertInstanceOf(SelectBuilder::class, $select);
        static::assertFalse(Dbal::isReady());

        Monkey\Actions\expectDone(Dbal::ACTION_READY)
            ->whenHappen(static function (): void {
                $schemas = Dbal::schemas();
                $schemas->registerForInstall(new TableOne(), new TableTwo(), new TablePivot());
            });

        $expected = <<<QUERY
SELECT `one`.`integer`, `one`.`text`, `wp_1_tests_sample_table_two`.`varchar`
FROM `wp_1_tests_sample_table` AS `one`
INNER JOIN `wp_1_tests_sample_table_pivot`
    ON `one`.`id` = `wp_1_tests_sample_table_pivot`.`one_id`
INNER JOIN `wp_1_tests_sample_table_two`
    ON `wp_1_tests_sample_table_pivot`.`two_id` = `wp_1_tests_sample_table_two`.`id`
WHERE `one`.`enum` = 'yes'
    AND `wp_1_tests_sample_table_two`.`decimal` >= '10.123'
QUERY;

        do_action('setup_theme');
        $this->assertSameQuery($expected, $select->build()->extract()->buildSqlNoEscape());
    }

    /**
     * @test
     */
    public function testBuildFailsIfSetupThemeNotOccurred(): void
    {
        $select = SelectBuilder::new(TableOne::NAME, 'one')
            ->where(TableOne::ENUM, 'yes');

        Monkey\Actions\expectDone(Dbal::ACTION_READY)
            ->zeroOrMoreTimes()
            ->whenHappen(static function (): void {
                Dbal::schemas()->registerForInstall(new TableOne());
            });

        $error = $select->build()->error()->getMessage();

        static::assertSame(
            1,
            preg_match('~SelectBuilder::build.+? before DBAL is ready~i', $error)
        );
    }
}
