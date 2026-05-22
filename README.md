# Syde Dbal

_Database abstraction layer for WordPress, on top of wpdb and dbDelta._

[![Static Analysis + Unit tests](https://github.com/inpsyde/Dbal/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/inpsyde/Dbal/actions/workflows/static-analysis.yml) [![Integration Tests](https://github.com/inpsyde/Dbal/actions/workflows/integration-tests.yml/badge.svg)](https://github.com/inpsyde/Dbal/actions/workflows/integration-tests.yml) [![Integration Tests](https://github.com/inpsyde/Dbal/actions/workflows/unit-tests.yml/badge.svg)](https://github.com/inpsyde/Dbal/actions/workflows/unit-tests.yml)

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
