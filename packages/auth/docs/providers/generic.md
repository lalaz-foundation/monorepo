# GenericUserProvider

The `GenericUserProvider` allows you to define custom user retrieval logic using callbacks. This is the recommended provider when you don't have `lalaz/orm` installed.

## When to Use

Use `GenericUserProvider` when:

- **You don't have `lalaz/orm` installed** (most common reason)
- Users are stored in an external API
- You need custom query logic
- Users come from multiple sources
- You're migrating from another system
- You need to wrap another provider with custom logic

> 💡 **Tip:** If you're getting a "no query capability available" error with `ModelUserProvider`, 
> switch to `GenericUserProvider` or install `lalaz/orm`.

## Basic Setup

### Creating with Callbacks

```php
use Lalaz\Auth\Providers\GenericUserProvider;

$provider = GenericUserProvider::create(
    // How to find user by ID
    byId: function ($id) {
        return User::find($id);
    },
    
    // How to find user by credentials
    byCredentials: function (array $credentials) {
        $email = $credentials['email'] ?? null;
        return User::where('email', $email)->first();
    }
);

// To customize password validation, use setValidateCallback:
$provider->setValidateCallback(function ($user, array $credentials) {
    $password = $credentials['password'] ?? '';
    return password_verify($password, $user->getAuthPassword());
});
```

### Using Arrow Functions

```php
$provider = GenericUserProvider::create(
    byId: fn($id) => User::find($id),
    byCredentials: fn($creds) => User::where('email', $creds['email'])->first()
);
```

## Configuration

You can configure a generic provider in `config/auth.php`:

```php
// config/auth.php
return [
    'providers' => [
        'external' => [
            'driver' => 'generic',
            // The callbacks are configured in a service provider
        ],
    ],
];
```

Then register the provider:

```php
// In a service provider
$auth = resolve(AuthManager::class);

$auth->extendProvider('generic', function ($config) {
    return GenericUserProvider::create(
        byId: fn($id) => $this->fetchUserFromApi($id),
        byCredentials: fn($creds) => $this->findUserByEmail($creds['email'])
    );
});
```

## Use Cases

### External API Authentication

```php
use Lalaz\Auth\Providers\GenericUserProvider;
use App\Services\UserApiClient;

$apiClient = new UserApiClient('https://api.users.example.com');

$provider = GenericUserProvider::create(
    byId: function ($id) use ($apiClient) {
        try {
            $data = $apiClient->getUser($id);
            return new User($data);
        } catch (NotFoundException $e) {
            return null;
        }
    },
    
    byCredentials: function (array $credentials) use ($apiClient) {
        try {
            $data = $apiClient->findByEmail($credentials['email']);
            return new User($data);
        } catch (NotFoundException $e) {
            return null;
        }
    },
    
    validateCredentials: function ($user, array $credentials) use ($apiClient) {
        // Validate password against the API
        return $apiClient->validatePassword(
            $user->getAuthIdentifier(),
            $credentials['password']
        );
    }
);
```

### Login by Email OR Username

```php
$provider = GenericUserProvider::create(
    byId: fn($id) => User::find($id),
    
    byCredentials: function (array $credentials) {
        $login = $credentials['login'] ?? 
                 $credentials['email'] ?? 
                 $credentials['username'] ?? null;
        
        if (!$login) {
            return null;
        }
        
        // Try email first
        $user = User::where('email', $login)->first();
        
        // Try username if email not found
        if (!$user) {
            $user = User::where('username', $login)->first();
        }
        
        return $user;
    }
);
```

### Multi-Database Users

```php
$provider = GenericUserProvider::create(
    byId: function ($id) {
        // Check main database first
        $user = User::find($id);
        
        if (!$user) {
            // Check legacy database
            $user = LegacyUser::find($id);
        }
        
        return $user;
    },
    
    byCredentials: function (array $credentials) {
        $email = $credentials['email'];
        
        // Check main database first
        $user = User::where('email', $email)->first();
        
        if (!$user) {
            // Check legacy database
            $user = LegacyUser::where('email', $email)->first();
        }
        
        return $user;
    }
);
```

