# Core Concepts

Understanding the core concepts of Lalaz Auth will help you build secure applications. This guide explains the fundamental building blocks.

## Overview

Lalaz Auth is built around four main concepts:

1. **Guards** - How users prove their identity
2. **Providers** - Where user data comes from
3. **Context** - The current authentication state
4. **Middlewares** - Protecting routes

Let's explore each one.

---

## Guards

### What is a Guard?

A **Guard** is responsible for authenticating users. It defines *how* a user proves they are who they claim to be.

Think of a guard like a security checkpoint:
- A **Session Guard** checks if you have a valid session cookie
- A **JWT Guard** checks if you have a valid token in the Authorization header
- An **API Key Guard** checks if you have a valid API key

### Available Guards

| Guard | Best For | How It Works |
|-------|----------|--------------|
| `SessionGuard` | Web applications | Stores user ID in server session |
| `JwtGuard` | APIs, mobile apps | Validates JWT in Authorization header |
| `ApiKeyGuard` | Third-party integrations | Validates API key in header or query |

### Using Guards

```php
use Lalaz\Auth\AuthManager;

$auth = resolve(AuthManager::class);

// Use the default guard
$user = $auth->user();

// Use a specific guard
$webUser = $auth->guard('web')->user();
$apiUser = $auth->guard('api')->user();
```

### Guard Interface

All guards implement `GuardInterface` with these methods:

```php
interface GuardInterface
{
    public function getName(): string;                   // Get guard name/identifier
    public function attempt(array $credentials): mixed;  // Try to authenticate
    public function login(mixed $user): void;            // Log a user in
    public function logout(): void;                      // Log out
    public function user(): mixed;                       // Get current user
    public function check(): bool;                       // Is authenticated?
    public function guest(): bool;                       // Is not authenticated?
    public function id(): mixed;                         // Get user ID
    public function validate(array $credentials): bool;  // Validate without login
    public function setProvider(UserProviderInterface $provider): void; // Set provider
}
```

---

## User Providers

### What is a Provider?

A **Provider** retrieves user data from your storage system (database, API, etc.). It's the bridge between the guard and your users table.

Think of it like this:
- The **Guard** says "I need to find user with ID 123"
- The **Provider** knows how to query your database and return that user

### Available Providers

| Provider | Best For | How It Works |
|----------|----------|--------------|
| `ModelUserProvider` | ORM-based apps | Uses your Model class to query users |
| `GenericUserProvider` | Custom systems | Uses callbacks you define |

### Provider Interface

```php
interface UserProviderInterface
{
    // Find a user by their ID
    public function retrieveById(mixed $identifier): mixed;
    
    // Find a user by credentials (email, username, etc.)
    public function retrieveByCredentials(array $credentials): mixed;
    
    // Verify password matches
    public function validateCredentials(mixed $user, array $credentials): bool;
}
```

### Using Providers

```php
use Lalaz\Auth\Providers\ModelUserProvider;
use Lalaz\Auth\Providers\GenericUserProvider;

// Model-based provider (uses your User model)
$provider = new ModelUserProvider(User::class);
$user = $provider->retrieveById(1);

// Generic provider (uses callbacks)
$provider = GenericUserProvider::create(
    byId: fn($id) => User::find($id),
    byCredentials: fn($creds) => User::where('email', $creds['email'])->first()
);
```

---

## Auth Context

### What is AuthContext?

The **AuthContext** stores the authenticated user(s) for the current request. It's the central place to check "who is logged in?"

Key features:
- Stores one user **per guard** (multi-guard support)
- Provides helper methods for role/permission checks
- Lives only for the duration of one request

### Basic Usage

```php
use Lalaz\Auth\AuthContext;

$context = resolve(AuthContext::class);

// Set a user (usually done by middleware)
$context->setUser($user, 'web');

// Get the current user
$user = $context->user();        // From current guard
$user = $context->user('api');   // From specific guard

// Check authentication state
$context->isAuthenticated();     // Is someone logged in?
$context->isGuest();             // Is no one logged in?
$context->check();               // Alias for isAuthenticated()
$context->guest();               // Alias for isGuest()

// Get user ID
$id = $context->id();
```

### Multi-Guard Context

AuthContext supports multiple guards simultaneously:

```php
// A user can be authenticated differently on different guards
$context->setUser($webUser, 'web');
$context->setUser($apiUser, 'api');

// Each guard is independent
$context->user('web');   // Returns $webUser
$context->user('api');   // Returns $apiUser

// Set the current guard
$context->setCurrentGuard('api');
$context->user();        // Now returns $apiUser
```

### GuardContext (Fluent Access)

For cleaner code, use `guard()` to get a guard-specific context:

```php
// Instead of:
$context->user('api');
$context->hasRole('admin', 'api');

// You can write:
$context->guard('api')->user();
$context->guard('api')->hasRole('admin');

// Chain multiple calls
if ($context->guard('api')->check() && $context->guard('api')->hasRole('admin')) {
    // API user is an admin
}
```

### Role and Permission Checks

AuthContext delegates role/permission checks to the user:

