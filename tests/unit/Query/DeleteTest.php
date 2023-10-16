<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Tests\Unit\Query;

use Inpsyde\Dbal\Query\Delete;
use Inpsyde\Dbal\Query\Where;
use Inpsyde\Dbal\Tests\TableOne;
use Inpsyde\Dbal\Tests\TablePivot;
use Inpsyde\Dbal\Tests\TableTwo;
use Inpsyde\Dbal\Tests\UnitTestCase;

class DeleteTest extends UnitTestCase
{
    /**
     * @test
     */
    public function testDelete(): void
    {
        $finder = $this->initializeSampleTablesFinder();

        $delete = Delete::from(TableOne::NAME, $finder)
            ->where(TableOne::POST_ID, 4, Where::GREATER)
            ->andWhere(TableOne::TEXT, ['foo', 'bar'], Where::IN);

        $expected = <<<QUERY
DELETE FROM `wp_1_tests_sample_table`
    WHERE `wp_1_tests_sample_table`.`post_id` > 4
    AND `wp_1_tests_sample_table`.`text` IN ('foo','bar')
QUERY;
        $this->assertSameQuery($expected, $delete->buildSql());
    }

    /**
     * @test
     */
    public function testDeleteWithJoin(): void
    {
        $finder = $this->initializeSampleTablesFinder();

        $delete = Delete::from(TableOne::NAME, $finder)
            ->innerJoin(TableTwo::NAME, TableOne::POST_ID, TableTwo::ID)
            ->where(TableOne::POST_ID, 4, Where::GREATER);

        $expected = <<<QUERY
DELETE `wp_1_tests_sample_table`, `wp_1_tests_sample_table_two`
    FROM `wp_1_tests_sample_table`
    INNER JOIN `wp_1_tests_sample_table_two`
       ON `wp_1_tests_sample_table`.`post_id` = `wp_1_tests_sample_table_two`.`id`
    WHERE `wp_1_tests_sample_table`.`post_id` > 4
QUERY;
        $this->assertSameQuery($expected, $delete->buildSql());
    }

    /**
     * @test
     */
    public function testDeleteWithJoinOnlyMain(): void
    {
        $finder = $this->initializeSampleTablesFinder();

        $delete = Delete::from(TableOne::NAME, $finder)
            ->innerJoin(TableTwo::NAME, TableOne::POST_ID, TableTwo::ID)
            ->deleteOnly(TableOne::NAME)
            ->where(TableOne::POST_ID, 4, Where::GREATER);

        $expected = <<<QUERY
DELETE `wp_1_tests_sample_table`
    FROM `wp_1_tests_sample_table`
    INNER JOIN `wp_1_tests_sample_table_two`
       ON `wp_1_tests_sample_table`.`post_id` = `wp_1_tests_sample_table_two`.`id`
    WHERE `wp_1_tests_sample_table`.`post_id` > 4
QUERY;
        $this->assertSameQuery($expected, $delete->buildSql());
    }

    /**
     * @test
     */
    public function testDeleteWithMultipleJoinDeletingFromAllButOne(): void
    {
        $finder = $this->initializeSampleTablesFinder();

        $delete = Delete::from(TableOne::NAME, $finder)
            ->innerJoin(
                TableTwo::NAME,
                TableOne::NAME . '.' . TableOne::POST_ID,
                TableTwo::NAME . '.' . TableTwo::ID
            )
            ->innerJoinWith(
                TablePivot::NAME,
                TableTwo::NAME,
                TableTwo::NAME . '.' . TablePivot::ID,
                TablePivot::NAME . '.' . TablePivot::ID
            )
            ->deleteOnly(TableTwo::NAME, TablePivot::NAME)
            ->where(TableOne::POST_ID, 4, Where::GREATER);

        $expected = <<<QUERY
DELETE `wp_1_tests_sample_table_two`, `wp_1_tests_sample_table_pivot`
    FROM `wp_1_tests_sample_table`
    INNER JOIN `wp_1_tests_sample_table_two`
       ON `wp_1_tests_sample_table`.`post_id` = `wp_1_tests_sample_table_two`.`id`
    INNER JOIN `wp_1_tests_sample_table_pivot`
       ON `wp_1_tests_sample_table_two`.`id` = `wp_1_tests_sample_table_pivot`.`id`
    WHERE `wp_1_tests_sample_table`.`post_id` > 4
QUERY;
        $this->assertSameQuery($expected, $delete->buildSql());
    }
}
