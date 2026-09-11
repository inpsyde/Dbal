# Learning by example

This page walks through a complete, realistic use-case from scratch: defining a custom database table, installing it, and performing the full range of CRUD operations using `syde/dbal`.

## The events table

All examples on this page use a custom WordPress database table called `events`:

| name       | type     | note                                             |
|------------|----------|--------------------------------------------------|
| id         | bigInt   | primary, unsigned, autoincrement                 |
| post_id    | bigInt   | not null, references to wp_posts                 |
| title      | varChar  | not null                                         |
| start_date | datetime |                                                  |
| end_date   | datetime |                                                  |
| timezone   | varChar  | not null, defaults to 'UTC'                      |
| status     | enum     | `['SCHEDULED', 'HAPPENING', 'PAST', 'CANCELED']` |
| type       | varChar  | not null                                         |

---

## 1. Defining the schema

Start by implementing `Syde\Dbal\Schema\InstallableSchema`. The `version()` method lets `syde/dbal` detect structural changes and trigger `dbDelta` automatically.

```php
use Syde\Dbal\Schema\Column;
use Syde\Dbal\Schema\Columns;
use Syde\Dbal\Schema\Index;
use Syde\Dbal\Schema\Indexes;
use Syde\Dbal\Schema\InstallableSchema;

class EventSchema implements InstallableSchema
{
    public const TABLE_NAME    = 'events';
    public const TABLE_VERSION = '1.0.0';
    public const ID         = 'id';
    public const POST_ID    = 'post_id';
    public const TITLE      = 'title';
    public const START_DATE = 'start_date';
    public const END_DATE   = 'end_date';
    public const TIMEZONE   = 'timezone';
    public const STATUS     = 'status';
    public const TYPE       = 'type';

    private function __construct(private string $name, private string $version)
    {
    }

    public static function new(): self
    {
        return new self(self::TABLE_NAME, self::TABLE_VERSION);
    }

    public function version(): string
    {
        return $this->version;
    }

    /**
     * Return true to share one table across the entire Multisite network.
     * Return false (default) for a per-site table.
     */
    public function isNetworkWide(): bool
    {
        return false;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function onInstall(\wpdb $wpdb, string $fullTableName): void
    {
        // seed initial data, log installation, etc.
    }

    public function onUpdate(\wpdb $wpdb, string $fullTableName, string $previousVer): void
    {
        // run data migrations when the schema version changes
    }

    public function columns(): Columns
    {
        return Columns::new(
            Column::entityId(self::ID),
            Column::bigInt(self::POST_ID)->makeNotNull(),
            Column::varChar(self::TITLE, 255)->makeNotNull(),
            Column::datetime(self::START_DATE),
            Column::datetime(self::END_DATE),
            Column::varChar(self::TIMEZONE, 32, 'UTC'),
            Column::enum(self::STATUS, ...['SCHEDULED', 'HAPPENING', 'PAST', 'CANCELED']),
            Column::varChar(self::TYPE, 16)->makeNotNull(),
        );
    }

    public function indexes(): ?Indexes
    {
        return Indexes::new(
            Index::primary(self::ID),
            Index::unique(self::POST_ID),
            Index::key(self::START_DATE),
            Index::key(self::END_DATE),
            Index::key(self::STATUS),
            Index::key(self::TYPE),
        );
    }
}
```

### Registering and unregistering the schema

Hook into `Dbal::ACTION_REGISTER_SCHEMA` to register the schema for installation:

```php
use Syde\Dbal\Dbal;
use Syde\Dbal\Schema\SchemasRegister;

add_action(
    Dbal::ACTION_REGISTER_SCHEMA,
    static function (SchemasRegister $schemasRegister): void {
        $schemasRegister->registerForInstall(EventSchema::new());
    }
);
```

`registerForInstall` creates the table on activation and applies any `dbDelta` updates when the version changes.

To drop the table when the plugin is deactivated, register it for uninstall inside the WordPress `register_deactivation_hook` callback:

```php
use Syde\Dbal\Dbal;
use Syde\Dbal\Schema\SchemasRegister;

register_deactivation_hook(
    __FILE__,
    static function (): void {
        add_action(
            Dbal::ACTION_REGISTER_SCHEMA,
            static function (SchemasRegister $schemasRegister): void {
                $schemasRegister->registerForUninstall(EventSchema::new());
            }
        );
    }
);
```

