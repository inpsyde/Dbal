<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Tests\Unit;

use Inpsyde\Dbal\Cache;
use Inpsyde\Dbal\Tests\TableOne;
use Inpsyde\Dbal\Tests\TablePivot;
use Inpsyde\Dbal\Tests\TableTwo;
use Inpsyde\Dbal\Tests\UnitTestCase;
use Brain\Monkey;

class CacheTest extends UnitTestCase
{
    private $cache = [];

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = [];

        Monkey\Functions\when('wp_cache_get')->alias(
            function (string $key, string $group) { // phpcs:ignore
                return $this->cache[$group . $key] ?? false;
            }
        );

        Monkey\Functions\when('wp_cache_set')->alias(
            function (string $key, $value, string $group) { // phpcs:ignore
                $this->cache[$group . $key] = $value;
            }
        );

        Monkey\Functions\when('wp_cache_delete')->alias(
            function (string $key, string $group) { // phpcs:ignore
                unset($this->cache[$group . $key]);
            }
        );

        Monkey\Functions\when('wp_using_ext_object_cache')->alias('__return_false');
        Monkey\Functions\when('get_current_network_id')->justReturn(1);
        Monkey\Functions\when('get_current_blog_id')->justReturn(1);
        Monkey\Functions\when('wp_generate_uuid4')->justReturn(random_bytes(8));
    }

    /**
     * @test
     */
    public function testBaseSetAndGet(): void
    {
        $cache = Cache::new($this->initializeSampleTablesFinder());

        static::assertNull($cache->get('foo'));

        $cache->set('foo', 'Foo');

        static::assertSame('Foo', $cache->get('foo'));
        static::assertSame('Foo', $cache->get('foo'));
        static::assertSame('Foo', $cache->get('foo'));

        $cache->delete('foo');

        static::assertNull($cache->get('foo'));
    }

    /**
     * @test
     */
    public function testKeyForTables(): void
    {
        $ta1 = (new TableOne())->name();
        $ta2 = (new TableTwo())->name();
        $ta3 = (new TablePivot())->name();

        $toTest = [
            [$ta1],
            [$ta2],
            [$ta3],
            [$ta1, $ta2],
            [$ta2, $ta1],
            [$ta1, $ta3],
            [$ta3, $ta1],
            [$ta2, $ta3],
            [$ta3, $ta2],
            [$ta1, $ta2, $ta3],
            [$ta1, $ta3, $ta2],
            [$ta2, $ta1, $ta3],
            [$ta2, $ta3, $ta1],
            [$ta3, $ta1, $ta2],
            [$ta3, $ta2, $ta1],
        ];

        $cache = Cache::new($this->initializeSampleTablesFinder());

        foreach ($toTest as $tables) {
            $rand = $tables[array_rand($tables, 1)];

            $key1 = $cache->buildCacheKeyForTables(...$tables);
            $key2 = $cache->buildCacheKeyForTables(...$tables);
            shuffle($tables);
            $key3 = $cache->buildCacheKeyForTables(...$tables);
            $tables = array_merge($tables, $tables);
            $key4 = $cache->buildCacheKeyForTables(...$tables);
            $tables[] = 'meh';
            $key5 = $cache->buildCacheKeyForTables(...$tables);

            static::assertSame($key1, $key2);
            static::assertSame($key1, $key3);
            static::assertSame($key1, $key4);
            static::assertSame($key1, $key5);

            $cache->cleanCacheForTables($rand);

            $key1b = $cache->buildCacheKeyForTables(...$tables);

            static::assertNotSame($key1, $key1b);

            $key1c = $cache->buildCacheKeyForTables(...$tables);

            static::assertSame($key1b, $key1c);
        }
    }

    /**
     * @test
     */
    public function testValueInvalidationByTableUpdate(): void
    {
        $ta1 = (new TableOne())->name();
        $ta2 = (new TableTwo())->name();

        $cache = Cache::new($this->initializeSampleTablesFinder());

        $generate = static function (string $key) use ($cache, $ta1, $ta2): array {
            $key = $cache->buildCacheKeyForTables($ta1, $ta2) . $key;
            $get = $cache->get($key);
            if ($get === null) {
                $get = ['a' => random_bytes(2), 'b' => random_bytes(3)];
                $cache->set($key, $get);
            }

            return $get;
        };

        $first = $generate('a');
        $second = $generate('a');
        $third = $generate('a');
        $fourth = $generate('b');
        $fifth = $generate('b');

        static::assertSame($first, $second);
        static::assertSame($first, $third);
        static::assertNotSame($first, $fourth);
        static::assertNotSame($first, $fifth);
        static::assertSame($fourth, $fifth);

        $cache->cleanCacheForTables($ta2);

        $sixth = $generate('a');
        $seventh = $generate('a');
        $eighth = $generate('b');
        $ninth = $generate('b');

        static::assertNotSame($first, $sixth);
        static::assertNotSame($first, $seventh);
        static::assertNotSame($fourth, $eighth);
        static::assertNotSame($fourth, $ninth);
        static::assertSame($sixth, $seventh);
        static::assertSame($eighth, $ninth);
    }
}
