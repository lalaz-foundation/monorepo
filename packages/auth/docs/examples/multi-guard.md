# Multi-Guard Example

Using multiple authentication guards for different user types.

## Overview

This example demonstrates:
- Web users with session authentication
- API clients with JWT authentication  
- Admin users with separate guard
- Service-to-service API key authentication
- Guard-specific middlewares

## Use Cases

- **E-commerce**: Customer (web) + Admin (admin) + Mobile App (API)
- **SaaS**: Users (web) + API Integrations (API key) + Admin Portal (admin)
- **Content Platform**: Readers (web) + Authors (admin) + Mobile (JWT)

## Project Structure

```
app/
├── Controllers/
│   ├── Web/
│   │   ├── AuthController.php
│   │   └── DashboardController.php
│   ├── Api/
│   │   ├── AuthController.php
│   │   └── ResourceController.php
│   └── Admin/
│       ├── AuthController.php
│       └── DashboardController.php
├── Models/
│   ├── User.php
│   └── Admin.php
config/
├── auth.php
routes/
├── web.php
├── api.php
└── admin.php
```

## Configuration

### config/auth.php

```php
<?php

return [
    // Default guard for web requests
    'default' => 'web',
    
    'guards' => [
        // Session-based for public website
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        
        // JWT for mobile apps and SPAs
        'api' => [
            'driver' => 'jwt',
            'provider' => 'users',
        ],
        
        // Separate session for admin panel
        'admin' => [
            'driver' => 'session',
            'provider' => 'admins',
            'session_key' => 'admin_auth_id', // Different session key
        ],
        
        // API key for service integrations
        'service' => [
            'driver' => 'api-key',
            'provider' => 'services',
        ],
    ],
    
    'providers' => [
        'users' => [
            'driver' => 'model',
            'model' => \App\Models\User::class,
        ],
        
        'admins' => [
            'driver' => 'model',
            'model' => \App\Models\Admin::class,
        ],
        
        'services' => [
            'driver' => 'generic',
            'resolver' => [\App\Services\ServiceAuth::class, 'findByApiKey'],
        ],
    ],
    
    'jwt' => [
        'secret' => env('JWT_SECRET'),
        'ttl' => 60,
        'refresh_ttl' => 20160,
    ],
];
```

## Models

### app/Models/User.php

```php
<?php

namespace App\Models;

use Lalaz\Data\Model;
use Lalaz\Auth\Contracts\AuthenticatableInterface;

class User extends Model implements AuthenticatableInterface
{
    protected static string $tableName = 'users';

    // ========================================
    // AuthenticatableInterface Implementation
    // ========================================

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

    // ========================================
    // Authorization Methods (optional)
    // ========================================

    public function getRoles(): array
    {
        return [$this->role ?? 'user'];
    }

    public function getPermissions(): array
    {
        return $this->getPermissionsForRole($this->role);
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->getPermissions());
    }

    private function getPermissionsForRole(?string $role): array
    {
        $map = [
            'premium' => ['content.view', 'content.download', 'api.access'],
            'user' => ['content.view'],
        ];
        return $map[$role] ?? [];
    }
}
```

### app/Models/Admin.php

```php
<?php

namespace App\Models;

use Lalaz\Data\Model;
use Lalaz\Auth\Contracts\AuthenticatableInterface;

class Admin extends Model implements AuthenticatableInterface
{
    protected static string $tableName = 'admins';

    public static function findByEmail(string $email): ?self
    {
        return self::findBy(['email' => $email]);
    }

    // ========================================
    // AuthenticatableInterface Implementation
    // ========================================

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

    // ========================================
    // Authorization Methods (optional)
    // ========================================

    public function getRoles(): array
    {
        // Admins can have multiple roles
        return json_decode($this->roles ?? '["admin"]', true);
    }

    public function getPermissions(): array
    {
        if ($this->is_super_admin) {
            return ['*']; // Super admin has all permissions
        }

        return json_decode($this->permissions ?? '[]', true);
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->getRoles());
    }

    public function hasPermission(string $permission): bool
    {
        $permissions = $this->getPermissions();
        
        if (in_array('*', $permissions)) {
            return true;
        }
        
        return in_array($permission, $permissions);
    }
}
```

### app/Services/ServiceAuth.php

