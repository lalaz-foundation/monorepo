# Database

Lalaz provides a powerful database layer with connection pooling, query building, schema management, and an elegant ORM. It supports MySQL, PostgreSQL, and SQLite out of the box.

## Configuration

Configure your database connection in `config/database.php`:

```php
<?php declare(strict_types=1);

return [
    'database' => [
        'driver' => env('DB_CONNECTION', 'sqlite'),

        'connections' => [
            'sqlite' => [
                'database' => env('DB_DATABASE', __DIR__ . '/../storage/database.sqlite'),
            ],

            'mysql' => [
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', 3306),
                'database' => env('DB_DATABASE', 'lalaz'),
                'username' => env('DB_USERNAME', 'root'),
                'password' => env('DB_PASSWORD', ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ],

            'postgres' => [
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', 5432),
                'database' => env('DB_DATABASE', 'lalaz'),
                'username' => env('DB_USERNAME', 'postgres'),
                'password' => env('DB_PASSWORD', ''),
                'schema' => 'public',
            ],
        ],

        'pool' => [
            'min' => 0,
            'max' => 5,
            'timeout_ms' => 5000,
        ],
    ],
];
```

### Environment Variables

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=myapp
DB_USERNAME=root
DB_PASSWORD=secret
```

## Query Builder

The query builder provides a fluent interface for building and executing database queries.

### Basic Queries

```php
use Lalaz\Database\Connection;

// Get all records
$users = $connection->table('users')->get();

// Get first record
$user = $connection->table('users')->first();

// Get specific columns
$users = $connection->table('users')
    ->select('id', 'name', 'email')
    ->get();

// Count records
$count = $connection->table('users')->count();
```

### Where Clauses

```php
// Basic where
$users = $connection->table('users')
    ->where('status', 'active')
    ->get();

// With operator
$users = $connection->table('users')
    ->where('age', '>=', 18)
    ->get();

// Multiple conditions
$users = $connection->table('users')
    ->where('status', 'active')
    ->where('role', 'admin')
    ->get();

// Or conditions
$users = $connection->table('users')
    ->where('role', 'admin')
    ->orWhere('role', 'moderator')
    ->get();

// Array of conditions
$users = $connection->table('users')
    ->where([
        'status' => 'active',
        'verified' => true,
    ])
    ->get();
```

### Advanced Where Clauses

```php
// Where In
$users = $connection->table('users')
    ->whereIn('id', [1, 2, 3])
    ->get();

// Where Not In
$users = $connection->table('users')
    ->whereNotIn('status', ['banned', 'suspended'])
    ->get();

// Where Null
$users = $connection->table('users')
    ->whereNull('deleted_at')
    ->get();

// Where Not Null
$users = $connection->table('users')
    ->whereNotNull('email_verified_at')
    ->get();

// Where Between
$users = $connection->table('users')
    ->whereBetween('age', [18, 65])
    ->get();

// Where Exists
$users = $connection->table('users')
    ->whereExists(fn($query) => $query
        ->from('orders')
        ->whereColumn('orders.user_id', 'users.id')
    )
    ->get();

// Raw Where
$users = $connection->table('users')
    ->whereRaw('YEAR(created_at) = ?', [2024])
    ->get();
```

### Nested Where Groups

```php
$users = $connection->table('users')
    ->where('status', 'active')
    ->where(function ($query) {
        $query->where('role', 'admin')
              ->orWhere('role', 'moderator');
    })
    ->get();

// SQL: SELECT * FROM users WHERE status = 'active' AND (role = 'admin' OR role = 'moderator')
```

### Ordering and Limiting

```php
// Order by
$users = $connection->table('users')
    ->orderBy('name', 'asc')
    ->get();

// Multiple order by
$users = $connection->table('users')
    ->orderBy('role', 'asc')
    ->orderBy('name', 'asc')
    ->get();

// Order by descending
$users = $connection->table('users')
    ->orderByDesc('created_at')
    ->get();

// Limit and offset
$users = $connection->table('users')
    ->limit(10)
    ->offset(20)
    ->get();

// Latest / Oldest
$users = $connection->table('users')
    ->latest('created_at')
    ->get();
```

### Joins

```php
// Inner join
$posts = $connection->table('posts')
    ->join('users', 'posts.user_id', '=', 'users.id')
    ->select('posts.*', 'users.name as author')
    ->get();

