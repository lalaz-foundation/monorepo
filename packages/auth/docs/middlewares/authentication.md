# AuthenticationMiddleware

The `AuthenticationMiddleware` verifies that users are authenticated before accessing protected routes.

## Quick Start

```php
use Lalaz\Auth\Middlewares\AuthenticationMiddleware;

// Web routes (session-based)
$router->middleware(AuthenticationMiddleware::web('/login'));

// API routes (JWT-based)
$router->middleware(AuthenticationMiddleware::jwt());

// API Key routes
$router->middleware(AuthenticationMiddleware::apiKey());
```

## Factory Methods

### web()

For session-based authentication with redirect on failure:

```php
public static function web(string $redirectTo = '/login'): self
```

```php
// Redirect to /login
AuthenticationMiddleware::web('/login');

// Redirect to custom URL
AuthenticationMiddleware::web('/auth/signin');

// For admin area
AuthenticationMiddleware::web('/admin/login');
```

### session()

Alias for `web()` with explicit guard:

```php
public static function session(
    string $guard = 'web',
    string $redirectTo = '/login'
): self
```

```php
// Explicit guard and redirect
AuthenticationMiddleware::session('web', '/login');
AuthenticationMiddleware::session('admin', '/admin/login');
```

### jwt()

For JWT-based authentication (returns 401 JSON on failure):

```php
public static function jwt(string $guard = 'api'): self
```

```php
// Default API guard
AuthenticationMiddleware::jwt();

// Custom guard
AuthenticationMiddleware::jwt('mobile-api');
```

### apiKey()

For API key authentication:

```php
public static function apiKey(string $guard = 'api-key'): self
```

```php
// Default API key guard
AuthenticationMiddleware::apiKey();

// Custom guard
AuthenticationMiddleware::apiKey('external');
```

### forGuard()

For any custom guard:

```php
public static function forGuard(
    string $guard,
    ?string $redirectTo = null
): self
```

```php
// Custom guard with redirect
AuthenticationMiddleware::forGuard('custom', '/custom/login');

// Custom guard without redirect (returns 401)
AuthenticationMiddleware::forGuard('custom');
```

## How It Works

```
┌─────────────────────────────────────────────────────┐
│                Request Arrives                       │
└─────────────────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────┐
│            AuthenticationMiddleware                  │
│                                                      │
│  1. Get configured guard (web, api, api-key)        │
│  2. Check if user is authenticated via guard        │
│  3. If authenticated:                               │
│     - Store user in AuthContext                     │
│     - Continue to next middleware/controller        │
│  4. If not authenticated:                           │
│     - Web: Redirect to login page                   │
│     - API: Return 401 JSON response                 │
└─────────────────────────────────────────────────────┘
```

## Usage Examples

### Protecting Web Routes

```php
// routes/web.php

// Single route
$router->get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(AuthenticationMiddleware::web('/login'));

// Route group
$router->group('/account', function ($router) {
    $router->get('/profile', [ProfileController::class, 'show']);
    $router->get('/settings', [SettingsController::class, 'show']);
    $router->put('/profile', [ProfileController::class, 'update']);
})->middleware(AuthenticationMiddleware::web('/login'));
```

### Protecting API Routes

```php
// routes/api.php

$router->group('/api/v1', function ($router) {
    // Public endpoints
    $router->post('/auth/login', [AuthController::class, 'login']);
    $router->post('/auth/register', [AuthController::class, 'register']);
});

$router->group('/api/v1', function ($router) {
    // Protected endpoints
    $router->get('/user', [UserController::class, 'show']);
    $router->put('/user', [UserController::class, 'update']);
    $router->post('/auth/logout', [AuthController::class, 'logout']);
})->middleware(AuthenticationMiddleware::jwt());
```

### Protecting Webhook Routes

```php
// routes/webhooks.php

$router->group('/webhooks', function ($router) {
    $router->post('/stripe', [WebhookController::class, 'stripe']);
    $router->post('/github', [WebhookController::class, 'github']);
    $router->post('/slack', [WebhookController::class, 'slack']);
})->middleware(AuthenticationMiddleware::apiKey());
```

### Mixed Authentication

