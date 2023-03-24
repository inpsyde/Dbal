<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Tests\Unit;

use Inpsyde\Dbal\Error;
use Inpsyde\Dbal\Tests\UnitTestCase;

class ErrorTest extends UnitTestCase
{
    /**
     * @test
     */
    public function testSerialize(): void
    {
        $first = new \Error('First');
        $second = new \InvalidArgumentException('Second', 0, $first);
        $third = new \RuntimeException('Third', 1, $second);

        $error = new Error('Error', 2, $third);

        $serialized = $error->serialize();

        $expected = "First\n> Second\n> Third\n> Error";

        static::assertSame($expected, $serialized);
    }
}
