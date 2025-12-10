```markdown
# Core Concepts

Understanding the core concepts of Lalaz Web will help you build robust web applications. This guide explains the fundamental building blocks.

## Overview

Lalaz Web is built around four main areas:

1. **HTTP Utilities** - Session management, cookies, and request handling
2. **Security** - CSRF protection and session fingerprinting
3. **View System** - Templating with Twig and components
4. **Middlewares** - Request/response processing pipeline

Let's explore each one.

---

## HTTP Utilities

### Session Manager

The **SessionManager** provides secure session handling with built-in protection against session hijacking.

```php
use Lalaz\Web\Http\SessionManager;

$session = new SessionManager();

// Basic operations
$session->set('user_id', 123);
$value = $session->get('user_id');
$session->delete('user_id');

// Check existence
if ($session->has('user_id')) {
    // Session has this key
}

// Flash data (one-time use)
$session->flash('success', 'Operation completed!');
$message = $session->getFlash('success'); // Returns and removes

// Regenerate session ID (after login)
$session->regenerate();

// Destroy session (logout)
$session->destroy();
```

### Cookie Policy

**CookiePolicy** provides a consistent interface for secure cookie handling:

```php
use Lalaz\Web\Http\CookiePolicy;

$cookies = new CookiePolicy();

// Set a cookie
$cookies->set('preference', 'dark_mode');

// Set with options
$cookies->set('remember', $token, [
    'expires' => time() + (86400 * 30), // 30 days
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict',
]);

// Get a cookie
$value = $cookies->get('preference');
$value = $cookies->get('missing', 'default');

// Check existence
if ($cookies->has('remember')) {
    // Cookie exists
}

// Delete a cookie
$cookies->delete('preference');
```

### Redirect Response

Create fluent redirects with **RedirectResponse**:

```php
use Lalaz\Web\Http\RedirectResponse;

// Simple redirect
return redirect('/dashboard');

// Redirect with flash data
return redirect('/posts')
    ->with('success', 'Post created!');

// Redirect back with errors
return back()
    ->withInput()
    ->withErrors(['email' => 'Invalid email']);

// Named route redirect (if supported)
return redirect()->route('user.profile', ['id' => 1]);
```

### HTTP Environment

Detect request characteristics with **HttpEnvironment**:

```php
use Lalaz\Web\Http\HttpEnvironment;

// Check connection type
HttpEnvironment::isSecure();    // HTTPS?
HttpEnvironment::isAjax();      // XMLHttpRequest?

// Get client info
HttpEnvironment::getClientIp(); // Real IP (handles proxies)
HttpEnvironment::getUserAgent();

// Check request origin
HttpEnvironment::getReferer();
HttpEnvironment::isSameOrigin();
```

### View Data Bag

Pass data between requests with **ViewDataBag**:

```php
use Lalaz\Web\Http\ViewDataBag;

$bag = new ViewDataBag();

// Store data for the next request
$bag->flash('old_input', $request->all());

// Retrieve and clear
$oldInput = $bag->pull('old_input');

// Keep data for another request
$bag->reflash();
$bag->keep(['old_input']);
```

---

## Security

### CSRF Protection

**CsrfProtection** provides stateless CSRF tokens using cookies:

```php
use Lalaz\Web\Security\CsrfProtection;

// Generate token (stored in cookie)
$token = CsrfProtection::token();

// Validate from request
if (CsrfProtection::validateToken($request->body(), $request->headers())) {
    // Token is valid
}

// Rotate token after validation
CsrfProtection::rotateToken();

// Get HTML hidden field
echo CsrfProtection::field();
// <input type="hidden" name="_token" value="...">
```

### Session Fingerprinting

**Fingerprint** prevents session hijacking by validating browser characteristics:

```php
use Lalaz\Web\Security\Fingerprint;

// Generate fingerprint for current request
$fingerprint = Fingerprint::generate();

// Validate session hasn't been stolen
if (!Fingerprint::validate($storedFingerprint)) {
    // Session may have been hijacked!
    $session->destroy();
}

// Components used for fingerprinting:
// - User-Agent
// - Accept-Language
// - Client IP (configurable)
```

### Security Headers Middleware

Add security headers automatically:

```php
use Lalaz\Web\Security\Middlewares\SecurityHeadersMiddleware;

// Default secure headers
$middleware = new SecurityHeadersMiddleware();

// Use preset profiles
$middleware = SecurityHeadersMiddleware::recommended();
$middleware = SecurityHeadersMiddleware::strict();
$middleware = SecurityHeadersMiddleware::api();
$middleware = SecurityHeadersMiddleware::minimal();