```php
// Public routes
$router->get('/', [HomeController::class, 'index']);
$router->get('/products', [ProductController::class, 'index']);

// Web routes (session)
$router->group('/account', function ($router) {
    $router->get('/orders', [OrderController::class, 'index']);
})->middleware(AuthenticationMiddleware::web('/login'));

// Admin routes (separate session)
$router->group('/admin', function ($router) {
    $router->get('/dashboard', [AdminController::class, 'dashboard']);
})->middleware(AuthenticationMiddleware::session('admin', '/admin/login'));

// API routes (JWT)
$router->group('/api', function ($router) {
    $router->get('/orders', [ApiOrderController::class, 'index']);
})->middleware(AuthenticationMiddleware::jwt());
```

## Response Behavior

### Web Routes (Redirect)

When authentication fails on web routes:

```php
// Middleware configured with redirect
AuthenticationMiddleware::web('/login');

// Unauthenticated request to /dashboard
// Result: HTTP 302 redirect to /login
```

**Storing intended URL:**

```php
class AuthenticationMiddleware
{
    public function handle($request, $next)
    {
        if (!$this->guard->check()) {
            // Store the intended URL
            $request->session()->set('intended_url', $request->url());
            
            return response()->redirect($this->redirectTo);
        }
        
        return $next($request);
    }
}
```

**Using intended URL after login:**

```php
class LoginController
{
    public function login($request, $response)
    {
        // ... authenticate user ...
        
        // Redirect to intended URL or default
        $intended = $request->session()->get('intended_url', '/dashboard');
        $request->session()->remove('intended_url');
        
        return $response->redirect($intended);
    }
}
```

### API Routes (JSON 401)

When authentication fails on API routes:

```php
// Middleware configured without redirect
AuthenticationMiddleware::jwt();

// Unauthenticated request to /api/user
// Result: HTTP 401 with JSON body
```

```json
{
    "error": "Unauthenticated",
    "message": "Authentication required"
}
```

## Accessing the Authenticated User

After successful authentication:

### In Controllers

```php
class DashboardController
{
    public function index($request, $response)
    {
        // Using helper function
        $user = user();
        
        // Using AuthContext
        $context = auth_context();
        $user = $context->user();
        
        // Using specific guard
        $user = user('api');
        
        return $response->view('dashboard', [
            'user' => $user,
        ]);
    }
}
```

### In Views

```php
<!-- dashboard.php -->
<h1>Welcome, <?= $user->name ?></h1>

<?php if (auth_context()->hasRole('admin')): ?>
    <a href="/admin">Admin Panel</a>
<?php endif; ?>
```

## Custom Middleware

### Extend for Additional Checks

```php
<?php

namespace App\Middleware;

use Lalaz\Auth\Middlewares\AuthenticationMiddleware;
use Lalaz\Auth\AuthContext;

class VerifiedUserMiddleware extends AuthenticationMiddleware
{
    public function handle($request, callable $next)
    {
        // First, run parent authentication
        $response = parent::handle($request, function ($req) use ($next) {
            // Then check if email is verified
            $user = auth_context()->user();
            
            if (!$user->isVerified()) {
                if ($this->redirectTo) {
                    return response()->redirect('/verify-email');
                }
                
                return response()->json([
                    'error' => 'Email not verified',
                    'message' => 'Please verify your email address',
                ], 403);
            }
            
            return $next($req);
        });
        
        return $response;
    }
}
```

### Custom Response Format

```php
class CustomAuthMiddleware extends AuthenticationMiddleware
{
    protected function respondToUnauthenticated($request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Please provide valid authentication credentials',
                ],
            ], 401);
        }
        
        // Flash message for web
        session()->flash('error', 'Please log in to continue');
        
        return response()->redirect($this->redirectTo);
    }
}
```

## Combining with Other Middleware

