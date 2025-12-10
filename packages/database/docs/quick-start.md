# Quick Start

Get started with Lalaz Database in under 5 minutes.

## Installation

```bash
composer require lalaz/database
```

## Basic Setup

### 1. Create Configuration

```php
$config = [
    'driver' => 'sqlite',
    'connections' => [
        'sqlite' => [
            'database' => ':memory:',
        ],
    ],
];
```

### 2. Create Connection

```php
use Lalaz\Database\ConnectionManager;
use Lalaz\Database\Connection;

$manager = new ConnectionManager($config);
$connection = new Connection($manager);
```

### 3. Start Querying

```php
// Create a table
$connection->exec('
    CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT UNIQUE NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
');

// Insert data
$connection->table('users')->insert([
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);

// Query data
$users = $connection->table('users')
    ->where('name', 'like', '%John%')
    ->get();

// Update data
$connection->table('users')
    ->where('id', 1)
    ->update(['name' => 'Jane Doe']);

// Delete data
$connection->table('users')
    ->where('id', 1)
    ->delete();
```

## Database-Specific Configurations

### SQLite

```php
$config = [
    'driver' => 'sqlite',
    'connections' => [
        'sqlite' => [
            'database' => '/path/to/database.sqlite',
            // Or use in-memory:
            // 'database' => ':memory:',
        ],
    ],
];
```

### MySQL

```php
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
];
```

### PostgreSQL

```php
$config = [
    'driver' => 'postgres',
    'connections' => [
        'postgres' => [
            'host' => 'localhost',
            'port' => 5432,
            'database' => 'myapp',
            'username' => 'postgres',
            'password' => 'secret',
            'schema' => 'public',
        ],
    ],
];
```

## Using Schema Builder

```php
use Lalaz\Database\Schema\SchemaBuilder;
use Lalaz\Database\Schema\Blueprint;

$schema = new SchemaBuilder($connection);

// Create tables
$schema->create('posts', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->text('content');
    $table->unsignedBigInteger('user_id');
    $table->timestamps();
    
    $table->foreign('user_id')
          ->references('id')
          ->on('users')
          ->onDelete('cascade');
});
```

## Working with Transactions

```php
// Automatic transaction
$orderId = $connection->transaction(function ($conn) {
    $conn->table('orders')->insert([
        'user_id' => 1,
        'total' => 99.99,
    ]);
    
    $orderId = $conn->getPdo()->lastInsertId();
    
    $conn->table('order_items')->insert([
        'order_id' => $orderId,
        'product_id' => 42,
        'quantity' => 2,
    ]);
    
    return $orderId;
});

// Manual transaction
$connection->beginTransaction();
try {
    // ... operations
    $connection->commit();
} catch (\Exception $e) {
    $connection->rollBack();
    throw $e;
}
```

## Using Migrations

### Create Migration File

```php
// database/migrations/2024_01_01_000001_create_users_table.php
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
            $table->timestamps();
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropIfExists('users');
    }
}
```

### Run Migrations

```php
use Lalaz\Database\Migrations\Migrator;
use Lalaz\Database\Migrations\MigrationRepository;

$repository = new MigrationRepository($connection, 'migrations');
$repository->createTable();

$migrator = new Migrator($connection, $repository);
$migrator->run('/path/to/migrations');
```

## Next Steps

- [Concepts](concepts.md) - Understand the architecture
- [API Reference](api-reference.md) - Explore all methods
- [Testing](testing.md) - Learn testing strategies
