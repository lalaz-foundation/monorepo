# API Reference

Complete API documentation for the Lalaz Database package.

## Connection

### Constructor

```php
public function __construct(
    ConnectionManagerInterface $manager,
    ?PDO $pdo = null,
    ?Grammar $grammar = null
)
```

### Methods

#### query

Execute a raw SQL statement.

```php
public function query(string $sql, array $bindings = []): PDOStatement
```

#### select

Execute a select query and return results.

```php
public function select(string $sql, array $bindings = []): array
```

#### insert

Execute an insert statement.

```php
public function insert(string $sql, array $bindings = []): bool
```

#### update

Execute an update statement and return affected rows.

```php
public function update(string $sql, array $bindings = []): int
```

#### delete

Execute a delete statement and return affected rows.

```php
public function delete(string $sql, array $bindings = []): int
```

#### transaction

Execute a callback within a transaction.

```php
public function transaction(callable $callback): mixed
```

#### beginTransaction

Begin a database transaction.

```php
public function beginTransaction(): bool
```

#### commit

Commit the current transaction.

```php
public function commit(): bool
```

#### rollBack

Roll back the current transaction.

```php
public function rollBack(): bool
```

#### table

Get a query builder for a table.

```php
public function table(string $table): QueryBuilder
```

#### getPdo

Get the underlying PDO instance.

```php
public function getPdo(): PDO
```

#### grammar

Get the SQL grammar.

```php
public function grammar(): Grammar
```

#### getDriverName

Get the database driver name.

```php
public function getDriverName(): string
```

---

## ConnectionManager

### Constructor

```php
public function __construct(
    array $config,
    ?LoggerInterface $logger = null,
    array $connectors = []
)
```

### Methods

#### acquire

Acquire a write connection from the pool.

```php
public function acquire(): PDO
```

#### acquireRead

Acquire a read connection (replica or primary).

```php
public function acquireRead(): PDO
```

#### acquireWithTimeout

Acquire a connection with custom timeout.

```php
public function acquireWithTimeout(?int $timeoutMs = null): PDO
```

#### release

Release a write connection back to the pool.

```php
public function release(PDO $connection): void
```

#### releaseRead

Release a read connection back to the pool.

```php
public function releaseRead(PDO $connection): void
```

#### driver

Get the primary driver name.

```php
public function driver(): string
```

#### readDriver

Get the read replica driver name.

```php
public function readDriver(): string
```

#### config

Get a configuration value.

```php
public function config(string $key, mixed $default = null): mixed
```

#### poolStatus

Get current pool statistics.

```php
public function poolStatus(): array
// Returns: ['total' => int, 'pooled' => int, 'max' => int, 'min' => int]
```

#### listenQuery

Register a query event listener.

```php
public function listenQuery(callable $listener): void
```

#### dispatchQueryEvent

Dispatch a query event to listeners.

```php
public function dispatchQueryEvent(array $event): void
```

---

## QueryBuilder

### Select Operations

#### select

Set columns to select.

```php
public function select(string|Expression ...$columns): self
```

#### addSelect

Add columns to select.

```php
public function addSelect(string|Expression ...$columns): self
```

#### selectSub

Add a subquery select.

```php
public function selectSub(
    Closure|self|string $query,
    string $as,
    array $bindings = []
): self
```

#### selectRaw

Add a raw select expression.

```php
public function selectRaw(string $expression, array $bindings = []): self
```

#### distinct

Set distinct flag.

```php
public function distinct(bool $value = true): self
```

### From Clause

#### from

Set the table to query.

```php
public function from(string $table): self
```

#### fromSub

Set a subquery as the source.

```php
public function fromSub(
    Closure|self|string $query,
    string $as,
    array $bindings = []
): self
```

#### table

Alias for from().

```php
public function table(string $table): self
```

### Joins

#### join

Add a join clause.

```php
public function join(
    string|Expression $table,
    Closure|string $first,
    ?string $operator = null,
    ?string $second = null,
    string $type = 'inner',
    string $boolean = 'and'
): self
```

#### leftJoin

Add a left join clause.

```php
public function leftJoin(
    string|Expression $table,
    Closure|string $first,
    ?string $operator = null,
    ?string $second = null
): self
```

#### rightJoin

Add a right join clause.

```php
public function rightJoin(
    string|Expression $table,
    Closure|string $first,
    ?string $operator = null,
    ?string $second = null
): self
```

#### crossJoin

Add a cross join clause.

```php
public function crossJoin(string|Expression $table): self
```

#### joinSub

Join with a subquery.

```php
public function joinSub(
    Closure|self $query,
    string $as,
    Closure|string $first,
    ?string $operator = null,
    ?string $second = null,
    string $type = 'inner'
): self
```

### Where Clauses

#### where

Add a where clause.

```php
public function where(
    Closure|string|array $column,
    mixed $operator = null,
    mixed $value = null,
    string $boolean = 'and'
): self
```

#### orWhere

Add an or where clause.

```php
public function orWhere(
    Closure|string|array $column,
    mixed $operator = null,
    mixed $value = null
): self
```

