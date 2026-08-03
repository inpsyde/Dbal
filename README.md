# Syde Dbal

_Database abstraction layer for WordPress, on top of wpdb and dbDelta._

[![Static Analysis + Unit tests](https://github.com/inpsyde/Dbal/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/inpsyde/Dbal/actions/workflows/static-analysis.yml) [![Integration Tests](https://github.com/inpsyde/Dbal/actions/workflows/integration-tests.yml/badge.svg)](https://github.com/inpsyde/Dbal/actions/workflows/integration-tests.yml) [![Integration Tests](https://github.com/inpsyde/Dbal/actions/workflows/unit-tests.yml/badge.svg)](https://github.com/inpsyde/Dbal/actions/workflows/unit-tests.yml)

`wpdb` gives you direct SQL access, but that comes with tradeoffs. Dbal exists to remove:

- **Security by default.** WordPress queries are unescaped unless you remember to call `prepare()` yourself - an opt-in safeguard that's easy to forget. Dbal escapes automatically, so secure queries are the default, not something you have to remember to do.
- **Typed results, not just strings.** Because Dbal knows your schema, query results come back as the type your schema declares - a column defined as `INT` returns an actual `int`, a `FLOAT` column returns a `float`. No more casting results by hand just to get the type you already told the schema to expect.
- **Schema migrations without the pain.** Creating - and especially upgrading - custom database tables in WordPress is notoriously painful, which is exactly why so many projects give up and cram everything into `wp_posts` instead. With Dbal you define your schema programmatically; when that definition changes, Dbal handles the upgrade for you.

New to Dbal? [Learning by example](docs/07-learning-by-example.md) is the fastest way to see these three things in action before going deeper into the API reference below.

## Requirements

- PHP >= 8.2
- WordPress (with `wpdb` available)

To run the integration test suite locally, you'll also need the `pdo_sqlite` PHP extension - it powers the SQLite-backed WordPress environment `composer tests:integration` boots via [`syde/wp-phpunit-integration`](https://github.com/inpsyde/wp-phpunit-integration), no MySQL or Docker required.

## Installation

```bash
composer require inpsyde/dbal
```

## Quick Start

```php
use Inpsyde\Dbal\Dbal;

// Select all events
$resultSet = Dbal::select('events')->all();

// Insert a record
$result = Dbal::writeOn('events')->insert([
    'post_id' => 1,
    'title'   => 'My Event',
    'status'  => 'SCHEDULED',
    'type'    => 'party',
]);

// Delete a record
Dbal::deleteFrom('events')->where(['id' => 1]);
```

See the [documentation](docs/) for the full API.

## Documentation

- [Getting started](docs/01-getting-started.md)
- [Columns & Column](docs/02-columns-and-column.md)
- [Indexes & Index](docs/03-indexes-and-index.md)
- [Schemas](docs/04-schemas.md)
- [Querying](docs/05-querying.md)
- [Error Handling](docs/06-error-handling.md)
- [Learning by example](docs/07-learning-by-example.md)

## Copyright and License

This package is [free software](https://www.gnu.org/philosophy/free-sw.en.html) distributed under the terms of the GNU General Public License version 2 or (at your option) any later version. For the full license, see [LICENSE](./LICENSE).
