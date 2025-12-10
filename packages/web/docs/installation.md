```markdown
# Installation

This guide walks you through installing and configuring the Lalaz Web package.

---

## Requirements

- PHP 8.2 or higher
- Lalaz Framework 1.0+
- Composer 2.0+

---

## Installation

### Via Package Manager (Recommended)

```bash
php lalaz package:add lalaz/web
```

### Via Composer

```bash
composer require lalaz/web
```

---

## Register the Service Provider

If not auto-discovered, add the service provider to `config/app.php`:

```php
<?php

return [
    'providers' => [
        // ... other providers
        Lalaz\Web\WebServiceProvider::class,
    ],
];
```

---

## Configuration

### Session Configuration

Create or edit `config/session.php`:

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Session Name
    |--------------------------------------------------------------------------
    |
    | The name of the session cookie.
    |
    */
    'name' => env('SESSION_NAME', 'lalaz_session'),

    /*
    |--------------------------------------------------------------------------
    | Session Lifetime
    |--------------------------------------------------------------------------
    |
    | Session lifetime in minutes. After this, the session expires.
    |
    */
    'lifetime' => env('SESSION_LIFETIME', 120),

    /*
    |--------------------------------------------------------------------------
    | Session Path
    |--------------------------------------------------------------------------
    |
    | The path for which the session cookie is available.
    |
    */
    'path' => '/',

    /*
    |--------------------------------------------------------------------------
    | Session Domain
    |--------------------------------------------------------------------------
    |
    | The domain for the session cookie. Null uses the current domain.
    |
    */
    'domain' => env('SESSION_DOMAIN', null),

    /*
    |--------------------------------------------------------------------------
    | Secure Cookies
    |--------------------------------------------------------------------------
    |
    | If true, cookies are only sent over HTTPS connections.
    |
    */
    'secure' => env('SESSION_SECURE', true),

    /*
    |--------------------------------------------------------------------------
    | HTTP Only
    |--------------------------------------------------------------------------
    |
    | If true, cookies are not accessible via JavaScript.
    |
    */
    'httponly' => true,

    /*
    |--------------------------------------------------------------------------
    | Same-Site Policy
    |--------------------------------------------------------------------------
    |
    | Controls when cookies are sent with cross-site requests.
    | Options: 'Lax', 'Strict', 'None'
    |
    */
    'samesite' => 'Lax',

    /*
    |--------------------------------------------------------------------------
    | Fingerprint Validation
    |--------------------------------------------------------------------------
    |
    | If true, validates session fingerprint to prevent hijacking.
    |
    */
    'fingerprint' => true,
];
```

### Security Headers Configuration

Create or edit `config/security.php`:

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Security Headers
    |--------------------------------------------------------------------------
    |
    | Configure the security headers added to responses.
    |
    */
    'headers' => [
        'frame_options' => 'SAMEORIGIN',
        'content_type_options' => 'nosniff',
        'xss_protection' => '1; mode=block',
        'referrer_policy' => 'strict-origin-when-cross-origin',
        'permissions_policy' => 'geolocation=(), microphone=(), camera=()',
    ],

    /*
    |--------------------------------------------------------------------------
    | HSTS (HTTP Strict Transport Security)
    |--------------------------------------------------------------------------
    |
    | Force browsers to use HTTPS.
    |
    */
    'hsts' => [
        'enabled' => env('HSTS_ENABLED', false),
        'value' => 'max-age=31536000; includeSubDomains',
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy
    |--------------------------------------------------------------------------
    |
    | Define allowed sources for scripts, styles, images, etc.
    |
    */
    'csp' => env('SECURITY_CSP', null),
];
```

### View Configuration

Create or edit `config/view.php`:

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | View Paths
    |--------------------------------------------------------------------------
    |
    | Paths where views are stored.
    |
    */
    'paths' => [
        resource_path('views'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Compiled View Path
    |--------------------------------------------------------------------------
    |
    | Where compiled Twig templates are cached.
    |
    */
    'compiled' => storage_path('framework/views'),

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Enable template caching in production.
    |
    */
    'cache' => env('VIEW_CACHE', false),

    /*
    |--------------------------------------------------------------------------
    | Debug
    |--------------------------------------------------------------------------
    |
    | Enable Twig debug mode.
    |
    */
    'debug' => env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Components Namespace
    |--------------------------------------------------------------------------
    |
    | The namespace where components are located.
    |
    */
    'components' => [
        'namespace' => 'App\\View\\Components',
        'path' => resource_path('views/components'),
    ],
];
```

