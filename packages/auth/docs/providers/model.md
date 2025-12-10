# ModelUserProvider

The `ModelUserProvider` retrieves users from the database using your ORM model. This is the most common provider for web applications.

## Requirements

> ⚠️ **Important:** This provider requires the `lalaz/orm` package to be installed.
>
> If you don't have `lalaz/orm`, use [GenericUserProvider](./generic.md) instead.

```bash
# Install ORM package
php lalaz package:add lalaz/orm
```

If you try to use `ModelUserProvider` without ORM, you'll get a clear error message with instructions:

```
RuntimeException: Cannot use ModelUserProvider with model 'App\Models\User': 
no query capability available. Either install the lalaz/orm package, 
or use GenericUserProvider instead.

Option 1 - Install ORM:
  php lalaz package:add lalaz/orm

Option 2 - Use GenericUserProvider in config/auth.php:
  'providers' => [
      'users' => [
          'driver' => 'generic',
          ...
      ],
  ],
```

## How It Works

```
1. Guard needs to find a user
        │
        ▼
2. ModelUserProvider receives request
        │
        ├─── retrieveById(123)
        │         └─── User::find(123)
        │
        └─── retrieveByCredentials(['email' => '...'])
                  └─── User::where('email', '...')->first()
```

## Basic Setup

### 1. Create Your User Model

```php
<?php

namespace App\Models;

use Lalaz\Data\Model;
use Lalaz\Auth\Contracts\AuthenticatableInterface;

class User extends Model implements AuthenticatableInterface
{
    protected static string $tableName = 'users';
    
    // Hide password from serialization
    protected array $hidden = ['password'];

    public function getAuthIdentifier(): mixed
    {
        return $this->id;
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthPassword(): string
    {
        return $this->password;
    }

    public function getRememberToken(): ?string
    {
        return $this->remember_token;
    }

    public function setRememberToken(?string $value): void
    {
        $this->remember_token = $value;
    }

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }

    // Additional authorization methods (not part of AuthenticatableInterface)

    public function getRoles(): array
    {
        return [$this->role ?? 'user'];
    }

    public function getPermissions(): array
    {
        return json_decode($this->permissions ?? '[]', true);
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->getRoles());
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->getPermissions());
    }
}
```

### 2. Configure Provider

```php
// config/auth.php
return [
    'providers' => [
        'users' => [
            'driver' => 'model',
            'model' => App\Models\User::class,
        ],
    ],
    
    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],
];
```

### 3. Database Migration

```php
class CreateUsersTable extends Migration
{
    public function up(): void
    {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('user');
            $table->json('permissions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }
}
```

## Usage

### Creating the Provider

The provider is usually created automatically from config, but you can create it manually:

```php
use Lalaz\Auth\Providers\ModelUserProvider;
use App\Models\User;

$provider = new ModelUserProvider(User::class);
```

### Retrieve by ID

```php
// Find user by primary key
$user = $provider->retrieveById(123);

if ($user) {
    echo $user->name;
} else {
    echo "User not found";
}
```

### Retrieve by Credentials

Find a user by any field (except password):

```php
// By email
$user = $provider->retrieveByCredentials([
    'email' => 'user@example.com',
]);

// By multiple fields
$user = $provider->retrieveByCredentials([
    'email' => 'user@example.com',
    'is_active' => true,
]);

// By username
$user = $provider->retrieveByCredentials([
    'username' => 'johndoe',
]);
```

> **Note:** The `password` field is automatically excluded from the query. Password validation is handled separately.

### Validate Credentials

Verify a password matches:

```php
$user = $provider->retrieveByCredentials([
    'email' => 'user@example.com',
]);

if ($user && $provider->validateCredentials($user, ['password' => 'secret'])) {
    // Password is correct!
    echo "Login successful";
} else {
    echo "Invalid credentials";
}
```

## Password Hashing

The provider uses PHP's `password_verify()` for validation, which supports:

- `PASSWORD_DEFAULT` (currently bcrypt)
- `PASSWORD_BCRYPT`
- `PASSWORD_ARGON2I`
- `PASSWORD_ARGON2ID`

### Hashing Passwords

Always hash passwords before storing:

```php
// When creating users
$user = new User();
$user->name = 'John Doe';
$user->email = 'john@example.com';
$user->password = password_hash('secret123', PASSWORD_DEFAULT);
$user->save();

// When updating passwords
$user->password = password_hash($newPassword, PASSWORD_DEFAULT);
$user->save();
```

### Rehashing

Check if password needs rehashing (after PHP upgrades):

```php
if (password_needs_rehash($user->password, PASSWORD_DEFAULT)) {
    $user->password = password_hash($plainPassword, PASSWORD_DEFAULT);
    $user->save();
}
```