// Custom headers
$middleware = SecurityHeadersMiddleware::with([
    'X-Frame-Options' => 'DENY',
    'Content-Security-Policy' => "default-src 'self'",
]);
```

---

## View System

### Template Engine

The view system uses Twig for templating:

```php
use Lalaz\Web\View\View;
use Lalaz\Web\View\TemplateEngine;

// Render a view
return View::make('posts/show', [
    'post' => $post,
    'comments' => $comments,
]);

// Using the engine directly
$engine = TemplateEngine::getInstance();
$html = $engine->render('posts/show', $data);

// Add global data
TemplateEngine::share('user', $currentUser);
TemplateEngine::share('app_name', 'My App');
```

### View Context

Share data across all views with **ViewContext**:

```php
use Lalaz\Web\View\ViewContext;

// Set global view data
ViewContext::set('user', $currentUser);
ViewContext::set('notifications', fn() => Notification::unread());

// Get data
$user = ViewContext::get('user');

// Get all resolved data
$data = ViewContext::resolved();

// Reset context
ViewContext::reset();
```

### Error Bag

Handle validation errors elegantly:

```php
use Lalaz\Web\View\ErrorBag;

// Get the error bag
$errors = errors();

// Add errors
$errors->add('email', 'Invalid email format');
$errors->fromArray([
    'email' => ['Invalid email', 'Email is required'],
    'password' => ['Password too short'],
]);

// Check for errors
$errors->has('email');        // Has this field?
$errors->any();               // Has any errors?
$errors->isEmpty();           // No errors?

// Get errors
$errors->first('email');      // First error message
$errors->all('email');        // All errors for field
$errors->messages();          // All errors
$errors->count();             // Total error count
```

In templates:

```twig
{% if errors.any %}
    <div class="alert alert-danger">
        <ul>
        {% for field, messages in errors.messages %}
            {% for message in messages %}
                <li>{{ message }}</li>
            {% endfor %}
        {% endfor %}
        </ul>
    </div>
{% endif %}
```

### Form Builder

Generate HTML forms programmatically:

```php
use Lalaz\Web\View\FormBuilder;

$form = new FormBuilder();

// Open form
echo $form->open('/posts', 'POST', ['class' => 'form']);

// Text input
echo $form->text('title', old('title'), [
    'class' => 'form-control',
    'placeholder' => 'Enter title',
]);

// Textarea
echo $form->textarea('content', old('content'), [
    'rows' => 5,
]);

// Select dropdown
echo $form->select('category', $categories, old('category'), [
    'class' => 'form-select',
]);

// Hidden CSRF field
echo $form->csrf();

// Submit button
echo $form->submit('Create Post', ['class' => 'btn btn-primary']);

// Close form
echo $form->close();
```

### Components

Create reusable UI components:

```php
// components/Alert.php
use Lalaz\Web\View\Components\Component;

class Alert extends Component
{
    public function __construct(
        public string $type = 'info',
        public string $message = '',
        public bool $dismissible = false
    ) {}

