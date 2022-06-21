<?php

declare(strict_types=1);

namespace Inpsyde\Dbal;

/**
 * @template T of Error|null
 *
 * @psalm-suppress MixedReturnTypeCoercion
 * @psalm-suppress InvalidReturnType
 * @psalm-suppress InvalidReturnStatement
 */
final class Result
{
    /**
     * @var mixed
     */
    private $value;

    /**
     * @var T
     */
    private $error;

    /**
     * @param mixed $value
     * @return Result<T>
     */
    public static function new($value): Result
    {
        if ($value instanceof Result) {
            return new static($value->value, $value->error);
        }

        if ($value instanceof ErrorCollector) {
            $value = $value->isEmpty() ? null : $value->error();
        }

        if ($value instanceof \Throwable) {
            return new static(null, Error::fromThrowable($value));
        }

        return new static($value, null);
    }

    /**
     * @param mixed $value
     * @param T $error
     */
    private function __construct($value, ?Error $error)
    {
        $this->value = $value;
        $this->error = $error;
    }

    /**
     * @return bool
     *
     * @psalm-assert-if-true Result<Error> $this
     * @psalm-assert-if-true Error $this->error
     * @psalm-assert-if-false Result<null> $this
     * @psalm-assert-if-false null $this->error
     */
    public function isErrored(): bool
    {
        return $this->error !== null;
    }

    /**
     * @return bool
     *
     * @psalm-assert-if-false Result<Error> $this
     * @psalm-assert-if-false Error $this->error
     * @psalm-assert-if-true Result<null> $this
     * @psalm-assert-if-true null $this->error
     */
    public function isValid(): bool
    {
        return !$this->isErrored();
    }

    /**
     * @return mixed
     *
     * @psalm-assert Result<null> $this
     * @psalm-assert null $this->error
     */
    public function extract()
    {
        $this->assert();

        return $this->value;
    }

    /**
     * @return void
     *
     * @psalm-assert Result<null> $this
     * @psalm-assert null $this->error
     */
    public function assert(): void
    {
        if ($this->isErrored()) {
            throw $this->error;
        }
    }

    /**
     * @return T
     */
    public function error(): ?Error
    {
        return $this->error ?: null;
    }

    /**
     * @param callable|null $onSuccess
     * @param callable|null $onError
     * @return Result<T>
     */
    public function bind(?callable $onSuccess = null, ?callable $onError = null): Result
    {
        try {
            $callback = $this->error ? $onError : $onSuccess;
            $param = $this->error ?? $this->value;

            return $callback
                ? $this->merge(Result::new($callback($param)))
                : static::new($this);
        } catch (\Throwable $throwable) {
            return $this->merge(Result::new(Error::fromThrowable($throwable)));
        }
    }

    /**
     * @param Result<T> $result
     * @return Result<T>
     */
    public function merge(Result $result): Result
    {
        if ($result->isErrored()) {
            if ($this->isErrored()) {
                return $this->mergeError($result->error);
            }

            return static::new($result);
        }

        return $this->isErrored() ? static::new($this) : static::new($result);
    }

    /**
     * @param Error $error
     * @return Result<Error>
     */
    public function mergeError(Error $error): Result
    {
        $previous = $this->error();
        if ($previous) {
            /** @var Error $previous */
            $error = $previous->merge($error);
        }

        return static::new($error);
    }

    /**
     * @param callable $callback
     * @param mixed $returnIfError
     * @return mixed
     */
    public function extractWith(callable $callback, $returnIfError = null)
    {
        if (!$this->isErrored()) {
            return $callback($this->value);
        }

        return $returnIfError;
    }
}
