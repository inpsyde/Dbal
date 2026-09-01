# Querying

Normally, in WordPress, creating queries happens through `$wpdb->prepare($sql, $data)` and `$wpdb->get_results($sql)`. While this is still a valid and safe way to query your database, `inpsyde/dbal` helps you to write a more code-based way of queries with automatic escaping through the WordPress API. In the following examples we will compare "normal" queries through WordPress with queries written in `inpsyde/dbal` based on our `events` table we used in our [Schema](schemas.md) example.

## Result and ResultSet

Before writing queries it helps to understand what `inpsyde/dbal` returns. Normally, `$wpdb->get_results()` returns mixed values: `object`, `array` or `null`. Getting additional information about the query executed is not present in this return value.

`inpsyde/dbal` introduces two objects to have more consistent control and context over the response: `Inpsyde\Dbal\Result` and `Inpsyde\Dbal\Query\ResultSet`.

### ResultSet

The `Inpsyde\Dbal\Query\ResultSet` is used for `Inpsyde\Dbal\Query\Select` to return an iterable collection of `Inpsyde\Dbal\Result`.

```php
use Inpsyde\Dbal\Dbal;
use Inpsyde\Dbal\Query\ResultSet;

/** @var ResultSet $resultSet */
$resultSet = Dbal::select('events')->all();

foreach ($resultSet as $result) {
    /**
     * @var object{
     *     id: int,
     *     post_id: int,
     *     start_date: DateTime,
     *     end_date: DateTime,
     *     timezone: string,
     *     status: 'SCHEDULED'|'HAPPENING'|'PAST'|'CANCELED',
     *     type: string
     * } $data
     */
    $data = $result->extract();
}
```

`ResultSet::map()` lets you transform each row into a typed object before iterating:

```php
use Inpsyde\Dbal\Query\ResultSet;
use Inpsyde\Dbal\Result;

/** @var ResultSet $resultSet */
$resultSet->map(static function (Result $result): Event {
    return Event::new($result->extract());
});

foreach ($resultSet as $result) {
    /** @var Event $result */
}
```

If any rows failed, `ResultSet::errored()` returns only the errored `Result` instances:

```php
foreach ($resultSet->errored() as $error) {
    // handle errored rows
}
```

### Result

`Inpsyde\Dbal\Result` is used for a single return value from `INSERT`, `UPDATE`, or `DELETE` operations.

```php
use Inpsyde\Dbal\Dbal;
use Inpsyde\Dbal\Result;

/** @var Result $result */
$result = Dbal::writeOn('events')->insert(
    [
        'post_id' => 1,
        'title'   => 'My Party',
        'status'  => 'SCHEDULED',
        'type'    => 'event',
    ]
);

$result->isErrored(); // true if the query failed
$result->isValid();   // true if the query succeeded
$data = $result->extract();
```

See [Error Handling](error-handling.md) for details on working with errored results.

---

## Select

For `SELECT` queries you start with invoking the `select()` method.

```php
use Inpsyde\Dbal\Dbal;

Dbal::select('events');

// vs WordPress core:
$sql = $wpdb->prepare('SELECT * FROM events');
$wpdb->get_results($sql);
```

To fetch all rows call `->all()`, which returns a `ResultSet`. To fetch only the first matching row, call `->pickFirst()` on the result set:

```php
use Inpsyde\Dbal\Dbal;

$resultSet = Dbal::select('events')->all();            // ResultSet of all rows
$first     = Dbal::select('events')->pickFirst();      // ResultSet containing at most one row
$result    = Dbal::select('events')->pickFirst()->first(); // Result - first row directly
```

### Table alias

The `select()` method takes an optional second parameter with which a table alias can be specified.

```php
use Inpsyde\Dbal\Dbal;

Dbal::select('events', 'e');

// vs WordPress core:
$sql = $wpdb->prepare('SELECT * FROM events AS e');
$wpdb->get_results($sql);
```

### Limit columns returned

By default `Dbal::select()` produces `SELECT *`. Use `cols()` to restrict which columns are returned.

```php
use Inpsyde\Dbal\Dbal;

Dbal::select('events')->cols('uuid', 'title', 'type');

// vs WordPress core:
$sql = $wpdb->prepare('SELECT uuid, title, type FROM events');
$wpdb->get_results($sql);
```

### WHERE clause

```php
use Inpsyde\Dbal\Dbal;
use Inpsyde\Dbal\Query\Where;

Dbal::select('events')->where('post_id', 5, Where::EQ);

// vs WordPress core:
$sql = $wpdb->prepare('SELECT * FROM events WHERE post_id = %d', [5]);
$wpdb->get_results($sql);
```

The following operators are supported:

