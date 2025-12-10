```markdown
# Glossary

A reference guide to web terminology used throughout the Lalaz Web package.

---

## HTTP Concepts

### Session
Server-side storage associated with a user's browser session. Data persists across requests until the session expires or is destroyed.

> "Sessions store user data on the server, identified by a cookie in the browser."

### Cookie
A small piece of data stored in the user's browser. Used to track sessions, store preferences, and maintain state.

**Types:**
- **Session cookie**: Expires when browser closes
- **Persistent cookie**: Has an expiration date
- **Secure cookie**: Only sent over HTTPS
- **HttpOnly cookie**: Not accessible via JavaScript

### Flash Data
Session data that exists only for a single request. Perfect for success/error messages after form submissions.

```php
flash('success', 'Operation completed!');
// Available only on the next request
```

### Redirect
An HTTP response that instructs the browser to navigate to a different URL. Uses status codes 301 (permanent), 302 (temporary), or 303 (see other).

```php
return redirect('/dashboard');
return back()->with('message', 'Saved!');
```

---

## Security Terms

### CSRF (Cross-Site Request Forgery)
An attack where a malicious site tricks users into submitting requests to your site while authenticated. CSRF tokens prevent this.

> "Without CSRF protection, clicking a link on an evil site could delete your account!"

### CSRF Token
A unique, unpredictable value that proves a form submission came from your application, not an attacker's site.

```twig
<form method="POST">
    {{ csrf_field() }}
    <!-- Creates: <input type="hidden" name="_token" value="abc123..."> -->
</form>
```

### Session Hijacking
An attack where an attacker steals or guesses a user's session ID to impersonate them. Fingerprinting helps prevent this.

### Fingerprint
A hash of browser characteristics (user agent, language, IP) used to validate that a session hasn't been stolen by a different browser/device.

### Security Headers
HTTP headers that instruct browsers to enable security features:

| Header | Purpose |
|--------|---------|
| `X-Frame-Options` | Prevent clickjacking |
| `X-Content-Type-Options` | Prevent MIME sniffing |
| `X-XSS-Protection` | Enable XSS filter |
| `Content-Security-Policy` | Control resource loading |
| `Strict-Transport-Security` | Force HTTPS |

### Content Security Policy (CSP)
A security header that controls which resources the browser is allowed to load. Prevents XSS and data injection attacks.

```
Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'
```

### HSTS (HTTP Strict Transport Security)
A header that tells browsers to only connect via HTTPS, even if the user types `http://`.

```
Strict-Transport-Security: max-age=31536000; includeSubDomains
```

### Clickjacking
An attack where a malicious site overlays invisible frames to trick users into clicking buttons on your site. `X-Frame-Options` prevents this.

### MIME Sniffing
When browsers ignore the declared content type and "sniff" the actual content. Can lead to security issues. `X-Content-Type-Options: nosniff` prevents this.

---

## View System

### Template Engine
Software that processes template files (like Twig) and combines them with data to produce HTML output.

```php
$engine->render('users/profile', ['user' => $user]);
```

### Twig
The template engine used by Lalaz Web. Provides secure, expressive syntax for views.

```twig
{% for user in users %}
    <li>{{ user.name }}</li>
{% endfor %}
```

### View Context
Global data shared across all views. Useful for user info, app settings, and other common data.

```php
ViewContext::set('app_name', 'My App');
// Available in all views as {{ app_name }}
```

### Error Bag
A collection of validation errors organized by field name. Makes displaying form errors simple.

```php
$errors->add('email', 'Invalid email format');
$errors->first('email'); // 'Invalid email format'
```

### Partial
A small template file designed to be included in other templates. Used for reusable HTML snippets.

```twig
{% include 'partials/header.twig' %}
```

### Component
A self-contained, reusable UI element with its own logic and template. More powerful than partials.

```php
class Alert extends Component
{
    public function __construct(
        public string $type,
        public string $message
    ) {}
}
```

---

## Form Handling

### Method Spoofing
A technique to simulate PUT, PATCH, or DELETE HTTP methods from HTML forms, which only support GET and POST.

```html
<form method="POST">
    <input type="hidden" name="_method" value="DELETE">
</form>
```

### Old Input
Form input from the previous request, preserved after validation failure so users don't have to re-enter data.

```php
old('email'); // Get previous value
return back()->withInput(); // Preserve all input
```

### Form Builder
A class that generates HTML form elements programmatically with proper attributes and values.

```php
$form->text('username', old('username'), ['class' => 'input']);
// <input type="text" name="username" value="..." class="input">
```

---

## Middleware

### Middleware
Code that processes requests before they reach your controller, or responses before they're sent to the browser.

```
Request → Middleware 1 → Middleware 2 → Controller
               ↑                             ↓
Response ← Middleware 1 ← Middleware 2 ← Response
```

