# User Providers Overview

User Providers are responsible for retrieving user data from your storage system. They act as a bridge between authentication guards and your user data.

## What is a Provider?

A provider knows:
- **How** to find a user by ID
- **How** to find a user by credentials (email, username, etc.)
- **How** to verify a password

Different storage systems require different providers:

| Storage | Provider | Example |
|---------|----------|---------|
| Database with ORM | `ModelUserProvider` | User model |
| Database without ORM | `GenericUserProvider` | PDO queries |
| External API | `GenericUserProvider` | API client |
| LDAP | Custom provider | LDAP connection |

## Available Providers

### ModelUserProvider

For applications using Lalaz ORM or similar:

```php
use Lalaz\Auth\Providers\ModelUserProvider;

$provider = new ModelUserProvider(App\Models\User::class);
```

**Pros:**
- Automatic query building
- Works with existing models
- Simple configuration

📖 [Full ModelUserProvider Documentation](./model.md)

---

### GenericUserProvider

For custom user retrieval logic:

```php
use Lalaz\Auth\Providers\GenericUserProvider;

$provider = GenericUserProvider::create(
    byId: fn($id) => fetchUserFromApi($id),
    byCredentials: fn($creds) => findUserByEmail($creds['email'])
);
```

**Pros:**
- Complete flexibility
- Works with any data source
- Custom logic support

📖 [Full GenericUserProvider Documentation](./generic.md)

---

## Configuration

Providers are configured in `config/auth.php`:

```php
return [
    'providers' => [
        // Model-based provider
        'users' => [
            'driver' => 'model',
            'model' => App\Models\User::class,
        ],

        // Another model-based provider for admins
        'admins' => [
            'driver' => 'model',
            'model' => App\Models\Admin::class,
        ],

        // Generic provider (configured elsewhere)
        'external' => [
            'driver' => 'generic',
        ],
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',  // Uses the 'users' provider
        ],
        'admin' => [
            'driver' => 'session',
            'provider' => 'admins',  // Uses the 'admins' provider
        ],
    ],
];
```

## Provider Interface

All providers implement `UserProviderInterface`:

```php
namespace Lalaz\Auth\Contracts;

interface UserProviderInterface
{
    /**
     * Retrieve a user by their unique identifier.
     *
     * @param mixed $identifier The user ID
     * @return mixed User object or null
     */
    public function retrieveById(mixed $identifier): mixed;

    /**
     * Retrieve a user by their credentials.
     *
     * @param array $credentials ['email' => '...', 'password' => '...']
     * @return mixed User object or null
     */
    public function retrieveByCredentials(array $credentials): mixed;

    /**
     * Validate a user against the given credentials.
     *
     * @param mixed $user The user object
     * @param array $credentials ['password' => '...']
     * @return bool True if credentials are valid
     */
    public function validateCredentials(mixed $user, array $credentials): bool;
}
```

## The Authenticatable Interface

Your User model should implement `AuthenticatableInterface`:

```php
namespace Lalaz\Auth\Contracts;

interface AuthenticatableInterface
{
    /**
     * Get the unique identifier for the user.
     */
    public function getAuthIdentifier(): mixed;

    /**
     * Get the name of the unique identifier column.
     */
    public function getAuthIdentifierName(): string;

    /**
     * Get the password for the user.
     */
    public function getAuthPassword(): string;

    /**
     * Get the remember token for the user.
     */
    public function getRememberToken(): ?string;

    /**
     * Set the remember token for the user.
     */
    public function setRememberToken(?string $value): void;

    /**
     * Get the column name for the remember token.
     */
    public function getRememberTokenName(): string;
}
```

### Example Implementation

```php
<?php

namespace App\Models;

use Lalaz\Data\Model;
use Lalaz\Auth\Contracts\AuthenticatableInterface;

class User extends Model implements AuthenticatableInterface
{
    protected static string $tableName = 'users';

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
        // Simple: single role in column
        return [$this->role];
        
        // Or: roles from JSON column
        // return json_decode($this->roles ?? '[]', true);
        
        // Or: roles from relationship
        // return $this->roles->pluck('name')->toArray();
    }

    public function getPermissions(): array
    {
        // Combine role permissions with direct permissions
        $permissions = json_decode($this->permissions ?? '[]', true);
        
        // Add permissions from roles
        foreach ($this->getRoles() as $role) {
            $rolePermissions = $this->getPermissionsForRole($role);
            $permissions = array_merge($permissions, $rolePermissions);
        }
        
        return array_unique($permissions);
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->getRoles());
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->getPermissions());
    }

    private function getPermissionsForRole(string $role): array
    {
        $rolePermissions = [
            'admin' => ['*'],  // All permissions
            'editor' => ['posts.create', 'posts.edit', 'posts.delete'],
            'viewer' => ['posts.view'],
        ];
        
        return $rolePermissions[$role] ?? [];
    }
}
```

## Using Providers Directly

While guards handle providers automatically, you can use them directly:

```php
use Lalaz\Auth\Providers\ModelUserProvider;
use App\Models\User;

$provider = new ModelUserProvider(User::class);

// Find by ID
$user = $provider->retrieveById(123);

// Find by credentials (without password validation)
$user = $provider->retrieveByCredentials([
    'email' => 'user@example.com',
]);

// Validate password
if ($user && $provider->validateCredentials($user, ['password' => 'secret'])) {
    echo "Password is correct!";
}
```

## Multiple Providers

Use different providers for different user types:

