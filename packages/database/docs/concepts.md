# Core Concepts

This document explains the fundamental concepts and architecture of the Lalaz Database package.

## Architecture Overview

The package follows a layered architecture:

```
Application Code
       ↓
  QueryBuilder / SchemaBuilder / Migrator
       ↓
    Connection (PDO Wrapper)
       ↓
  ConnectionManager (Pool & Routing)
       ↓
    Connectors (Driver-specific)
       ↓
      PDO
```

## Connection Management

### ConnectionManager

The `ConnectionManager` is responsible for:

1. **Connection Pooling**: Maintains a pool of PDO connections
2. **Driver Resolution**: Resolves and configures database drivers
3. **Read Replica Routing**: Routes queries to appropriate servers
4. **Query Event Dispatching**: Broadcasts query events for logging

```php
$manager = new ConnectionManager([
    'driver' => 'mysql',
    'connections' => [...],
    'pool' => ['min' => 2, 'max' => 10],
]);

// Acquire connection from pool
$pdo = $manager->acquire();

// Release back to pool
$manager->release($pdo);
```

### Connection

The `Connection` class wraps PDO and provides:

1. **Query Execution**: select, insert, update, delete
2. **Transaction Management**: begin, commit, rollback
3. **Query Building**: Access to fluent QueryBuilder
4. **Role-based Routing**: Automatic read/write splitting

```php
$connection = new Connection($manager);

// Direct query
$rows = $connection->select('SELECT * FROM users');

// Query builder
$rows = $connection->table('users')->get();

// Transaction
$connection->transaction(function ($conn) {
    // Guaranteed commit or rollback
});
```

### Connection Pooling

Connection pooling improves performance by reusing connections:

```
┌─────────────────────────────────────┐
│         Connection Pool             │
├─────────────────────────────────────┤
│  ┌─────┐ ┌─────┐ ┌─────┐ ┌─────┐   │
│  │ PDO │ │ PDO │ │ PDO │ │ ... │   │
│  └──┬──┘ └──┬──┘ └──┬──┘ └──┬──┘   │
│     │       │       │       │       │
│  acquire() │    release()  │       │
└─────┼───────┼───────┼───────┼───────┘
      ↓       ↓       ↓       ↓
   Request  Request  Request  Request
```

Configuration:
```php
'pool' => [
    'min' => 0,      // Minimum idle connections
    'max' => 10,     // Maximum total connections
    'timeout_ms' => 5000, // Acquire timeout
]
```

### Read Replica Support

For read scaling, configure read replicas:

```
         ┌─────────────┐
         │   Write     │←─── INSERT/UPDATE/DELETE
         │  (Primary)  │
         └──────┬──────┘
                │ Replication
    ┌───────────┼───────────┐
    ↓           ↓           ↓
┌───────┐  ┌───────┐  ┌───────┐
│Replica│  │Replica│  │Replica│←─── SELECT
│   1   │  │   2   │  │   3   │
└───────┘  └───────┘  └───────┘
```

Configuration:
```php
'read' => [
    'enabled' => true,
    'driver' => 'mysql',
    'sticky' => true, // Use primary after write
    'connections' => [
        ['host' => 'replica1.db.example.com', ...],
        ['host' => 'replica2.db.example.com', ...],
    ],
]
```

## Query Builder

The QueryBuilder provides a fluent interface for SQL construction:

### Method Chaining

```php
$query = $connection->table('users')
    ->select('id', 'name')     // Returns $this
    ->where('active', true)    // Returns $this
    ->orderBy('name')          // Returns $this
    ->limit(10);               // Returns $this

// Execute
$results = $query->get();      // Returns array
```

### Immutability Consideration

The QueryBuilder is mutable. Each method modifies and returns the same instance:

```php
$query = $connection->table('users');
$query->where('role', 'admin');

// $query is now modified
$admins = $query->get();
```

For separate queries, use `newQuery()`:

```php
$query = $connection->table('users');

$adminQuery = $query->newQuery()
    ->from('users')
    ->where('role', 'admin');

$userQuery = $query->newQuery()
    ->from('users')
    ->where('role', 'user');
```

### Query Compilation

The Grammar class compiles QueryBuilder state to SQL:

```
QueryBuilder State           Grammar            SQL Output
─────────────────────────────────────────────────────────────
columns: ['id', 'name']  →                  → SELECT "id", "name"
from: 'users'            →   compile()      → FROM "users"
wheres: [{col: 'active', →                  → WHERE "active" = ?
  op: '=', val: true}]
```

Each driver has its own Grammar for syntax differences:
- **SqliteGrammar**: Double quotes for identifiers
- **MySqlGrammar**: Backticks for identifiers
- **PostgresGrammar**: Double quotes, specific functions

## Schema Management