### Middleware Pipeline
The chain of middleware that a request passes through. Each middleware can:
- Process the request
- Pass to the next middleware
- Return a response early

### Global Middleware
Middleware that runs on every request in your application.

### Route Middleware
Middleware that only runs on specific routes or route groups.

---

## Session Management

### Session ID
A unique identifier (usually stored in a cookie) that links a browser to its server-side session data.

### Session Regeneration
Creating a new session ID while preserving session data. Should be done after login to prevent session fixation attacks.

```php
$session->regenerate();
```

### Session Fixation
An attack where an attacker sets a victim's session ID before login, allowing them to access the authenticated session.

### Session Lifetime
How long a session remains valid. Controlled by PHP settings and cookie expiration.

```php
'lifetime' => 120, // Session expires after 120 minutes
```

### Session Driver
Where session data is stored:
- **File**: Server filesystem (default)
- **Database**: In a database table
- **Redis/Memcached**: In-memory cache

---

## Request Information

### HTTP Environment
Details about the current HTTP request: method, headers, IP address, etc.

### Client IP
The IP address of the user making the request. Can be complicated when using proxies or load balancers.

### User Agent
A string identifying the user's browser and operating system.

```
Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36...
```

### Referer (Referrer)
The URL of the page that linked to the current request. Note: deliberately misspelled in HTTP spec.

### AJAX Request
A request made by JavaScript (XMLHttpRequest or fetch), not by navigating in the browser.

```php
if (HttpEnvironment::isAjax()) {
    return json(['status' => 'ok']);
}
```

---

## Response Types

### View Response
An HTTP response containing rendered HTML from a template.

```php
return View::make('home', ['title' => 'Welcome']);
```

### Redirect Response
An HTTP response that instructs the browser to navigate elsewhere.

```php
return redirect('/login');
return back();
```

### JSON Response
An HTTP response containing JSON data, typically for APIs.

```php
return response()->json(['data' => $items]);
```

---

## Cookie Attributes

### Secure
Cookie only sent over HTTPS connections.

### HttpOnly
Cookie not accessible via JavaScript's `document.cookie`.

### SameSite
Controls when cookies are sent with cross-site requests:
- **Strict**: Never sent cross-site
- **Lax**: Sent on top-level navigation
- **None**: Always sent (requires Secure)

### Expires / Max-Age
When the cookie should be deleted by the browser.

### Domain
Which domain(s) the cookie is valid for.

### Path
Which URL paths the cookie is valid for.

---

## Helper Functions

### `view()`
Render a template with data.

```php
return view('users/show', ['user' => $user]);
```

### `redirect()`
Create a redirect response.

```php
return redirect('/home');
```

### `back()`
Redirect to the previous URL.

```php
return back()->withErrors($errors);
```

### `old()`
Get old input from the previous request.

```php
<input value="{{ old('email') }}">
```

### `errors()`
Get the error bag instance.

```php
{% if errors().has('email') %}
    {{ errors().first('email') }}
{% endif %}
```

### `flash()`
Set or get a flash message.

```php
flash('success', 'Saved!');  // Set
$message = flash('success'); // Get
```

### `csrf_token()`
Get the current CSRF token.

```php
<meta name="csrf-token" content="{{ csrf_token() }}">
```

### `csrf_field()`
Generate a hidden input with the CSRF token.

```twig
{{ csrf_field() }}
{# <input type="hidden" name="_token" value="..."> #}
```

---

## Twig Syntax

### Variable Output
Display a variable (auto-escaped for safety):

```twig
{{ user.name }}
{{ message|escape }}
```

### Conditionals

```twig
{% if user.isAdmin %}
    Admin content
{% elseif user.isModerator %}
    Moderator content
{% else %}
    Regular content
{% endif %}
```

### Loops

```twig
{% for item in items %}
    {{ item.name }}
{% else %}
    No items found
{% endfor %}
```

### Template Inheritance

```twig
{# layout.twig #}
<!DOCTYPE html>
<html>
<body>
    {% block content %}{% endblock %}
</body>
</html>

{# page.twig #}
{% extends 'layout.twig' %}
{% block content %}
    Page content here
{% endblock %}
```

### Includes

```twig
{% include 'partials/sidebar.twig' %}
{% include 'partials/card.twig' with {'title': 'Hello'} %}
```

---

## See Also

- [Core Concepts](./concepts.md) — Detailed explanation of sessions, views, and security
- [Quick Start](./quick-start.md) — Get started in 5 minutes
- [API Reference](./api-reference.md) — Complete method documentation

---

<p align="center">
  <sub>Can't find a term? <a href="https://github.com/lalaz-foundation/framework/issues">Open an issue</a> and we'll add it!</sub>
</p>

```