---

## 2. The Event model

Before writing queries it helps to have a typed model to work with. The `Event` class uses a private constructor, public readonly properties, and three static constructors: `new()` for creating a new (not-yet-persisted) event, `newFromResultSet()` for hydrating a row returned by `syde/dbal`. `isValid()` distinguishes a successfully hydrated event from an error wrapper.

```php
use Syde\Dbal\Error;
use Syde\Dbal\Result;

class Event
{
    private function __construct(
        public readonly ?int      $id,
        public readonly int       $postId,
        public readonly string    $title,
        public readonly ?string   $startDate,
        public readonly ?string   $endDate,
        public readonly string    $timezone,
        public readonly string    $status,
        public readonly string    $type,
        public readonly ?Error    $error = null,
    ) {
    }

    public function isValid(): bool
    {
        return $this->error === null;
    }

    /**
     * Create a new, not-yet-persisted Event.
     */
    public static function new(
        int     $postId,
        string  $title,
        string  $type,
        string  $status   = 'SCHEDULED',
        ?string $startDate = null,
        ?string $endDate   = null,
        string  $timezone  = 'UTC',
    ): self {
        return new self(
            id:        null,
            postId:    $postId,
            title:     $title,
            startDate: $startDate,
            endDate:   $endDate,
            timezone:  $timezone,
            status:    $status,
            type:      $type,
        );
    }

    /**
     * Return the event's fields as an array suitable for insert/update queries.
     * The id is excluded — it is managed by the database.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'post_id'    => $this->postId,
            'title'      => $this->title,
            'start_date' => $this->startDate,
            'end_date'   => $this->endDate,
            'timezone'   => $this->timezone,
            'status'     => $this->status,
            'type'       => $this->type,
        ];
    }

    /**
     * Hydrate an Event from a single Result returned by syde/dbal.
     */
    public static function newFromResultSet(Result $result): self
    {
        $data = $result->extract();

        return new self(
            id:        $data->id,
            postId:    $data->post_id,
            title:     $data->title,
            startDate: $data->start_date,
            endDate:   $data->end_date,
            timezone:  $data->timezone,
            status:    $data->status,
            type:      $data->type,
            error:     $data->error(),
        );
    }
}
```

---

## 3. Inserting records

### Single insert

```php
use Syde\Dbal\Dbal;

$event = Event::new(
    postId:    1,
    title:     'New Year\'s Party',
    type:      'party',
    status:    'SCHEDULED',
    startDate: '2025-01-01 20:00:00',
    endDate:   '2025-01-02 02:00:00',
    timezone:  'Europe/Berlin',
);

$result      = Dbal::writeOn('events')->insert($event->toArray());
$insertedEvent = Event::newFromResultSet($result);

if ($insertedEvent->isValid()) {
    $newId = $result->extract()->inserted_id; // auto-incremented ID of the new row
} else {
    // log or handle $insertedEvent->error->allMessages()
}
```

### Bulk insert

Use `insertMany()` to insert multiple rows in a single query. At least two rows are required; use `insert()` for a single row.

```php
use Syde\Dbal\Dbal;

$concert    = Event::new(postId: 2, title: 'Summer Concert',  type: 'concert',    timezone: 'UTC');
$exhibition = Event::new(postId: 3, title: 'Art Exhibition',  type: 'exhibition', status: 'PAST', timezone: 'UTC');
$meetup     = Event::new(postId: 4, title: 'Tech Meetup',     type: 'meetup',     timezone: 'America/New_York');

$result = Dbal::writeOn('events')->insertMany(
    $concert->toArray(),
    $exhibition->toArray(),
    $meetup->toArray(),
);
```

---

## 4. Updating records

### Simple update

`update(data, where)` updates all rows matching the `where` column/value pair:

```php
use Syde\Dbal\Dbal;

$event = Event::new(
    postId: 1,
    title:  'New Year\'s Party (Updated)',
    type:   'party',
    status: 'HAPPENING',
);

$result = Dbal::writeOn('events')->update($event->toArray(), ['id' => 1]);

if ($result->isValid()) {
    // update succeeded
}
```

### Update with a Where expression

`updateWhere(data, Where)` accepts a full `Where` object for more complex conditions — useful when a simple column equality check is not enough:

