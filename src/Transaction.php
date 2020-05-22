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
     * @return Result
     */
    public function __invoke(callable $callback): Result
    {
        $wpdb = Dbal::wpdb();

        $suppressErrors = $wpdb->suppress_errors(true);
        $errors = new ErrorCollector();
        $result = $errors;
        $rollback = false;

        $phpErrors = PhpErrors::convertToExceptions();

        try {
            $started = $this->startTransaction($errors);

            [$result, $rollback] =  $errors->isEmpty()
                ? $this->applyCallback($callback, $errors)
                : [$errors, $started];

            if (!$rollback && $result === null) {
                $errors->withError('Transaction callback did not signal any result.');
                $result = $errors;
            }
        } catch (Error $error) {
            $errors->pushError($error);
            $rollback = true;
            $result = $errors;
        } catch (\Throwable $throwable) {
            $errors->withError($throwable->getMessage());
            $rollback = true;
            $result = $errors;
        } finally {
            $rollback and $this->executeQuery('ROLLBACK;', $errors);

            $phpErrors->restoreHandler();
            $wpdb->suppress_errors($suppressErrors);

            return Result::new($result);
        }
    }

    /**
     * @param ErrorCollector $errors
     * @return bool
     */
    private function startTransaction(ErrorCollector $errors): bool
    {
        if ($this->isolation) {
            $this->executeQuery("SET TRANSACTION ISOLATION LEVEL {$this->isolation};", $errors);
        }

        if (!$errors->isEmpty()) {
            return false;
        }

        $startQuery = $this->mode ? "START TRANSACTION {$this->mode};" : 'START TRANSACTION;';
        $this->executeQuery($startQuery, $errors);

        return true;
    }

    /**
     * @param callable $callback
     * @param ErrorCollector $errors
     * @return array{0:mixed, 1:bool}
     *
     * phpcs:disable Inpsyde.CodeQuality.ReturnTypeDeclaration
     */
    private function applyCallback(callable $callback, ErrorCollector $errors): array
    {
        // phpcs:enable Inpsyde.CodeQuality.ReturnTypeDeclaration

        $success = null;

        // phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration
        $signal = static function ($result) use (&$success) {
            // phpcs:enable Inpsyde.CodeQuality.ArgumentTypeDeclaration
            $success = $result;
        };

        $callback($signal, $errors);

        $wpdb = Dbal::wpdb();

        if ($wpdb->last_error) {
            $errors->withError($wpdb->last_error);
        }

        if (!$errors->isEmpty()) {
            return [$errors, true];
        }

        $this->executeQuery('COMMIT;', $errors);

        return [$success, !$errors->isEmpty()];
    }

    /**
     * @param string $query
     * @param ErrorCollector $errors
     * @return void
     */
    private function executeQuery(string $query, ErrorCollector $errors): void
    {
        $wpdb = Dbal::wpdb();

        $result = $wpdb->query($query); // phpcs:ignore
        if ($wpdb->last_error) {
            $errors->withError($wpdb->last_error);
            $wpdb->last_error = '';

            return;
        }

        if ($result === false) {
            $errors->withError("Error executing query: {$query}.");
        }
    }
}
