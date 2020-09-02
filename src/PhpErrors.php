<?php

declare(strict_types=1);

namespace Inpsyde\Dbal;

final class PhpErrors
{

    /**
     * @var callable|null
     */
    private $previousErrorHandler;

    /**
     * @var bool
     */
    private $restored = false;

    /**
     * @return \Inpsyde\Dbal\PhpErrors
     */
    public static function convertToExceptions(): PhpErrors
    {
        // phpcs:disable WordPress.PHP
        $previousErrorHandler = set_error_handler(
            static function (int $code, string $message, string $file = '', int $line = 0): bool {
                if (error_reporting() !== 0) {
                    throw new \ErrorException($message, $code, E_ERROR, $file, $line);
                }

                return false;
            }
        );
        // phpcs:enable WordPress.PHP

        return new static($previousErrorHandler);
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
