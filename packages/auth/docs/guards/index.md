# Guards Overview

Guards are the foundation of authentication in Lalaz Auth. They determine *how* users prove their identity.

## What is a Guard?

A guard handles the authentication mechanism. Different situations require different guards:

| Situation | Best Guard | Why |
|-----------|------------|-----|
| Traditional web app | `SessionGuard` | Browser manages cookies automatically |
| REST API | `JwtGuard` | Stateless, scalable, mobile-friendly |
| Third-party integrations | `ApiKeyGuard` | Simple, long-lived credentials |
| Multiple methods | Multi-guard setup | Different routes, different guards |

## Available Guards

### SessionGuard

The classic approach for web applications. Stores user ID in a server-side session.

```php
use Lalaz\Auth\Guards\SessionGuard;

// Created automatically from config, but you can make one manually:
$guard = new SessionGuard($userProvider, $sessionAdapter);
```

**Pros:**
- Simple to understand
- Browser handles cookies
- Easy session management

**Cons:**
- Server stores session data
- Not ideal for mobile apps
- Requires CSRF protection

📖 [Full SessionGuard Documentation](./session.md)

---

### JwtGuard

Modern approach for APIs. Uses JSON Web Tokens sent in headers.

```php
use Lalaz\Auth\Guards\JwtGuard;

$guard = new JwtGuard($userProvider, $jwtEncoder, $blacklistService);
```

**Pros:**
- Stateless (no server storage)
- Perfect for APIs and mobile
- Contains user data in token

**Cons:**
- Can't immediately revoke (use blacklist)
- Token in every request

📖 [Full JwtGuard Documentation](./jwt.md)

---

### ApiKeyGuard

Simple approach for machine-to-machine authentication or third-party integrations.

```php
use Lalaz\Auth\Guards\ApiKeyGuard;

$guard = new ApiKeyGuard($userProvider, $apiKeyProvider);
```

**Pros:**
- Very simple
- Long-lived credentials
- Easy to implement

**Cons:**
- Less secure than JWT
- No expiration by default
- Manual key management

📖 [Full ApiKeyGuard Documentation](./api-key.md)

---

## Configuring Guards

Guards are configured in `config/auth.php`:

```php
return [
    'defaults' => [
        'guard' => 'web',
    ],

    'guards' => [
        // Session-based for web pages
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        // JWT for API
        'api' => [
            'driver' => 'jwt',
            'provider' => 'users',
        ],

        // API keys for external integrations
        'external' => [
            'driver' => 'api-key',
            'provider' => 'api_clients',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'model',
            'model' => App\Models\User::class,
        ],
        'api_clients' => [
            'driver' => 'model',
            'model' => App\Models\ApiClient::class,
        ],
    ],
];
```

## Using Guards

### Access via AuthManager

```php
use Lalaz\Auth\AuthManager;

$auth = resolve(AuthManager::class);

// Use default guard
$user = $auth->user();
$auth->login($user);
$auth->logout();

// Use specific guard
$auth->guard('web')->login($user);
$auth->guard('api')->user();
```

### Access via Helper Functions

```php
// Get authenticated user
$user = user();         // Default guard
$user = user('api');    // Specific guard

// Check authentication
if (auth_context()->check()) {
    // User is logged in
}
```

### In Middleware

```php
use Lalaz\Auth\Middlewares\AuthenticationMiddleware;

// For web routes (session)
$router->middleware(AuthenticationMiddleware::web('/login'));

// For API routes (JWT)
$router->middleware(AuthenticationMiddleware::jwt());

// For external routes (API key)
$router->middleware(AuthenticationMiddleware::apiKey());
```

## Guard Interface

All guards implement `Lalaz\Auth\Contracts\GuardInterface`:

```php
interface GuardInterface
{
    /**
     * Attempt to authenticate a user with credentials.
     * Returns the user on success, null on failure.
     */
    public function attempt(array $credentials): mixed;

    /**
     * Log a user into the application.
     */
    public function login(mixed $user): void;

    /**
     * Log the user out of the application.
     */
    public function logout(): void;

    /**
     * Get the currently authenticated user.
     */
    public function user(): mixed;

    /**
     * Determine if the current user is authenticated.
     */
    public function check(): bool;

    /**
     * Determine if the current user is a guest (not authenticated).
     */
    public function guest(): bool;

    /**
     * Get the ID of the currently authenticated user.
     */
    public function id(): mixed;

    /**
     * Validate a user's credentials without logging them in.
     */
    public function validate(array $credentials): bool;
}
```

