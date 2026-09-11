# Indexes and Index

## Indexes

The `Syde\Dbal\Schema\Indexes` implementation groups multiple `Syde\Dbal\Schema\Index` instances. This being required for creating a [Schema](./schemas.md).

```php
use Syde\Dbal\Schema\Indexes;
use Syde\Dbal\Schema\Index;

Indexes::new(
    Index::primary('id'),
    Index::unique('uuid'),
    Index::key('type'),
);
```

## Index

Indexes are used to find rows with specific column values quickly. Without an index, the database engine must begin with the first row and then read through the entire table to find the relevant rows. The larger the table, the more this costs. If the table has an index for the columns in question, the database engine can quickly determine the position to seek to in the middle of the data file without having to look at all the data. This is much faster than reading every row sequentially.

An Index references to a column (see [./columns-and-column.md](columns-and-column.md)) name.

### Primary
The primary key for a table represents the column or set of columns that you use in your most vital queries. It has an associated index, for fast query performance.

```php
use Syde\Dbal\Schema\Index;

Index::primary('id');
```
### Unique

When a Unique Key is applied on a certain field of a database table, than it does not allow duplicate values to be inserted in that column, i.e. it is used to uniquely identify a record in a table.

```php
use Syde\Dbal\Schema\Index;

Index::unique('uuid');
```

### Key

A Key is a not unique and not primary key and is mostly used to optimize performance for accessing data.

```php
use Syde\Dbal\Schema\Index;

Index::key('type');
```
