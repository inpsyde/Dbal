# Querying

Normally, in WordPress, creating queries happens through `$wpdb->prepare($sql, $data)` and `$wpdb->get_results($sql)`. While this is still a valid and safe way to query your database, `inpsyde/dbal` helps you to write a more code-based way of queries with automatic escaping through the WordPress API. In the following examples we will compare "normal" queries through WordPress with queries written in `inpyde/dbal` based on our `events` table we used in our [Schema](04-schemas.md) example. 

## Select

For `SELECT` queries you start with invoking the `select()` method.

```php
use Inpsyde\Dbal\Dbal;

Dbal::select('events');

// vs WordPress core:
$sql = $wpdb->prepare('SELECT * FROM events');
$wpdb->get_results($sql);
```

### Table alias
The `select()` method takes an optional second parameter with which a table alias can be specified.

```php
use Inpsyde\Dbal\Dbal;

Dbal::select('events', 'my-custom-alias');

// vs WordPress core:
$sql = $wpdb->prepare('SELECT * FROM events AS my-custom-alias');
$wpdb->get_results($sql);
```

### Limit columns returned

By default, the `Dbal::select()` calls `SELECT *`, if we want to limit this, we can use the `col()` method.

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

### GROUP BY clause
The `SELECT` statement can be specified with `GROUP BY` clause.

```php
use Inpsyde\Dbal\Dbal;

Dbal::select('events')->groupBy('post_id')

// vs WordPress core:
$sql = $wpdb->prepare('SELECT * FROM events GROUP BY post_id');
$wpdb->get_results($sql);
```

### Join clauses

For `SELECT` clauses you can generate different types of joins: `INNER` and `LEFT`. 

```php
use Inpsyde\Dbal\Dbal;

Dbal::select('events', 'my-custom-alias')->innerJoin('posts', 'post_id', 'ID');

// vs WordPress core:
$sql = $wpdb->prepare('SELECT * FROM events INNER JOIN wp_posts ON events.post_id = wp_posts.ID');
$wpdb->get_results($sql);
```

Additionally, `inpsyde/dbal` also provides: `innerJoinWhere`, `innerJoinWith`, `joinViaPivot`, `leftJoin`, `leftJoinWhere`, `leftJoinWith`.

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
The `LIMIT` can be used to paginate through your database table or limit the result to a specific amount of results.

```php
use Inpsyde\Dbal\Dbal;
$limit = 5;
$offset = 0;
Dbal::select('events')->limit($limit, $offset);

// vs WordPress core:
$sql = $wpdb->prepare('SELECT * FROM events LIMIT %d, %d', [$limit, $offset]);
$wpdb->get_results($sql);
```

## Update

For `UPDATE` queries you start with invoking the `writeOn()` method.

```php
use Inpsyde\Dbal\Dbal;

$result = Dbal::writeOn('events')->update(
    [
        'post_id' => 5,
        'title' => 'My Party',
        'status' => 'SCHEDULED',
        'type' => 'event'
    ],
    [ 'id' => 1 ]
);

// vs WordPress core:
$wpdb->update(
    'events', 
    [
        'post_id' => 5,
        'title' => 'My Party',
        'status' => 'SCHEDULED',
        'type' => 'event'
    ],
    [ 'id' => 1 ],
    [ "%d", "%s", "%s", "%s" ],
    [ "%d" ]
);
```

## Create

For `CREATE` queries you start with invoking the `writeOn()` method.

```php
use Inpsyde\Dbal\Dbal;

$result = Dbal::writeOn('events')->insert(
    [
        'post_id' => 1,
        'title' => 'My Party',
        'status' => 'SCHEDULED',
        'type' => 'event'
    ]
);

$data = $result->extract();
$data->inserted_id; // 1

// vs WordPress core:
$wpdb->insert(
    'events', 
    [
        'post_id' => 1,
        'title' => 'My Party',
        'status' => 'SCHEDULED',
        'type' => 'event'
    ]
    [ "%d", "%s", "%s", "%s" ],
);
```


## Delete

For `DELETE` queries you start with invoking the `deleteFrom()` method.

```php
use Inpsyde\Dbal\Dbal;

$result = Dbal::deleteFrom('events')
    ->where(['id' => 1]);

// vs WordPress core:
$wpdb->delete('events',['id' => 1]);
```

## Transactions

`inpdye/dbal` provides an API for transaction management, with the method `transaction()`.

The following modes are supported:

- `Transaction::REPEATABLE_READ`
- `Transaction::READ_COMMITTED`
- `Transaction::READ_UNCOMMITTED` 
- `Transaction::SERIALIZABLE`
- `Transaction::READ_WRITE`
- `Transaction::READ_ONLY`
- `Transaction::CONSISTENT_SNAPSHOT`
- `Transaction::DEFAULT`

```php
use Inpsyde\Dbal\Dbal;
use Inpsyde\Dbal\Query\Transaction;

$modes = Transaction::READ_WRITE;
$transaction = Dbal::transaction($modes);

$callbacks = [
    static fn(): Result => Dbal::writeOn('events')->insert(['title' => 'Lets party!']),
];

$result = $transaction($callbacks);
```

## Result and ResultSet

Before we start with writing queries, we need to understand what `inpsyde/dbal` returns. Normally, `$wpdb->get_results()` returns mixed values: `object`, `array` or `null`. Getting additional information about the query executed is not present in this return value.

`inpsyde/dbal` introduces two objects to have more consistent control and context over the response: `Inpyde\Dbal\Result` and `Inpsyde\Dbal\Query\ResultSet`.

### ResultSet

The `Inpsyde\Dbal\ResultSet` is being used for `Inpsyde\Dbal\Query\Select` to return an iterable collections of `Inpsyde\Dbal\Result`.

```php
use Inpsyde\Dbal\Dbal;
use Inpsyde\Dbal\Query\ResultSet;

/** @var ResultSet $resultSet */
$resultSet = Dbal::select('events')->all();

foreach($resultSet as $result){
    /** 
     * @var object{
     *     id:int, 
     *     uuid:string, 
     *     post_id:int, 
     *     start_date: DateTime, 
     *     end_date: Date_Time, 
     *     timezone: string, 
     *     status: 'SCHEDULED', 'HAPPENING', 'PAST', 'CANCELED', 
     *     type: string 
     * } $data 
     */
     $data = $result->extract();
}
```

The `ResultSet` has some more methods which can be used to convert the data into proper objects:

```php
use Inpsyde\Dbal\Query\ResultSet
use Inpsyde\Dbal\Result;

/** @var ResultSet $resultSet */
$resultSet->map(static function(Result $result): Event {
    return Event::new($result->extract());
});

foreach($resultSet as $result){
    /** @var Event $result */
}
```

### Result

The `Inpsyde\Dbal\Result` is being used for a single result return from a query like on `WRITE`, `UPDATE` or `CREATE`.

```php
use Inpsyde\Dbal\Dbal;
use Inpsyde\Dbal\Result;

/** @var Result $result */
$result = Dbal::writeOn('events')->insert(
    [
        'post_id' => 1,
        'title' => 'My Party',
        'status' => 'SCHEDULED',
        'type' => 'event'
    ]
);

$result->isErrored();
$result->isValid();
$data = $result->extract();
```