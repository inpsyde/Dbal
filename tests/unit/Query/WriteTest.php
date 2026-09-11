<?php

declare(strict_types=1);

namespace Syde\Dbal\Tests\Unit\Query;

use Syde\Dbal\Query\Where;
use Syde\Dbal\Query\Write;
use Syde\Dbal\Tests\TableOne;
use Syde\Dbal\Tests\UnitTestCase;

class WriteTest extends UnitTestCase
{
    /**
     * @test
     */
    public function testInsert(): void
    {
        $finder = $this->initializeSampleTablesFinder();

        $result = Write::on(TableOne::NAME, $finder)->insert(
            [
                TableOne::POST_ID => '123',
                TableOne::TEXT => '',
                TableOne::SERIALIZED => ['foo' => 'bar'],
                TableOne::INTEGER => 1,
                TableOne::DOUBLE => '1.23',
            ]
        );

        $result->extract();

        $serialized = addslashes(serialize(['foo' => 'bar']));
        $expectedQuery = <<<SQL
INSERT INTO `wp_1_tests_sample_table`
    (`post_id`, `text`, `serialized`, `integer`, `double`)
    VALUES (123, '', '{$serialized}', 1, '1.23')
SQL;
        global $wpdb;
        $this->assertSameQuery($expectedQuery, $wpdb->last_query);
    }

    /**
     * @test
     */
    public function testInsertMany(): void
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
        $this->assertSameQuery($expectedQuery, $wpdb->last_query);
    }

    /**
     * @test
     */
    public function testUpdateWhere(): void
    {
        $finder = $this->initializeSampleTablesFinder();

        Write::on(TableOne::NAME, $finder)->insert(
            ['text' => 'one', 'integer' => '11', 'can_be_null' => 'not null']
        );
        $result = Write::on(TableOne::NAME, $finder)->updateWhere(
            ['text' => 'one', 'integer' => '11', 'can_be_null' => null],
            Where::new()->with('id', 1, '>=')->and('double', [1.123, 2.456, 3.789], 'IN')
        );

        static::assertFalse($result->isErrored());

        $expectedQuery = <<<SQL
UPDATE `wp_1_tests_sample_table` SET `text` = 'one', `can_be_null` = '', `integer` = 11
WHERE `wp_1_tests_sample_table`.`id` >= 1
  AND `wp_1_tests_sample_table`.`double` IN ('1.123','2.456','3.789');
SQL;

        global $wpdb;
        $this->assertSameQuery($expectedQuery, $wpdb->last_query);
    }

    /**
     * @test
     */
    public function testUpdate(): void
    {
        $finder = $this->initializeSampleTablesFinder();

        Write::on(TableOne::NAME, $finder)->insert(
            ['text' => 'one', 'integer' => '11', 'can_be_null' => 'not null']
        );
        $result = Write::on(TableOne::NAME, $finder)->update(
            ['text' => 'one', 'integer' => '11', 'can_be_null' => null],
            ['id' => 1, 'double' => 1.123]
        );

        $result->extract();

        $expectedQuery = <<<SQL
UPDATE `wp_1_tests_sample_table` SET `text` = 'one', `can_be_null` = NULL, `integer` = 11
WHERE `id` = 1 AND `double` = '1.123'
SQL;

        global $wpdb;
        $this->assertSameQuery($expectedQuery, $wpdb->last_query);
    }
}
