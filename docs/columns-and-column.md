# Columns and Column

## Columns

The `Inpsyde\Dbal\Schema\Columns` implementation groups multiple `Inpsyde\Dbal\Schema\Column` instances. This is required when working [Schemas](./schemas.md).

```php
use Inpsyde\Dbal\Schema\Columns;
use Inpsyde\Dbal\Schema\Column;

$columns = Columns::new(
    Column::int('my_int'),
    Column::char('my_char')
);
```

## Column

`inpsyde/dbal` brings an implementation for columns which are used in a Schema to create a database table. The main class for this is `Inpsyde\Dbal\Schema\Column` which provides a wide range of methods:

### Default data types

#### Integers

Types that map numeric data without fractions.

Integers consists of: TINYINT, SMALLINT, MEDIUMINT, INT, BIGINT. All of them allow a second parameter "defaultValue".

```php
use Inpsyde\Dbal\Schema\Column;

Column::tinyInt('my_tinyInt', 1); // with default "1"
Column::smallInt('my_smallInt');
Column::mediumInt('my_mediumInt');
Column::int('my_int');
Column::bigInt('my_bigInt');
```

#### Real numbers

Real numbers consists of: FLOAT, DOUBLE and DECIMAL. Each of them has additional options for "precision", "scale" and "defaultValue".

```php
use Inpsyde\Dbal\Schema\Column;

Column::float('my_float', 5, 5, 1.00034);
Column::double('my_double');
Column::decimal('my_decimal');
```

#### Date and times

The following date and time formats are supported: DATETIME, TIMESTAMP, TIME, DATE, YEAR. All support a "defaultValue".

```php
use Inpsyde\Dbal\Schema\Column;

Column::datetime('my_datetime');
Column::timestamp('my_timestamp', 551260979);
Column::date('my_date');
Column::time('my_time');
Column::year('my_year');
```

#### Strings

The following string formats are supported: CHAR, VARCHAR, TINYTEXT, MEDIUMTEXT, TEXT, LONGTEXT, ENUM, SET.

```php
use Inpsyde\Dbal\Schema\Column;

// without size
Column::tinyText('my_tinyText');
Column::mediumText('my_mediumText');
Column::text('my_text');
Column::longText('my_longText');
```

The CHAR and VARCHAR are supporting a "size" additionally to the "defaultValue".

```php
use Inpsyde\Dbal\Schema\Column;

// with size
Column::char('my_char', 254, 'lorum ipsum');
Column::varChar('my_varchar', 254, 'lorum ipsum');
```

ENUM and SET supporting a limited set of values, while the first one of the values is the "defaultValue".

```php
use Inpsyde\Dbal\Schema\Column;

Column::enum('my_enum', 'DEFAULT', 'FOO', 'BAR');
Column::set('my_set', 'DEFAULT', 'FOO', 'BAR');
```

#### Blob

The following blob formats are supported: TINYBLOB, BLOB, MEDIUMBLOB, LONGBLOB.

```php
use Inpsyde\Dbal\Schema\Column;

Column::tinyBlob('my_tinyBlob');
Column::blob('my_blob');
Column::mediumBlob('my_mediumBlob');
Column::longBlob('my_longBlob');
```

#### JSON

```php
use Inpsyde\Dbal\Schema\Column;

Column::json('my_json');
```

#### Bit and Bool

Bit and bool are two more formats which can be used. The BOOL uses BIT under the hood, while BIT allows to set a size with maximum of 63.

```php
use Inpsyde\Dbal\Schema\Column;

Column::bit('my_bit', 2);
Column::bool('my_bool', false);
```

#### Binary

VARBINARY and BINARY are two more formats which are supported. Both allow a "size" and "defaultValue".

```php
use Inpsyde\Dbal\Schema\Column;

Column::binary('my_binary', 255);
Column::varBinary('my_varBinary', 65535);
```

### Special data types

Additionally to the default formats, `Inpsyde\Dbal\Schema\Column` also provides some pre-configured typical formats:

#### EntityId

The entityId is usually a bigInt which is notNull, unsigned and an autoIncrement. In order to not manually configure these attributes through code, you can just call:

```php
use Inpsyde\Dbal\Schema\Column;

Column::entityId('my_entityId');
```

#### ForeignId