```php
use Syde\Dbal\Dbal;
use Syde\Dbal\Query\Where;

// Mark all events whose end_date is in the past as PAST
$result = Dbal::writeOn('events')->updateWhere(
    ['status' => 'PAST'],
    Where::new()->with('end_date', new DateTime(), Where::LESS)
);
```

Conditions can be chained with `andWith()` and `orWith()`:

```php
use Syde\Dbal\Dbal;
use Syde\Dbal\Query\Where;

// Cancel all scheduled party-type events for a specific post
$result = Dbal::writeOn('events')->updateWhere(
    ['status' => 'CANCELED'],
    Where::new()
        ->with('post_id', 1, Where::EQ)
        ->andWith('type', 'party', Where::EQ)
        ->andWith('status', 'SCHEDULED', Where::EQ)
);
```

---

## 5. Reading records

### Fetch all rows

```php
use Syde\Dbal\Dbal;
use Syde\Dbal\Query\ResultSet;
use Syde\Dbal\Result;

/** @var ResultSet $resultSet */
$resultSet = Dbal::select('events')->all();

foreach ($resultSet as $result) {
    $data = $result->extract();
    // $data->id, $data->title, $data->status, etc.
}
```

### Fetch the first matching row

```php
use Syde\Dbal\Dbal;

$result = Dbal::select('events')->pickFirst()->first();

if ($result !== null && $result->isValid()) {
    $data = $result->extract();
}
```

### Filter with WHERE

```php
use Syde\Dbal\Dbal;
use Syde\Dbal\Query\Where;

// Single condition
$resultSet = Dbal::select('events')
    ->where('status', 'SCHEDULED', Where::EQ)
    ->all();

// Multiple AND conditions
$resultSet = Dbal::select('events')
    ->where('status', 'SCHEDULED', Where::EQ)
    ->andWhere('type', 'party', Where::EQ)
    ->all();

// Multiple OR conditions
$resultSet = Dbal::select('events')
    ->where('status', 'SCHEDULED', Where::EQ)
    ->orWhere('status', 'HAPPENING', Where::EQ)
    ->all();

// IN operator
$resultSet = Dbal::select('events')
    ->where('type', ['party', 'concert'], Where::IN)
    ->all();
```

### Restrict returned columns

```php
use Syde\Dbal\Dbal;

$resultSet = Dbal::select('events')
    ->cols('id', 'title', 'status', 'type')
    ->where('status', 'SCHEDULED', Where::EQ)
    ->all();
```

### Order and paginate

```php
use Syde\Dbal\Dbal;
use Syde\Dbal\Query\Select;

$page    = 2;
$perPage = 10;

$resultSet = Dbal::select('events')
    ->where('status', 'SCHEDULED', Where::EQ)
    ->orderBy('start_date', Select::ASC)
    ->limit($perPage, ($page - 1) * $perPage)
    ->all();
```

### Map rows to typed objects

`ResultSet::map()` transforms each row before iteration:

```php
use Syde\Dbal\Dbal;
use Syde\Dbal\Query\ResultSet;
use Syde\Dbal\Result;

$resultSet = Dbal::select('events')->all();

$resultSet->map(static function (Result $result): Event {
    return Event::newFromResultSet($result);
});

foreach ($resultSet as $event) {
    /** @var Event $event */
}
```

### Error handling on reads

```php
use Syde\Dbal\Dbal;

$resultSet = Dbal::select('events')->all();

if ($resultSet->hasErrors()) {
    foreach ($resultSet->toArray() as $result) {
        // log $result->error()
    }
}
```

---

## 6. Deleting records

### Simple delete

```php
use Syde\Dbal\Dbal;

$result = Dbal::deleteFrom('events')->where(['id' => 1]);

if ($result->isValid()) {
    // row deleted
}
```

### Delete with a join

When joining tables, use `deleteOnly()` to restrict which table rows are actually removed — this prevents accidentally deleting rows from joined tables:

```php
use Syde\Dbal\Dbal;

// Delete events whose associated post no longer exists (or has been trashed)
$result = Dbal::deleteFrom('events', 'e')
    ->innerJoin('posts', 'post_id', 'ID')
    ->deleteOnly('events')
    ->where(['post_status' => 'trash']);
```

### Delete multiple rows by condition

```php
use Syde\Dbal\Dbal;

// Remove all canceled events for a given post
$result = Dbal::deleteFrom('events')->where([
    'post_id' => 5,
    'status'  => 'CANCELED',
]);
```