| constant            | operator   |
|---------------------|------------|
| `Where::EQ`         | `=`        |
| `Where::NOT_EQ`     | `!=`       |
| `Where::GREATER`    | `>`        |
| `Where::GREATER_EQ` | `>=`       |
| `Where::LESS`       | `<`        |
| `Where::LESS_EQ`    | `<=`       |
| `Where::IS`         | `IS`       |
| `Where::IS_NOT`     | `IS NOT`   |
| `Where::IN`         | `IN`       |
| `Where::NOT_IN`     | `NOT IN`   |
| `Where::LIKE`       | `LIKE`     |
| `Where::NOT_LIKE`   | `NOT LIKE` |

Multiple conditions can be chained with `andWhere()` and `orWhere()`:

```php
use Inpsyde\Dbal\Dbal;
use Inpsyde\Dbal\Query\Where;

// AND - both conditions must match
Dbal::select('events')
    ->where('status', 'SCHEDULED', Where::EQ)
    ->andWhere('post_id', 5, Where::EQ);

// OR - either condition can match
Dbal::select('events')
    ->where('status', 'SCHEDULED', Where::EQ)
    ->orWhere('status', 'HAPPENING', Where::EQ);

// vs WordPress core:
$sql = $wpdb->prepare(
    'SELECT * FROM events WHERE status = %s AND post_id = %d',
    ['SCHEDULED', 5]
);
$wpdb->get_results($sql);
```

### GROUP BY clause

The `SELECT` statement can be specified with a `GROUP BY` clause.

```php
use Inpsyde\Dbal\Dbal;

Dbal::select('events')->groupBy('post_id');

// vs WordPress core:
$sql = $wpdb->prepare('SELECT * FROM events GROUP BY post_id');
$wpdb->get_results($sql);
```

### Join clauses

For `SELECT` queries you can generate `INNER` and `LEFT` joins.

**`innerJoin(table, localColumn, foreignColumn)`** - basic inner join:

```php
use Inpsyde\Dbal\Dbal;

Dbal::select('events', 'e')->innerJoin('posts', 'post_id', 'ID');

// vs WordPress core:
$sql = $wpdb->prepare('SELECT * FROM events AS e INNER JOIN wp_posts ON e.post_id = wp_posts.ID');
$wpdb->get_results($sql);
```

**`innerJoinWhere(table, localColumn, foreignColumn, whereColumn, value, operator)`** - inner join with a `WHERE` filter applied to the joined table:

```php
use Inpsyde\Dbal\Query\Where;

Dbal::select('events', 'e')
    ->innerJoinWhere('posts', 'post_id', 'ID', 'post_status', 'publish', Where::EQ);

// Equivalent to:
// INNER JOIN wp_posts ON e.post_id = wp_posts.ID AND wp_posts.post_status = 'publish'
```

**`leftJoin(table, localColumn, foreignColumn)`** - left join (rows with no match in the joined table are included):

```php
Dbal::select('events', 'e')->leftJoin('posts', 'post_id', 'ID');
```

**`leftJoinWhere(table, localColumn, foreignColumn, whereColumn, value, operator)`** - left join with a `WHERE` filter on the joined table:

```php
Dbal::select('events', 'e')
    ->leftJoinWhere('posts', 'post_id', 'ID', 'post_status', 'publish', Where::EQ);
```

**`joinViaPivot(pivotTable, localColumn, pivotLocalColumn, foreignTable, pivotForeignColumn, foreignColumn)`** - many-to-many join through a pivot table:

```php
// Join events → event_categories (pivot) → categories
Dbal::select('events', 'e')
    ->joinViaPivot('event_categories', 'id', 'event_id', 'categories', 'category_id', 'id');

// Equivalent to:
// INNER JOIN event_categories ON e.id = event_categories.event_id
// INNER JOIN categories ON event_categories.category_id = categories.id
```

**`innerJoinWith()`** and **`leftJoinWith()`** accept a callable for programmatic join configuration when the join conditions are not known at compile time.

### Order-By clause

The `orderBy()` method adds an expression to the `ORDER BY` clause.

```php
use Inpsyde\Dbal\Dbal;
use Inpsyde\Dbal\Query\Select;

Dbal::select('events')->orderBy('start_date', Select::ASC);

// vs WordPress core:
$sql = $wpdb->prepare('SELECT * FROM events ORDER BY start_date ASC');
$wpdb->get_results($sql);
```

### Limit clause

The `LIMIT` clause can be used to paginate through your database table or limit the result to a specific number of rows.

```php
use Inpsyde\Dbal\Dbal;

$limit  = 5;
$offset = 0;
Dbal::select('events')->limit($limit, $offset);

// vs WordPress core:
$sql = $wpdb->prepare('SELECT * FROM events LIMIT %d, %d', [$limit, $offset]);
$wpdb->get_results($sql);
```

---

## Update

For `UPDATE` queries use the `writeOn()` method.

