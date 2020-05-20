<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Tests\Unit\Query;

use Inpsyde\Dbal\Query\Compare;
use Inpsyde\Dbal\Query\Where;
use Inpsyde\Dbal\Query\Write;
use Inpsyde\Dbal\Tests\TableOne;
use Inpsyde\Dbal\Tests\UnitTestCase;

class WriteTest extends UnitTestCase
{
    public function testInsertMany()
    {
        $finder = $this->initializeSampleTablesFinder();

        $dateFmt = 'Y-m-d H:i:s';
        $utc = new \DateTimeZone('UTC');

        $expectedDate = \DateTimeImmutable::createFromFormat($dateFmt, '2012-12-20 20:12:20', $utc);
        $dateInRome = $expectedDate->setTimezone(new \DateTimeZone('Europe/Rome'));

        static::assertSame('2012-12-20 21:12:20', $dateInRome->format($dateFmt));

        $result = Write::on(TableOne::NAME, $finder)->insertMany(
            [
                TableOne::POST_ID => 1,
                TableOne::TEXT => 'one',
                TableOne::INTEGER => '11',
                TableOne::DATETIME => $dateInRome,
            ],
            [
                TableOne::POST_ID => 2,
                TableOne::TEXT => 'two',
                TableOne::INTEGER => 22,
                TableOne::DATETIME => $dateInRome,
            ],
            [
                TableOne::POST_ID => 3,
                TableOne::TEXT => 'three',
                TableOne::INTEGER => 33.01,
                TableOne::DATETIME => $dateInRome,
            ]
        );

        static::assertFalse($result->isErrored());

        $expectedQuery = <<<SQL
INSERT INTO `wp_1_tests_sample_table` (`post_id`, `text`, `integer`, `datetime`) VALUES 
	(1, 'one', 11, '2012-12-20 20:12:20'),
	(2, 'two', 22, '2012-12-20 20:12:20'),
	(3, 'three', 33, '2012-12-20 20:12:20')
SQL;

        global $wpdb;
        $this->compareQueries($expectedQuery, $wpdb->last_query);
    }

    public function testUpdateWhere()
    {
        $finder = $this->initializeSampleTablesFinder();

        $result = Write::on(TableOne::NAME, $finder)->updateWhere(
            ['text' => 'one', 'integer' => '11'],
            Where::new()->with('id', 1, '>=')->and('double', [1.123, 2.456, 3.789], 'IN')
        );

        static::assertFalse($result->isErrored());

        $expectedQuery = <<<SQL
UPDATE `wp_1_tests_sample_table` SET `text` = 'one', `integer` = 11
WHERE `wp_1_tests_sample_table`.`id` >= 1
  AND `wp_1_tests_sample_table`.`double` IN ('1.123','2.456','3.789');
SQL;

        global $wpdb;
        $this->compareQueries($expectedQuery, $wpdb->last_query);
    }

    public function testDeleteWhere()
    {
        $finder = $this->initializeSampleTablesFinder();
        $where = Where::new()->withCompare(Compare::columnValue(TableOne::POST_ID, 1, '>='));
        $result = Write::on(TableOne::NAME, $finder)->deleteWhere($where);

        static::assertFalse($result->isErrored());

        $expectedQuery = <<<SQL
DELETE FROM `wp_1_tests_sample_table` WHERE `wp_1_tests_sample_table`.`post_id` >= 1;
SQL;

        global $wpdb;
        $this->compareQueries($expectedQuery, $wpdb->last_query);
    }

    public function testDelete()
    {
        $finder = $this->initializeSampleTablesFinder();
        $result = Write::on(TableOne::NAME, $finder)->delete([TableOne::POST_ID => 1]);

        $result->extract();

        $expectedQuery = <<<SQL
DELETE FROM `wp_1_tests_sample_table` WHERE `post_id` = 1
SQL;

        global $wpdb;
        $this->compareQueries($expectedQuery, $wpdb->last_query);
    }

    public function testDeleteOnPrimary()
    {
        $finder = $this->initializeSampleTablesFinder();
        $result = Write::on(TableOne::NAME, $finder)->deleteOnPrimary(1);
        $result->extract();

        $expectedQuery = "DELETE FROM `wp_1_tests_sample_table` WHERE `id` = 1";
        global $wpdb;
        $this->compareQueries($expectedQuery, $wpdb->last_query);
    }

    /**
     * @param string $expectedRaw
     * @param string $actualRaw
     * @return void
     */
    private function compareQueries(string $expectedRaw, string $actualRaw): void
    {
        $expected = trim(preg_replace('/\s+/', ' ', $expectedRaw));
        $actual = trim(preg_replace('/\s+/', ' ', $actualRaw));

        static::assertSame($expected, $actual);
    }
}
