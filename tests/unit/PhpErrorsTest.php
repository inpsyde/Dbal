<?php

declare(strict_types=1);

namespace Syde\Dbal\Tests\Unit;

use Syde\Dbal\PhpErrors;
use Syde\Dbal\Tests\UnitTestCase;

class PhpErrorsTest extends UnitTestCase
{
    /**
     * @test
     */
    public function testConvertToException(): void
    {
        $phpErrors = PhpErrors::convertToExceptions();

        $this->expectExceptionMessage('A notice');
        trigger_error('A notice', E_USER_NOTICE);

        $phpErrors->restoreHandler();
    }

    /**
     * @test
     */
    public function testConvertToExceptionDoNothingIfSilenced(): void
    {
        $phpErrors = PhpErrors::convertToExceptions();

        @trigger_error('A notice', E_USER_NOTICE);

        $phpErrors->restoreHandler();

        $this->expectOutputString('');
    }
}
