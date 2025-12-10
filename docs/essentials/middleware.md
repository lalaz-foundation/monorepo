# Middleware

Middleware provide a mechanism to filter and inspect HTTP requests as they enter your application, and responses before they are sent to the client. They are perfect for authentication, logging, CORS handling, and more.

## How Middleware Works

Middleware wraps around the request/response cycle. Each middleware can:
- Inspect or modify the incoming request
- Pass the request to the next handler in the chain
- Inspect or modify the outgoing response
- Short-circuit the chain and return a response immediately

```
Request → Middleware A → Middleware B → Controller → Middleware B → Middleware A → Response
```

## Creating Middleware

All middleware must implement the `MiddlewareInterface`:

```php
<?php declare(strict_types=1);

namespace App\Middleware;

use Lalaz\Web\Http\Contracts\MiddlewareInterface;
use Lalaz\Web\Http\Contracts\RequestInterface;
use Lalaz\Web\Http\Contracts\ResponseInterface;

class AuthMiddleware implements MiddlewareInterface
{
    public function handle(
        RequestInterface $request,
        ResponseInterface $response,
        callable $next
    ): mixed {
        // Check authentication
        if (!$request->header('Authorization')) {
            $response->status(401)->json(['error' => 'Unauthorized']);
            return null;
        }

        // Pass to next handler
        return $next($request, $response);
    }
}
```

### The MiddlewareInterface

```php
interface MiddlewareInterface
{
    public function handle(
        RequestInterface $request,
        ResponseInterface $response,
        callable $next
    ): mixed;
}
```

Key points:
- Call `$next($request, $response)` to pass control to the next handler
- Return the value from `$next()` to enable the return-based response pattern
- Return early (without calling `$next`) to short-circuit the chain

## Registering Middleware

### Per-Route Middleware

Apply middleware to individual routes:

```php
use App\Middleware\AuthMiddleware;
use App\Middleware\RateLimitMiddleware;

$router->get('/profile', UserController::class . '@profile', [
    AuthMiddleware::class,
]);

$router->post('/api/data', DataController::class . '@store', [
    AuthMiddleware::class,
    RateLimitMiddleware::class,
]);
```

### Group Middleware

Apply middleware to a group of routes:

```php
use App\Middleware\AuthMiddleware;
use App\Middleware\AdminMiddleware;

// Using array attributes
$router->group([
    'prefix' => '/admin',
    'middleware' => [AuthMiddleware::class, AdminMiddleware::class]
], function ($router) {
    $router->get('/dashboard', AdminController::class . '@dashboard');
    $router->get('/users', AdminController::class . '@users');
    $router->post('/settings', AdminController::class . '@settings');
});
```

### Fluent Middleware

Apply middleware after defining a group:

```php
$router->group('/api', function ($router) {
    $router->get('/users', UserController::class . '@index');
    $router->post('/users', UserController::class . '@store');
})->middleware(AuthMiddleware::class)
  ->middleware(RateLimitMiddleware::class);
```

Or apply multiple at once:

```php
$router->group('/api', function ($router) {
    $router->get('/users', UserController::class . '@index');
})->middlewares([
    AuthMiddleware::class,
    RateLimitMiddleware::class,
    CorsMiddleware::class,
]);
```

## Built-in Middleware

### RequestLoggingMiddleware

Logs HTTP requests with timing, memory usage, and status information:

```php
use Lalaz\Web\Http\Middlewares\RequestLoggingMiddleware;

$middleware = new RequestLoggingMiddleware(
    excludePaths: ['/health', '/metrics'],  // Paths to skip logging
    logHeaders: false,                       // Include request headers in logs
    logBody: false,                          // Include request body in logs
    slowThreshold: 1000,                     // Milliseconds for slow request warning
);
```

Log output example:
```
[db:mysql][write] 12.34ms select SELECT * FROM users WHERE id = ?
```

Features:
- Request duration tracking
- Memory usage tracking
- Configurable log levels by status code
- Path exclusion support
- Header/body logging (debug mode only)

### MethodSpoofingMiddleware

