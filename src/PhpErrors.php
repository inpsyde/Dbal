<?php

declare(strict_types=1);

namespace Inpsyde\Dbal;

final class PhpErrors
{
    private const PHP_8_FATAL_ERROR_CODES = E_ERROR
        | E_CORE_ERROR
        | E_COMPILE_ERROR
        | E_USER_ERROR
        | E_RECOVERABLE_ERROR
        | E_PARSE;

    /**
     * @var callable|null
     */
    private $previousErrorHandler;

    /**
     * @var bool
     */
    private $restored = false;

    /**
     * @return PhpErrors
     */
    public static function convertToExceptions(): PhpErrors
    {
        // phpcs:disable WordPress.PHP
        $previousErrorHandler = set_error_handler(
            static function (int $code, string $message, string $file = '', int $line = 0): bool {
                if (!static::areErrorsSuppressed($code)) {
                    throw new \ErrorException($message, $code, E_ERROR, $file, $line);
                }

                return true;
            }
        );

        return new static($previousErrorHandler);
    }

    /**
     * @param int $code
     * @return bool
     */
    private static function areErrorsSuppressed(int $code): bool
    {
        $errorReporting = error_reporting();

        if (PHP_MAJOR_VERSION < 8) {
            return $errorReporting === 0;
        }

        if ($errorReporting !== self::PHP_8_FATAL_ERROR_CODES) {
            return false;
        }

        return ($code & self::PHP_8_FATAL_ERROR_CODES) !== $code;
    }

    /**
     * @param callable|null $previousErrorHandler
     */
    private function __construct(?callable $previousErrorHandler)
    {
        $this->previousErrorHandler = $previousErrorHandler;
    }

    /**
     * @return void
     */
    public function restoreHandler(): void
    {
        if (!$this->restored) {
            $this->restored = true;
            /** @psalm-suppress MixedArgumentTypeCoercion */
            set_error_handler($this->previousErrorHandler); // phpcs:ignore
            $this->previousErrorHandler = null;
        }
    }
}