### LDAP Authentication

```php
use Lalaz\Auth\Providers\GenericUserProvider;

$provider = GenericUserProvider::create(
    byId: function ($dn) use ($ldap) {
        $entry = $ldap->read($dn);
        return $entry ? new LdapUser($entry) : null;
    },
    
    byCredentials: function (array $credentials) use ($ldap) {
        $username = $credentials['username'];
        
        $results = $ldap->search(
            "ou=users,dc=example,dc=com",
            "(uid={$username})"
        );
        
        if ($results->count() === 0) {
            return null;
        }
        
        return new LdapUser($results->first());
    },
    
    validateCredentials: function ($user, array $credentials) use ($ldap) {
        try {
            $ldap->bind($user->dn, $credentials['password']);
            return true;
        } catch (LdapException $e) {
            return false;
        }
    }
);
```

### OAuth/Social Login

```php
$provider = GenericUserProvider::create(
    byId: fn($id) => User::find($id),
    
    byCredentials: function (array $credentials) {
        $oauthId = $credentials['oauth_id'] ?? null;
        $oauthProvider = $credentials['oauth_provider'] ?? null;
        
        if ($oauthId && $oauthProvider) {
            // Find by OAuth ID
            return User::where('oauth_provider', $oauthProvider)
                       ->where('oauth_id', $oauthId)
                       ->first();
        }
        
        // Fall back to email
        $email = $credentials['email'] ?? null;
        return $email ? User::where('email', $email)->first() : null;
    },
    
    validateCredentials: function ($user, array $credentials) {
        // OAuth doesn't use password validation
        if (isset($credentials['oauth_token'])) {
            return true;  // Token already validated by OAuth provider
        }
        
        // Fall back to password for non-OAuth
        $password = $credentials['password'] ?? '';
        return password_verify($password, $user->password);
    }
);
```

### API Key Provider

```php
$provider = GenericUserProvider::create(
    byId: fn($id) => ApiClient::find($id),
    
    byCredentials: function (array $credentials) {
        $apiKey = $credentials['api_key'] ?? null;
        
        if (!$apiKey) {
            return null;
        }
        
        // Hash the key for comparison
        $hash = hash('sha256', $apiKey);
        
        return ApiClient::where('api_key_hash', $hash)
                        ->where('is_active', true)
                        ->first();
    },
    
    validateCredentials: function ($client, array $credentials) {
        // API key is already validated in byCredentials
        return $client !== null && $client->is_active;
    }
);
```

## Advanced Patterns

### Caching Provider

Wrap another provider with caching:

```php
$innerProvider = new ModelUserProvider(User::class);

$provider = GenericUserProvider::create(
    byId: function ($id) use ($innerProvider, $cache) {
        $key = "user:{$id}";
        
        if ($cache->has($key)) {
            return $cache->get($key);
        }
        
        $user = $innerProvider->retrieveById($id);
        
        if ($user) {
            $cache->set($key, $user, ttl: 300);  // Cache for 5 minutes
        }
        
        return $user;
    },
    
    byCredentials: function (array $credentials) use ($innerProvider) {
        // Don't cache credential lookups (security)
        return $innerProvider->retrieveByCredentials($credentials);
    },
    
    validateCredentials: function ($user, array $credentials) use ($innerProvider) {
        return $innerProvider->validateCredentials($user, $credentials);
    }
);
```

### Logging Provider

Add logging to user retrieval:

