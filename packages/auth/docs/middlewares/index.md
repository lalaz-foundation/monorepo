# Middlewares Overview

Middlewares protect your routes by checking authentication and authorization before requests reach your controllers.

## Available Middlewares

| Middleware | Purpose | Example Use |
|------------|---------|-------------|
| `AuthenticationMiddleware` | Verify user is logged in | Any protected route |
| `AuthorizationMiddleware` | Check roles/permissions | Admin-only areas |
| `PermissionMiddleware` | Check specific permissions | Feature access |

## Quick Start

```php
use Lalaz\Auth\Middlewares\AuthenticationMiddleware;
use Lalaz\Auth\Middlewares\AuthorizationMiddleware;

// Require authentication
$router->get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(AuthenticationMiddleware::web('/login'));

// Require authentication + admin role
$router->get('/admin', [AdminController::class, 'index'])
    ->middleware([
        AuthenticationMiddleware::web('/login'),
        AuthorizationMiddleware::requireRoles('admin'),
    ]);
```

## How Middlewares Work

```
Request arrives
     │
     ▼
┌─────────────────────────────┐
│ AuthenticationMiddleware    │
│ Is user authenticated?      │
│   No  → Redirect to login   │
│   Yes → Continue            │
└─────────────────────────────┘
     │
     ▼
┌─────────────────────────────┐
│ AuthorizationMiddleware     │
│ Does user have required     │
│ role/permission?            │
│   No  → 403 Forbidden       │
│   Yes → Continue            │
└─────────────────────────────┘
     │
     ▼
Controller handles request
```

## AuthenticationMiddleware

Verifies the user is logged in.

### Web (Session) Authentication

```php
use Lalaz\Auth\Middlewares\AuthenticationMiddleware;

// Redirect to /login if not authenticated
$router->group('/account', function ($router) {
    $router->get('/profile', [ProfileController::class, 'show']);
    $router->get('/settings', [SettingsController::class, 'show']);
})->middleware(AuthenticationMiddleware::web('/login'));
```

### API (JWT) Authentication

```php
// Return 401 JSON response if not authenticated
$router->group('/api', function ($router) {
    $router->get('/user', [ApiController::class, 'user']);
})->middleware(AuthenticationMiddleware::jwt());
```

### API Key Authentication

```php
// Validate API key from header or query string
$router->group('/webhook', function ($router) {
    $router->post('/stripe', [WebhookController::class, 'stripe']);
})->middleware(AuthenticationMiddleware::apiKey());
```

📖 [Full AuthenticationMiddleware Documentation](./authentication.md)

---

## AuthorizationMiddleware

Checks roles and permissions after authentication.

### Require Specific Role

```php
use Lalaz\Auth\Middlewares\AuthorizationMiddleware;

$router->group('/admin', function ($router) {
    // ... admin routes
})->middleware([
    AuthenticationMiddleware::web('/login'),
    AuthorizationMiddleware::requireRoles('admin'),
]);
```

### Require Any of Multiple Roles

```php
// User must have admin OR moderator role
$router->middleware([
    AuthenticationMiddleware::web('/login'),
    AuthorizationMiddleware::requireAnyRole(['admin', 'moderator']),
]);
```

### Require Specific Permission

```php
// User must have 'posts.create' permission
$router->post('/posts', [PostController::class, 'store'])
    ->middleware([
        AuthenticationMiddleware::web('/login'),
        AuthorizationMiddleware::requirePermissions('posts.create'),
    ]);
```

📖 [Full AuthorizationMiddleware Documentation](./authorization.md)

---

## PermissionMiddleware

Dedicated middleware for permission checks.

```php
use Lalaz\Auth\Middlewares\PermissionMiddleware;

// Single permission
$router->delete('/posts/{id}', [PostController::class, 'destroy'])
    ->middleware(PermissionMiddleware::require('posts.delete'));

// Multiple permissions (must have all)
$router->post('/posts/publish', [PostController::class, 'publish'])
    ->middleware(PermissionMiddleware::requireAll(['posts.edit', 'posts.publish']));

// Any of the permissions
$router->get('/content', [ContentController::class, 'index'])
    ->middleware(PermissionMiddleware::requireAny(['posts.view', 'pages.view']));
```

📖 [Full PermissionMiddleware Documentation](./permission.md)

---

## Combining Middlewares

Middlewares execute in order. Always authenticate before authorizing.

### Basic Pattern

```php
$router->group('/admin', function ($router) {
    // Admin dashboard
    $router->get('/', [AdminController::class, 'dashboard']);
    
    // User management (needs additional permission)
    $router->get('/users', [AdminController::class, 'users'])
        ->middleware(PermissionMiddleware::require('users.manage'));
        
})->middleware([
    AuthenticationMiddleware::web('/login'),
    AuthorizationMiddleware::requireRoles('admin'),
]);
```

### Multiple Levels

```php
// Level 1: All authenticated users
$router->group('/app', function ($router) {
    $router->get('/dashboard', [DashboardController::class, 'index']);
    
    // Level 2: Editor role required
    $router->group('/posts', function ($router) {
        $router->get('/', [PostController::class, 'index']);
        $router->post('/', [PostController::class, 'store']);
        
        // Level 3: Specific permission
        $router->delete('/{id}', [PostController::class, 'destroy'])
            ->middleware(PermissionMiddleware::require('posts.delete'));
            
    })->middleware(AuthorizationMiddleware::requireRoles('editor'));
    
})->middleware(AuthenticationMiddleware::web('/login'));
```

## Middleware Factory Methods

### AuthenticationMiddleware