#### whereColumn

Add a where column comparison.

```php
public function whereColumn(
    string|array $first,
    ?string $operator = null,
    ?string $second = null,
    string $boolean = 'and'
): self
```

#### whereRaw

Add a raw where clause.

```php
public function whereRaw(
    string $sql,
    array $bindings = [],
    string $boolean = 'and'
): self
```

#### whereBetween

Add a where between clause.

```php
public function whereBetween(
    string $column,
    array $values,
    string $boolean = 'and',
    bool $not = false
): self
```

#### whereIn

Add a where in clause.

```php
public function whereIn(
    string $column,
    array|Closure|self $values,
    string $boolean = 'and',
    bool $not = false
): self
```

#### whereNull

Add a where null clause.

```php
public function whereNull(
    string|array $column,
    string $boolean = 'and',
    bool $not = false
): self
```

#### whereExists

Add a where exists clause.

```php
public function whereExists(
    Closure|self $query,
    string $boolean = 'and',
    bool $not = false
): self
```

### Grouping

#### groupBy

Add group by columns.

```php
public function groupBy(string|Expression ...$columns): self
```

#### groupByRaw

Add raw group by.

```php
public function groupByRaw(string $sql): self
```

### Having

#### having

Add a having clause.

```php
public function having(
    string|Expression $column,
    string $operator,
    mixed $value,
    string $boolean = 'and'
): self
```

#### havingRaw

Add a raw having clause.

```php
public function havingRaw(
    string $sql,
    array $bindings = [],
    string $boolean = 'and'
): self
```

### Ordering

#### orderBy

Add order by column.

```php
public function orderBy(
    string|Expression $column,
    string $direction = 'asc'
): self
```

#### orderByDesc

Add descending order.

```php
public function orderByDesc(string|Expression $column): self
```

#### orderByRaw

Add raw order by.

```php
public function orderByRaw(string $sql, array $bindings = []): self
```

#### reorder

Clear and optionally set new order.

```php
public function reorder(?string $column = null, string $direction = 'asc'): self
```

#### latest

Order by column descending.

```php
public function latest(string $column = 'created_at'): self
```

#### oldest

Order by column ascending.

```php
public function oldest(string $column = 'created_at'): self
```

### Limiting

#### limit

Set query limit.

```php
public function limit(int $value): self
```

#### offset

Set query offset.

```php
public function offset(int $value): self
```

#### forPage

Set pagination.

```php
public function forPage(int $page, int $perPage = 15): self
```

### Locking

#### lock

Set lock mode.

```php
public function lock(bool|string $value = true): self
```

#### lockForUpdate

Lock for update.

```php
public function lockForUpdate(): self
```

#### sharedLock

Shared lock.

```php
public function sharedLock(): self
```

### Unions

#### union

Add a union query.

```php
public function union(Closure|self $query, bool $all = false): self
```

#### unionAll

Add a union all query.

```php
public function unionAll(Closure|self $query): self
```

### Retrieval

#### get

Execute query and get results.

```php
public function get(array|string $columns = ['*']): array
```

#### first

Get first result.

```php
public function first(array|string $columns = ['*']): ?array
```

#### value

Get single column value.

```php
public function value(string $column): mixed
```

#### pluck

Get column values as array.

```php
public function pluck(string $column, ?string $key = null): array
```

#### exists

Check if records exist.

```php
public function exists(): bool
```

#### doesntExist

Check if records don't exist.

```php
public function doesntExist(): bool
```

### Aggregates

#### count

Count records.

```php
public function count(string $column = '*'): int
```

#### sum

Sum column values.

```php
public function sum(string $column): float
```

#### avg

Average column values.

```php
public function avg(string $column): float
```

#### min

Minimum column value.

```php
public function min(string $column): mixed
```

#### max

Maximum column value.

```php
public function max(string $column): mixed
```

### Insert Operations

#### insert

Insert records.

```php
public function insert(array $values): bool
```

#### insertGetId

Insert and get ID.

```php
public function insertGetId(array $values, ?string $id = null): string
```

#### upsert

Insert or update on conflict.

```php
public function upsert(
    array $values,
    array|string $uniqueBy,
    ?array $updateColumns = null
): bool
```

### Update Operations

#### update

Update records.

```php
public function update(array $values): int
```

#### increment

Increment column.

```php
public function increment(
    string $column,
    int|float $amount = 1,
    array $extra = []
): int
```

#### decrement

Decrement column.

```php
public function decrement(
    string $column,
    int|float $amount = 1,
    array $extra = []
): int
```

### Delete Operations

#### delete

Delete records.

```php
public function delete(?int $id = null): int
```

### SQL Output

#### toSql

Get the SQL string.

```php
public function toSql(): string
```

#### bindings

Get query bindings.

```php
public function bindings(): array
```

---

## SchemaBuilder

### Constructor

```php
public function __construct(Connection $connection)
```

### Methods

#### create

Create a new table.

```php
public function create(string $table, Closure $callback): void
```

#### table

Modify an existing table.

