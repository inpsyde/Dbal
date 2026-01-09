# Schemas

## Create your own Schema

When creating custom database tables, you must use `Inpsyde\Dbal\Schema\InstallableSchema`. This schema type provides a `version()` method that enables `inpsyde/dbal` to automatically detect structural changes and trigger the appropriate updates via `dbDelta`.

In addition, `InstallableSchema` exposes two lifecycle hooks: `onInstall()` and `onUpdate()`. These methods are invoked automatically when the corresponding operations are executed internally. They allow you to perform custom logic such as logging, data migrations, or reacting to structural changes in the database table.

```php
use Inpsyde\Dbal\Schema\Column;
use Inpsyde\Dbal\Schema\Columns;
use Inpsyde\Dbal\Schema\Index;
use Inpsyde\Dbal\Schema\Indexes;
use Inpsyde\Dbal\Schema\InstallableSchema;

class EventSchema implements InstallableSchema
{
    public const TABLE_NAME = 'events';
    public const TABLE_VERSION = '1.0.0';
    public const ID = 'id';
    public const UUID = 'uuid';
    public const POST_ID =  'post_id';
    public const START_DATE = 'start_date';
    public const END_DATE = 'end_date';
    public const TIMEZONE = 'timezone';
    public const STATUS = 'status';
    public const TYPE = 'type';
    
    private function __construct(private string $name, private string $version)
    {
    }
    
    /**
     * @return EventSchema
     */
    public static function new(): EventSchema
    {
        return new self(self::TABLE_NAME, self::TABLE_VERSION);
    }
    
    /**
     * @return string
     */
    public function version(): string
    {
        return $this->version;
    }

    /**
     * @return bool
     */
    public function isNetworkWide(): bool
    {
        return false;
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return $this->name;
    }

    public function onInstall(\wpdb $wpdb, string $fullTableName): void
    {
        return;
    }

    public function onUpdate(\wpdb $wpdb, string $fullTableName, string $previousVer): void
    {
        return;
    }
    
    /**
     * @return Columns
     */
    public function columns(): Columns
    {
        return Columns::new(
            Column::entityId(self::ID),
            Column::varChar(self::UUID, 37)->makeNotNull(),
            Column::bigInt(self::POST_ID)->makeNotNull(),
            Column::datetime(self::START_DATE),
            Column::datetime(self::END_DATE),
            Column::varChar(self::TIMEZONE, 32, 'UTC'),
            Column::enum(self::STATUS, ...['SCHEDULED', 'HAPPENING', 'PAST', 'CANCELED']),
            Column::varChar(self::TYPE, 16)->makeNotNull(),
        );
    }
    
    /**
     * @return Indexes|null
     */
    public function indexes(): ?Indexes
    {
        return Indexes::new(
            Index::primary(self::ID),
            Index::unique(self::UUID),
            Index::unique(self::POST_ID),
            Index::key(self::START_DATE),
            Index::key(self::END_DATE),
            Index::key(self::STATUS),
            Index::key(self::TYPE)
        );
    }
}
```

In order to register the Schema, you need to hook into the `Inpsyde\Dbal\Dbal` instance when it initializes it's process to register your schema.

```php
use Inpsyde\Dbal;

add_action(
    Dbal\Dbal::ACTION_REGISTER_SCHEMA,
    static function (Dbal\Schema\SchemasRegister $schemasRegister): void {
        $schemasRegister->registerForInstall(EventSchema::new());
    }
);
```

## WordPress Schema

`inpsyde/dbal` also supports Schemas of existing tables through `$wpdb->{tableName}` to make use of the full API. In order to access those, you can use either the `Inpsyde/Dbal/Schema/SchemaFinder` or `Inpsyde/Dbal/Schema/WpSchemas`.

```php
use Inpsyde\Dbal\Dbal;

$postsSchema = Dbal::schemaFinder()->findCoreSchema('posts');
$postsColumns = $postsSchema->columns();
// or
$postsColumns = Dbal::wpSchema()->postsColumns();
```