```php
// Session-based (web)
AuthenticationMiddleware::web(string $redirectTo = '/login');
AuthenticationMiddleware::session(string $guard = 'web', string $redirectTo = '/login');

// JWT-based (API)
AuthenticationMiddleware::jwt(string $guard = 'api');

// API Key
AuthenticationMiddleware::apiKey(string $guard = 'api-key');

// Custom guard
AuthenticationMiddleware::forGuard(string $guard, ?string $redirectTo = null);
```

### AuthorizationMiddleware

```php
// Role checks
AuthorizationMiddleware::requireRoles(string ...$roles);
AuthorizationMiddleware::requireAnyRole(array $roles);

// Permission checks
AuthorizationMiddleware::requirePermissions(string ...$permissions);
AuthorizationMiddleware::requireAnyPermission(array $permissions);
```

### PermissionMiddleware

```php
// Permission checks
PermissionMiddleware::require(string $permission);
PermissionMiddleware::requireAll(array $permissions);
PermissionMiddleware::requireAny(array $permissions);
```

## Custom Middleware

Create your own authentication middleware:

```php
<?php

namespace App\Middleware;

use Lalaz\Auth\AuthContext;

class CustomAuthMiddleware
{
    public function __construct(
        private AuthContext $context
    ) {}

    public function handle($request, callable $next)
    {
        // Check authentication
        if (!$this->context->check()) {
            return $this->handleUnauthenticated($request);
        }

        // Custom logic
        $user = $this->context->user();
        
        if (!$user->isActive()) {
            return response()->json(['error' => 'Account disabled'], 403);
        }

        if (!$user->isVerified()) {
            return response()->redirect('/verify-email');
        }

        return $next($request);
    }

    private function handleUnauthenticated($request)
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }
        
        return response()->redirect('/login');
    }
}
```

### Register Custom Middleware

```php
// In routes or service provider
$router->middleware('custom.auth', CustomAuthMiddleware::class);

// Use it
$router->get('/protected', [Controller::class, 'index'])
    ->middleware('custom.auth');
```

## Error Responses

### Web Routes (HTML)

Unauthenticated users are redirected to login page:

```php
// Redirects to /login
AuthenticationMiddleware::web('/login');

// Redirects to /admin/login
AuthenticationMiddleware::web('/admin/login');
```

Unauthorized users see 403 error:

```php
// Returns 403 Forbidden
AuthorizationMiddleware::requireRoles('admin');
```

### API Routes (JSON)

Unauthenticated requests get 401 response:

```json
{
    "error": "Unauthenticated",
    "message": "Authentication required"
}
```

Unauthorized requests get 403 response:

```json
{
    "error": "Forbidden",
    "message": "Insufficient permissions"
}
```

## Route Patterns

### Public + Protected

```php
// Public routes
$router->get('/', [HomeController::class, 'index']);
$router->get('/about', [PageController::class, 'about']);

// Auth routes (guests only)
$router->group('/auth', function ($router) {
    $router->get('/login', [AuthController::class, 'showLogin']);
    $router->post('/login', [AuthController::class, 'login']);
    $router->get('/register', [AuthController::class, 'showRegister']);
    $router->post('/register', [AuthController::class, 'register']);
})->middleware(GuestMiddleware::class);

// Protected routes
$router->group('/', function ($router) {
    $router->get('/dashboard', [DashboardController::class, 'index']);
    $router->post('/logout', [AuthController::class, 'logout']);
})->middleware(AuthenticationMiddleware::web('/auth/login'));
```

### API Versioning

```php
// Public API
$router->group('/api/v1', function ($router) {
    $router->get('/products', [ProductController::class, 'index']);
});

// Protected API
$router->group('/api/v1', function ($router) {
    $router->post('/products', [ProductController::class, 'store']);
    $router->put('/products/{id}', [ProductController::class, 'update']);
    $router->delete('/products/{id}', [ProductController::class, 'destroy']);
})->middleware(AuthenticationMiddleware::jwt());
```

### Role-Based Sections

```php
// User area
$router->group('/user', function ($router) {
    $router->get('/profile', [UserController::class, 'profile']);
})->middleware(AuthenticationMiddleware::web('/login'));

// Admin area
$router->group('/admin', function ($router) {
    $router->get('/dashboard', [AdminController::class, 'dashboard']);
    $router->resource('/users', AdminUserController::class);
})->middleware([
    AuthenticationMiddleware::web('/login'),
    AuthorizationMiddleware::requireRoles('admin'),
]);

// Super admin area
$router->group('/super-admin', function ($router) {
    $router->get('/system', [SystemController::class, 'index']);
})->middleware([
    AuthenticationMiddleware::web('/login'),
    AuthorizationMiddleware::requireRoles('super-admin'),
]);
```

## Testing Middleware

```php
use PHPUnit\Framework\TestCase;

class MiddlewareTest extends TestCase
{
    public function test_unauthenticated_user_is_redirected(): void
    {
        $response = $this->get('/dashboard');
        
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_access(): void
    {
        $this->actingAs($user);
        
        $response = $this->get('/dashboard');
        
        $response->assertOk();
    }

    public function test_unauthorized_user_gets_403(): void
    {
        $this->actingAs($regularUser);
        
        $response = $this->get('/admin');
        
        $response->assertForbidden();
    }

    public function test_admin_can_access_admin_area(): void
    {
        $this->actingAs($adminUser);
        
        $response = $this->get('/admin');
        
        $response->assertOk();
    }
}
```

## Next Steps

- Learn about [AuthenticationMiddleware](./authentication.md) in detail
- Learn about [AuthorizationMiddleware](./authorization.md) for role checks
- Learn about [PermissionMiddleware](./permission.md) for permission checks
- Set up [Roles and Permissions](../authorization.md)
