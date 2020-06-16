<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Tests\Integration;

use Inpsyde\Dbal\Dbal;
use Inpsyde\Dbal\Query\Where;
use Inpsyde\Dbal\Query\WpQueryUtils;
use Inpsyde\Dbal\Tests\QueriesTestCase;
use Inpsyde\Dbal\Tests\TableOne;

class JoinWpTableTest extends QueriesTestCase
{
    /**
     * @test
     */
    public function testJoinWpPostsTable()
    {
        $id = wp_insert_post(
            [
                'post_title' => 'Test',
                'post_content' => 'The content.',
                'post_type' => 'post',
                'post_status' => 'publish',
            ],
            true
        );

        if (is_wp_error($id)) {
            throw new \Exception($id->get_error_message());
        }

        $insert = Dbal::writeOn(TableOne::NAME)->insert(
            [
                TableOne::POST_ID => $id,
                TableOne::TEXT => 'foo',
                TableOne::SERIALIZED => ['foo'],
                TableOne::INTEGER => 42,
                TableOne::DOUBLE => 42.0,
                TableOne::DATETIME => new \DateTime('now'),
                TableOne::ENUM => 'yes',
            ]
        );

        $insert->assert();

        $results = Dbal::select(TableOne::NAME, 'one')
            ->innerJoin(Dbal::wpdb()->posts, TableOne::POST_ID, 'ID', 'p')
            ->cols('one.*', 'p.post_title', 'p.post_content')
            ->where('p.ID', $id)
            ->pickFirst();

        $results->assert();

        $value = $results->first();
        static::assertIsArray($value);
        static::assertSame((int)$id, $value[TableOne::POST_ID]);
        static::assertSame(['foo'], $value[TableOne::SERIALIZED]);
    }

    /**
     * @test
     */
    public function testBuildQueryViaWpQueryAndUseAsClause()
    {
        $id = wp_insert_post(
            [
                'post_title' => 'Test',
                'post_content' => 'The content.',
                'post_type' => 'post',
                'post_status' => 'publish',
            ],
            true
        );

        if (is_wp_error($id)) {
            throw new \Exception($id->get_error_message());
        }

        $insert = Dbal::writeOn(TableOne::NAME)->insert(
            [
                TableOne::POST_ID => $id,
                TableOne::TEXT => 'foo',
                TableOne::SERIALIZED => ['foo'],
                TableOne::INTEGER => 42,
                TableOne::DOUBLE => 42.0,
                TableOne::DATETIME => new \DateTime('now'),
                TableOne::ENUM => 'yes',
            ]
        );

        $insert->assert();

        $args = [
            'post__in' => [$id],
            'post_type' => 'post',
            'post_status' => 'publish',
            'fields' => 'ids',
            'orderby' => 'none',
            'ignore_sticky_posts' => 1,
            'no_found_rows' => true,
            'nopaging' => true,
        ];

        $postsRequest = WpQueryUtils::buildSqlForArgs($args);
        $results = Dbal::select(TableOne::NAME)
            ->whereRaw("({$postsRequest})", TableOne::POST_ID, Where::IN)
            ->pickFirst();

        $results->assert();

        $value = $results->first();
        static::assertIsArray($value);
        static::assertSame((int)$id, $value[TableOne::POST_ID]);
        static::assertSame(['foo'], $value[TableOne::SERIALIZED]);
    }

    /**
     * @test
     */
    public function testSelectWithRawJoin()
    {
        $write = Dbal::writeOn(TableOne::NAME);
        $baseData = [TableOne::TEXT => 'foo', TableOne::ENUM => 'yes'];

        $expectedReverse = [];
        $letters = ['Z', 'R', 'M', 'F', 'A'];
        for ($i = 0; $i < 5; $i++) {
            $id = wp_insert_post(['post_title' => $letters[$i]], true);
            if (is_wp_error($id)) {
                throw new \Exception($id->get_error_message());
            }
            $result = $write->insert(array_merge($baseData, [TableOne::POST_ID => $id]));
            $expectedReverse[] = [
                TableOne::ID => $result->extract()->insertId,
                TableOne::POST_ID => $id,
               'title' => $letters[$i],
            ];
        }

        $posts = Dbal::wpdb()->posts;
        $sql = "SELECT ID as post_id, post_title FROM {$posts}";

        $select = Dbal::select(TableOne::NAME, 'o')
            ->innerJoinRaw($sql, 'p', TableOne::POST_ID)
            ->cols('o.' . TableOne::ID, 'o.' . TableOne::POST_ID)
            ->andRawCol('p.post_title', 'title')
            ->orderByRaw('p.post_title')
            ->where(TableOne::POST_ID, 0, Where::GREATER)
            ->all();

        $select->assert();

        $expected = array_reverse($expectedReverse, false);
        $actual = $select->toArray();

        static::assertSame($expected, $actual);
    }
}