Allows HTML forms to use HTTP methods other than GET and POST:

```php
use Lalaz\Web\Http\Middlewares\MethodSpoofingMiddleware;

// Register globally or per-route
$router->post('/users/{id}', UserController::class . '@update', [
    MethodSpoofingMiddleware::class,
]);
```

Usage in HTML forms:

```html
<form method="POST" action="/users/5">
    <input type="hidden" name="_method" value="DELETE">
    <button>Delete User</button>
</form>
```

Or using the Twig helper:

```twig
<form method="POST" action="/users/5">
    {{ methodField('DELETE') | raw }}
    <button>Delete User</button>
</form>
```

Supported methods: `PUT`, `PATCH`, `DELETE`

## Common Middleware Examples

### Authentication Middleware

```php
<?php declare(strict_types=1);

namespace App\Middleware;

use Lalaz\Web\Http\Contracts\MiddlewareInterface;
use Lalaz\Web\Http\Contracts\RequestInterface;
use Lalaz\Web\Http\Contracts\ResponseInterface;

class AuthMiddleware implements MiddlewareInterface
{
    public function handle(
        RequestInterface $request,
        ResponseInterface $response,
        callable $next
    ): mixed {
        $token = $request->header('Authorization');

        if (!$token || !$this->validateToken($token)) {
            return $response->status(401)->json([
                'error' => 'Unauthorized',
                'message' => 'Valid authentication token required',
            ]);
        }

        // Optionally add user info to request
        // $request->setAttribute('user', $this->getUserFromToken($token));

        return $next($request, $response);
    }

    private function validateToken(string $token): bool
    {
        // Your token validation logic
        return str_starts_with($token, 'Bearer ');
    }
}
```

### CORS Middleware

```php
<?php declare(strict_types=1);

namespace App\Middleware;

use Lalaz\Web\Http\Contracts\MiddlewareInterface;
use Lalaz\Web\Http\Contracts\RequestInterface;
use Lalaz\Web\Http\Contracts\ResponseInterface;

class CorsMiddleware implements MiddlewareInterface
{
    private array $allowedOrigins = ['https://example.com'];
    private array $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
    private array $allowedHeaders = ['Content-Type', 'Authorization'];

    public function handle(
        RequestInterface $request,
        ResponseInterface $response,
        callable $next
    ): mixed {
        $origin = $request->header('Origin');

        // Handle preflight requests
        if ($request->method() === 'OPTIONS') {
            return $this->handlePreflight($response, $origin);
        }

        // Process the request
        $result = $next($request, $response);

        // Add CORS headers to response
        if ($this->isAllowedOrigin($origin)) {
            $response->header('Access-Control-Allow-Origin', $origin);
            $response->header('Access-Control-Allow-Credentials', 'true');
        }

        return $result;
    }

    private function handlePreflight(ResponseInterface $response, ?string $origin): mixed
    {
        if ($this->isAllowedOrigin($origin)) {
            $response->header('Access-Control-Allow-Origin', $origin);
            $response->header('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods));
            $response->header('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders));
            $response->header('Access-Control-Max-Age', '86400');
        }

        return $response->status(204);
    }

    private function isAllowedOrigin(?string $origin): bool
    {
        return $origin !== null && in_array($origin, $this->allowedOrigins, true);
    }
}
```

### Rate Limiting Middleware