The foreignId, similar to the entityId has some pre-configured attributes: notNull and unsigned.

```php
use Inpsyde\Dbal\Schema\Column;

Column::foreignId('my_foreignId');
```

### Attributes

`Inpsyde\Dbal\Schema\Column` additionally supports attributes for the data types.

#### Unsigned

All integer types can have an optional attribute UNSIGNED. Unsigned type can be used to permit only non-negative numbers in a column or when you need a larger upper numeric range for the column. For example, if an INT column is UNSIGNED, the size of the column's range is the same but its endpoints shift from -2147483648 and 2147483647 up to 0 and 4294967295.

```php
use Inpsyde\Dbal\Schema\Column;

Column::bigInt('my_bigInt')->makeUnsigned();
```

#### Zero fill

ZEROFILL pads the displayed value with leading zeros up to the column's display width. For example, a value of `42` in a column with display width `8` renders as `00000042`. The stored value is unaffected - only the display representation changes. Note that ZEROFILL implicitly applies UNSIGNED, so negative values cannot be stored in a zero-filled column.

```php
use Inpsyde\Dbal\Schema\Column;

Column::int('my_int')->makeZeroFill(8); // display width, e.g. "00000042"
```

#### Auto increment

When set, MySQL automatically assigns an incrementing integer value to this column for each new inserted row, starting from `1`. It is typically used for primary key columns to guarantee uniqueness without manual value assignment. Only one AUTO_INCREMENT column is allowed per table, and it must be part of an index.

```php
use Inpsyde\Dbal\Schema\Column;

Column::int('my_int')->makeAutoIncrement();
```

#### Current timestamp as default

Sets `CURRENT_TIMESTAMP` as the column's default value, so new rows automatically receive the current date and time when no explicit value is provided on insert. Applies to `TIMESTAMP` and `DATETIME` columns. Useful for `created_at`-style audit fields.

```php
use Inpsyde\Dbal\Schema\Column;

Column::timestamp('my_timestamp')->useCurrentTimestampAsDefault();
Column::datetime('my_datetime')->useCurrentTimestampAsDefault();
```

#### Default timeZone

Sets the timezone context used when interpreting date/time values for this column. When a default timezone is configured, stored values are normalised against it on read and write, which is particularly useful in applications that operate across multiple timezones. Applies to `TIMESTAMP` and `DATETIME` columns.

```php
use Inpsyde\Dbal\Schema\Column;

Column::timestamp('my_timestamp')->useDefaultTimeZone(new DateTimeZone('UTC'));
Column::datetime('my_datetime')->useDefaultTimeZone(new DateTimeZone('UTC'));
```

#### Not null

Enforces that the column must always contain a value - `NULL` is not permitted. Any `INSERT` or `UPDATE` that omits this column or explicitly sets it to `NULL` will fail at the database level. Use this for required fields where the absence of a value would be invalid.

```php
use Inpsyde\Dbal\Schema\Column;

Column::varChar('my_varChar')->makeNotNull();
```

#### Charset collation

Sets the character set and collation for a string column, overriding the table or database defaults. The character set defines which characters can be stored - `utf8mb4` supports the full Unicode range including emoji and supplementary characters. The collation controls how values are compared and sorted - `utf8mb4_unicode_ci` is case-insensitive and accent-insensitive, which is the standard choice for most WordPress installations. Both parameters are optional; omitting them falls back to the table or database default.

```php
use Inpsyde\Dbal\Schema\Column;

Column::varChar('my_varChar')->useCharsetCollation('utf8mb4', 'utf8mb4_unicode_ci');
```

### Special Attributes

#### Store serialized

When enabled, the column value is automatically serialized before being written to the database and unserialized when read back. Useful for storing arrays or objects in a single column.

```php
use Inpsyde\Dbal\Schema\Column;

Column::text('my_data')->storeSerialized();
```

`storeSerialized()` is supported by `char`, `varchar`, `tinytext`, `mediumtext`, `text` and `longtext`.

#### Retrieve as DateTime

When enabled, the stored string value is automatically cast to a `DateTime` instance when the column is read. Applies to date/time column types.

```php
use Inpsyde\Dbal\Schema\Column;

Column::datetime('start_date')->retrieveAsDateTime();
Column::timestamp('created_at')->retrieveAsDateTime();
```