```php
// Authentication + Authorization
$router->group('/admin', function ($router) {
    $router->get('/', [AdminController::class, 'index']);
})->middleware([
    AuthenticationMiddleware::web('/login'),
    AuthorizationMiddleware::requireRoles('admin'),
]);

// Authentication + Rate Limiting
$router->group('/api', function ($router) {
    $router->post('/sensitive', [ApiController::class, 'sensitive']);
})->middleware([
    AuthenticationMiddleware::jwt(),
    RateLimitMiddleware::perMinute(10),
]);

// Authentication + CORS
$router->group('/api', function ($router) {
    // ...
})->middleware([
    CorsMiddleware::class,
    AuthenticationMiddleware::jwt(),
]);
```

## Configuration

### Guards in config/auth.php

```php
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
        'api-key' => [
            'driver' => 'api-key',
            'provider' => 'api_clients',
        ],
    ],
];
```

## Testing

### Unit Tests

```php
use PHPUnit\Framework\TestCase;
use Lalaz\Auth\Middlewares\AuthenticationMiddleware;

class AuthenticationMiddlewareTest extends TestCase
{
    public function test_authenticated_request_passes(): void
    {
        // Setup authenticated guard
        $guard = $this->createMock(GuardInterface::class);
        $guard->method('check')->willReturn(true);
        $guard->method('user')->willReturn($this->createUser());
        
        $middleware = new AuthenticationMiddleware($guard, $context, '/login');
        
        $called = false;
        $next = function ($request) use (&$called) {
            $called = true;
            return response();
        };
        
        $middleware->handle($request, $next);
        
        $this->assertTrue($called);
    }

    public function test_unauthenticated_request_redirects(): void
    {
        $guard = $this->createMock(GuardInterface::class);
        $guard->method('check')->willReturn(false);
        
        $middleware = new AuthenticationMiddleware($guard, $context, '/login');
        
        $response = $middleware->handle($request, $next);
        
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/login', $response->getHeader('Location'));
    }

    public function test_api_request_returns_401(): void
    {
        $guard = $this->createMock(GuardInterface::class);
        $guard->method('check')->willReturn(false);
        
        $middleware = new AuthenticationMiddleware($guard, $context, null);
        
        $response = $middleware->handle($apiRequest, $next);
        
        $this->assertEquals(401, $response->getStatusCode());
    }
}
```

### Integration Tests

```php
class AuthenticationMiddlewareIntegrationTest extends TestCase
{
    public function test_web_auth_redirects_guest(): void
    {
        $response = $this->get('/dashboard');
        
        $response->assertRedirect('/login');
    }

    public function test_web_auth_allows_authenticated(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        
        $response = $this->get('/dashboard');
        
        $response->assertOk();
    }

    public function test_api_auth_returns_401(): void
    {
        $response = $this->getJson('/api/user');
        
        $response->assertUnauthorized();
        $response->assertJson(['error' => 'Unauthenticated']);
    }

    public function test_api_auth_allows_with_token(): void
    {
        $user = User::factory()->create();
        $token = $this->createToken($user);
        
        $response = $this->getJson('/api/user', [
            'Authorization' => "Bearer {$token}",
        ]);
        
        $response->assertOk();
    }
}
```

## Troubleshooting

### User Not Being Set

**Problem:** `user()` returns null after middleware.

**Solutions:**
1. Check middleware is actually running (add debug log)
2. Verify guard configuration matches middleware
3. Check session/token is valid

```php
// Debug in middleware
public function handle($request, $next)
{
    error_log('Auth middleware running');
    error_log('Guard check: ' . ($this->guard->check() ? 'true' : 'false'));
    
    // ...
}
```

### Redirect Loop

**Problem:** Infinite redirect between protected page and login.

**Solutions:**
1. Ensure login page is not protected
2. Check login actually creates session
3. Verify session is persisting

```php
// Make sure login route is not protected
$router->get('/login', [AuthController::class, 'showLogin']);
// NOT: ->middleware(AuthenticationMiddleware::web('/login'))
```

### JWT Not Being Read

**Problem:** API always returns 401.

**Solutions:**
1. Check Authorization header format
2. Verify JWT secret matches
3. Check token expiration

```bash
# Debug request
curl -v https://api.example.com/user \
  -H "Authorization: Bearer your-token-here"
```

## Next Steps

- Add [AuthorizationMiddleware](./authorization.md) for role checks
- Add [PermissionMiddleware](./permission.md) for permission checks
- Configure [Guards](../guards/index.md)