## Advanced Configuration

### Custom Identifier Field

If your users use a different identifier column:

```php
class User extends Model implements AuthenticatableInterface
{
    public function getAuthIdentifier(): mixed
    {
        return $this->uuid;  // Use UUID instead of ID
    }

    public function getAuthIdentifierName(): string
    {
        return 'uuid';
    }
}
```

### Multiple Credential Fields

Support login by email OR username:

```php
class FlexibleModelProvider extends ModelUserProvider
{
    public function retrieveByCredentials(array $credentials): mixed
    {
        $login = $credentials['login'] ?? null;
        
        if (!$login) {
            return parent::retrieveByCredentials($credentials);
        }

        // Try email first
        $user = $this->model::where('email', $login)->first();
        
        // Fall back to username
        if (!$user) {
            $user = $this->model::where('username', $login)->first();
        }
        
        return $user;
    }
}
```

### Scoped Queries

Filter users automatically:

```php
class ActiveUserProvider extends ModelUserProvider
{
    public function retrieveById(mixed $identifier): mixed
    {
        return $this->model::query()
            ->where($this->getAuthIdentifierName(), $identifier)
            ->where('is_active', true)
            ->first();
    }

    public function retrieveByCredentials(array $credentials): mixed
    {
        $credentials['is_active'] = true;
        return parent::retrieveByCredentials($credentials);
    }
}
```

### Eager Loading Relationships

Load related data automatically:

```php
class EagerLoadingProvider extends ModelUserProvider
{
    public function retrieveById(mixed $identifier): mixed
    {
        return $this->model::with(['roles', 'permissions'])
            ->find($identifier);
    }

    public function retrieveByCredentials(array $credentials): mixed
    {
        unset($credentials['password']);
        
        return $this->model::with(['roles', 'permissions'])
            ->where($credentials)
            ->first();
    }
}
```

## Role-Based Users

### Simple Role Column

```php
class User extends Model implements AuthenticatableInterface
{
    public function getRoles(): array
    {
        return [$this->role];  // Single role: 'admin', 'user', etc.
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }
}
```

### JSON Roles Column

```php
class User extends Model implements AuthenticatableInterface
{
    public function getRoles(): array
    {
        return json_decode($this->roles ?? '[]', true);
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->getRoles());
    }

    public function addRole(string $role): void
    {
        $roles = $this->getRoles();
        if (!in_array($role, $roles)) {
            $roles[] = $role;
            $this->roles = json_encode($roles);
            $this->save();
        }
    }

    public function removeRole(string $role): void
    {
        $roles = array_filter($this->getRoles(), fn($r) => $r !== $role);
        $this->roles = json_encode(array_values($roles));
        $this->save();
    }
}
```

### Relationship-Based Roles

```php
// User model
class User extends Model implements AuthenticatableInterface
{
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    public function getRoles(): array
    {
        return $this->roles->pluck('name')->toArray();
    }

    public function hasRole(string $role): bool
    {
        return $this->roles->contains('name', $role);
    }
}

// Role model
class Role extends Model
{
    protected static string $tableName = 'roles';
    
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }
}
```

## Permissions

### JSON Permissions

```php
class User extends Model implements AuthenticatableInterface
{
    public function getPermissions(): array
    {
        return json_decode($this->permissions ?? '[]', true);
    }

    public function hasPermission(string $permission): bool
    {
        // Check direct permissions
        if (in_array($permission, $this->getPermissions())) {
            return true;
        }

        // Check for wildcard
        if (in_array('*', $this->getPermissions())) {
            return true;
        }

        // Check for category wildcard (e.g., 'posts.*' matches 'posts.create')
        $category = explode('.', $permission)[0] ?? '';
        if (in_array("{$category}.*", $this->getPermissions())) {
            return true;
        }

        return false;
    }
}
```

### Role-Based Permissions

```php
class User extends Model implements AuthenticatableInterface
{
    private static array $rolePermissions = [
        'admin' => ['*'],
        'editor' => ['posts.create', 'posts.edit', 'posts.delete', 'posts.view'],
        'author' => ['posts.create', 'posts.edit', 'posts.view'],
        'viewer' => ['posts.view'],
    ];

    public function getPermissions(): array
    {
        $permissions = [];
        
        foreach ($this->getRoles() as $role) {
            $rolePerms = self::$rolePermissions[$role] ?? [];
            $permissions = array_merge($permissions, $rolePerms);
        }
        
        return array_unique($permissions);
    }
}
```

## Complete Example

