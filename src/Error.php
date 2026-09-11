<?php

declare(strict_types=1);

namespace Syde\Dbal;

final class Error extends \Error
{
    private bool $fromThrowable = false;

    /**
     * @param Error $error
     * @param string $message
     * @return static
     */
    public static function withMerged(Error $error, string $message = ''): Error
    {
        $instance = (new static($message))->merge($error);
        $instance->file = $error->file;
        $instance->line = $error->line;

        return $instance;
    }

    /**
     * @param Error $error
     * @param \Throwable $throwable
     * @return static
     */
    public static function withMergedThrowable(Error $error, \Throwable $throwable): Error
    {
        return static::fromThrowable($throwable)->merge($error);
    }

    /**
     * @param \Throwable $error
     * @return static
     */
    public static function fromThrowable(\Throwable $error): Error
    {
        if ($error instanceof Error) {
            return $error;
        }

        $instance = new static($error->getMessage(), (int) $error->getCode(), $error);
        $instance->fromThrowable = true;

        return $instance;
    }

    /**
     * @param string $message
     * @param int $code
     * @param \Throwable|null $previous
     */
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        /** @var array<array<string, mixed>> $trace */
        $trace = $this->getTrace();
        if (!$trace) {
            return;
        }

        $classFile = (new \ReflectionClass($this))->getFileName();

        foreach ($trace as $originated) {
            if (($originated['file'] ?? '') === $classFile) {
                continue;
            }

            $this->file = (string) $originated['file'];
            $this->line = (int) ($originated['line'] ?? 0);
            break;
        }
    }

    /**
     * @param \Throwable $error
     * @return static
     */
    public function merge(\Throwable $error): Error
    {
        return new static($error->getMessage(), (int) $error->getCode(), $this);
    }

    /**
     * @return list<string>
     */
    public function allMessages(): array
    {
        $messages = [$this->getMessage()];
        $previous = $this->getPrevious();

        while ($previous) {
            if (!$previous instanceof Error || !$previous->fromThrowable) {
                $messages[] = $previous->getMessage();
            }

            $previous = $previous->getPrevious();
        }

        return $messages;
    }

    /**
     * @return string
     */
    public function serialize(): string
    {
        return implode("\n> ", array_reverse($this->allMessages()));
    }
}