```php
// Check roles
$context->hasRole('admin');                    // Has this role?
$context->hasAnyRole(['admin', 'moderator']);  // Has any of these?

// Check permissions
$context->hasPermission('posts.create');
$context->hasAnyPermission(['posts.create', 'posts.edit']);

// For specific guard
$context->hasRole('admin', 'api');
$context->guard('api')->hasRole('admin');
```

---

## AuthManager

### What is AuthManager?

The **AuthManager** is the entry point for authentication. It:
- Manages all registered guards
- Creates guard instances on demand
- Provides convenience methods that delegate to the default guard

### Using AuthManager

```php
use Lalaz\Auth\AuthManager;

$auth = resolve(AuthManager::class);

// Work with guards
$auth->guard('web');           // Get a specific guard
$auth->guard();                // Get the default guard
$auth->setDefaultGuard('api'); // Change default

// Convenience methods (use default guard)
$auth->check();                // Is authenticated?
$auth->user();                 // Get user
$auth->attempt($credentials);  // Try to login
$auth->login($user);           // Login directly
$auth->logout();               // Logout
```

### Registering Custom Guards

```php
$auth->extend('oauth', function () {
    return new OAuthGuard(
        clientId: config('oauth.client_id'),
        clientSecret: config('oauth.client_secret')
    );
});

// Now you can use it
$auth->guard('oauth')->login($user);
```

---

## Request Flow

Here's how authentication works during a typical request:

```
1. Request arrives
        │
        ▼
2. AuthenticationMiddleware runs
        │
        ├─── Gets guard from configuration
        │
        ├─── Guard checks credentials (session/token/key)
        │
        ├─── Guard uses Provider to load user
        │
        └─── User stored in AuthContext
                │
                ▼
3. Your controller runs
        │
        ├─── Access user via user() or auth_context()
        │
        ├─── Check roles/permissions
        │
        └─── Return response
                │
                ▼
4. Response sent
```

### Example Flow: JWT Authentication

```
1. Client sends: GET /api/profile
   Headers: Authorization: Bearer eyJ...

2. AuthenticationMiddleware::jwt() runs
   └─ JwtGuard extracts token from header
   └─ JwtGuard decodes and validates token
   └─ JwtGuard extracts user ID from 'sub' claim
   └─ UserProvider loads user by ID
   └─ User stored in AuthContext for 'api' guard

3. ProfileController::show() runs
   └─ $user = user('api');  // Gets the authenticated user
   └─ Returns user profile as JSON

4. Response: 200 OK { "id": 1, "name": "John" }
```

---

## Putting It Together

Here's a complete example showing all concepts:

```php
<?php

use Lalaz\Auth\AuthManager;
use Lalaz\Auth\AuthContext;
use Lalaz\Auth\Middlewares\AuthenticationMiddleware;
use Lalaz\Auth\Middlewares\AuthorizationMiddleware;

// === CONFIGURATION (config/auth.php) ===
// Defines guards (session, jwt) and providers (users)

// === ROUTES ===
// Web routes use session guard
$router->group('/admin', function ($router) {
    $router->get('/dashboard', [AdminController::class, 'dashboard']);
    $router->get('/users', [AdminController::class, 'users']);
})->middleware([
    AuthenticationMiddleware::web('/login'),
    AuthorizationMiddleware::requireRoles('admin'),
]);

// API routes use JWT guard
$router->group('/api', function ($router) {
    $router->get('/profile', [ApiController::class, 'profile']);
})->middleware(AuthenticationMiddleware::jwt());

// === CONTROLLER ===
class AdminController
{
    public function dashboard($request, $response)
    {
        // User is guaranteed to be authenticated (middleware)
        // User is guaranteed to have 'admin' role (middleware)
        
        $user = user();  // Get authenticated user
        $context = auth_context();
        
        // Additional permission check
        $canManageUsers = $context->hasPermission('users.manage');
        
        $response->view('admin/dashboard', [
            'user' => $user,
            'canManageUsers' => $canManageUsers,
        ]);
    }
}

// === LOGIN CONTROLLER ===
class LoginController
{
    public function login($request, $response)
    {
        $auth = resolve(AuthManager::class);
        
        // Attempt to authenticate
        $user = $auth->guard('web')->attempt([
            'email' => $request->input('email'),
            'password' => $request->input('password'),
        ]);
        
        if ($user) {
            $auth->guard('web')->login($user);
            $response->redirect('/admin/dashboard');
        } else {
            $response->redirect('/login?error=invalid');
        }
    }
}
```

---

## Summary

| Concept | Purpose | Key Class |
|---------|---------|-----------|
| **Guard** | Authenticate users | `SessionGuard`, `JwtGuard`, `ApiKeyGuard` |
| **Provider** | Retrieve user data | `ModelUserProvider`, `GenericUserProvider` |
| **Context** | Store auth state | `AuthContext`, `GuardContext` |
| **Manager** | Orchestrate guards | `AuthManager` |
| **Middleware** | Protect routes | `AuthenticationMiddleware` |

## Next Steps

- Learn about specific [Guards](./guards/index.md)
- Set up [User Providers](./providers/index.md)
- Protect routes with [Middlewares](./middlewares/index.md)