    public function template(): string
    {
        return 'components/alert';
    }
}
```

```twig
{# components/alert.twig #}
<div class="alert alert-{{ type }}{% if dismissible %} alert-dismissible{% endif %}">
    {{ message }}
    {% if dismissible %}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    {% endif %}
</div>
```

Usage in views:

```twig
{% component 'Alert', {type: 'success', message: 'Post saved!'} %}
```

---

## Middlewares

### Method Spoofing

Enable PUT/PATCH/DELETE methods from HTML forms:

```php
use Lalaz\Web\Http\Middlewares\MethodSpoofingMiddleware;

// In middleware stack
$router->middleware(new MethodSpoofingMiddleware());
```

In forms:

```html
<form action="/posts/1" method="POST">
    <input type="hidden" name="_method" value="DELETE">
    <!-- or -->
    <input type="hidden" name="_method" value="PUT">
</form>
```

### CSRF Middleware

Validate CSRF tokens on state-changing requests:

```php
use Lalaz\Web\Security\Middlewares\CsrfMiddleware;

// Apply to all routes
$router->middleware(new CsrfMiddleware());

// Exclude certain patterns
$router->middleware(new CsrfMiddleware([
    '/api/*',
    '/webhook/*',
]));

// Factory methods
$middleware = CsrfMiddleware::excludingApi();
$middleware = CsrfMiddleware::excludingWebhooks(['/custom/*']);
```

### Security Headers Middleware

Add security headers to responses:

```php
use Lalaz\Web\Security\Middlewares\SecurityHeadersMiddleware;

// Default headers
$router->middleware(new SecurityHeadersMiddleware());

// Strict mode for high-security apps
$router->middleware(SecurityHeadersMiddleware::strict());

// API-optimized headers
$router->middleware(SecurityHeadersMiddleware::api());
```

---

## Request Flow

Here's how a typical web request flows through the system:

```
1. Request arrives
        │
        ▼
2. MethodSpoofingMiddleware runs
        │
        └─── Converts _method field to actual HTTP method
                │
                ▼
3. SecurityHeadersMiddleware runs
        │
        └─── Adds security headers to response
                │
                ▼
4. CsrfMiddleware runs
        │
        └─── Validates CSRF token for POST/PUT/PATCH/DELETE
                │
                ▼
5. SessionManager initializes
        │
        ├─── Starts session
        └─── Validates fingerprint
                │
                ▼
6. Your Controller runs
        │
        ├─── Access session data
        ├─── Process request
        └─── Return view or redirect
                │
                ▼
7. View renders
        │
        ├─── ViewContext data injected
        ├─── ErrorBag available
        └─── Flash messages retrieved
                │
                ▼
8. Response sent
```

---

## Putting It Together

Here's a complete example showing all concepts:

```php
<?php

use Lalaz\Web\View\View;
use Lalaz\Web\Http\RedirectResponse;
use Lalaz\Web\Security\Middlewares\CsrfMiddleware;
use Lalaz\Web\Security\Middlewares\SecurityHeadersMiddleware;

// === MIDDLEWARE SETUP ===
$router->middleware([
    new SecurityHeadersMiddleware(),
    new CsrfMiddleware(['/api/*']),
]);

// === ROUTES ===
$router->get('/contact', [ContactController::class, 'show']);
$router->post('/contact', [ContactController::class, 'send']);

// === CONTROLLER ===
class ContactController
{
    public function show($request, $response)
    {
        return View::make('contact/form');
    }

    public function send($request, $response)
    {
        $validator = new Validator($request->all(), [
            'name' => 'required|min:2',
            'email' => 'required|email',
            'message' => 'required|min:10',
        ]);

        if ($validator->fails()) {
            return back()
                ->withInput()
                ->withErrors($validator->errors());
        }

        // Send email...
        Mail::send('contact-form', $request->only(['name', 'email', 'message']));

        flash('success', 'Thank you for your message!');

        return redirect('/contact');
    }
}
```

```twig
{# contact/form.twig #}
{% extends 'layouts/app.twig' %}

{% block content %}
<h1>Contact Us</h1>

{% if flash('success') %}
    <div class="alert alert-success">{{ flash('success') }}</div>
{% endif %}

<form action="/contact" method="POST">
    {{ csrf_field() }}
    
    <div class="mb-3">
        <label>Name</label>
        <input type="text" name="name" value="{{ old('name') }}" 
               class="form-control {% if errors.has('name') %}is-invalid{% endif %}">
        {% if errors.has('name') %}
            <div class="invalid-feedback">{{ errors.first('name') }}</div>
        {% endif %}
    </div>
    
    <div class="mb-3">
        <label>Email</label>
        <input type="email" name="email" value="{{ old('email') }}"
               class="form-control {% if errors.has('email') %}is-invalid{% endif %}">
        {% if errors.has('email') %}
            <div class="invalid-feedback">{{ errors.first('email') }}</div>
        {% endif %}
    </div>
    
    <div class="mb-3">
        <label>Message</label>
        <textarea name="message" rows="5"
                  class="form-control {% if errors.has('message') %}is-invalid{% endif %}">{{ old('message') }}</textarea>
        {% if errors.has('message') %}
            <div class="invalid-feedback">{{ errors.first('message') }}</div>
        {% endif %}
    </div>
    
    <button type="submit" class="btn btn-primary">Send Message</button>
</form>
{% endblock %}
```

---

## Summary

| Concept | Purpose | Key Class |
|---------|---------|-----------|
| **Session** | Store user data | `SessionManager` |
| **Cookies** | Persistent client storage | `CookiePolicy` |
| **Redirect** | Navigation responses | `RedirectResponse` |
| **CSRF** | Prevent forgery attacks | `CsrfProtection`, `CsrfMiddleware` |
| **Fingerprint** | Session hijacking prevention | `Fingerprint` |
| **View** | Template rendering | `View`, `TemplateEngine` |
| **Errors** | Validation feedback | `ErrorBag` |
| **Components** | Reusable UI pieces | `Component` |

## Next Steps

- Learn about [Session Manager](./http/session-manager.md) in detail
- Set up [CSRF Protection](./security/csrf-protection.md)
- Master the [View System](./view/index.md)
- Build [Components](./view/components.md)

```
