<?php

declare(strict_types=1);

namespace Inpsyde\Dbal;

final class ErrorCollector
{
    private ?Error $error = null;

    /**
     * @return bool
     *
     * @phpstan-impure
     * @psalm-assert-if-true null $this->error
     * @psalm-assert-if-false Error $this->error
     */
    public function isEmpty(): bool
    {
        return $this->error === null;
    }

    /**
     * @return Error|null
     */
    public function error(): ?Error
    {
        return $this->error;
    }

    /**
     * @param ErrorCollector $errors
     * @return void
     */
    public function pushFrom(ErrorCollector $errors): void
    {
        if (!$errors->error) {
            return;
        }

        $this->error = $this->error ? $this->error->merge($errors->error) : $errors->error();
    }

    /**
     * @param string $error
     */
    public function withError(string $error): void
    {
        $this->pushError(new Error($error));
    }

    /**
     * @param Error $error
     */
    public function pushError(Error $error): void
    {
        $this->error = $this->error ? $this->error->merge($error) : $error;
    }

    /**
     * @return void
     */
    public function assert(): void
    {
        if ($this->error) {
            throw $this->error;
        }
    }
}
