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

    /** @var callable */
    private $handler;
    private bool $restored = false;

    /**
     * @return PhpErrors
     */
    public static function convertToExceptions(): PhpErrors
    {
        // phpcs:disable Syde.Files.LineLength.TooLong
        $handler = static function (int $code, string $message, string $file = '', int $line = 0): bool {
            if (!PhpErrors::areErrorsSuppressed($code)) {
                // phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
                throw new \ErrorException(esc_html($message), $code, E_ERROR, $file, $line);
                // phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }

            return true;
        };

        // phpcs:disable WordPress.PHP
        set_error_handler($handler);

        return new PhpErrors($handler);
    }

    /**
     * @param int $code
     * @return bool
     */
    private static function areErrorsSuppressed(int $code): bool
    {
        $errorReporting = error_reporting();

        if ($errorReporting !== self::PHP_8_FATAL_ERROR_CODES) {
            return false;
        }

        return ($code & self::PHP_8_FATAL_ERROR_CODES) !== $code;
    }

    /**
     * @param callable $handler
     */
    private function __construct(callable $handler)
    {
        $this->handler = $handler;
    }

    private static function currentErrorHandler(): ?callable
    {
        $handler = set_error_handler(null); // phpcs:ignore
        restore_error_handler();

        return $handler;
    }

    /**
     * @return void
     */
    public function restoreHandler(): void
    {
        if ($this->restored) {
            return;
        }

        $this->restored = true;

        if (self::currentErrorHandler() !== $this->handler) {
            return;
        }

        restore_error_handler();
    }
}
