# Lalaz Database

A high-performance database abstraction layer for PHP 8.3+ with connection pooling, read replicas, fluent query builder, schema management, and migrations.

## Features

- **Connection Management**: PDO wrapper with connection pooling, read replica routing, and query profiling
- **Query Builder**: Fluent interface for building SQL queries with joins, subqueries, unions, and aggregates
- **Schema Builder**: Database-agnostic schema management with Blueprint for table definitions
- **Migrations**: Version-controlled database schema changes with batch support
- **Seeders**: Database seeding for development and testing
- **Multi-Driver**: SQLite, MySQL, and PostgreSQL support
- **Read Replicas**: Automatic read/write splitting with sticky sessions
- **Transaction Support**: Nested transactions with automatic rollback
- **Query Logging**: Built-in query profiling and logging via PSR-3

## Installation

```bash
composer require lalaz/database
```

## Quick Start

### Basic Configuration

```php
use Lalaz\Database\ConnectionManager;
use Lalaz\Database\Connection;

$config = [
    'driver' => 'mysql',
    'connections' => [
        'mysql' => [
            'host' => 'localhost',
            'port' => 3306,
            'database' => 'myapp',
            'username' => 'root',
            'password' => 'secret',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],
    ],
    'pool' => [
        'min' => 0,
        'max' => 10,
        'timeout_ms' => 5000,
    ],
];

$manager = new ConnectionManager($config);
$connection = new Connection($manager);
```

### Query Builder

```php
// Select queries
$users = $connection->table('users')
    ->select('id', 'name', 'email')
    ->where('active', true)
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();

// Insert
$connection->table('users')->insert([
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);

// Update
$connection->table('users')
    ->where('id', 1)
    ->update(['name' => 'Jane Doe']);

// Delete
$connection->table('users')
    ->where('id', 1)
    ->delete();
```

### Transactions

```php
$connection->transaction(function ($conn) {
    $conn->table('accounts')
        ->where('id', 1)
        ->decrement('balance', 100);
    
    $conn->table('accounts')
        ->where('id', 2)
        ->increment('balance', 100);
});
```

## Connection Management

### Connection Pooling

The `ConnectionManager` provides efficient connection pooling:

```php
$config = [
    'driver' => 'mysql',
    'connections' => [...],
    'pool' => [
        'min' => 2,        // Minimum connections to maintain
        'max' => 20,       // Maximum connections allowed
        'timeout_ms' => 5000, // Acquire timeout
    ],
];

$manager = new ConnectionManager($config);

// Check pool status
$status = $manager->poolStatus();
// ['total' => 5, 'pooled' => 3, 'max' => 20, 'min' => 2]
```

### Read Replicas

Configure read replica routing for scaling:

```php
$config = [
    'driver' => 'mysql',
    'connections' => [
        'mysql' => [
            'host' => 'primary.db.example.com',
            // ... primary config
        ],
    ],
    'read' => [
        'enabled' => true,
        'driver' => 'mysql',
        'sticky' => true, // Use primary after writes
        'connections' => [
            ['host' => 'replica1.db.example.com', ...],
            ['host' => 'replica2.db.example.com', ...],
        ],
        'pool' => [
            'max' => 10,
            'timeout_ms' => 3000,
        ],
    ],
];
```

### Query Logging

```php
use Psr\Log\LoggerInterface;

$manager = new ConnectionManager($config, $logger);

// Or add custom listener
$manager->listenQuery(function (array $event) {
    echo sprintf(
        "[%s] %s: %sms",
        $event['role'],
        $event['sql'],
        $event['duration_ms']
    );
});
```

## Query Builder

### Selects

```php
// Basic select
$users = $connection->table('users')->get();

// Select specific columns
$users = $connection->table('users')
    ->select('id', 'name')
    ->get();

// Distinct
$names = $connection->table('users')
    ->distinct()
    ->select('name')
    ->get();

// Raw expressions
$users = $connection->table('users')
    ->selectRaw('COUNT(*) as total, status')
    ->groupBy('status')
    ->get();

// Subquery select
$users = $connection->table('users')
    ->selectSub(
        fn($q) => $q->from('orders')
            ->selectRaw('COUNT(*)')
            ->whereColumn('orders.user_id', 'users.id'),
        'order_count'
    )
    ->get();
```

### Where Clauses