```php
// config/auth.php
return [
    'providers' => [
        'users' => [
            'driver' => 'model',
            'model' => App\Models\User::class,
        ],
        'admins' => [
            'driver' => 'model',
            'model' => App\Models\Admin::class,
        ],
        'api_clients' => [
            'driver' => 'model',
            'model' => App\Models\ApiClient::class,
        ],
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        'admin' => [
            'driver' => 'session',
            'provider' => 'admins',
        ],
        'api' => [
            'driver' => 'api-key',
            'provider' => 'api_clients',
        ],
    ],
];
```

## Creating Custom Providers

For unique requirements, create a custom provider:

```php
<?php

namespace App\Auth;

use Lalaz\Auth\Contracts\UserProviderInterface;
use App\Services\LdapService;

class LdapUserProvider implements UserProviderInterface
{
    public function __construct(
        private LdapService $ldap
    ) {}

    public function retrieveById(mixed $identifier): mixed
    {
        return $this->ldap->getUserByDn($identifier);
    }

    public function retrieveByCredentials(array $credentials): mixed
    {
        $username = $credentials['username'] ?? null;
        
        if (!$username) {
            return null;
        }

        return $this->ldap->findUser($username);
    }

    public function validateCredentials(mixed $user, array $credentials): bool
    {
        $password = $credentials['password'] ?? '';
        
        return $this->ldap->authenticate($user->dn, $password);
    }
}
```

### Register Custom Provider

```php
// In a service provider or bootstrap file
$auth = resolve(AuthManager::class);

$auth->extendProvider('ldap', function ($config) {
    return new LdapUserProvider(
        ldap: resolve(LdapService::class)
    );
});
```

Then in config:

```php
'providers' => [
    'employees' => [
        'driver' => 'ldap',
    ],
],
```

## Provider Events

You can add hooks for auditing or logging:

```php
class AuditableUserProvider implements UserProviderInterface
{
    public function __construct(
        private UserProviderInterface $inner,
        private LoggerInterface $logger
    ) {}

    public function retrieveById(mixed $identifier): mixed
    {
        $user = $this->inner->retrieveById($identifier);
        
        $this->logger->info('User retrieved by ID', [
            'user_id' => $identifier,
            'found' => $user !== null,
        ]);
        
        return $user;
    }

    public function retrieveByCredentials(array $credentials): mixed
    {
        $user = $this->inner->retrieveByCredentials($credentials);
        
        $this->logger->info('User retrieved by credentials', [
            'email' => $credentials['email'] ?? 'unknown',
            'found' => $user !== null,
        ]);
        
        return $user;
    }

    public function validateCredentials(mixed $user, array $credentials): bool
    {
        $valid = $this->inner->validateCredentials($user, $credentials);
        
        $this->logger->info('Credentials validated', [
            'user_id' => $user->getAuthIdentifier(),
            'valid' => $valid,
        ]);
        
        return $valid;
    }
}
```

## Common Patterns

### Soft Deletes

Only return active users:

```php
class ActiveUserProvider implements UserProviderInterface
{
    public function __construct(
        private UserProviderInterface $inner
    ) {}

    public function retrieveById(mixed $identifier): mixed
    {
        $user = $this->inner->retrieveById($identifier);
        
        if ($user && $user->deleted_at !== null) {
            return null;  // User is soft-deleted
        }
        
        return $user;
    }

    // ... other methods
}
```

### Multi-Tenant

Scope users to tenant:

```php
class TenantUserProvider implements UserProviderInterface
{
    public function __construct(
        private string $modelClass,
        private TenantContext $tenant
    ) {}

    public function retrieveById(mixed $identifier): mixed
    {
        return $this->modelClass::query()
            ->where('id', $identifier)
            ->where('tenant_id', $this->tenant->getAuthIdentifier())
            ->first();
    }

    // ... other methods
}
```

## Testing Providers

```php
use PHPUnit\Framework\TestCase;
use Lalaz\Auth\Providers\ModelUserProvider;

class ModelUserProviderTest extends TestCase
{
    private ModelUserProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new ModelUserProvider(User::class);
    }

    public function test_retrieves_user_by_id(): void
    {
        $user = User::create(['name' => 'Test', 'email' => 'test@example.com']);
        
        $retrieved = $this->provider->retrieveById($user->id);
        
        $this->assertEquals($user->id, $retrieved->id);
    }

    public function test_retrieves_user_by_email(): void
    {
        User::create(['name' => 'Test', 'email' => 'test@example.com']);
        
        $retrieved = $this->provider->retrieveByCredentials([
            'email' => 'test@example.com',
        ]);
        
        $this->assertNotNull($retrieved);
        $this->assertEquals('test@example.com', $retrieved->email);
    }

    public function test_validates_correct_password(): void
    {
        $user = User::create([
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => password_hash('secret', PASSWORD_DEFAULT),
        ]);
        
        $isValid = $this->provider->validateCredentials($user, [
            'password' => 'secret',
        ]);
        
        $this->assertTrue($isValid);
    }

    public function test_rejects_wrong_password(): void
    {
        $user = User::create([
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => password_hash('secret', PASSWORD_DEFAULT),
        ]);
        
        $isValid = $this->provider->validateCredentials($user, [
            'password' => 'wrong',
        ]);
        
        $this->assertFalse($isValid);
    }
}
```

## Next Steps

- Learn about [ModelUserProvider](./model.md) in detail
- Learn about [GenericUserProvider](./generic.md) for custom logic
- Set up [Guards](../guards/index.md) to use your providers
- Add [Authorization](../authorization.md) with roles and permissions
