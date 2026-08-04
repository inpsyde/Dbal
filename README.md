# Syde Dbal

_Database abstraction layer for WordPress, on top of wpdb and dbDelta._

[![Static Analysis + Unit tests](https://github.com/inpsyde/Dbal/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/inpsyde/Dbal/actions/workflows/static-analysis.yml) [![Integration Tests](https://github.com/inpsyde/Dbal/actions/workflows/integration-tests.yml/badge.svg)](https://github.com/inpsyde/Dbal/actions/workflows/integration-tests.yml) [![Integration Tests](https://github.com/inpsyde/Dbal/actions/workflows/unit-tests.yml/badge.svg)](https://github.com/inpsyde/Dbal/actions/workflows/unit-tests.yml)

`wpdb` gives you direct SQL access, but that comes with tradeoffs. Dbal exists to remove:

- **Security by default.** WordPress queries are unescaped unless you remember to call `prepare()` yourself - an opt-in safeguard that's easy to forget. Dbal escapes automatically, so secure queries are the default, not something you have to remember to do.
- **Typed results, not just strings.** Because Dbal knows your schema, query results come back as the type your schema declares - a column defined as `INT` returns an actual `int`, a `FLOAT` column returns a `float`. No more casting results by hand just to get the type you already told the schema to expect.
- **Schema migrations without the pain.** Creating - and especially upgrading - custom database tables in WordPress is notoriously painful, which is exactly why so many projects give up and cram everything into `wp_posts` instead. With Dbal you define your schema programmatically; when that definition changes, Dbal handles the upgrade for you.

## New to Dbal?

- [Getting started](docs/getting-started.md) - the best first stop: introduces the `Dbal` entry point and how the package is structured.
- [Learning by example](docs/learning-by-example.md) - a complete, realistic walkthrough of defining a table and running the full range of CRUD operations.
- [Columns & Column](docs/columns-and-column.md) - how to define table columns when building a schema.
- [Indexes & Index](docs/indexes-and-index.md) - how to define primary, unique, and regular indexes for a schema.
- [Schemas](docs/schemas.md) - how to implement `InstallableSchema` to create and upgrade custom database tables.
- [Querying](docs/querying.md) - how to build safe, code-based queries and work with `Result`/`ResultSet`.
- [Error handling](docs/error-handling.md) - the structured approach to handling query errors via `Error`, `ErrorCollector`, and `PhpErrors`.

## Requirements

- PHP >= 8.0
- WordPress (with `wpdb` available)

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

## Copyright and License

This package is [free software](https://www.gnu.org/philosophy/free-sw.en.html) distributed under the terms of the GNU General Public License version 2 or (at your option) any later version. For the full license, see [LICENSE](./LICENSE).
