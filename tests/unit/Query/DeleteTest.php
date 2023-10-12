<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Tests\Unit\Query;

use Inpsyde\Dbal\Query\Compare;
use Inpsyde\Dbal\Query\Delete;
use Inpsyde\Dbal\Query\Select;
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
}