---

## Environment Variables

Add these to your `.env` file:

```env
# Session
SESSION_NAME=lalaz_session
SESSION_LIFETIME=120
SESSION_DOMAIN=null
SESSION_SECURE=true

# Security
HSTS_ENABLED=false
SECURITY_CSP=

# Views
VIEW_CACHE=false
APP_DEBUG=true
```

---

## Directory Structure

After installation, ensure this structure exists:

```
your-project/
├── config/
│   ├── session.php
│   ├── security.php
│   └── view.php
├── resources/
│   └── views/
│       ├── layouts/
│       │   └── app.twig
│       └── components/
├── storage/
│   └── framework/
│       └── views/    # Compiled templates
```

---

## Middleware Setup

Register the middleware in your application:

```php
<?php
// app/Http/Kernel.php or routes/middleware.php

use Lalaz\Web\Http\Middlewares\MethodSpoofingMiddleware;
use Lalaz\Web\Security\Middlewares\CsrfMiddleware;
use Lalaz\Web\Security\Middlewares\SecurityHeadersMiddleware;

// Global middleware (runs on every request)
$middleware = [
    SecurityHeadersMiddleware::class,
    MethodSpoofingMiddleware::class,
];

// Web middleware group
$middlewareGroups = [
    'web' => [
        new CsrfMiddleware(['/api/*']),
    ],
];
```

Or in your routes:

```php
<?php
// routes/web.php

$router->middleware([
    new SecurityHeadersMiddleware(),
    new CsrfMiddleware(),
]);

$router->get('/', [HomeController::class, 'index']);
```

---

## Verify Installation

Create a test route to verify everything works:

```php
<?php
// routes/web.php

$router->get('/web-test', function ($request, $response) {
    // Test session
    session()->set('test', 'Hello, World!');
    $value = session()->get('test');

    // Test CSRF
    $token = csrf_token();

    // Test view
    return view('test', [
        'session_value' => $value,
        'csrf_token' => $token,
    ]);
});
```

Create the test view:

```twig
{# resources/views/test.twig #}
<!DOCTYPE html>
<html>
<head>
    <title>Web Package Test</title>
</head>
<body>
    <h1>Lalaz Web Package Test</h1>
    
    <h2>Session</h2>
    <p>Session value: {{ session_value }}</p>
    
    <h2>CSRF</h2>
    <p>Token: {{ csrf_token }}</p>
    <form method="POST">
        {{ csrf_field() }}
        <button type="submit">Test CSRF</button>
    </form>
    
    <h2>Flash Messages</h2>
    {% if flash('test') %}
        <p>Flash: {{ flash('test') }}</p>
    {% else %}
        <p>No flash message</p>
    {% endif %}
    
    <p style="color: green;">✓ Everything is working!</p>
</body>
</html>
```

Visit `/web-test` in your browser. You should see all values displayed correctly.

---

## Next Steps

- Read [Core Concepts](./concepts.md) to understand the architecture
- Set up [CSRF Protection](./security/csrf-protection.md) for forms
- Learn about [Session Management](./http/session-manager.md)
- Configure [Security Headers](./security/security-headers.md)

---

<p align="center">
  <a href="./quick-start.md">Quick Start →</a>
</p>

```