```php
// Basic where
$users = $connection->table('users')
    ->where('status', 'active')
    ->where('age', '>=', 18)
    ->get();

// Or where
$users = $connection->table('users')
    ->where('role', 'admin')
    ->orWhere('role', 'moderator')
    ->get();

// Where in
$users = $connection->table('users')
    ->whereIn('id', [1, 2, 3])
    ->get();

// Where between
$users = $connection->table('users')
    ->whereBetween('created_at', ['2024-01-01', '2024-12-31'])
    ->get();

// Where null
$users = $connection->table('users')
    ->whereNull('deleted_at')
    ->get();

// Where exists
$users = $connection->table('users')
    ->whereExists(fn($q) => $q
        ->from('orders')
        ->whereColumn('orders.user_id', 'users.id'))
    ->get();

// Nested where
$users = $connection->table('users')
    ->where('active', true)
    ->where(function ($query) {
        $query->where('role', 'admin')
              ->orWhere('role', 'moderator');
    })
    ->get();

// Raw where
$users = $connection->table('users')
    ->whereRaw('YEAR(created_at) = ?', [2024])
    ->get();
```

### Joins

```php
// Inner join
$results = $connection->table('users')
    ->join('orders', 'users.id', '=', 'orders.user_id')
    ->select('users.name', 'orders.total')
    ->get();

// Left join
$results = $connection->table('users')
    ->leftJoin('profiles', 'users.id', '=', 'profiles.user_id')
    ->get();

// Right join
$results = $connection->table('users')
    ->rightJoin('posts', 'users.id', '=', 'posts.author_id')
    ->get();

// Cross join
$combinations = $connection->table('sizes')
    ->crossJoin('colors')
    ->get();

// Advanced join
$results = $connection->table('users')
    ->join('orders', function ($join) {
        $join->on('users.id', '=', 'orders.user_id')
             ->on('orders.status', '=', 'completed');
    })
    ->get();

// Join with subquery
$results = $connection->table('users')
    ->joinSub(
        fn($q) => $q->from('orders')
            ->selectRaw('user_id, SUM(total) as total_spent')
            ->groupBy('user_id'),
        'order_totals',
        'users.id',
        '=',
        'order_totals.user_id'
    )
    ->get();
```

### Aggregates

```php
$count = $connection->table('users')->count();
$sum = $connection->table('orders')->sum('total');
$avg = $connection->table('products')->avg('price');
$min = $connection->table('products')->min('price');
$max = $connection->table('products')->max('price');

// Check existence
if ($connection->table('users')->where('email', $email)->exists()) {
    // User exists
}
```

### Grouping and Having

```php
$stats = $connection->table('orders')
    ->selectRaw('user_id, COUNT(*) as order_count, SUM(total) as total_spent')
    ->groupBy('user_id')
    ->having('order_count', '>', 5)
    ->get();
```

### Ordering and Pagination

```php
// Order by
$users = $connection->table('users')
    ->orderBy('name', 'asc')
    ->orderByDesc('created_at')
    ->get();

// Pagination
$users = $connection->table('users')
    ->forPage(2, 15) // Page 2, 15 per page
    ->get();

// Limit and offset
$users = $connection->table('users')
    ->offset(10)
    ->limit(5)
    ->get();

// Latest/Oldest helpers
$recent = $connection->table('posts')->latest()->first();
$oldest = $connection->table('posts')->oldest()->first();
```

### Unions

```php
$admins = $connection->table('users')
    ->select('name', 'email')
    ->where('role', 'admin');

$moderators = $connection->table('users')
    ->select('name', 'email')
    ->where('role', 'moderator')
    ->union($admins)
    ->get();
```

### Locking

```php
// Pessimistic locking
$user = $connection->table('users')
    ->where('id', 1)
    ->lockForUpdate()
    ->first();

// Shared lock
$user = $connection->table('users')
    ->where('id', 1)
    ->sharedLock()
    ->first();
```

### Insert Operations

```php
// Single insert
$connection->table('users')->insert([
    'name' => 'John',
    'email' => 'john@example.com',
]);

// Get last insert ID
$id = $connection->table('users')->insertGetId([
    'name' => 'Jane',
    'email' => 'jane@example.com',
]);

// Batch insert
$connection->table('users')->insert([
    ['name' => 'User 1', 'email' => 'user1@example.com'],
    ['name' => 'User 2', 'email' => 'user2@example.com'],
]);

// Upsert (insert or update)
$connection->table('users')->upsert(
    [
        ['email' => 'john@example.com', 'name' => 'John Updated'],
        ['email' => 'new@example.com', 'name' => 'New User'],
    ],
    ['email'], // Unique columns
    ['name']   // Columns to update on conflict
);
```