```php
<?php declare(strict_types=1);

namespace App\Middleware;

use Lalaz\Web\Http\Contracts\MiddlewareInterface;
use Lalaz\Web\Http\Contracts\RequestInterface;
use Lalaz\Web\Http\Contracts\ResponseInterface;

class RateLimitMiddleware implements MiddlewareInterface
{
    private int $maxRequests = 60;
    private int $windowSeconds = 60;

    public function handle(
        RequestInterface $request,
        ResponseInterface $response,
        callable $next
    ): mixed {
        $key = $this->resolveKey($request);
        $remaining = $this->getRemainingRequests($key);

        if ($remaining <= 0) {
            return $response->status(429)->json([
                'error' => 'Too Many Requests',
                'message' => 'Rate limit exceeded. Try again later.',
            ]);
        }

        $this->incrementCounter($key);

        // Add rate limit headers
        $response->header('X-RateLimit-Limit', (string) $this->maxRequests);
        $response->header('X-RateLimit-Remaining', (string) ($remaining - 1));

        return $next($request, $response);
    }

    private function resolveKey(RequestInterface $request): string
    {
        // Use IP or authenticated user ID
        return 'rate_limit:' . ($request->ip() ?? 'unknown');
    }

    private function getRemainingRequests(string $key): int
    {
        // Implement using cache or database
        return $this->maxRequests;
    }

    private function incrementCounter(string $key): void
    {
        // Implement counter logic
    }
}
```

### Maintenance Mode Middleware

```php
<?php declare(strict_types=1);

namespace App\Middleware;

use Lalaz\Web\Http\Contracts\MiddlewareInterface;
use Lalaz\Web\Http\Contracts\RequestInterface;
use Lalaz\Web\Http\Contracts\ResponseInterface;

class MaintenanceMiddleware implements MiddlewareInterface
{
    private array $allowedIps = ['127.0.0.1'];

    public function handle(
        RequestInterface $request,
        ResponseInterface $response,
        callable $next
    ): mixed {
        if ($this->isInMaintenance() && !$this->isAllowed($request)) {
            return $response->status(503)->json([
                'error' => 'Service Unavailable',
                'message' => 'The application is under maintenance.',
            ]);
        }

        return $next($request, $response);
    }

    private function isInMaintenance(): bool
    {
        return env('APP_MAINTENANCE', false);
    }

    private function isAllowed(RequestInterface $request): bool
    {
        return in_array($request->ip(), $this->allowedIps, true);
    }
}
```

## Middleware Execution Order

Middleware execute in the order they are registered. For groups:

```php
$router->group([
    'prefix' => '/api',
    'middleware' => [LoggingMiddleware::class, AuthMiddleware::class]
], function ($router) {
    $router->get('/users', UserController::class . '@index', [
        CacheMiddleware::class,
    ]);
});
```

Execution order for `GET /api/users`:
1. `LoggingMiddleware` (group)
2. `AuthMiddleware` (group)
3. `CacheMiddleware` (route)
4. Controller action
5. `CacheMiddleware` (response)
6. `AuthMiddleware` (response)
7. `LoggingMiddleware` (response)

## Middleware Best Practices

### Keep Middleware Focused

Each middleware should do one thing well:

```php
// ✅ Good: Single responsibility
class AuthMiddleware implements MiddlewareInterface { /* auth only */ }
class LoggingMiddleware implements MiddlewareInterface { /* logging only */ }

// ❌ Bad: Multiple responsibilities
class DoEverythingMiddleware implements MiddlewareInterface {
    public function handle($req, $res, $next): mixed
    {
        // Auth + logging + rate limiting + CORS...
    }
}
```

### Use Dependency Injection

Inject dependencies through the constructor:

```php
class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private TokenValidator $validator,
        private UserRepository $users,
    ) {}

    public function handle($req, $res, $next): mixed
    {
        $user = $this->validator->validate($req->header('Authorization'));
        // ...
    }
}
```

### Handle Exceptions Gracefully

Wrap potentially failing operations:

```php
public function handle($req, $res, $next): mixed
{
    try {
        // Your middleware logic
        return $next($req, $res);
    } catch (AuthenticationException $e) {
        return $res->status(401)->json(['error' => $e->getMessage()]);
    } catch (\Throwable $e) {
        // Log and re-throw or handle gracefully
        throw $e;
    }
}
```

### Use Route Attributes

For controller-level middleware, use the `#[Route]` attribute:

```php
use Lalaz\Web\Routing\Attribute\Route;

class AdminController
{
    #[Route(path: '/admin/dashboard', method: 'GET', middlewares: [AdminMiddleware::class])]
    public function dashboard(): array
    {
        return ['admin' => 'dashboard'];
    }
}
```