// Left join
$users = $connection->table('users')
    ->leftJoin('posts', 'users.id', '=', 'posts.user_id')
    ->select('users.*', $connection->raw('COUNT(posts.id) as post_count'))
    ->groupBy('users.id')
    ->get();

// Right join
$posts = $connection->table('posts')
    ->rightJoin('users', 'posts.user_id', '=', 'users.id')
    ->get();

// Cross join
$sizes = $connection->table('sizes')
    ->crossJoin('colors')
    ->get();

// Advanced join with closure
$posts = $connection->table('posts')
    ->join('users', function ($join) {
        $join->on('posts.user_id', '=', 'users.id')
             ->where('users.status', '=', 'active');
    })
    ->get();
```

### Aggregates

```php
$count = $connection->table('users')->count();
$max = $connection->table('orders')->max('total');
$min = $connection->table('orders')->min('total');
$avg = $connection->table('orders')->avg('total');
$sum = $connection->table('orders')->sum('total');
```

### Grouping

```php
$stats = $connection->table('orders')
    ->select('status', $connection->raw('COUNT(*) as count'))
    ->groupBy('status')
    ->get();

// With having
$users = $connection->table('users')
    ->select('country', $connection->raw('COUNT(*) as user_count'))
    ->groupBy('country')
    ->having('user_count', '>', 100)
    ->get();