```php
public function table(string $table, Closure $callback): void
```

#### drop

Drop a table.

```php
public function drop(string $table): void
```

#### dropIfExists

Drop table if it exists.

```php
public function dropIfExists(string $table): void
```

#### rename

Rename a table.

```php
public function rename(string $from, string $to): void
```

#### hasTable

Check if table exists.

```php
public function hasTable(string $table): bool
```

#### hasColumn

Check if column exists.

```php
public function hasColumn(string $table, string $column): bool
```

---

## Blueprint

### Column Types

```php
public function id(string $column = 'id'): ColumnDefinition
public function bigIncrements(string $column): ColumnDefinition
public function increments(string $column): ColumnDefinition
public function bigInteger(string $column): ColumnDefinition
public function integer(string $column): ColumnDefinition
public function mediumInteger(string $column): ColumnDefinition
public function smallInteger(string $column): ColumnDefinition
public function tinyInteger(string $column): ColumnDefinition
public function unsignedBigInteger(string $column): ColumnDefinition
public function unsignedInteger(string $column): ColumnDefinition
public function string(string $column, int $length = 255): ColumnDefinition
public function char(string $column, int $length = 255): ColumnDefinition
public function text(string $column): ColumnDefinition
public function mediumText(string $column): ColumnDefinition
public function longText(string $column): ColumnDefinition
public function decimal(string $column, int $precision = 8, int $scale = 2): ColumnDefinition
public function float(string $column): ColumnDefinition
public function double(string $column): ColumnDefinition
public function boolean(string $column): ColumnDefinition
public function date(string $column): ColumnDefinition
public function dateTime(string $column): ColumnDefinition
public function timestamp(string $column): ColumnDefinition
public function time(string $column): ColumnDefinition
public function year(string $column): ColumnDefinition
public function binary(string $column): ColumnDefinition
public function json(string $column): ColumnDefinition
public function uuid(string $column): ColumnDefinition
public function enum(string $column, array $allowed): ColumnDefinition
```

### Helpers

```php
public function timestamps(): void  // created_at, updated_at
public function softDeletes(): void // deleted_at
public function rememberToken(): void // remember_token
```

### Indexes

```php
public function primary(string|array $columns): void
public function unique(string|array $columns, ?string $name = null): void
public function index(string|array $columns, ?string $name = null): void
```

### Foreign Keys

```php
public function foreign(string $column): ForeignKeyDefinition
```

### Column Operations

```php
public function renameColumn(string $from, string $to): void
public function dropColumn(string|array $columns): void
```

### Index Operations

```php
public function dropPrimary(?string $name = null): void
public function dropUnique(string $name): void
public function dropIndex(string $name): void
public function dropForeign(string $name): void
```

---

## ColumnDefinition

### Modifiers

```php
public function nullable(bool $value = true): self
public function default(mixed $value): self
public function unsigned(): self
public function autoIncrement(): self
public function primary(): self
public function unique(): self
public function index(): self
public function comment(string $comment): self
public function after(string $column): self
public function first(): self
```

---

## ForeignKeyDefinition

### Methods

```php
public function references(string $column): self
public function on(string $table): self
public function onDelete(string $action): self  // CASCADE, SET NULL, RESTRICT, NO ACTION
public function onUpdate(string $action): self
```

---

## Migrator

### Constructor

```php
public function __construct(
    Connection $connection,
    MigrationRepository $repository
)
```

### Methods

#### run

Run pending migrations.

```php
public function run(string $path): array
```

#### rollback

Rollback migrations.

```php
public function rollback(?int $steps = null): array
```

#### reset

Reset all migrations.

```php
public function reset(): array
```

#### getRan

Get list of ran migrations.

```php
public function getRan(): array
```

#### getPending

Get list of pending migrations.

```php
public function getPending(string $path): array
```

---

## MigrationRepository

### Constructor

```php
public function __construct(Connection $connection, string $table = 'migrations')
```

### Methods

#### createTable

Create the migrations table.

```php
public function createTable(): void
```

#### getRan

Get all ran migrations.

```php
public function getRan(): array
```

#### getLast

Get last batch migrations.

```php
public function getLast(): array
```

#### log

Log a migration as ran.

```php
public function log(string $migration, int $batch): void
```

#### delete

Delete a migration record.

```php
public function delete(string $migration): void
```

#### getNextBatchNumber

Get next batch number.

```php
public function getNextBatchNumber(): int
```

---

## Migration

Abstract base class for migrations.

```php
abstract class Migration
{
    abstract public function up(SchemaBuilder $schema): void;
    abstract public function down(SchemaBuilder $schema): void;
}
```

---

## SeederRunner

### Constructor

```php
public function __construct(Connection $connection)
```

### Methods

#### run

Run a seeder instance.

```php
public function run(Seeder $seeder): void
```

#### runClass

Run a seeder by class name.

```php
public function runClass(string $class): void
```

---

## Seeder

Abstract base class for seeders.

```php
abstract class Seeder
{
    abstract public function run(Connection $connection): void;
}
```