### Update Operations

```php
// Basic update
$affected = $connection->table('users')
    ->where('id', 1)
    ->update(['name' => 'Updated Name']);

// Increment/Decrement
$connection->table('posts')
    ->where('id', 1)
    ->increment('views');

$connection->table('products')
    ->where('id', 1)
    ->decrement('stock', 5);

// With additional updates
$connection->table('users')
    ->where('id', 1)
    ->increment('login_count', 1, [
        'last_login_at' => date('Y-m-d H:i:s'),
    ]);
```

## Schema Builder

### Creating Tables

```php
use Lalaz\Database\Schema\SchemaBuilder;
use Lalaz\Database\Schema\Blueprint;

$schema = new SchemaBuilder($connection);

$schema->create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->string('password');
    $table->text('bio')->nullable();
    $table->boolean('active')->default(true);
    $table->timestamp('email_verified_at')->nullable();
    $table->timestamps();
    
    $table->index('active');
});
```

### Column Types

```php
$schema->create('examples', function (Blueprint $table) {
    // Auto-increment
    $table->id();                      // BIGINT PRIMARY KEY AUTO_INCREMENT
    $table->bigIncrements('id');       // Same as id()
    $table->increments('id');          // INT AUTO_INCREMENT
    
    // Integers
    $table->bigInteger('views');
    $table->integer('quantity');
    $table->mediumInteger('medium');
    $table->smallInteger('small');
    $table->tinyInteger('tiny');
    $table->unsignedBigInteger('user_id');
    $table->unsignedInteger('count');
    
    // Strings
    $table->char('code', 3);
    $table->string('name');           // VARCHAR(255)
    $table->string('title', 100);     // VARCHAR(100)
    $table->text('description');
    $table->mediumText('content');
    $table->longText('body');
    
    // Numbers
    $table->decimal('price', 10, 2);
    $table->float('rating');
    $table->double('latitude');
    
    // Boolean
    $table->boolean('active');
    
    // Date/Time
    $table->date('birth_date');
    $table->dateTime('published_at');
    $table->timestamp('verified_at');
    $table->time('start_time');
    $table->year('graduation_year');
    
    // Binary
    $table->binary('data');
    
    // Special
    $table->json('settings');
    $table->uuid('uuid');
    $table->enum('status', ['pending', 'active', 'cancelled']);
    
    // Helpers
    $table->timestamps();             // created_at, updated_at
    $table->softDeletes();           // deleted_at
    $table->rememberToken();         // remember_token
});
```

### Column Modifiers

```php
$table->string('email')
    ->nullable()
    ->unique()
    ->default('guest@example.com')
    ->comment('User email address');

$table->string('old_column')
    ->after('new_column');

$table->integer('position')
    ->first();
```

### Indexes

```php
$schema->create('posts', function (Blueprint $table) {
    $table->id();
    $table->string('slug');
    $table->string('title');
    $table->unsignedBigInteger('user_id');
    
    // Single column indexes
    $table->unique('slug');
    $table->index('title');
    
    // Composite indexes
    $table->index(['user_id', 'created_at']);
    
    // Named indexes
    $table->index('status', 'idx_posts_status');
});
```

### Foreign Keys

```php
$schema->create('posts', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id');
    $table->unsignedBigInteger('category_id');
    
    // Basic foreign key
    $table->foreign('user_id')
          ->references('id')
          ->on('users');
    
    // With cascade
    $table->foreign('category_id')
          ->references('id')
          ->on('categories')
          ->onDelete('cascade')
          ->onUpdate('cascade');
});
```

### Modifying Tables

```php
// Add column
$schema->table('users', function (Blueprint $table) {
    $table->string('phone')->nullable()->after('email');
});

// Rename column (SQLite has limitations)
$schema->table('users', function (Blueprint $table) {
    $table->renameColumn('phone', 'phone_number');
});

// Drop column
$schema->table('users', function (Blueprint $table) {
    $table->dropColumn('temporary');
    $table->dropColumn(['col1', 'col2']);
});

// Rename table
$schema->rename('old_table', 'new_table');

// Drop table
$schema->drop('temporary_table');
$schema->dropIfExists('maybe_exists');
```