## Creating Custom Guards

You can create your own guard by implementing `GuardInterface`:

```php
namespace App\Auth;

use Lalaz\Auth\Contracts\GuardInterface;
use Lalaz\Auth\Contracts\UserProviderInterface;

class OAuthGuard implements GuardInterface
{
    private ?object $user = null;
    
    public function __construct(
        private UserProviderInterface $provider,
        private string $clientId,
        private string $clientSecret,
    ) {}

    public function attempt(array $credentials): mixed
    {
        // Validate OAuth token with provider
        $tokenData = $this->validateWithOAuthProvider($credentials['token']);
        
        if (!$tokenData) {
            return null;
        }

        // Find or create user
        $this->user = $this->provider->retrieveByCredentials([
            'oauth_id' => $tokenData['user_id'],
        ]);

        return $this->user;
    }

    public function login(mixed $user): void
    {
        $this->user = $user;
    }

    public function logout(): void
    {
        $this->user = null;
    }

    public function user(): mixed
    {
        return $this->user;
    }

    public function check(): bool
    {
        return $this->user !== null;
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    public function id(): mixed
    {
        return $this->user?->getAuthIdentifier();
    }

    public function validate(array $credentials): bool
    {
        return $this->validateWithOAuthProvider($credentials['token']) !== null;
    }

    private function validateWithOAuthProvider(string $token): ?array
    {
        // Implement OAuth token validation
    }
}
```

### Registering Custom Guards

```php
// In a service provider or bootstrap file
$auth = resolve(AuthManager::class);

$auth->extend('oauth', function ($config) {
    return new OAuthGuard(
        provider: $this->createUserProvider($config['provider']),
        clientId: config('services.oauth.client_id'),
        clientSecret: config('services.oauth.client_secret'),
    );
});
```

Then in config:

```php
'guards' => [
    'oauth' => [
        'driver' => 'oauth',
        'provider' => 'users',
    ],
],
```

## Multi-Guard Setup

You can use multiple guards in one application:

```php
// config/auth.php
return [
    'defaults' => [
        'guard' => 'web',
    ],
    
    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        'api' => [
            'driver' => 'jwt',
            'provider' => 'users',
        ],
        'admin' => [
            'driver' => 'session',
            'provider' => 'admins',
        ],
    ],
    
    'providers' => [
        'users' => [
            'driver' => 'model',
            'model' => App\Models\User::class,
        ],
        'admins' => [
            'driver' => 'model',
            'model' => App\Models\Admin::class,
        ],
    ],
];
```

### Using Multiple Guards

```php
// Routes file
$router->group('/dashboard', function ($router) {
    // ... web routes
})->middleware(AuthenticationMiddleware::session('web', '/login'));

$router->group('/api', function ($router) {
    // ... API routes
})->middleware(AuthenticationMiddleware::jwt('api'));

$router->group('/admin', function ($router) {
    // ... admin routes
})->middleware(AuthenticationMiddleware::session('admin', '/admin/login'));
```

### In Controllers

```php
class DashboardController
{
    public function index($request, $response)
    {
        // Get user from web guard
        $webUser = user('web');
        
        // Get user from admin guard (if they're also logged in as admin)
        $adminUser = user('admin');
        
        $response->view('dashboard', [
            'user' => $webUser,
            'isAdmin' => $adminUser !== null,
        ]);
    }
}
```

## Guard Selection Tips

### Use SessionGuard when:
- Building traditional web applications
- Users access via web browser
- You need simple logout (destroy session)
- SEO is important (server-side rendering)

### Use JwtGuard when:
- Building APIs
- Supporting mobile applications
- Need stateless authentication
- Want to scale horizontally

### Use ApiKeyGuard when:
- Building webhooks
- Supporting third-party integrations
- Machine-to-machine authentication
- Simple internal services

### Use Multiple Guards when:
- Same app serves web and API
- Different user types need different auth
- Admin panel separate from main app

## Next Steps

- Learn about [Session Guard](./session.md)
- Learn about [JWT Guard](./jwt.md)
- Learn about [API Key Guard](./api-key.md)
- Configure [User Providers](../providers/index.md)