```php
<?php

namespace App\Models;

use Lalaz\Data\Model;
use Lalaz\Auth\Contracts\AuthenticatableInterface;

class User extends Model implements AuthenticatableInterface
{
    protected static string $tableName = 'users';
    
    protected array $hidden = ['password', 'remember_token'];
    
    protected array $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    // ==========================================
    // AuthenticatableInterface Implementation
    // ==========================================

    public function getAuthIdentifier(): mixed
    {
        return $this->id;
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthPassword(): string
    {
        return $this->password ?? '';
    }

    public function getRememberToken(): ?string
    {
        return $this->remember_token;
    }

    public function setRememberToken(?string $value): void
    {
        $this->remember_token = $value;
    }

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }

    // ==========================================
    // Authorization Methods (optional)
    // ==========================================

    public function getRoles(): array
    {
        // Option 1: Single role column
        if ($this->role) {
            return [$this->role];
        }
        
        // Option 2: JSON roles
        if ($this->roles) {
            return json_decode($this->roles, true) ?: [];
        }
        
        return ['user'];  // Default role
    }

    public function getPermissions(): array
    {
        // Direct permissions from JSON column
        $direct = json_decode($this->permissions ?? '[]', true);
        
        // Permissions inherited from roles
        $fromRoles = $this->getPermissionsFromRoles();
        
        return array_unique(array_merge($direct, $fromRoles));
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->getRoles());
    }

    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }
        return false;
    }

    public function hasPermission(string $permission): bool
    {
        $permissions = $this->getPermissions();
        
        // Exact match
        if (in_array($permission, $permissions)) {
            return true;
        }
        
        // Wildcard (admin has all permissions)
        if (in_array('*', $permissions)) {
            return true;
        }
        
        // Category wildcard (posts.* matches posts.create)
        $parts = explode('.', $permission);
        if (count($parts) > 1) {
            $wildcard = $parts[0] . '.*';
            if (in_array($wildcard, $permissions)) {
                return true;
            }
        }
        
        return false;
    }

    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }
        return false;
    }

    // ==========================================
    // Helper Methods
    // ==========================================

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    public function isVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function setPassword(string $password): void
    {
        $this->password = password_hash($password, PASSWORD_DEFAULT);
    }

    public function checkPassword(string $password): bool
    {
        return password_verify($password, $this->password);
    }

    // ==========================================
    // Private Helpers
    // ==========================================

    private function getPermissionsFromRoles(): array
    {
        $rolePermissions = [
            'admin' => ['*'],
            'moderator' => [
                'users.view', 'users.edit',
                'posts.*',
                'comments.*',
            ],
            'editor' => [
                'posts.create', 'posts.edit', 'posts.delete', 'posts.view',
            ],
            'user' => [
                'posts.view',
                'comments.create', 'comments.view',
            ],
        ];

        $permissions = [];
        
        foreach ($this->getRoles() as $role) {
            $perms = $rolePermissions[$role] ?? [];
            $permissions = array_merge($permissions, $perms);
        }
        
        return $permissions;
    }
}
```

## Testing

```php
use PHPUnit\Framework\TestCase;
use Lalaz\Auth\Providers\ModelUserProvider;
use App\Models\User;

class ModelUserProviderTest extends TestCase
{
    private ModelUserProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new ModelUserProvider(User::class);
    }

    public function test_retrieve_by_id(): void
    {
        $user = User::create([
            'name' => 'John',
            'email' => 'john@example.com',
            'password' => password_hash('secret', PASSWORD_DEFAULT),
        ]);

        $found = $this->provider->retrieveById($user->id);

        $this->assertNotNull($found);
        $this->assertEquals('John', $found->name);
    }

    public function test_retrieve_by_email(): void
    {
        User::create([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => password_hash('secret', PASSWORD_DEFAULT),
        ]);

        $found = $this->provider->retrieveByCredentials([
            'email' => 'jane@example.com',
        ]);

        $this->assertNotNull($found);
        $this->assertEquals('Jane', $found->name);
    }

    public function test_validate_correct_password(): void
    {
        $user = User::create([
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => password_hash('correct', PASSWORD_DEFAULT),
        ]);

        $valid = $this->provider->validateCredentials($user, [
            'password' => 'correct',
        ]);

        $this->assertTrue($valid);
    }

    public function test_validate_wrong_password(): void
    {
        $user = User::create([
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => password_hash('correct', PASSWORD_DEFAULT),
        ]);

        $valid = $this->provider->validateCredentials($user, [
            'password' => 'wrong',
        ]);

        $this->assertFalse($valid);
    }

    public function test_returns_null_for_nonexistent_user(): void
    {
        $found = $this->provider->retrieveById(99999);

        $this->assertNull($found);
    }
}
```

## Next Steps

- Learn about [GenericUserProvider](./generic.md) for custom retrieval
- Configure [Guards](../guards/index.md) to use your provider
- Set up [Authorization](../authorization.md) with roles and permissions
