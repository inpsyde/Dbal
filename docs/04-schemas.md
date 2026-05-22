# Schemas

## Create your own Schema

When creating custom database tables, implement `Inpsyde\Dbal\Schema\InstallableSchema`. A complete, annotated example can be found in [07-learning-by-example.md](./07-learning-by-example.md).

### `name(): string`

Returns the bare table name without the WordPress prefix (e.g. `'events'`, not `'wp_events'`). `inpsyde/dbal` prepends the active prefix automatically.

```php
public function name(): string
{
    return 'events';
}
```

### `version(): string`

Returns the current schema version string. `inpsyde/dbal` compares this against the stored version and triggers `dbDelta` automatically whenever the value changes, keeping the table structure in sync without manual intervention.

```php
public function version(): string
{
    return '1.0.0';
}
```

### `isNetworkWide(): bool`

Controls table scope on WordPress Multisite installs. Return `true` to create a single shared table for the entire network (using the global prefix). Return `false` for a per-site table created once per sub-site.

```php
public function isNetworkWide(): bool
{
    return false;
}
```

### `columns(): Columns`

Returns a `Columns` instance describing every column in the table. See [02-columns-and-column.md](./02-columns-and-column.md) for the full column API.

```php
use Inpsyde\Dbal\Schema\Column;
use Inpsyde\Dbal\Schema\Columns;

public function columns(): Columns
{
    return Columns::new(
        Column::entityId('id'),
        Column::varChar('title', 255)->makeNotNull(),
        Column::enum('status', ...['DRAFT', 'PUBLISHED']),
    );
}
```

### `indexes(): ?Indexes`

Returns an `Indexes` instance defining the table's indexes, or `null` for no indexes. See [03-indexes.md](./03-indexes.md) for the full index API.

```php
use Inpsyde\Dbal\Schema\Index;
use Inpsyde\Dbal\Schema\Indexes;

public function indexes(): ?Indexes
{
    return Indexes::new(
        Index::primary('id'),
        Index::key('status'),
    );
}
```

### `onInstall(\wpdb $wpdb, string $fullTableName): void`

Called automatically after the table is first created. Use it to seed initial data, write log entries, or perform any other one-time setup.

```php
public function onInstall(\wpdb $wpdb, string $fullTableName): void
{
    $wpdb->insert($fullTableName, ['title' => 'Default', 'status' => 'DRAFT']);
}
```

### `onUpdate(\wpdb $wpdb, string $fullTableName, string $previousVersion): void`

Called automatically after `dbDelta` runs an update. `$previousVersion` is the version string that was stored before the update, which lets you branch on specific migration paths.

```php
public function onUpdate(\wpdb $wpdb, string $fullTableName, string $previousVersion): void
{
    if (version_compare($previousVersion, '1.1.0', '<')) {
        // migrate data introduced in 1.1.0
    }
}
```

---

## Registering a Schema

Hook into `Dbal::ACTION_REGISTER_SCHEMA` to register the schema for installation:

```php
use Inpsyde\Dbal\Dbal;
use Inpsyde\Dbal\Schema\SchemasRegister;

add_action(
    Dbal::ACTION_REGISTER_SCHEMA,
    static function (SchemasRegister $schemasRegister): void {
        $schemasRegister->registerForInstall(EventSchema::new());
    }
);
```

To drop the table when the plugin is deactivated, register it for uninstall inside `register_deactivation_hook`:

```php
use Inpsyde\Dbal\Dbal;
use Inpsyde\Dbal\Schema\SchemasRegister;

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

### TableInstaller actions and filters

`inpsyde/dbal` fires the following WordPress actions and filters during schema installation and updates, allowing you to hook in custom logic:

| Hook                           | Type   | When fired                                                                            | Arguments                    |
|--------------------------------|--------|---------------------------------------------------------------------------------------|------------------------------|
| `dbal.table-install`           | action | Before a new table is created                                                         | `Schema $schema`             |
| `dbal.table-installed`         | action | After a new table is created                                                          | `Schema $schema`             |
| `dbal.table-update`            | action | Before an existing table is updated via `dbDelta`                                     | `Schema $schema`             |
| `dbal.table-updated`           | action | After an existing table is updated                                                    | `Schema $schema`             |
| `dbal.skip-table-exists-check` | filter | Before install - return `true` to skip the check for whether the table already exists | `bool $skip, Schema $schema` |

```php
add_action('dbal.table-installed', static function (Inpsyde\Dbal\Schema\Schema $schema): void {
    // e.g., seed initial data after the table is first created
    if ($schema->name() === 'events') {
        // seed...
    }
});

add_filter('dbal.skip-table-exists-check', static function (bool $skip, $schema): bool {
    // force re-install for a specific schema
    return $schema->name() === 'events' ? true : $skip;
}, 10, 2);
```

---

## WordPress Schema

`inpsyde/dbal` also supports schemas for existing WordPress core tables through `$wpdb->{tableName}` to make use of the full API. To access those, use either `SchemaFinder` or `WpSchemas`:

```php
use Inpsyde\Dbal\Dbal;

$postsSchema  = Dbal::schemaFinder()->findCoreSchema('posts');
$postsColumns = $postsSchema->columns();
// or
$postsColumns = Dbal::wpSchema()->postsColumns();
```
