<?php

declare(strict_types=1);

namespace Syde\Dbal\Tests\Unit\Query;

use Brain\Monkey;
use Syde\Dbal\Dbal;
use Syde\Dbal\Query\Where;
use Syde\Dbal\Query\WriteBuilder;
use Syde\Dbal\Tests\TableOne;
use Syde\Dbal\Tests\UnitTestCase;

class WriteBuilderTest extends UnitTestCase
{
    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\Functions\when('wp_cache_delete')->justReturn();
        Monkey\Functions\when('wp_cache_add_global_groups')->justReturn();
    }

    /**
     * @test
     */
    public function testUpdateWhereAfterBuild(): void
    {
        $builder = WriteBuilder::new(TableOne::NAME);

        static::assertFalse(Dbal::isReady());

        Monkey\Actions\expectDone(Dbal::ACTION_READY)
            ->whenHappen(static function (): void {
                Dbal::schemas()->registerForInstall(new TableOne());
            });

        $expectedQuery = <<<SQL
UPDATE `wp_1_tests_sample_table` SET `text` = 'one', `can_be_null` = '', `integer` = 11
WHERE `wp_1_tests_sample_table`.`id` >= 1
  AND `wp_1_tests_sample_table`.`double` IN ('1.123','2.456','3.789');
SQL;

        do_action('setup_theme');

        $data = [
            'post_id' => 1,
            'double' => 1.123,
            'text' => 'two',
            'integer' => 12,
            'can_be_null' => 'not null',
        ];
        $builder->insert($data)->assert();

        $data = ['text' => 'one', 'integer' => '11', 'can_be_null' => null];
        $where = Where::new()->with('id', 1, '>=')->and('double', [1.123, 2.456, 3.789], 'IN');
        $builder->updateWhere($data, $where)->assert();

        global $wpdb;
        $this->assertSameQuery($expectedQuery, $wpdb->last_query);
    }

    /**
     * @test
     */
    public function testInsertFailsIfBuiltBeforeSetupTheme(): void
    {
        $builder = WriteBuilder::new(TableOne::NAME);

        Monkey\Actions\expectDone(Dbal::ACTION_READY)
            ->zeroOrMoreTimes()
            ->whenHappen(static function (): void {
                Dbal::schemas()->registerForInstall(new TableOne());
            });

        $data = [
            'post_id' => 1,
            'double' => 1.123,
            'text' => 'two',
            'integer' => 12,
        ];

        $error = $builder->insert($data)->error();
        static::assertNotNull($error);
        $errors = $error->allMessages();
        static::assertSame(1, preg_match('~could not execute Write::insert~i', $errors[0]));
        static::assertSame(1, preg_match('~before DBAL is ready~i', $errors[1]));
    }
}
