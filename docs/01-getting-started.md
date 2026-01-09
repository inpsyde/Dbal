# Getting started

`inpsyde/dbal` is a database abstraction layer for WordPress built on top of `wpdb` and `dbDelta`. It provides a structured and extensible way to define schemas, interact with database tables, and perform read/write operations in a consistent manner.

The main entry point of the package is `Inpsyde\Dbal\Dbal`. This class exposes all relevant services through its public API and acts as the central access point for database-related functionality.

Throughout this documentation, we will use a simple custom database table called `events` with the following fields:

| name       | type     | note                                             |
|------------|----------|--------------------------------------------------|
| id         | bigInt   | primary, unsigned, autoincrement                 |
| uuid       | varChar  | entityId                                         |
| post_id    | bigInt   | not null, references to wp_posts                 |
| title      | varChar  | not null,                                        |
| start_date | datetime |                                                  |
| end_date   | datetime |                                                  |
| timezone   | varChar  | not null, defaults to 'UTC'                      |
| status     | enum     | `['SCHEDULED', 'HAPPENING', 'PAST', 'CANCELED']` |
| type       | varChar  | not null                                         |


## Accessing wpdb

The `wpdb` object is automatically fetched by `inpsyde/dbal` and can be accessed:

```php
$wpdb = Inpsyde\Dbal\Dbal::wpdb();
```

## Readiness Check

To verify whether the package is properly initialized and ready for use, you can perform a readiness check:

```php
use Inpsyde\Dbal\Dbal;

$isReady = Dbal::isReady(); // false
```

This is especially useful during early bootstrap phases or when working with custom initialization logic.

## Caching

`inpsyde/dbal` includes a lightweight caching layer based on the WordPress Core `wp_cache_*` functions. This cache is used internally in several places and can also be accessed directly:

```php
use Inpsyde\Dbal\Dbal;

$cache = Dbal::cache();
```

## Schema Finder

The `Inpsyde\Dbal\Schema\SchemaFinder` allows you to locate both WordPress core schemas (WpSchema) and custom, installable schemas (Schema) available in your installation:

```php
use Inpsyde\Dbal\Dbal;

$schemaFinder = Dbal::schemaFinder();

$schemaFinder->findCoreSchema('posts'); // returns a WpSchema for `wp_posts`
$schemaFinder->findSchema('events');    // returns an InstallableSchema (used in this documentation)
```

## Querying

`Inpsyde\Dbal\Dbal` provides convenient shortcuts for building and executing database queries. A more detailed explanation of the querying API can be found in [./05-querying.md](./05-querying.md).

**Basic examples:**

```php
use Inpsyde\Dbal\Dbal;

$resultSet = Dbal::select('events')
    ->all();

$result = Dbal::writeOn('events')
    ->insert([
        'post_id' => 5,
        'title'   => 'New years party!',
        'status'  => 'SCHEDULED',
        'type'    => 'party',
    ]);

$result = Dbal::deleteFrom('events')
    ->where(['post_id' => 5]);
```