```php
<?php

namespace App\Services;

use App\Models\ApiClient;
use Lalaz\Auth\Contracts\AuthenticatableInterface;

class ServiceAuth
{
    /**
     * Find service/client by API key
     */
    public static function findByApiKey(string $apiKey): ?AuthenticatableInterface
    {
        $client = ApiClient::findBy(['api_key' => $apiKey, 'is_active' => true]);
        
        if ($client) {
            // Log API access
            $client->last_used_at = date('Y-m-d H:i:s');
            $client->save();
        }
        
        return $client;
    }
}
```

### app/Models/ApiClient.php

```php
<?php

namespace App\Models;

use Lalaz\Data\Model;
use Lalaz\Auth\Contracts\AuthenticatableInterface;

class ApiClient extends Model implements AuthenticatableInterface
{
    protected static string $tableName = 'api_clients';

    // ========================================
    // AuthenticatableInterface Implementation
    // ========================================

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
        return ''; // API clients don't use passwords
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken(?string $value): void
    {
        // Not applicable for API clients
    }

    public function getRememberTokenName(): string
    {
        return '';
    }

    // ========================================
    // Authorization Methods (optional)
    // ========================================

    public function getRoles(): array
    {
        return ['service'];
    }

    public function getPermissions(): array
    {
        return json_decode($this->allowed_permissions ?? '[]', true);
    }

    public function hasRole(string $role): bool
    {
        return $role === 'service';
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->getPermissions());
    }
}
```

## Routes

### routes/web.php

```php
<?php

use App\Controllers\Web\AuthController;
use App\Controllers\Web\DashboardController;
use Lalaz\Auth\Middlewares\AuthenticationMiddleware;

// Public routes
$router->get('/', function ($request, $response) {
    return $response->view('welcome');
});

// Web auth routes
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/register', [AuthController::class, 'showRegister']);
$router->post('/register', [AuthController::class, 'register']);

// Protected web routes (uses 'web' guard by default)
$router->group('/', function ($router) {
    $router->get('/dashboard', [DashboardController::class, 'index']);
    $router->get('/profile', [DashboardController::class, 'profile']);
    $router->post('/logout', [AuthController::class, 'logout']);
})->middleware(AuthenticationMiddleware::web('/login'));
```

### routes/api.php

```php
<?php

use App\Controllers\Api\AuthController;
use App\Controllers\Api\ResourceController;
use Lalaz\Auth\Middlewares\AuthenticationMiddleware;
use Lalaz\Auth\Middlewares\PermissionMiddleware;

$router->group('/api/v1', function ($router) {
    
    // Public API routes
    $router->post('/auth/login', [AuthController::class, 'login']);
    $router->post('/auth/register', [AuthController::class, 'register']);
    $router->post('/auth/refresh', [AuthController::class, 'refresh']);
    
    // Protected API routes (uses 'api' JWT guard)
    $router->group('/', function ($router) {
        $router->get('/me', [AuthController::class, 'me']);
        $router->post('/logout', [AuthController::class, 'logout']);
        
        // Resources
        $router->get('/resources', [ResourceController::class, 'index']);
        $router->get('/resources/{id}', [ResourceController::class, 'show']);
        $router->post('/resources', [ResourceController::class, 'store'])
            ->middleware(PermissionMiddleware::require('content.create'));
            
    })->middleware(AuthenticationMiddleware::api());
    
    // Service API routes (API key auth)
    $router->group('/service', function ($router) {
        $router->get('/data-export', [ResourceController::class, 'export']);
        $router->post('/webhook', [ResourceController::class, 'webhook']);
    })->middleware(AuthenticationMiddleware::forGuard('service'));
});
```

### routes/admin.php

```php
<?php

use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\UserController;
use Lalaz\Auth\Middlewares\AuthenticationMiddleware;
use Lalaz\Auth\Middlewares\AuthorizationMiddleware;
use Lalaz\Auth\Middlewares\PermissionMiddleware;

$router->group('/admin', function ($router) {
    
    // Admin auth (no middleware)
    $router->get('/login', [AuthController::class, 'showLogin']);
    $router->post('/login', [AuthController::class, 'login']);
    
    // Protected admin routes
    $router->group('/', function ($router) {
        // Dashboard - all admins
        $router->get('/', [DashboardController::class, 'index']);
        $router->post('/logout', [AuthController::class, 'logout']);
        
        // User management - need permission
        $router->group('/users', function ($router) {
            $router->get('/', [UserController::class, 'index']);
            $router->get('/{id}', [UserController::class, 'show']);
            $router->post('/', [UserController::class, 'store'])
                ->middleware(PermissionMiddleware::require('users.create'));
            $router->put('/{id}', [UserController::class, 'update'])
                ->middleware(PermissionMiddleware::require('users.edit'));
            $router->delete('/{id}', [UserController::class, 'destroy'])
                ->middleware(PermissionMiddleware::require('users.delete'));
        });
        
        // Settings - super admin only
        $router->group('/settings', function ($router) {
            $router->get('/', [DashboardController::class, 'settings']);
            $router->post('/', [DashboardController::class, 'updateSettings']);
        })->middleware(AuthorizationMiddleware::requireRoles('super-admin'));
        
    })->middleware(AuthenticationMiddleware::forGuard('admin', '/admin/login'));
});
```

