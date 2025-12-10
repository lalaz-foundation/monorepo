```markdown
# API Reference

Complete reference for all classes and methods in the Lalaz Web package.

---

## HTTP Module

### SessionManager

Manages server-side sessions with security features.

```php
use Lalaz\Web\Http\SessionManager;
```

#### Methods

| Method | Description | Return |
|--------|-------------|--------|
| `start()` | Start the session | `void` |
| `set(string $key, mixed $value)` | Set a session value | `void` |
| `get(string $key, mixed $default = null)` | Get a session value | `mixed` |
| `has(string $key)` | Check if key exists | `bool` |
| `delete(string $key)` | Remove a session value | `void` |
| `all()` | Get all session data | `array` |
| `flash(string $key, mixed $value)` | Set flash data | `void` |
| `getFlash(string $key, mixed $default = null)` | Get and remove flash data | `mixed` |
| `regenerate(bool $deleteOld = true)` | Regenerate session ID | `bool` |
| `destroy()` | Destroy the session | `void` |
| `id()` | Get session ID | `string` |

---

### CookiePolicy

Manages HTTP cookies with secure defaults.

```php
use Lalaz\Web\Http\CookiePolicy;
```

#### Methods

| Method | Description | Return |
|--------|-------------|--------|
| `set(string $name, string $value, array $options = [])` | Set a cookie | `bool` |
| `get(string $name, mixed $default = null)` | Get a cookie value | `mixed` |
| `has(string $name)` | Check if cookie exists | `bool` |
| `delete(string $name)` | Delete a cookie | `bool` |
| `all()` | Get all cookies | `array` |

#### Cookie Options

```php
$cookies->set('name', 'value', [
    'expires' => time() + 3600,  // Timestamp
    'path' => '/',               // Cookie path
    'domain' => '',              // Cookie domain
    'secure' => true,            // HTTPS only
    'httponly' => true,          // No JS access
    'samesite' => 'Lax',         // Lax|Strict|None
]);
```

---

### RedirectResponse

Fluent interface for creating redirects.

```php
use Lalaz\Web\Http\RedirectResponse;
```

#### Methods

| Method | Description | Return |
|--------|-------------|--------|
| `to(string $url)` | Set redirect URL | `self` |
| `with(string $key, mixed $value)` | Add flash data | `self` |
| `withInput(array $input = null)` | Preserve form input | `self` |
| `withErrors(array $errors)` | Add validation errors | `self` |
| `back()` | Redirect to previous URL | `self` |
| `send()` | Send the redirect response | `void` |

#### Factory Functions

```php
redirect(string $url = ''): RedirectResponse
back(): RedirectResponse
```

---

### HttpEnvironment

Detects HTTP request characteristics.

```php
use Lalaz\Web\Http\HttpEnvironment;
```

#### Static Methods

| Method | Description | Return |
|--------|-------------|--------|
| `isSecure()` | Check if HTTPS | `bool` |
| `isAjax()` | Check if XMLHttpRequest | `bool` |
| `getClientIp()` | Get client IP address | `string` |
| `getUserAgent()` | Get user agent string | `string` |
| `getReferer()` | Get referer URL | `?string` |
| `isSameOrigin()` | Check if same origin request | `bool` |
| `getHost()` | Get request host | `string` |
| `getScheme()` | Get HTTP or HTTPS | `string` |

---

### ViewDataBag

Stores data for passing between requests.

```php
use Lalaz\Web\Http\ViewDataBag;
```

#### Methods

| Method | Description | Return |
|--------|-------------|--------|
| `set(string $key, mixed $value)` | Set a value | `void` |
| `get(string $key, mixed $default = null)` | Get a value | `mixed` |
| `has(string $key)` | Check if key exists | `bool` |
| `flash(string $key, mixed $value)` | Set flash data | `void` |
| `pull(string $key, mixed $default = null)` | Get and remove | `mixed` |
| `reflash()` | Keep all flash data | `void` |
| `keep(array $keys)` | Keep specific flash data | `void` |

---

## Security Module

### CsrfProtection

Stateless CSRF protection using cookies.

```php
use Lalaz\Web\Security\CsrfProtection;
```

#### Static Methods

| Method | Description | Return |
|--------|-------------|--------|
| `token()` | Get or generate CSRF token | `string` |
| `validateToken(array\|object\|null $body, array $headers)` | Validate token | `bool` |
| `rotateToken()` | Generate new token | `void` |
| `field()` | Get hidden input HTML | `string` |
| `getTokenFromRequest(array\|object\|null $body, array $headers)` | Extract token | `?string` |

---

### Fingerprint

Session fingerprinting for hijacking prevention.

```php
use Lalaz\Web\Security\Fingerprint;
```

#### Static Methods

| Method | Description | Return |
|--------|-------------|--------|
| `generate()` | Generate fingerprint for current request | `string` |
| `validate(string $stored)` | Validate against stored fingerprint | `bool` |
| `hash(array $components)` | Hash fingerprint components | `string` |

---

### CsrfMiddleware

Middleware for CSRF validation.

```php
use Lalaz\Web\Security\Middlewares\CsrfMiddleware;
```

#### Constructor

```php
__construct(array $except = [])
```

- `$except` - Route patterns to exclude (supports `*` wildcards)

#### Static Factory Methods

| Method | Description | Return |
|--------|-------------|--------|
| `excludingApi()` | Exclude `/api/*` routes | `self` |
| `excludingWebhooks(array $paths = [])` | Exclude webhook routes | `self` |

#### Example

```php
// Exclude patterns
$middleware = new CsrfMiddleware([
    '/api/*',
    '/webhook/*',
    '/oauth/callback',
]);
```

---

### SecurityHeadersMiddleware

Adds security headers to responses.

```php
use Lalaz\Web\Security\Middlewares\SecurityHeadersMiddleware;
```

#### Constructor

```php
__construct(array $customHeaders = [])
```

#### Static Factory Methods

| Method | Description |
|--------|-------------|
| `with(array $headers)` | Custom headers |
| `minimal()` | Minimal safe defaults |
| `recommended()` | Stricter defaults |
| `strict()` | Full security with HSTS/CSP |
| `api()` | API-optimized headers |

#### Methods

| Method | Description | Return |
|--------|-------------|--------|
| `getHeaders()` | Get configured headers | `array` |

#### Default Headers

```php
[
    'X-Frame-Options' => 'SAMEORIGIN',
    'X-Content-Type-Options' => 'nosniff',
    'X-XSS-Protection' => '1; mode=block',
    'Referrer-Policy' => 'strict-origin-when-cross-origin',
]
```

---

## View Module

### View

Factory for rendering views.

```php
use Lalaz\Web\View\View;
```

#### Static Methods

| Method | Description | Return |
|--------|-------------|--------|
| `make(string $template, array $data = [])` | Render a view | `ViewResponse` |
| `partial(string $template, array $data = [])` | Render partial as string | `string` |

---

### TemplateEngine

Twig template engine wrapper.

```php
use Lalaz\Web\View\TemplateEngine;
```

#### Methods

| Method | Description | Return |
|--------|-------------|--------|
| `getInstance()` | Get singleton instance | `self` |
| `render(string $template, array $data = [])` | Render template | `string` |
| `share(string $key, mixed $value)` | Share data globally | `void` |
| `addFunction(ViewFunction $function)` | Add Twig function | `void` |
| `exists(string $template)` | Check if template exists | `bool` |

---

### ViewContext

Shares data across all views.

```php
use Lalaz\Web\View\ViewContext;
```

#### Static Methods

| Method | Description | Return |
|--------|-------------|--------|
| `set(string $key, mixed $value)` | Set global value | `void` |
| `get(string $key, mixed $default = null)` | Get value (resolves callables) | `mixed` |
| `resolved()` | Get all resolved values | `array` |
| `reset()` | Clear all data | `void` |

---

### ErrorBag

Manages validation errors.

```php
use Lalaz\Web\View\ErrorBag;
```

#### Methods

| Method | Description | Return |
|--------|-------------|--------|
| `add(string $field, string $message)` | Add an error | `void` |
| `fromArray(array $errors)` | Add multiple errors | `void` |
| `has(string $field)` | Check if field has errors | `bool` |
| `first(string $field)` | Get first error for field | `?string` |
| `all(string $field = null)` | Get all errors | `array` |
| `messages()` | Get all errors by field | `array` |
| `count()` | Get total error count | `int` |
| `any()` | Check if any errors exist | `bool` |
| `isEmpty()` | Check if no errors | `bool` |
| `clear()` | Remove all errors | `void` |

---

### FormBuilder

Generates HTML form elements.

```php
use Lalaz\Web\View\FormBuilder;
```

#### Methods

| Method | Description | Return |
|--------|-------------|--------|
| `open(string $action, string $method = 'POST', array $attrs = [])` | Open form tag | `string` |
| `close()` | Close form tag | `string` |
| `csrf()` | CSRF hidden field | `string` |
| `method(string $method)` | Method spoofing field | `string` |
| `text(string $name, ?string $value = null, array $attrs = [])` | Text input | `string` |
| `email(string $name, ?string $value = null, array $attrs = [])` | Email input | `string` |
| `password(string $name, array $attrs = [])` | Password input | `string` |
| `hidden(string $name, ?string $value = null, array $attrs = [])` | Hidden input | `string` |
| `textarea(string $name, ?string $value = null, array $attrs = [])` | Textarea | `string` |
| `select(string $name, array $options, ?string $selected = null, array $attrs = [])` | Select dropdown | `string` |
| `checkbox(string $name, string $value = '1', bool $checked = false, array $attrs = [])` | Checkbox | `string` |
| `radio(string $name, string $value, bool $checked = false, array $attrs = [])` | Radio button | `string` |
| `submit(string $text = 'Submit', array $attrs = [])` | Submit button | `string` |
| `button(string $text, array $attrs = [])` | Button | `string` |
| `label(string $for, string $text, array $attrs = [])` | Label | `string` |

---

### ViewFunction

Defines custom Twig functions.

```php
use Lalaz\Web\View\ViewFunction;
```

#### Constructor

```php
__construct(
    string $name,
    callable $callable,
    array $options = []
)
```

#### Methods

| Method | Description | Return |
|--------|-------------|--------|
| `getName()` | Get function name | `string` |
| `getCallable()` | Get callable | `callable` |
| `getOptions()` | Get Twig options | `array` |

---

### Component

Base class for UI components.

```php
use Lalaz\Web\View\Components\Component;
```

#### Methods

| Method | Description | Return |
|--------|-------------|--------|
| `data()` | Get component data | `array` |
| `template()` | Get template path | `?string` |
| `render()` | Render component | `string` |

---

### ComponentRegistry

Registers and manages components.

```php
use Lalaz\Web\View\Components\ComponentRegistry;
```

#### Methods

| Method | Description | Return |
|--------|-------------|--------|
| `register(string $name, string $class)` | Register component | `void` |
| `get(string $name)` | Get component class | `?string` |
| `has(string $name)` | Check if registered | `bool` |
| `all()` | Get all components | `array` |

---

## Helper Functions

### View Helpers

```php
view(string $template, array $data = []): ViewResponse
partial(string $template, array $data = []): string
```

### Redirect Helpers

```php
redirect(string $url = ''): RedirectResponse
back(): RedirectResponse
```

### Form Helpers

```php
old(string $key, mixed $default = null): mixed
errors(): ErrorBag
```

### Session Helpers

```php
session(): SessionManager
flash(string $key, mixed $value = null): mixed
```

### Security Helpers

```php
csrf_token(): string
csrf_field(): string
```

### Environment Helpers

```php
is_secure(): bool
is_ajax(): bool
client_ip(): string
```

---

## See Also

- [Core Concepts](./concepts.md) — Understanding the architecture
- [Helpers](./helpers.md) — Helper function details
- [Testing Guide](./testing.md) — Writing tests

```