```php
$innerProvider = new ModelUserProvider(User::class);

$provider = GenericUserProvider::create(
    byId: function ($id) use ($innerProvider, $logger) {
        $user = $innerProvider->retrieveById($id);
        
        $logger->info('User retrieved by ID', [
            'user_id' => $id,
            'found' => $user !== null,
        ]);
        
        return $user;
    },
    
    byCredentials: function (array $credentials) use ($innerProvider, $logger) {
        $user = $innerProvider->retrieveByCredentials($credentials);
        
        $logger->info('User retrieved by credentials', [
            'email' => $credentials['email'] ?? 'unknown',
            'found' => $user !== null,
        ]);
        
        return $user;
    },
    
    validateCredentials: function ($user, array $credentials) use ($innerProvider, $logger) {
        $valid = $innerProvider->validateCredentials($user, $credentials);
        
        $logger->info('Password validation', [
            'user_id' => $user->getAuthIdentifier(),
            'valid' => $valid,
        ]);
        
        return $valid;
    }
);
```

### Tenant-Scoped Provider

Scope all queries to current tenant:

```php
$provider = GenericUserProvider::create(
    byId: function ($id) use ($tenantContext) {
        return User::where('id', $id)
                   ->where('tenant_id', $tenantContext->getAuthIdentifier())
                   ->first();
    },
    
    byCredentials: function (array $credentials) use ($tenantContext) {
        unset($credentials['password']);
        
        return User::where($credentials)
                   ->where('tenant_id', $tenantContext->getAuthIdentifier())
                   ->first();
    }
);
```

### Composite User

Combine data from multiple sources:

```php
$provider = GenericUserProvider::create(
    byId: function ($id) use ($userRepo, $profileRepo) {
        $user = $userRepo->find($id);
        
        if (!$user) {
            return null;
        }
        
        // Attach profile data
        $profile = $profileRepo->findByUserId($id);
        $user->profile = $profile;
        
        return $user;
    },
    
    byCredentials: function (array $credentials) use ($userRepo, $profileRepo) {
        $user = $userRepo->findByEmail($credentials['email']);
        
        if (!$user) {
            return null;
        }
        
        // Attach profile data
        $profile = $profileRepo->findByUserId($user->id);
        $user->profile = $profile;
        
        return $user;
    }
);
```

## Complete Example

Here's a complete example showing all features:

```php
<?php

namespace App\Providers;

use Lalaz\Auth\Providers\GenericUserProvider;
use App\Models\User;
use App\Models\LegacyUser;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

class CustomUserProviderFactory
{
    public function __construct(
        private LoggerInterface $logger,
        private CacheInterface $cache
    ) {}

    public function create(): GenericUserProvider
    {
        return GenericUserProvider::create(
            byId: $this->byIdCallback(),
            byCredentials: $this->byCredentialsCallback(),
            validateCredentials: $this->validateCredentialsCallback()
        );
    }

    private function byIdCallback(): callable
    {
        return function ($id) {
            // Check cache first
            $cacheKey = "user:{$id}";
            
            if ($this->cache->has($cacheKey)) {
                $this->logger->debug("User cache hit", ['id' => $id]);
                return $this->cache->get($cacheKey);
            }

            // Try main database
            $user = User::find($id);

            // Try legacy database if not found
            if (!$user) {
                $user = $this->migrateFromLegacy($id);
            }

            // Cache the result
            if ($user) {
                $this->cache->set($cacheKey, $user, 300);
            }

            $this->logger->info('User retrieved by ID', [
                'user_id' => $id,
                'found' => $user !== null,
            ]);

            return $user;
        };
    }

    private function byCredentialsCallback(): callable
    {
        return function (array $credentials) {
            $login = $credentials['login'] ?? 
                     $credentials['email'] ?? 
                     $credentials['username'] ?? null;

            if (!$login) {
                return null;
            }

            // Remove password from query
            unset($credentials['password']);

            // Try email first
            $user = User::where('email', $login)
                        ->where('is_active', true)
                        ->first();

            // Try username
            if (!$user) {
                $user = User::where('username', $login)
                            ->where('is_active', true)
                            ->first();
            }

            // Try legacy database
            if (!$user) {
                $legacyUser = LegacyUser::where('email', $login)->first();
                if ($legacyUser) {
                    $user = $this->migrateFromLegacy($legacyUser->id);
                }
            }

            $this->logger->info("User retrieved by credentials", [
                'login' => $login,
                'found' => $user !== null,
            ]);

            return $user;
        };
    }

    private function validateCredentialsCallback(): callable
    {
        return function ($user, array $credentials) {
            $password = $credentials['password'] ?? '';

            // Empty password always fails
            if (empty($password)) {
                return false;
            }

            // Check password
            $valid = password_verify($password, $user->getAuthPassword());

            // Rehash if needed
            if ($valid && password_needs_rehash($user->getAuthPassword(), PASSWORD_DEFAULT)) {
                $user->password = password_hash($password, PASSWORD_DEFAULT);
                $user->save();
                
                $this->logger->info("Password rehashed", ['user_id' => $user->getAuthIdentifier()]);
            }

            $this->logger->info("Credentials validated", [
                'user_id' => $user->getAuthIdentifier(),
                'valid' => $valid,
            ]);

            return $valid;
        };
    }

    private function migrateFromLegacy(int $id): ?User
    {
        $legacyUser = LegacyUser::find($id);
        
        if (!$legacyUser) {
            return null;
        }

        // Migrate to new system
        $user = new User();
        $user->id = $legacyUser->id;
        $user->name = $legacyUser->full_name;
        $user->email = $legacyUser->email;
        $user->password = $legacyUser->password_hash;  // Assumes same hash format
        $user->save();

        $this->logger->info("User migrated from legacy", [
            'user_id' => $user->getAuthIdentifier(),
        ]);

        return $user;
    }
}
```