### SchemaBuilder

Creates and modifies database schema:

```php
$schema = new SchemaBuilder($connection);

// Create table
$schema->create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
});

// Modify table
$schema->table('users', function (Blueprint $table) {
    $table->string('email')->after('name');
});
```

### Blueprint

Blueprint collects table definition instructions:

```php
$table->id();                    // Adds ID column command
$table->string('name');          // Adds string column command
$table->timestamps();            // Adds created_at, updated_at
$table->index('name');           // Adds index command
$table->foreign('user_id')       // Adds foreign key command
      ->references('id')
      ->on('users');
```

Commands are collected and compiled to DDL:

```
Blueprint Commands              SchemaGrammar         DDL Output
───────────────────────────────────────────────────────────────────
[id: bigIncrements]        →                    → "id" BIGINT AUTO_INCREMENT
[name: string(255)]        →    compile()       → "name" VARCHAR(255)
[index: idx_name on name]  →                    → CREATE INDEX idx_name...
```

### Column Definition

ColumnDefinition provides fluent column modifiers:

```php
$table->string('email')
    ->nullable()           // NULL allowed
    ->unique()             // Unique constraint
    ->default('none')      // Default value
    ->comment('Email')     // Column comment
    ->after('name');       // Position hint
```

## Migration System

### Migration Lifecycle

```
        ┌──────────────┐
        │ Create File  │
        │ (timestamp)  │
        └──────┬───────┘
               ↓
        ┌──────────────┐
        │    Run Up    │──→ Schema Changes
        │   migrate    │    Applied to DB
        └──────┬───────┘
               ↓
        ┌──────────────┐
        │   Record     │──→ _migrations table
        │   Batch      │    updated
        └──────┬───────┘
               ↓
        ┌──────────────┐
        │  Rollback    │←── Optional
        │   Run Down   │
        └──────────────┘
```

### MigrationRepository

Tracks which migrations have run:

```sql
CREATE TABLE migrations (
    id INTEGER PRIMARY KEY,
    migration VARCHAR(255),  -- Migration name
    batch INTEGER            -- Batch number
);
```

### Batch System

Migrations run together form a batch:

```
Batch 1: 2024_01_01_create_users
         2024_01_01_create_posts

Batch 2: 2024_02_01_add_email_to_users

Rollback → Removes batch 2 first
```

## Expression System

Raw SQL expressions bypass escaping:

```php
use Lalaz\Database\Query\Expression;

// Direct SQL
$query->select(new Expression('COUNT(*) as total'));

// Via helper
$query->selectRaw('COUNT(*) as total');

// In where
$query->whereRaw('YEAR(created_at) = ?', [2024]);
```

Use with caution - expressions are not escaped.

## Transaction Management

### ACID Compliance

Transactions ensure:
- **Atomicity**: All or nothing
- **Consistency**: Valid state transitions
- **Isolation**: Concurrent transaction separation
- **Durability**: Committed changes persist

### Transaction Ownership

Connection tracks transaction ownership to prevent nested issues:

```php
$connection->beginTransaction();  // ownsTransaction = true
    // ... operations
$connection->commit();            // ownsTransaction = false

// Automatic cleanup on destruction
// If still in transaction, rollback occurs
```

### Sticky Reads

After write operations, reads route to primary:

```
1. SELECT → Read replica
2. INSERT → Primary (sets sticky flag)
3. SELECT → Primary (sticky active)
4. ... continues on primary until new connection
```

This prevents read-after-write inconsistency with replication lag.

## Error Handling

### Retry Logic

Configurable retry for transient errors:

```php
'retry' => [
    'attempts' => 3,
    'delay_ms' => 100,
    'retry_on' => ['2006', 'HY000'], // MySQL gone away
]
```

### Exception Hierarchy

```
DatabaseException (base)
├── ConnectionException
├── QueryException
├── SchemaException
└── MigrationException
```

## Performance Considerations

### Query Profiling

Every query is profiled:

```php
$manager->listenQuery(function (array $event) {
    // $event contains:
    // - sql: The query
    // - bindings: Parameters
    // - duration_ms: Execution time
    // - type: select/insert/update/delete
    // - role: read/write
    // - driver: sqlite/mysql/postgres
});
```

### Prepared Statements

All queries use prepared statements:

1. **Security**: Prevents SQL injection
2. **Performance**: Statement caching
3. **Type Safety**: Proper parameter binding

### Batch Operations

For bulk operations, use batch methods:

```php
// Efficient: Single query
$connection->table('users')->insert([
    ['name' => 'User 1'],
    ['name' => 'User 2'],
    ['name' => 'User 3'],
]);

// Inefficient: Multiple queries
foreach ($users as $user) {
    $connection->table('users')->insert($user);
}
```
