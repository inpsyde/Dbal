# Columns and Column

## Columns

The `Inpsyde\Dbal\Schema\Columns` implementation groups multiple `Inpsyde\Dbal\Schema\Column` instances. This is required when working [Schemas](./04-schemas.md).

```php
use Inpsyde\Dbal\Schema\Columns;
use Inpsyde\Dbal\Schema\Column;

$columns = Columns::new(
    Column::int('my_int'),
    Column::char('my_char')
);
```

## Column

`inpsyde/dbal` brings an implementation for columns which are used in a Schema to create a database table. The main class for this is `Inpsyde\Dbal\Schema\Column` which provides a wide rage of methods:

### Default data types

#### Integers

Types that map numeric data without fractions.

Integers consists of: TINYINT, SMALLINT, MEDIUMINT, INT, BIGINT. All of them allow a second parameter "defaultValue".

```php
Inpsyde\Dbal\Schema\Column::tinyInt('my_tinyInt', 1); // with default "1"
Inpsyde\Dbal\Schema\Column::smallInt('my_smallInt');
Inpsyde\Dbal\Schema\Column::mediumInt('my_mediumInt');
Inpsyde\Dbal\Schema\Column::int('my_int');
Inpsyde\Dbal\Schema\Column::bigInt('my_bitInt');
```

#### Real numbers

Real numbers consists of: FLOAT, DOUBLE and DECIMAL. Each of them has additional options for "precision", "scale" and "defaultValue".

```php
Inpsyde\Dbal\Schema\Column::float('my_float', 5, 5, 1.00034);
Inpsyde\Dbal\Schema\Column::double('my_double');
Inpsyde\Dbal\Schema\Column::decimal('my_decial');
```

#### Date and times

The following date and time formats are supported: DATETIME, TIMESTAMP, TIME, DATE, YEAR. All support a "defaultValue".

```php
Inpsyde\Dbal\Schema\Column::datetime('my_datetime');
Inpsyde\Dbal\Schema\Column::timestamp('my_timestamp', 551260979);
Inpsyde\Dbal\Schema\Column::date('my_date');
Inpsyde\Dbal\Schema\Column::time('my_time');
Inpsyde\Dbal\Schema\Column::year('my_year');
```

#### Strings

The following string formats are supported: CHAR, VARCHAR, TINYTEXT, MEDIUMTEXT, TEXT, LONGTEXT, ENUM, SET.

```php
// without size
Inpsyde\Dbal\Schema\Column::tinyText('my_char');
Inpsyde\Dbal\Schema\Column::mediumText('my_char');
Inpsyde\Dbal\Schema\Column::text('my_char');
Inpsyde\Dbal\Schema\Column::longText('my_char');
```

The CHAR and VARCHAR are supporting a "size" additionally to the "defaultValue".

```php
// with size
Inpsyde\Dbal\Schema\Column::char('my_char', 254, 'lorum ipsum');
Inpsyde\Dbal\Schema\Column::varChar('my_varchar', 254, 'lorum ipsum');
```

ENUM and SET supporting a limited set of values, while the first one of the values is the "defaultValue".

```php
Inpsyde\Dbal\Schema\Column::enum('my_enum', ['DEFAULT', 'FOO', 'BAR']);
Inpsyde\Dbal\Schema\Column::set('my_set', ['DEFAULT', 'FOO', 'BAR']);
```

#### Blob

The following blob formats are supported: TINYBLOB, BLOB, MEDIUMBLOB, LONGBLOB.

```php
Inpsyde\Dbal\Schema\Column::tinyBlob('my_tinyBlob');
Inpsyde\Dbal\Schema\Column::blog('my_blob');
Inpsyde\Dbal\Schema\Column::mediumBlob('my_mediumBlob');
Inpsyde\Dbal\Schema\Column::longBlob('my_longBlob');
```

#### JSON

```php
Inpsyde\Dbal\Schema\Column::json('my_json');
```

#### Bit and Bool

Bit and bool are two more formats which can be used. The BOOL uses BIT under the hood, while BIT allows to set a size with maximum of 63.

```php
Inpsyde\Dbal\Schema\Column::bit('my_bit', 2);
Inpsyde\Dbal\Schema\Column::bool('my_bool', false);
```

#### Binary

VARBINARY and BINARY are two more formats which are supported. Both allow a "size" and "defaultValue".

```php
Inpsyde\Dbal\Schema\Column::binary('my_binary', 255);
Inpsyde\Dbal\Schema\Column::varBinary('my_varBinary', 65535);
```

### Special data types

Additionally to the default formats, `Inpsyde\Dbal\Schema\Column` also provides some pre-configured typical formats:

#### EntityId

The entityId is usually a bigInt which is notNull, unsigned and an autoIncrement. In order to not manually configure these attributes through code, you can just call:

```php
Inpsyde\Dbal\Schema\Column::entityId('my_entityId');
```

#### ForeignId

The foreignId, similar to the entityId has some pre-configured attributes: notNull and unsigned.

```php
Inpsyde\Dbal\Schema\Column::foreignId('my_foreignId');
```

### Attributes

`Inpsyde\Dbal\Schema\Column` additionally supports attributes for the data types.

#### Unsigned

All integer types can have an optional attribute UNSIGNED. Unsigned type can be used to permit only non-negative numbers in a column or when you need a larger upper numeric range for the column. For example, if an INT column is UNSIGNED, the size of the column's range is the same but its endpoints shift from -2147483648 and 2147483647 up to 0 and 4294967295.

```php
Inpsyde\Dbal\Schema\Column::bigInt('my_bigInt')->makeUnsigned();
```

#### Zero fill

```php
Inpsyde\Dbal\Schema\Column::int('my_int')->makeZeroFill();
```

#### Auto increment

```php
Inpsyde\Dbal\Schema\Column::int('my_int')->makeAutoIncrement();
```

#### Current timestamp as default

```php
Inpsyde\Dbal\Schema\Column::timestamp('my_timestamp')->useCurrentTimestampAsDefault();
Inpsyde\Dbal\Schema\Column::datetime('my_datetime')->useCurrentTimestampAsDefault();
```

#### Default timeZone

```php
Inpsyde\Dbal\Schema\Column::timestamp('my_timestamp')->useDefaultTimeZone(new DateTimeZone('utc'));
Inpsyde\Dbal\Schema\Column::datetime('my_datetime')->useDefaultTimeZone(new DateTimeZone('utc'));
```

#### Not null

```php
Inpsyde\Dbal\Schema\Column::varChar('my_varChar')->makeNotNull();
```

#### Charset collation

```php
Inpsyde\Dbal\Schema\Column::varChar('my_varChar')->useCharsetCollation('utf-8');
```

### Special Attributes

#### Store serialized

#### Retrieve as DateTime