## Controllers

### Web Controller

```php
<?php

namespace App\Controllers\Web;

use function Lalaz\Auth\Helpers\auth;
use function Lalaz\Auth\Helpers\user;

class AuthController
{
    public function login($request, $response)
    {
        // Uses default 'web' guard
        $user = auth()->attempt([
            'email' => $request->input('email'),
            'password' => $request->input('password'),
        ]);
        
        if ($user) {
            return $response->redirect('/dashboard');
        }
        
        flash('error', 'Invalid credentials');
        return $response->redirect('/login');
    }
    
    public function logout($request, $response)
    {
        auth()->logout();
        return $response->redirect('/');
    }
}

class DashboardController
{
    public function index($request, $response)
    {
        // user() uses default 'web' guard
        return $response->view('dashboard', [
            'user' => user(),
        ]);
    }
}
```

### API Controller

```php
<?php

namespace App\Controllers\Api;

use Lalaz\Auth\AuthManager;
use function Lalaz\Auth\Helpers\user;

class AuthController
{
    private AuthManager $auth;

    public function __construct()
    {
        $this->auth = new AuthManager();
    }

    public function login($request, $response)
    {
        // Explicitly use 'api' guard
        $guard = $this->auth->guard('api');
        
        $user = $guard->attempt([
            'email' => $request->json('email'),
            'password' => $request->json('password'),
        ]);
        
        if (!$user) {
            return $response->json(['error' => 'Invalid credentials'], 401);
        }
        
        // JWT guard returns token data
        return $response->json([
            'user' => $user->toArray(),
            'tokens' => $guard->getTokenData(),
        ]);
    }

    public function me($request, $response)
    {
        // Get user from API guard
        return $response->json([
            'user' => user('api')->toArray(),
        ]);
    }
}
```

### Admin Controller

```php
<?php

namespace App\Controllers\Admin;

use Lalaz\Auth\AuthManager;
use function Lalaz\Auth\Helpers\user;
use function Lalaz\Auth\Helpers\auth_context;

class AuthController
{
    private AuthManager $auth;

    public function __construct()
    {
        $this->auth = new AuthManager();
    }

    public function login($request, $response)
    {
        // Explicitly use 'admin' guard
        $guard = $this->auth->guard('admin');
        
        $admin = $guard->attempt([
            'email' => $request->input('email'),
            'password' => $request->input('password'),
        ]);
        
        if (!$admin) {
            flash('error', 'Invalid credentials');
            return $response->redirect('/admin/login');
        }
        
        return $response->redirect('/admin');
    }

    public function logout($request, $response)
    {
        $this->auth->guard('admin')->logout();
        return $response->redirect('/admin/login');
    }
}

class DashboardController
{
    public function index($request, $response)
    {
        // Get admin from 'admin' guard
        $admin = user('admin');
        
        return $response->view('admin/dashboard', [
            'admin' => $admin,
            'canManageUsers' => auth_context()->hasPermission('users.manage'),
        ]);
    }
}
```

## Helper Functions with Guards

```php
use function Lalaz\Auth\Helpers\user;
use function Lalaz\Auth\Helpers\auth;
use function Lalaz\Auth\Helpers\auth_context;

// Get user from specific guard
$webUser = user();           // Default 'web' guard
$apiUser = user('api');      // JWT guard
$admin = user('admin');      // Admin guard
$service = user('service');  // API key guard

// Check authentication on specific guard
$auth = new AuthManager();

if ($auth->guard('admin')->check()) {
    // Admin is logged in
}

if ($auth->guard('api')->check()) {
    // API user is authenticated
}

// Get guard instance
$apiGuard = $auth->guard('api');
$token = $apiGuard->token();  // Get JWT token
```

## Simultaneous Sessions

Users can be logged in to multiple guards simultaneously:

