<?php

declare(strict_types=1);

namespace Syde\Dbal\Tests\Unit\Query;

use Syde\Dbal\Error;
use Syde\Dbal\Query\ResultSet;
use Syde\Dbal\Query\Select;
use Syde\Dbal\Tests\TableOne;
use Syde\Dbal\Tests\UnitTestCase;

class ResultSetTest extends UnitTestCase
{
    /**
     * @test
     */
    public function testErrored(): void
    {
        $error = new Error('Test');
        $set = ResultSet::errored($error);

        static::assertTrue($set->hasErrors());
        static::assertFalse($set->isValid());
        static::assertFalse($set->hasMorePages());
        static::assertNull($set->pagination());
        static::assertNull($set->first());
        static::assertSame([], iterator_to_array($set->getIterator()));
        static::assertSame([], iterator_to_array($set->autoPaginationIterator()));
        static::assertSame(0, count($set));

        static::assertSame(
            '{"errors":["Test"]}',
            json_encode($set)
        );

        static::assertSame(
            '{"errors":["Result set has no next page.","Test"]}',
            json_encode($set->nextPage())
        );

        $this->expectExceptionMessageMatches('/Test/');
        $set->toArray();
    }

    /**
     * @test
     */
    public function testSingleRow(): void
    {
        $row = [
            TableOne::ID => '1',
            TableOne::TEXT => 'Foo bar',
            TableOne::SERIALIZED => serialize(['a', 'b']),
            TableOne::INTEGER => '123',
            TableOne::DOUBLE => '0.001',
            TableOne::DATETIME => '2019-12-09 07:36:00',
            TableOne::ENUM => 'yes',
        ];

        $expected = [
            TableOne::ID => 1,
            TableOne::TEXT => 'Foo bar',
            TableOne::SERIALIZED => ['a', 'b'],
            TableOne::INTEGER => 123,
            TableOne::DOUBLE => 0.001,
            TableOne::DATETIME => \DateTime::createFromFormat(
                'Y-m-d H:i:s',
                '2019-12-09 07:36:00',
                new \DateTimeZone('UTC')
            ),
            TableOne::ENUM => 'yes',
        ];

        $finder = $this->initializeSampleTablesFinder();
        $this->infixNextWpdbRowsResult($row);

        $set = Select::from(TableOne::NAME, null, $finder)
            ->allCols()
            ->pickFirst();

        static::assertFalse($set->hasErrors());
        static::assertTrue($set->isValid());
        static::assertFalse($set->hasMorePages());
        static::assertNull($set->pagination());
        static::assertEquals($expected, $set->first());
        static::assertEquals([$expected], iterator_to_array($set->getIterator()));
        static::assertEquals([$expected], iterator_to_array($set->autoPaginationIterator()));
        static::assertSame(1, count($set));
        static::assertEquals([$expected], $set->toArray());
        static::assertSame(json_encode([$expected]), json_encode($set));
        static::assertSame(
            '{"errors":["Result set has no next page."]}',
            json_encode($set->nextPage())
        );
    }

    /**
     * @test
     */
    public function testMultiPage(): void
    {
        $rows = [
            1 => [
                [
                    TableOne::ID => '1',
                    TableOne::TEXT => 'Foo',
                    TableOne::SERIALIZED => serialize(['a', 'b']),
                    TableOne::INTEGER => '123',
                    TableOne::DOUBLE => '0.001',
                    TableOne::DATETIME => '2019-12-09 07:36:00',
                    TableOne::ENUM => 'yes',
                ],
                [
                    TableOne::ID => '2',
                    TableOne::TEXT => 'Bar',
                    TableOne::SERIALIZED => serialize(['c', 'd']),
                    TableOne::INTEGER => '234',
                    TableOne::DOUBLE => '0.002',
                    TableOne::DATETIME => '2019-12-09 07:37:00',
                    TableOne::ENUM => 'no',
                ],
            ],
            2 => [
                [
                    TableOne::ID => '3',
                    TableOne::TEXT => 'Baz',
                    TableOne::SERIALIZED => serialize(['e', 'f']),
                    TableOne::INTEGER => '567',
                    TableOne::DOUBLE => '0.003',
                    TableOne::DATETIME => '2019-12-09 07:38:00',
                    TableOne::ENUM => 'yes',
                ],
                [
                    TableOne::ID => '4',
                    TableOne::TEXT => 'Meh',
                    TableOne::SERIALIZED => serialize(['g', 'h']),
                    TableOne::INTEGER => '890',
                    TableOne::DOUBLE => '0.004',
                    TableOne::DATETIME => '2019-12-09 07:39:00',
                    TableOne::ENUM => 'no',
                ],
            ],
        ];

        $expected = [
            1 => [
                [
                    TableOne::ID => 1,
                    TableOne::TEXT => 'Foo',
                    TableOne::SERIALIZED => ['a', 'b'],
                    TableOne::INTEGER => 123,
                    TableOne::DOUBLE => 0.001,
                    TableOne::DATETIME => \DateTime::createFromFormat(
                        'Y-m-d H:i:s',
                        '2019-12-09 07:36:00',
                        new \DateTimeZone('UTC')
                    ),
                    TableOne::ENUM => 'yes',
                ],
                [
                    TableOne::ID => 2,
                    TableOne::TEXT => 'Bar',
                    TableOne::SERIALIZED => ['c', 'd'],
                    TableOne::INTEGER => 234,
                    TableOne::DOUBLE => 0.002,
                    TableOne::DATETIME => \DateTime::createFromFormat(
                        'Y-m-d H:i:s',
                        '2019-12-09 07:37:00',
                        new \DateTimeZone('UTC')
                    ),
                    TableOne::ENUM => 'no',
                ],
            ],
            2 => [
                [
                    TableOne::ID => 3,
                    TableOne::TEXT => 'Baz',
                    TableOne::SERIALIZED => ['e', 'f'],
                    TableOne::INTEGER => 567,
                    TableOne::DOUBLE => 0.003,
                    TableOne::DATETIME => \DateTime::createFromFormat(
                        'Y-m-d H:i:s',
                        '2019-12-09 07:38:00',
                        new \DateTimeZone('UTC')
                    ),
                    TableOne::ENUM => 'yes',
                ],
                [
                    TableOne::ID => 4,
                    TableOne::TEXT => 'Meh',
                    TableOne::SERIALIZED => ['g', 'h'],
                    TableOne::INTEGER => 890,
                    TableOne::DOUBLE => 0.004,
                    TableOne::DATETIME => \DateTime::createFromFormat(
                        'Y-m-d H:i:s',
                        '2019-12-09 07:39:00',
                        new \DateTimeZone('UTC')
                    ),
                    TableOne::ENUM => 'no',
                ],
            ],
        ];

        $finder = $this->initializeSampleTablesFinder();
        $this->infixNextWpdbRowsResult(...$rows[1]);
        $this->infixNextWpdbGetVarResult('4');

        $set = Select::from(TableOne::NAME, null, $finder)
            ->allCols()
            ->paginated(1, 2)
            ->all();

        static::assertFalse($set->hasErrors());
        static::assertTrue($set->isValid());
        static::assertTrue($set->hasMorePages());
        static::assertSame(2, $set->pagination()->perPage());
        static::assertSame(1, $set->pagination()->page());
        static::assertSame(2, $set->pagination()->totalPages());
        static::assertEquals($expected[1], iterator_to_array($set->getIterator()));
        static::assertSame(2, count($set));
        static::assertEquals($expected[1], $set->toArray());

        $this->infixNextWpdbRowsResult(...$rows[2]);
        $this->infixNextWpdbGetVarResult('4');

        $allExpected = array_merge($expected[1], $expected[2]);
        $iterator = $set->autoPaginationIterator();
        $i = 0;
        foreach ($iterator as $row) {
            static::assertEquals($allExpected[$i], $row);
            $i++;
        }
    }