**`update(data, where)`** - update rows matching a column/value pair:

```php
use Inpsyde\Dbal\Dbal;

$result = Dbal::writeOn('events')->update(
    [
        'post_id' => 5,
        'title'   => 'My Party',
        'status'  => 'SCHEDULED',
        'type'    => 'event',
    ],
    ['id' => 1]
);

// vs WordPress core:
$wpdb->update(
    'events',
    ['post_id' => 5, 'title' => 'My Party', 'status' => 'SCHEDULED', 'type' => 'event'],
    ['id' => 1],
    ['%d', '%s', '%s', '%s'],
    ['%d']
);
```

**`updateWhere(data, Where)`** - update using a full `Where` expression for more complex conditions:

```php
use Inpsyde\Dbal\Dbal;
use Inpsyde\Dbal\Query\Where;

$result = Dbal::writeOn('events')->updateWhere(
    ['status' => 'PAST'],
    Where::new()->with('end_date', new DateTime('-1 day'), Where::LESS)
);
```

---

## Create

For `INSERT` queries use the `writeOn()` method.

**`insert(data)`** - insert a single row:

```php
use Inpsyde\Dbal\Dbal;

$result = Dbal::writeOn('events')->insert(
    [
        'post_id' => 1,
        'title'   => 'My Party',
        'status'  => 'SCHEDULED',
        'type'    => 'event',
    ]
);

$data = $result->extract();
$data->inserted_id; // ID of the newly inserted row

// vs WordPress core:
$wpdb->insert(
    'events',
    ['post_id' => 1, 'title' => 'My Party', 'status' => 'SCHEDULED', 'type' => 'event'],
    ['%d', '%s', '%s', '%s']
);
```

**`insertMany(firstRow, secondRow, ...rows)`** - insert multiple rows in a single query:

```php
use Inpsyde\Dbal\Dbal;

$result = Dbal::writeOn('events')->insertMany(
    ['post_id' => 1, 'title' => 'Event A', 'status' => 'SCHEDULED', 'type' => 'party'],
    ['post_id' => 2, 'title' => 'Event B', 'status' => 'PAST',      'type' => 'concert'],
    ['post_id' => 3, 'title' => 'Event C', 'status' => 'SCHEDULED', 'type' => 'party'],
);
```

> **Note:** `insertMany()` requires at least two rows. Use `insert()` for a single row.

---

## Delete

For `DELETE` queries use the `deleteFrom()` method.

```php
use Inpsyde\Dbal\Dbal;

$result = Dbal::deleteFrom('events')->where(['id' => 1]);

// vs WordPress core:
$wpdb->delete('events', ['id' => 1]);
```

When joining tables in a delete query, `deleteOnly()` restricts which tables rows are actually deleted from, preventing accidental deletion from joined tables:

```php
use Inpsyde\Dbal\Dbal;

// Join posts but only delete rows from events
$result = Dbal::deleteFrom('events')
    ->innerJoin('posts', 'post_id', 'ID')
    ->deleteOnly('events')
    ->where(['post_id' => 5]);
```

---

## Transactions

`inpsyde/dbal` provides an API for transaction management via `Dbal::transaction()`.

The following isolation flags are available as bitmask constants on `Transaction`:

| Constant                           | Description |
|------------------------------------|-------------|
| `Transaction::DEFAULT`             | MySQL default (equivalent to `REPEATABLE_READ`) |
| `Transaction::REPEATABLE_READ`     | Repeated reads within a transaction return the same snapshot |
| `Transaction::READ_COMMITTED`      | Each read sees the latest committed data |
| `Transaction::READ_UNCOMMITTED`    | Dirty reads allowed - reads uncommitted changes from other transactions |
| `Transaction::SERIALIZABLE`        | Strictest level; transactions execute as if run serially |
| `Transaction::READ_WRITE`          | Transaction can read and write (default behaviour) |
| `Transaction::READ_ONLY`           | Optimisation hint; transaction will not modify data |
| `Transaction::CONSISTENT_SNAPSHOT` | Creates a consistent read snapshot at transaction start |

Flags can be combined using the bitwise OR operator (`|`):

```php
use Inpsyde\Dbal\Dbal;
use Inpsyde\Dbal\Result;
use Inpsyde\Dbal\Transaction;

// Single flag
$transaction = Dbal::transaction(Transaction::READ_WRITE);

// Combined flags - read-only with a consistent snapshot
$transaction = Dbal::transaction(Transaction::READ_ONLY | Transaction::CONSISTENT_SNAPSHOT);

$callbacks = [
    static fn(): Result => Dbal::writeOn('events')->insert(['title' => "Let's party!"]),
];

$result = $transaction($callbacks);
```

The transaction automatically rolls back if any callback throws or returns an errored `Result`.