```php
// User logged in via web
$webUser = user('web');  // Returns User model

// Same user accessing API
$apiUser = user('api');  // Returns same User model (different auth method)

// Admin logged in separately  
$admin = user('admin');  // Returns Admin model

// All can be active at the same time
// Different session keys, different providers
```

## Database Schema

```sql
-- Regular users
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Admin users (separate table)
CREATE TABLE admins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    roles JSON DEFAULT '["admin"]',
    permissions JSON,
    is_super_admin BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- API clients for service auth
CREATE TABLE api_clients (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    api_key VARCHAR(64) UNIQUE NOT NULL,
    contact_email VARCHAR(255),
    allowed_permissions JSON,
    is_active BOOLEAN DEFAULT TRUE,
    last_used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## Testing Multi-Guard

```php
<?php

namespace Tests;

use App\Models\User;
use App\Models\Admin;
use PHPUnit\Framework\TestCase;

class MultiGuardTest extends TestCase
{
    public function test_web_and_api_guards_are_independent(): void
    {
        $user = User::factory()->create();
        
        // Login via web
        $this->actingAs($user, 'web');
        
        // Web guard is authenticated
        $this->assertTrue(auth()->guard('web')->check());
        
        // API guard is NOT authenticated (no token)
        $this->assertFalse(auth()->guard('api')->check());
    }

    public function test_admin_guard_is_separate_from_user(): void
    {
        $user = User::factory()->create();
        $admin = Admin::factory()->create();
        
        // Login user via web
        $this->actingAs($user, 'web');
        
        // Login admin via admin guard
        $this->actingAs($admin, 'admin');
        
        // Both are authenticated
        $this->assertEquals($user->id, user('web')->getAuthIdentifier());
        $this->assertEquals($admin->id, user('admin')->getAuthIdentifier());
    }

    public function test_api_key_guard_authenticates_service(): void
    {
        $client = ApiClient::factory()->create([
            'api_key' => 'test-api-key',
            'allowed_permissions' => ['data.export'],
        ]);
        
        $response = $this->get('/api/v1/service/data-export', [
            'X-API-Key' => 'test-api-key',
        ]);
        
        $response->assertStatus(200);
    }

    public function test_wrong_guard_type_fails(): void
    {
        $user = User::factory()->create();
        
        // Try to access admin route with user credentials
        $this->actingAs($user, 'web');
        
        $response = $this->get('/admin');
        
        // Should redirect to admin login (not authenticated on admin guard)
        $response->assertRedirect('/admin/login');
    }
}
```

## Guard Selection Diagram

```
┌─────────────────────────────────────────────────────────┐
│                    Incoming Request                      │
└─────────────────────────┬───────────────────────────────┘
                          │
              ┌───────────┼───────────┐
              │           │           │
      ┌───────▼───┐  ┌────▼────┐  ┌───▼───────┐
      │  /web/*   │  │ /api/*  │  │ /admin/*  │
      └─────┬─────┘  └────┬────┘  └─────┬─────┘
            │             │             │
      ┌─────▼─────┐  ┌────▼────┐  ┌─────▼─────┐
      │  Session  │  │   JWT   │  │  Session  │
      │   Guard   │  │  Guard  │  │   Guard   │
      │   'web'   │  │  'api'  │  │  'admin'  │
      └─────┬─────┘  └────┬────┘  └─────┬─────┘
            │             │             │
      ┌─────▼─────┐  ┌────▼────┐  ┌─────▼─────┐
      │   User    │  │  User   │  │   Admin   │
      │  Provider │  │ Provider│  │  Provider │
      └─────┬─────┘  └────┬────┘  └─────┬─────┘
            │             │             │
      ┌─────▼─────┐  ┌────▼────┐  ┌─────▼─────┐
      │   users   │  │  users  │  │  admins   │
      │   table   │  │  table  │  │   table   │
      └───────────┘  └─────────┘  └───────────┘
```

## Best Practices

1. **Different Session Keys** - Use unique session keys for each session guard
2. **Separate Tables** - Consider separate tables for admins vs users
3. **Guard-Specific Permissions** - Define permissions per guard/user type
4. **Explicit Guard Usage** - Always specify guard when dealing with multiple types
5. **Test Guard Isolation** - Ensure guards don't leak authentication state

## Next Steps

- [Guards Overview](../guards/index.md) - Guard implementations
- [Providers Overview](../providers/index.md) - User providers
- [JWT Configuration](../jwt/index.md) - JWT setup
- [API Key Guard](../guards/api-key.md) - Service authentication
