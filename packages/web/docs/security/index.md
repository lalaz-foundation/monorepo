```markdown
# Security Overview

The Security module provides protection against common web vulnerabilities including CSRF attacks, session hijacking, and other security concerns.

---

## Components

| Component | Description |
|-----------|-------------|
| [CSRF Protection](./csrf-protection.md) | Stateless CSRF token generation and validation |
| [Session Fingerprinting](./fingerprint.md) | Session hijacking prevention |
| [CSRF Middleware](./csrf-middleware.md) | Automatic CSRF validation for routes |
| [Security Headers](./security-headers.md) | HTTP security header injection |

---

## Quick Reference

### CSRF Protection

```php
use Lalaz\Web\Security\CsrfProtection;

// Generate token (stored in cookie)
$token = CsrfProtection::token();

// Validate token
$isValid = CsrfProtection::validateToken($body, $headers);

// Get HTML field
echo CsrfProtection::field();

// Rotate token after use
CsrfProtection::rotateToken();
```

### Session Fingerprinting

```php
use Lalaz\Web\Security\Fingerprint;

// Generate fingerprint
$fingerprint = Fingerprint::generate();

// Validate session
if (!Fingerprint::validate($stored)) {
    // Possible session hijacking!
    session()->destroy();
}
```

### CSRF Middleware

```php
use Lalaz\Web\Security\Middlewares\CsrfMiddleware;

// Default (validates all POST/PUT/PATCH/DELETE)
new CsrfMiddleware();

// With exclusions
new CsrfMiddleware(['/api/*', '/webhook/*']);

// Factory methods
CsrfMiddleware::excludingApi();
CsrfMiddleware::excludingWebhooks();
```

### Security Headers

```php
use Lalaz\Web\Security\Middlewares\SecurityHeadersMiddleware;

// Default headers
new SecurityHeadersMiddleware();

// Preset profiles
SecurityHeadersMiddleware::minimal();
SecurityHeadersMiddleware::recommended();
SecurityHeadersMiddleware::strict();
SecurityHeadersMiddleware::api();

// Custom
SecurityHeadersMiddleware::with([
    'X-Frame-Options' => 'DENY',
]);
```

---

## Architecture

```
┌─────────────────────────────────────────────────┐
│               Security Module                    │
├─────────────────────────────────────────────────┤
│                                                  │
│  ┌──────────────────┐  ┌──────────────────┐    │
│  │  CsrfProtection  │  │   Fingerprint    │    │
│  │                  │  │                  │    │
│  │  - token()       │  │  - generate()    │    │
│  │  - validate()    │  │  - validate()    │    │
│  │  - field()       │  │  - hash()        │    │
│  │  - rotate()      │  │                  │    │
│  └────────┬─────────┘  └────────┬─────────┘    │
│           │                     │               │
│           └──────────┬──────────┘               │
│                      │                          │
│                      ▼                          │
│  ┌───────────────────────────────────────┐     │
│  │            Middlewares                 │     │
│  │                                        │     │
│  │  ┌──────────────┐  ┌───────────────┐  │     │
│  │  │    CSRF      │  │   Security    │  │     │
│  │  │  Middleware  │  │   Headers     │  │     │
│  │  └──────────────┘  └───────────────┘  │     │
│  │                                        │     │
│  └───────────────────────────────────────┘     │
│                                                  │
└─────────────────────────────────────────────────┘
```

---

## Security Best Practices

### 1. Always Use CSRF Protection

```twig
<form method="POST" action="/settings">
    {{ csrf_field() }}
    <!-- form fields -->
</form>
```

### 2. Enable Session Fingerprinting

```php
// config/session.php
return [
    'fingerprint' => true,
];
```

### 3. Apply Security Headers

```php
// Apply to all routes
$router->middleware(SecurityHeadersMiddleware::recommended());
```

### 4. Regenerate Sessions After Login

```php
// After successful authentication
$session->regenerate();
$session->set('user_id', $user->id);
```

### 5. Use HTTPS in Production

```php
// Force HTTPS redirect
if (!HttpEnvironment::isSecure()) {
    return redirect('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
}
```

---

## Common Attack Prevention

### CSRF (Cross-Site Request Forgery)

**Attack:** Malicious site tricks user into submitting forms.

**Prevention:**
```php
// Middleware validates all state-changing requests
$router->middleware(new CsrfMiddleware());
```

### Session Hijacking

**Attack:** Attacker steals session ID to impersonate user.

**Prevention:**
```php
// Fingerprinting validates browser characteristics
if (!Fingerprint::validate($session->get('fingerprint'))) {
    $session->destroy();
    throw new SecurityException('Session validation failed');
}
```

### Clickjacking

**Attack:** Site embedded in invisible iframe.

**Prevention:**
```php
// X-Frame-Options header
SecurityHeadersMiddleware::with([
    'X-Frame-Options' => 'DENY',
]);
```

### XSS (Cross-Site Scripting)

**Attack:** Injected scripts execute in user's browser.

**Prevention:**
```php
// CSP header restricts script sources
SecurityHeadersMiddleware::with([
    'Content-Security-Policy' => "script-src 'self'",
]);
```

---

## Middleware Configuration

### Web Application (Full Protection)

```php
$router->middleware([
    SecurityHeadersMiddleware::recommended(),
    new CsrfMiddleware(),
]);
```

### API (Stateless)

```php
$router->middleware([
    SecurityHeadersMiddleware::api(),
    // No CSRF (use Bearer tokens instead)
]);
```

### High Security Application

```php
$router->middleware([
    SecurityHeadersMiddleware::strict(),
    new CsrfMiddleware(),
]);
```

---

## See Also

- [CSRF Protection](./csrf-protection.md) — Detailed CSRF documentation
- [Fingerprint](./fingerprint.md) — Session hijacking prevention
- [HTTP Overview](../http/index.md) — Session management

```
