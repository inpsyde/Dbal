<?php

declare(strict_types=1);

namespace Inpsyde\Dbal;

class Transaction
{
    public const REPEATABLE_READ = 1;
    public const READ_COMMITTED = 2;
    public const READ_UNCOMMITTED = 4;
    public const SERIALIZABLE = 8;
    public const READ_WRITE = 32;
    public const READ_ONLY = 64;
    public const CONSISTENT_SNAPSHOT = 256;
    public const DEFAULT = 0;

    /**
     * @var string
     */
    private $mode;

    /**
     * @var string
     */
    private $isolation;

    /**
     * @param int $flags
     * @return \Inpsyde\Dbal\Transaction
     */
    public static function new(int $flags = self::DEFAULT): Transaction
    {
        if ($flags === self::DEFAULT) {
            return new static('', '');
        }

        $mode = '';
        $isolation = '';

        if (($flags & self::READ_ONLY) === self::READ_ONLY) {
            $mode = 'READ ONLY';
        } elseif (($flags & self::READ_WRITE) === self::READ_WRITE) {
            $mode = 'READ WRITE';
        }

        $consistentSnapshot = ($flags & self::CONSISTENT_SNAPSHOT) === self::CONSISTENT_SNAPSHOT;
        if ($consistentSnapshot) {
            $mode = $mode
                ? "WITH CONSISTENT SNAPSHOT, {$mode}"
                : 'WITH CONSISTENT SNAPSHOT';
        }

        switch (true) {
            case $consistentSnapshot:
            case ($flags & self::REPEATABLE_READ) === self::REPEATABLE_READ:
                $isolation = 'REPEATABLE READ';
                break;
            case ($flags & self::READ_COMMITTED) === self::READ_COMMITTED:
                $isolation = 'READ COMMITTED';
                break;
            case ($flags & self::READ_UNCOMMITTED) === self::READ_UNCOMMITTED:
                $isolation = 'READ UNCOMMITTED';
                break;
            case ($flags & self::SERIALIZABLE) === self::SERIALIZABLE:
                $isolation = 'SERIALIZABLE';
                break;
        }

        return new static($mode, $isolation);
    }

    /**
     * @param string $mode
     * @param string $isolation
     */
    private function __construct(string $mode, string $isolation)
    {
        $this->mode = $mode;
        $this->isolation = $isolation;
    }

    /**
     * @param callable $callback
     * @param callable ...$callbacks
     * @return Result
     */
    public function __invoke(callable $callback, callable ...$callbacks): Result
    {
        $wpdb = Dbal::wpdb();

        $suppressErrors = $wpdb->suppress_errors(true);
        $result = Result::new(null);
        $rollback = false;

        $phpErrors = PhpErrors::convertToExceptions();

        try {
            $result = $this->prepareTransaction($result);
            if (!$result->isErrored()) {
                $result = $this->startTransaction($result);
                $result->isErrored() and $rollback = true;
            }

            if (!$result->isErrored()) {
                $result = $this->applyCallback($callback, ...$callbacks);
                $result->isErrored() and $rollback = true;
            }
        } catch (Error $error) {
            $result = $result->mergeError($error);
            $rollback = true;
        } catch (\Throwable $throwable) {
            $result = $result->mergeError(Error::fromThrowable($throwable));
            $rollback = true;
        } finally {
            $rollback and $result = $this->executeQuery('ROLLBACK;', $result);
            $phpErrors->restoreHandler();
            $wpdb->suppress_errors($suppressErrors);

            return $result;
        }
    }

    /**
     * @param Result $result
     * @return Result
     */
    private function prepareTransaction(Result $result): Result
    {
        if ($this->isolation) {
            $result = $this->executeQuery(
                "SET TRANSACTION ISOLATION LEVEL {$this->isolation};",
                $result
            );
        }

        return $result;
    }

    /**
     * @param Result $result
     * @return Result
     */
    private function startTransaction(Result $result): Result
    {
        $startQuery = $this->mode ? "START TRANSACTION {$this->mode};" : 'START TRANSACTION;';

        return $this->executeQuery($startQuery, $result);
    }

    /**
     * @param callable $callback
     * @param callable ...$callbacks
     * @return Result
     */
    private function applyCallback(callable $callback, callable ...$callbacks): Result
    {
        // phpcs:enable Inpsyde.CodeQuality.ReturnTypeDeclaration

        array_unshift($callbacks, $callback);

        $result = Result::new(null);
        foreach ($callbacks as $callback) {
            $result = $result->merge(Result::new($callback($result)));
        }

        /** @var Result $result */

        $wpdb = Dbal::wpdb();

        if ($wpdb->last_error) {
            $result = $result->mergeError(new Error($wpdb->last_error));
        }

        if ($result->isErrored()) {
            return $result;
        }

        return $this->executeQuery('COMMIT;', $result);
    }

    /**
     * @param string $query
     * @param Result $result
     * @return Result
     */
    private function executeQuery(string $query, Result $result): Result
    {
        $wpdb = Dbal::wpdb();

        $queryResult = $wpdb->query($query); // phpcs:ignore
        if ($wpdb->last_error) {
            $result = $result->mergeError(new Error($wpdb->last_error));
            $wpdb->last_error = '';

            return $result;
        }

        if ($queryResult === false) {
            $result = $result->mergeError(new Error("Error executing query: {$query}."));
        }

        return $result;
    }
}