    /**
     * @test
     */
    public function testFilteredMapped(): void
    {
        $rows = [
            [
                TableOne::ID => '1',
                TableOne::TEXT => 'Foo',
                TableOne::SERIALIZED => serialize(['a', 'b']),
                TableOne::INTEGER => '123',
                TableOne::DOUBLE => '0.001',
                TableOne::DATETIME => '2019-12-09 07:36:00',
                TableOne::ENUM => 'yes',
            ],
            [
                TableOne::ID => '2',
                TableOne::TEXT => 'Bar',
                TableOne::SERIALIZED => serialize(['c', 'd']),
                TableOne::INTEGER => '234',
                TableOne::DOUBLE => '0.002',
                TableOne::DATETIME => '2019-12-09 07:37:00',
                TableOne::ENUM => 'no',
            ],
        ];

        $expected = (object) [
            TableOne::ID => 2,
            TableOne::TEXT => 'Bar',
            TableOne::SERIALIZED => ['c', 'd'],
            TableOne::INTEGER => 234,
            TableOne::DOUBLE => 0.002,
            TableOne::DATETIME => \DateTime::createFromFormat(
                'Y-m-d H:i:s',
                '2019-12-09 07:37:00',
                new \DateTimeZone('UTC')
            ),
            TableOne::ENUM => 'no',
        ];

        $finder = $this->initializeSampleTablesFinder();
        $this->infixNextWpdbRowsResult(...$rows);
        $this->infixNextWpdbGetVarResult('2');

        $set = Select::from(TableOne::NAME, null, $finder)
            ->allCols()
            ->paginated(1, 2)
            ->all()
            ->filter(static function (array $row): bool {
                return $row[TableOne::ID] === 2;
            })
            ->map(static function (array $row): \stdClass {
                return (object) $row;
            });

        $firstItem = iterator_to_array($set)[0];

        static::assertFalse($set->hasErrors());
        static::assertTrue($set->isValid());
        static::assertFalse($set->hasMorePages());
        static::assertSame(2, $set->pagination()->perPage());
        static::assertSame(1, $set->pagination()->page());
        static::assertSame(1, $set->pagination()->totalPages());
        static::assertInstanceOf(\stdClass::class, $firstItem);
        static::assertEquals($expected, $firstItem);
    }

    /**
     * @test
     */
    public function testErroredToResult(): void
    {
        $error = new Error('Test');
        $result = ResultSet::errored($error)->toResult();

        static::assertSame('Test', $result->error()->getMessage());
    }

    /**
     * @test
     */
    public function testSingleRowToResult(): void
    {
        $row = [
            TableOne::ID => '1',
            TableOne::TEXT => 'Foo bar',
            TableOne::SERIALIZED => serialize(['a', 'b']),
            TableOne::INTEGER => '123',
            TableOne::DOUBLE => '0.001',
            TableOne::DATETIME => '2019-12-09 07:36:00',
            TableOne::ENUM => 'yes',
        ];

        $finder = $this->initializeSampleTablesFinder();
        $this->infixNextWpdbRowsResult($row);

        $check = null;
        Select::from(TableOne::NAME, null, $finder)
            ->allCols()
            ->pickFirst()
            ->toResult()
            ->bind(
                static function (\Iterator $iterator) use (&$check): void {
                    static::assertSame(0.001, $iterator->current()[TableOne::DOUBLE]);
                    $check = true; // make sure the above assertion ran.
                },
                static function (): void {
                    static::fail('bind "onError" branch should never be executed.');
                }
            );

        static::assertTrue($check);
    }
}
