<?php

declare(strict_types=1);

namespace Syde\Dbal;

final class Result
{
    private mixed $value;

    private ?Error $error;

    /**
     * @param mixed $value
     * @return Result
     */
    public static function new(mixed $value): Result
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
     * @param Error|null $error
     */
    private function __construct(mixed $value, ?Error $error)
    {
        $this->value = $value;
        $this->error = $error;
    }

    /**
     * @return bool
     */
    public function isErrored(): bool
    {
        return $this->error !== null;
    }

    /**
     * @return bool
     */
    public function isValid(): bool
    {
        return !$this->isErrored();
    }

    /**
     * @return mixed
     *
     * phpcs:disable Syde.Functions.ReturnTypeDeclaration.NoReturnType
     */
    public function extract()
    {
        $this->assert();

        return $this->value;
    }

    /**
     * @return void
     */
    public function assert(): void
    {
        if ($this->isErrored()) {
            throw $this->error;
        }
    }

    /**
     * @return Error|null
     */
    public function error(): Error|null
    {
        return $this->error ?: null;
    }

    /**
     * @param callable|null $onSuccess
     * @param callable|null $onError
     * @return Result
     */
    public function bind(?callable $onSuccess = null, ?callable $onError = null): Result
    {
        try {
            $callback = $this->error ? $onError : $onSuccess;
            $param = $this->error ?? $this->value;

            return ($callback !== null)
                ? $this->merge(self::new($callback($param)))
                : static::new($this);
        } catch (\Throwable $throwable) {
            return $this->merge(self::new(Error::fromThrowable($throwable)));
        }
    }

    /**
     * @param Result $result
     * @return Result
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
     * @return Result
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
     *
     * @return mixed
     *
     * phpcs:disable Syde.Functions.ReturnTypeDeclaration.NoReturnType
     */
    public function extractWith(callable $callback, mixed $returnIfError = null)
    {
        if (!$this->isErrored()) {
            return $callback($this->value);
        }

        return $returnIfError;
    }
}
