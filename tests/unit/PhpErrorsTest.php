<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Tests\Unit;

use Inpsyde\Dbal\PhpErrors;
use Inpsyde\Dbal\Tests\UnitTestCase;

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
