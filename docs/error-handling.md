# Error Handling

`syde/dbal` provides a structured approach to error handling through three dedicated classes: `Syde\Dbal\Error`, `Syde\Dbal\ErrorCollector`, and `Syde\Dbal\PhpErrors`.

## Result errors

Every query returns either a `Result` (write operations) or a `ResultSet` (selects). Both expose whether the operation failed:

```php
use Syde\Dbal\Dbal;

$result = Dbal::writeOn('events')->insert([
    'post_id' => 1,
    'title'   => 'My Event',
    'status'  => 'SCHEDULED',
    'type'    => 'party',
]);

if ($result->isErrored()) {
    // query failed - inspect the error
    $error = $result->error(); // returns the Error instance
}

if ($result->isValid()) {
    // query succeeded
    $data = $result->extract();
}
```

For a `ResultSet`, use `hasErrors()` to check whether any rows failed, and `toResult()` to collapse the set into a single `Result` for error inspection:

```php
use Syde\Dbal\Dbal;

$resultSet = Dbal::select('events')->all();

if ($resultSet->hasErrors()) {
    $result = $resultSet->toResult();
    // $result->isErrored() === true
}
```

## Error

`Syde\Dbal\Error` extends PHP's native `\Error` and carries structured error information. Multiple errors can be merged into a chain:

```php
use Syde\Dbal\Error;

// Merge two errors into one
$merged = $errorA->withMerged($errorB);

// Or merge from a Throwable
$merged = $errorA->withMergedThrowable($exception);

// Retrieve all messages across the merged chain (useful for logging)
$messages = $merged->allMessages(); // string[]

// Serialize the full error chain as a string
$serialized = $merged->serialize();
```

## ErrorCollector

`Syde\Dbal\ErrorCollector` accumulates multiple errors before acting on them. It is used internally during query building and can also be used in your own validation logic:

```php
use Syde\Dbal\ErrorCollector;

$collector = new ErrorCollector();

$collector->withError('Value must not be empty');
$collector->withError('Invalid format');

if (!$collector->isEmpty()) {
    // all errors collected - handle or log
}
```

## PhpErrors - catching wpdb notices

WordPress's `$wpdb` can emit PHP notices and warnings instead of structured errors. `PhpErrors::convertToExceptions()` installs a temporary error handler that converts these into catchable exceptions:

```php
use Syde\Dbal\PhpErrors;

$phpErrors = PhpErrors::convertToExceptions();

try {
    // run queries that might produce wpdb PHP notices or warnings
} catch (\ErrorException $e) {
    // handle
} finally {
    $phpErrors->restoreHandler(); // always restore the previous error handler
}
```
