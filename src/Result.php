<?php

declare(strict_types=1);

namespace Inpsyde\Dbal;

use Inpsyde\Dbal\Query\Error;
use Inpsyde\Dbal\Query\ErrorCollector;

class Result
{
    /**
     * @var mixed
     */
    private $value;

    /**
     * @var \Inpsyde\Dbal\Query\ErrorCollector|null
     */
    private $errors;

    /**
     * @param mixed $value
     * @return \Inpsyde\Dbal\Result
     *
     * phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration
     */
    public static function new($value): Result
    {
        // phpcs:enable Inpsyde.CodeQuality.ArgumentTypeDeclaration
        if ($value instanceof ErrorCollector) {
            return $value->isEmpty() ? new static(null, null) : new static(null, $value);
        }

        if ($value instanceof Result) {
            return new static($value->value, $value->errors);
        }

        $maybeError = static::maybeCastToErrors($value);
        if ($maybeError) {
            return new static(null, $maybeError);
        }

        return new static($value, null);
    }

    /**
     * @param $value
     * @return \Inpsyde\Dbal\Query\ErrorCollector|null
     *
     * @psalm-suppress MissingParamType
     * phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration
     */
    private static function maybeCastToErrors($value): ?ErrorCollector
    {
        // phpcs:enable Inpsyde.CodeQuality.ArgumentTypeDeclaration
        if ($value instanceof Error) {
            $errors = new ErrorCollector();
            $errors->pushError($value);
            $value = $errors;
        }

        if ($value instanceof \Throwable) {
            $errors = new ErrorCollector();
            $errors->withError($value->getMessage());
            $value = $errors;
        }

        if (!$value instanceof ErrorCollector) {
            return null;
        }

        return $value->isEmpty() ? null : $value;
    }

    /**
     * @param mixed $value
     * @param \Inpsyde\Dbal\Query\ErrorCollector|null $errors
     *
     * phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration
     */
    private function __construct($value, ?ErrorCollector $errors)
    {
        // phpcs:enable Inpsyde.CodeQuality.ArgumentTypeDeclaration

        $this->value = $value;
        $this->errors = $errors;
    }

    /**
     * @return bool
     *
     * @psalm-assert-if-false null $this->errors
     * @psalm-assert-if-true ErrorCollector $this->errors
     */
    public function isErrored(): bool
    {
        return $this->errors !== null;
    }

    /**
     * @return bool
     *
     * @psalm-assert-if-true null $this->errors
     * @psalm-assert-if-false ErrorCollector $this->errors
     */
    public function isValid(): bool
    {
        return !$this->isErrored();
    }

    /**
     * @return mixed
     *
     * phpcs:disable Inpsyde.CodeQuality.ReturnTypeDeclaration
     */
    public function extract()
    {
        //phpcs:enable Inpsyde.CodeQuality.ReturnTypeDeclaration
        if ($this->isErrored()) {
            $this->errors->assert();
        }

        return $this->value;
    }

    /**
     * @param callable|null $onSuccess
     * @param callable|null $onError
     * @return \Inpsyde\Dbal\Result
     */
    public function bind(?callable $onSuccess, ?callable $onError): Result
    {
        if ($this->isValid()) {
            return $onSuccess ? static::new($onSuccess($this->value)) : static::new($this->value);
        }

        $value = $this->value;
        if ($onError) {
            $maybeError = static::maybeCastToErrors($onError($this->errors));
            $maybeError and $value = $maybeError;
        }

        return static::new($value);
    }
}