## Migrations

### Creating Migrations

```php
use Lalaz\Database\Migrations\Migration;
use Lalaz\Database\Schema\Blueprint;
use Lalaz\Database\Schema\SchemaBuilder;

class CreateUsersTable extends Migration
{
    public function up(SchemaBuilder $schema): void
    {
        $schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropIfExists('users');
    }
}
```

### Running Migrations

```php
use Lalaz\Database\Migrations\Migrator;
use Lalaz\Database\Migrations\MigrationRepository;

$repository = new MigrationRepository($connection, 'migrations');
$repository->createTable(); // Create migrations tracking table

$migrator = new Migrator($connection, $repository);

// Run all pending migrations
$migrator->run('/path/to/migrations');

// Rollback last batch
$migrator->rollback();

// Rollback specific steps
$migrator->rollback(2); // Rollback 2 migrations

// Reset all migrations
$migrator->reset();

// Get migration status
$ran = $migrator->getRan();
$pending = $migrator->getPending('/path/to/migrations');
```

### Console Commands

```bash
# Run migrations
php lalaz migrate

# Rollback
php lalaz migrate:rollback
php lalaz migrate:rollback --step=2

# Reset all
php lalaz migrate:reset

# Refresh (reset + migrate)
php lalaz migrate:refresh

# Check status
php lalaz migrate:status

# Create migration
php lalaz craft:migration CreateUsersTable
```

## Seeders

### Creating Seeders

```php
use Lalaz\Database\Seeding\Seeder;
use Lalaz\Database\Connection;

class UsersSeeder extends Seeder
{
    public function run(Connection $connection): void
    {
        $connection->table('users')->insert([
            [
                'name' => 'Admin',
                'email' => 'admin@example.com',
                'password' => password_hash('secret', PASSWORD_DEFAULT),
            ],
            [
                'name' => 'User',
                'email' => 'user@example.com',
                'password' => password_hash('secret', PASSWORD_DEFAULT),
            ],
        ]);
    }
}
```

### Running Seeders

```php
use Lalaz\Database\Seeding\SeederRunner;

$runner = new SeederRunner($connection);

// Run specific seeder
$runner->run(new UsersSeeder());

// Run from class name
$runner->runClass(UsersSeeder::class);
```

## Transactions

### Basic Transactions

```php
// Automatic transaction handling
$result = $connection->transaction(function ($conn) {
    $conn->table('orders')->insert([
        'user_id' => 1,
        'total' => 100.00,
    ]);
    
    return $conn->getPdo()->lastInsertId();
});

// Manual transaction control
$connection->beginTransaction();

try {
    $connection->table('accounts')->where('id', 1)->decrement('balance', 50);
    $connection->table('accounts')->where('id', 2)->increment('balance', 50);
    $connection->commit();
} catch (\Exception $e) {
    $connection->rollBack();
    throw $e;
}
```

## Raw Queries

```php
// Select
$users = $connection->select(
    'SELECT * FROM users WHERE active = ?',
    [true]
);

// Insert
$connection->insert(
    'INSERT INTO logs (message) VALUES (?)',
    ['User logged in']
);

// Update
$affected = $connection->update(
    'UPDATE users SET last_login = ? WHERE id = ?',
    [date('Y-m-d H:i:s'), 1]
);

// Delete
$deleted = $connection->delete(
    'DELETE FROM sessions WHERE expires_at < ?',
    [date('Y-m-d H:i:s')]
);

// Generic query
$statement = $connection->query(
    'SELECT COUNT(*) as count FROM users'
);
```

## Service Provider

Register the database service in your container:

```php
use Lalaz\Database\DatabaseServiceProvider;

$provider = new DatabaseServiceProvider($container);
$provider->register();

// Use from container
$connection = $container->get(Connection::class);
```

## Testing

```bash
# Run tests
composer test

# Run with coverage
composer test:coverage

# Run specific test file
./vendor/bin/phpunit tests/Unit/Query/QueryBuilderTest.php
```

## Requirements

- PHP 8.3 or higher
- PDO extension
- One of: SQLite, MySQL 5.7+, PostgreSQL 10+

## License

MIT License. See [LICENSE](LICENSE) for details.
