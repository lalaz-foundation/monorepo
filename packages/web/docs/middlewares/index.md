```markdown
# Middlewares Overview

Middlewares process HTTP requests before they reach your controllers and can modify responses before they're sent to the client.

---

## Available Middlewares

| Middleware | Description |
|------------|-------------|
| [Method Spoofing](./method-spoofing.md) | Enable PUT/PATCH/DELETE from HTML forms |
| [CSRF Middleware](./csrf.md) | Validate CSRF tokens on state-changing requests |
| [Security Headers](./security-headers.md) | Add security headers to responses |

---

## How Middlewares Work

Middlewares form a pipeline that requests pass through:

```
Request
    │
    ▼
┌─────────────────────┐
│  Middleware 1       │
│  (Security Headers) │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│  Middleware 2       │
│  (Method Spoofing)  │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│  Middleware 3       │
│  (CSRF)             │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│    Controller       │
└──────────┬──────────┘
           │
           ▼
Response (passes back through middlewares)
```

---

## Quick Reference

### Method Spoofing

Enables PUT, PATCH, and DELETE methods from HTML forms:

```php
use Lalaz\Web\Http\Middlewares\MethodSpoofingMiddleware;

$router->middleware(new MethodSpoofingMiddleware());
```

```html
<form method="POST" action="/posts/1">
    <input type="hidden" name="_method" value="DELETE">
    <button type="submit">Delete</button>
</form>
```

### CSRF Protection

Validates CSRF tokens on POST, PUT, PATCH, DELETE requests:

```php
use Lalaz\Web\Security\Middlewares\CsrfMiddleware;

// Default (no exclusions)
$router->middleware(new CsrfMiddleware());

// With route exclusions
$router->middleware(new CsrfMiddleware([
    '/api/*',
    '/webhook/*',
]));

// Factory methods
$router->middleware(CsrfMiddleware::excludingApi());
$router->middleware(CsrfMiddleware::excludingWebhooks());
```

### Security Headers

Adds security headers to all responses:

```php
use Lalaz\Web\Security\Middlewares\SecurityHeadersMiddleware;

// Default headers
$router->middleware(new SecurityHeadersMiddleware());

// Presets
$router->middleware(SecurityHeadersMiddleware::minimal());
$router->middleware(SecurityHeadersMiddleware::recommended());
$router->middleware(SecurityHeadersMiddleware::strict());
$router->middleware(SecurityHeadersMiddleware::api());

// Custom
$router->middleware(SecurityHeadersMiddleware::with([
    'X-Frame-Options' => 'DENY',
    'Content-Security-Policy' => "default-src 'self'",
]));
```

---

## Registering Middlewares

### Global Middlewares

Apply to all routes:

```php
// In your application bootstrap or kernel

$globalMiddleware = [
    new SecurityHeadersMiddleware(),
    new MethodSpoofingMiddleware(),
];

foreach ($globalMiddleware as $middleware) {
    $router->middleware($middleware);
}
```

### Route Group Middlewares

Apply to a group of routes:

```php
$router->group('/admin', function ($router) {
    $router->get('/dashboard', [AdminController::class, 'dashboard']);
    $router->get('/users', [AdminController::class, 'users']);
})->middleware([
    new CsrfMiddleware(),
    AuthenticationMiddleware::web('/login'),
]);
```

### Single Route Middleware

Apply to specific routes:

```php
$router->post('/contact', [ContactController::class, 'send'])
    ->middleware(new CsrfMiddleware());
```

---

## Middleware Interface

All middlewares implement `MiddlewareInterface`:

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

### Creating Custom Middleware

```php
<?php

namespace App\Http\Middlewares;

use Lalaz\Web\Http\Contracts\MiddlewareInterface;
use Lalaz\Web\Http\Contracts\RequestInterface;
use Lalaz\Web\Http\Contracts\ResponseInterface;

class LoggingMiddleware implements MiddlewareInterface
{
    public function handle(
        RequestInterface $request,
        ResponseInterface $response,
        callable $next
    ): mixed {
        // Before controller
        $start = microtime(true);
        
        // Call next middleware/controller
        $result = $next($request, $response);
        
        // After controller
        $duration = microtime(true) - $start;
        logger()->info('Request processed', [
            'method' => $request->method(),
            'path' => $request->path(),
            'duration' => $duration,
        ]);
        
        return $result;
    }
}
```

---

## Recommended Middleware Stack

### Web Application

```php
$router->middleware([
    // 1. Security headers first
    SecurityHeadersMiddleware::recommended(),
    
    // 2. Method spoofing for forms
    new MethodSpoofingMiddleware(),
    
    // 3. CSRF protection (exclude API)
    new CsrfMiddleware(['/api/*']),
]);
```

### API Application

```php
$router->middleware([
    // Security headers for APIs
    SecurityHeadersMiddleware::api(),
    
    // No CSRF (use Bearer tokens)
    // No method spoofing (APIs use proper methods)
]);
```

### High Security Application

```php
$router->middleware([
    // Strict security headers with HSTS
    SecurityHeadersMiddleware::strict(),
    
    // Method spoofing
    new MethodSpoofingMiddleware(),
    
    // CSRF with no exclusions
    new CsrfMiddleware(),
]);
```

---

## Order Matters

Middleware order is important:

1. **Security Headers** - Should be first to ensure headers are always set
2. **Method Spoofing** - Must run before routing to set correct method
3. **CSRF** - Should run after method is determined
4. **Authentication** - After CSRF to ensure valid request
5. **Authorization** - After authentication to check permissions

```php
// Correct order
$router->middleware([
    new SecurityHeadersMiddleware(),     // 1
    new MethodSpoofingMiddleware(),      // 2
    new CsrfMiddleware(),                // 3
    AuthenticationMiddleware::web(),     // 4
    AuthorizationMiddleware::roles(),    // 5
]);
```

---

## See Also

- [CSRF Protection](../security/csrf-protection.md) — Understanding CSRF
- [Security Headers](../security/security-headers.md) — Header details
- [HTTP Overview](../http/index.md) — HTTP utilities

```