## Using in Configuration

```php
// In AppServiceProvider or bootstrap
$factory = new CustomUserProviderFactory($logger, $cache);

$auth = resolve(AuthManager::class);

$auth->extendProvider('custom', function () use ($factory) {
    return $factory->create();
});

// In config/auth.php
return [
    'providers' => [
        'users' => [
            'driver' => 'custom',
        ],
    ],
];
```

## Testing

```php
use PHPUnit\Framework\TestCase;
use Lalaz\Auth\Providers\GenericUserProvider;

class GenericUserProviderTest extends TestCase
{
    public function test_retrieves_user_by_id(): void
    {
        $user = new stdClass();
        $user->id = 123;
        $user->name = 'Test';
        
        $provider = GenericUserProvider::create(
            byId: fn($id) => $id === 123 ? $user : null,
            byCredentials: fn($creds) => null
        );
        
        $result = $provider->retrieveById(123);
        
        $this->assertSame($user, $result);
    }

    public function test_retrieves_user_by_credentials(): void
    {
        $user = new stdClass();
        $user->email = 'test@example.com';
        
        $provider = GenericUserProvider::create(
            byId: fn($id) => null,
            byCredentials: fn($creds) => 
                ($creds['email'] ?? null) === 'test@example.com' ? $user : null
        );
        
        $result = $provider->retrieveByCredentials([
            'email' => 'test@example.com',
        ]);
        
        $this->assertSame($user, $result);
    }

    public function test_validates_credentials(): void
    {
        $user = new stdClass();
        $user->password = password_hash('secret', PASSWORD_DEFAULT);
        
        $provider = GenericUserProvider::create(
            byId: fn($id) => null,
            byCredentials: fn($creds) => null,
            validateCredentials: fn($u, $creds) => 
                password_verify($creds['password'] ?? '', $u->password)
        );
        
        $this->assertTrue($provider->validateCredentials($user, ['password' => 'secret']));
        $this->assertFalse($provider->validateCredentials($user, ['password' => 'wrong']));
    }
}
```

## Next Steps

- Learn about [ModelUserProvider](./model.md) for ORM-based users
- Configure [Guards](../guards/index.md) to use your provider
- Add [Authentication Middleware](../middlewares/authentication.md)