```

### Insert

```php
// Single insert
$connection->table('users')->insert([
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);

// Get inserted ID
$id = $connection->table('users')->insertGetId([
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);

// Batch insert
$connection->table('users')->insert([
    ['name' => 'John', 'email' => 'john@example.com'],
    ['name' => 'Jane', 'email' => 'jane@example.com'],
]);
```

### Update

```php
// Update matching records
$affected = $connection->table('users')
    ->where('id', 1)
    ->update(['status' => 'active']);

// Increment / Decrement
$connection->table('users')
    ->where('id', 1)
    ->increment('login_count');

$connection->table('products')
    ->where('id', 1)
    ->decrement('stock', 5);
```

### Delete

```php
// Delete matching records
$deleted = $connection->table('users')
    ->where('status', 'inactive')
    ->delete();

// Delete by ID
$connection->table('users')
    ->where('id', 1)
    ->delete();
```

### Raw Queries

```php
// Raw select
$users = $connection->select('SELECT * FROM users WHERE status = ?', ['active']);

// Raw insert
$connection->insert('INSERT INTO users (name, email) VALUES (?, ?)', ['John', 'john@example.com']);

// Raw update
$affected = $connection->update('UPDATE users SET status = ? WHERE id = ?', ['active', 1]);

// Raw delete
$deleted = $connection->delete('DELETE FROM users WHERE status = ?', ['inactive']);

// Raw expressions in queries
$users = $connection->table('users')
    ->select('*', $connection->raw('DATEDIFF(NOW(), created_at) as days_since_joined'))
    ->get();
```

## Transactions

```php
// Using callback
$connection->transaction(function ($connection) {
    $connection->table('accounts')
        ->where('id', 1)
        ->decrement('balance', 100);

    $connection->table('accounts')
        ->where('id', 2)
        ->increment('balance', 100);
});

// Manual transactions
$connection->beginTransaction();

try {
    $connection->table('accounts')->where('id', 1)->decrement('balance', 100);
    $connection->table('accounts')->where('id', 2)->increment('balance', 100);

    $connection->commit();
} catch (\Exception $e) {
    $connection->rollBack();
    throw $e;
}
```

## Schema Builder

Create and modify database tables using the schema builder.

### Creating Tables

```php
use Lalaz\Database\Schema\SchemaBuilder;

$schema->create('users', function ($table) {
    $table->increments('id');
    $table->string('name');
    $table->string('email')->unique();
    $table->string('password');
    $table->boolean('is_admin')->default(false);
    $table->timestamps();
});
```

### Available Column Types

```php
$schema->create('examples', function ($table) {
    // Auto-incrementing primary key
    $table->increments('id');

    // Integer types
    $table->integer('votes');
    $table->bigInteger('big_number');

    // String types
    $table->string('name', 100);  // VARCHAR(100)
    $table->text('description');

    // Boolean
    $table->boolean('active');

    // Date/Time
    $table->timestamp('published_at');
    $table->timestamps();  // created_at, updated_at

    // UUID
    $table->uuid('uuid');

    // JSON
    $table->json('metadata');

    // Soft deletes
    $table->softDeletes();  // deleted_at column
});
```

### Column Modifiers

```php
$table->string('email')->nullable();
$table->string('name')->default('Guest');
$table->integer('position')->unsigned();
$table->increments('id');  // auto-increment + primary key
```

### Indexes

```php
$schema->create('posts', function ($table) {
    $table->increments('id');
    $table->string('slug');
    $table->integer('user_id');

    // Single column index
    $table->index('slug');

    // Unique index
    $table->unique('slug');

    // Composite index
    $table->index(['user_id', 'created_at']);
});
```

### Foreign Keys

```php
$schema->create('posts', function ($table) {
    $table->increments('id');
    $table->integer('user_id');

    $table->foreign('user_id', 'id', 'users')
        ->onDelete('cascade')
        ->onUpdate('cascade');
});
```

### Modifying Tables

```php
// Add column
$schema->table('users', function ($table) {
    $table->string('phone')->nullable();
});

// Rename column
$schema->table('users', function ($table) {
    $table->renameColumn('name', 'full_name');
});

// Drop column (MySQL/PostgreSQL)
$schema->table('users', function ($table) {
    $table->dropColumn('phone');
});
```

### Dropping Tables

```php
$schema->drop('users');
$schema->dropIfExists('users');
```

## Migrations

Migrations track database schema changes over time.

### Creating Migrations

```bash
php lalaz craft:migration create_users_table
```

Creates `migrations/YYYY_MM_DD_HHMMSS_create_users_table.php`:

```php
<?php declare(strict_types=1);

use Lalaz\Database\Migrations\Migration;
use Lalaz\Database\Schema\SchemaBuilder;

return new class extends Migration
{
    public function up(SchemaBuilder $schema): void
    {
        $schema->create('users', function ($table) {
            $table->increments('id');
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
};
```

### Running Migrations

```bash
# Run all pending migrations
php lalaz migrate

# Rollback last batch
php lalaz migrate:rollback

# Reset all migrations
php lalaz migrate:reset

# Refresh (reset + migrate)
php lalaz migrate:refresh

# Check migration status
php lalaz migrate:status
```

## ORM (Object-Relational Mapping)

Lalaz's ORM provides an ActiveRecord implementation for working with your database.

### Defining Models

```php
<?php declare(strict_types=1);

namespace App\Models;

use Lalaz\Orm\Model;

class User extends Model
{
    protected ?string $table = 'users';
    protected string $primaryKey = 'id';

    protected array $fillable = [
        'name',
        'email',
        'password',
    ];

    protected array $hidden = [
        'password',
    ];

    protected array $casts = [
        'email_verified_at' => 'datetime',
        'is_admin' => 'boolean',
    ];
}
```

### Retrieving Models

```php
// Get all users
$users = User::all($manager);

// Find by primary key
$user = User::find($manager, 1);

// Find or fail (throws exception)
$user = User::findOrFail($manager, 1);

// Query builder
$users = User::queryWith($manager)
    ->where('status', 'active')
    ->orderBy('name')
    ->get();

// First matching record
$user = User::queryWith($manager)
    ->where('email', 'john@example.com')
    ->first();
```

### Creating Models

```php
// Using create (mass assignment)
$user = User::create($manager, [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => password_hash('secret', PASSWORD_DEFAULT),
]);

// Using build + save
$user = User::build($manager, [
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);
$user->password = password_hash('secret', PASSWORD_DEFAULT);
$user->save();
```

### Updating Models

```php
$user = User::find($manager, 1);
$user->name = 'Jane Doe';
$user->save();

// Mass update
$user->fill([
    'name' => 'Jane Doe',
    'email' => 'jane@example.com',
]);
$user->save();
```

### Deleting Models

```php
$user = User::find($manager, 1);
$user->delete();

// Soft deletes (if enabled)
$user->delete();        // Sets deleted_at
$user->forceDelete();   // Permanently deletes
$user->restore();       // Restores soft-deleted model
```

### Mass Assignment

Control which attributes can be mass-assigned:

```php
class User extends Model
{
    // Only these fields can be mass-assigned
    protected array $fillable = [
        'name',
        'email',
        'password',
    ];

    // Or, guard specific fields
    protected array $guarded = [
        'id',
        'is_admin',
    ];
}
```

### Attribute Casting

```php
class User extends Model
{
    protected array $casts = [
        'is_admin' => 'boolean',
        'age' => 'integer',
        'balance' => 'float',
        'settings' => 'array',      // JSON to array
        'metadata' => 'object',     // JSON to object
        'birthday' => 'date',
        'created_at' => 'datetime',
    ];
}
```

### Timestamps

Models automatically manage `created_at` and `updated_at`:

```php
class User extends Model
{
    // Enable/disable timestamps
    protected bool $timestamps = true;

    // Customize column names
    protected string $createdAtColumn = 'created_at';
    protected string $updatedAtColumn = 'updated_at';
}
```

### Soft Deletes

```php
class User extends Model
{
    protected bool $softDeletes = true;
    protected string $deletedAtColumn = 'deleted_at';
}

// Query only non-deleted
$users = User::queryWith($manager)->get();

// Include soft-deleted
$users = (new User($manager))->withTrashed()->get();

// Only soft-deleted
$users = (new User($manager))->onlyTrashed()->get();
```

## Relationships

### One-to-One

```php
class User extends Model
{
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
        // Or with custom keys:
        // return $this->hasOne(Profile::class, 'user_id', 'id');
    }
}

// Usage
$user = User::find($manager, 1);
$profile = $user->profile;  // Lazy loading
```

### One-to-Many

```php
class User extends Model
{
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}

class Post extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

// Usage
$user = User::find($manager, 1);
$posts = $user->posts;  // Collection of posts

$post = Post::find($manager, 1);
$author = $post->user;  // User model
```

### Many-to-Many

```php
class User extends Model
{
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
        // With custom pivot table:
        // return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id');
    }
}

class Role extends Model
{
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}

// Usage
$user = User::find($manager, 1);
$roles = $user->roles;
```

### Eager Loading

Prevent N+1 query problems:

```php
// Eager load relationships
$users = User::queryWith($manager)
    ->with('posts')
    ->get();

// Multiple relationships
$users = User::queryWith($manager)
    ->with(['posts', 'profile'])
    ->get();

// Nested eager loading
$users = User::queryWith($manager)
    ->with('posts.comments')
    ->get();
```

## Read Replicas

Configure read replicas for read/write splitting:

```php
return [
    'database' => [
        'driver' => 'mysql',
        'connections' => [
            'mysql' => [
                'host' => env('DB_HOST', '127.0.0.1'),
                // ... write connection config
            ],
        ],
        'read' => [
            'enabled' => true,
            'sticky' => true,  // Use write connection after write operations
            'connections' => [
                ['host' => 'read-replica-1.example.com'],
                ['host' => 'read-replica-2.example.com'],
            ],
        ],
    ],
];
```

## Connection Pooling

Lalaz manages database connections efficiently with connection pooling:

```php
return [
    'database' => [
        'pool' => [
            'min' => 0,           // Minimum connections to keep open
            'max' => 10,          // Maximum connections allowed
            'timeout_ms' => 5000, // Timeout when pool is exhausted
        ],
    ],
];
```

## Query Logging

Enable query logging for debugging:

```php
// The ConnectionManager automatically logs queries when a logger is provided
$manager = new ConnectionManager($config, $logger);

// Log output:
// [db:mysql][write] 12.34ms select SELECT * FROM users WHERE id = ?
```

## Best Practices

### Use Query Builder for Complex Queries

```php
// ✅ Good: Query builder with relationships
$users = User::queryWith($manager)
    ->where('status', 'active')
    ->with('posts')
    ->orderBy('name')
    ->get();

// ❌ Avoid: N+1 queries
$users = User::all($manager);
foreach ($users as $user) {
    echo $user->posts->count(); // Query per user!
}
```

### Use Transactions for Data Integrity

```php
$connection->transaction(function ($conn) use ($data) {
    $user = User::create($this->manager, $data['user']);
    Profile::create($this->manager, [
        'user_id' => $user->id,
        ...$data['profile'],
    ]);
});
```

### Paginate Large Result Sets

```php
$users = User::queryWith($manager)
    ->orderBy('id')
    ->limit(25)
    ->offset(($page - 1) * 25)
    ->get();
```

### Use Model Events for Side Effects

```php
class User extends Model
{
    protected function registerObservers(): void
    {
        $this->events?->listen('creating', function ($model) {
            $model->uuid = Str::uuid();
        });

        $this->events?->listen('deleted', function ($model) {
            // Clean up related data
        });
    }
}
```
