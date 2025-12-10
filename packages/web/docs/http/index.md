```markdown
# HTTP Overview

The HTTP module provides utilities for handling HTTP requests and responses, session management, cookies, and environment detection.

---

## Components

| Component | Description |
|-----------|-------------|
| [Session Manager](./session-manager.md) | Server-side session handling with fingerprint protection |
| [Redirect Response](./redirect-response.md) | Fluent redirect creation |
| [Cookie Policy](./cookie-policy.md) | Secure cookie management |
| [HTTP Environment](./http-environment.md) | Request environment detection |
| [View Data Bag](./view-data-bag.md) | Request-to-request data passing |
| [Flash Messages](./flash-messages.md) | One-time session messages |

---

## Quick Reference

### Session Manager

```php
use Lalaz\Web\Http\SessionManager;

$session = new SessionManager();

// Basic operations
$session->set('user_id', 123);
$session->get('user_id');
$session->has('user_id');
$session->delete('user_id');

// Flash data
$session->flash('message', 'Success!');
$session->getFlash('message');

// Security
$session->regenerate();
$session->destroy();
```

### Redirect Response

```php
// Simple redirect
return redirect('/dashboard');

// With flash data
return redirect('/posts')
    ->with('success', 'Post created!');

// Back with errors
return back()
    ->withInput()
    ->withErrors(['email' => 'Invalid']);
```

### Cookie Policy

```php
use Lalaz\Web\Http\CookiePolicy;

$cookies = new CookiePolicy();

$cookies->set('theme', 'dark');
$cookies->get('theme');
$cookies->has('theme');
$cookies->delete('theme');
```

### HTTP Environment

```php
use Lalaz\Web\Http\HttpEnvironment;

HttpEnvironment::isSecure();    // HTTPS?
HttpEnvironment::isAjax();      // XMLHttpRequest?
HttpEnvironment::getClientIp();
HttpEnvironment::getUserAgent();
```

---

## Architecture

```
┌─────────────────────────────────────────────────┐
│                 HTTP Module                      │
├─────────────────────────────────────────────────┤
│                                                  │
│  ┌──────────────────┐  ┌──────────────────┐    │
│  │  SessionManager  │  │  CookiePolicy    │    │
│  │                  │  │                  │    │
│  │  - set/get/has   │  │  - set/get/has   │    │
│  │  - flash data    │  │  - secure attrs  │    │
│  │  - fingerprint   │  │  - delete        │    │
│  └────────┬─────────┘  └──────────────────┘    │
│           │                                      │
│           ▼                                      │
│  ┌──────────────────┐                           │
│  │   ViewDataBag    │                           │
│  │                  │                           │
│  │  - old input     │                           │
│  │  - errors        │                           │
│  │  - flash         │                           │
│  └──────────────────┘                           │
│                                                  │
│  ┌──────────────────┐  ┌──────────────────┐    │
│  │ RedirectResponse │  │ HttpEnvironment  │    │
│  │                  │  │                  │    │
│  │  - with()        │  │  - isSecure()    │    │
│  │  - withInput()   │  │  - isAjax()      │    │
│  │  - withErrors()  │  │  - getClientIp() │    │
│  └──────────────────┘  └──────────────────┘    │
│                                                  │
└─────────────────────────────────────────────────┘
```

---

## Common Patterns

### Post-Redirect-Get (PRG)

```php
public function store(Request $request)
{
    // Process form...
    
    if ($errors) {
        return back()
            ->withInput()
            ->withErrors($errors);
    }
    
    flash('success', 'Saved!');
    return redirect('/items');
}
```

### Conditional Response

```php
public function show(Request $request, $id)
{
    $item = Item::find($id);
    
    if (HttpEnvironment::isAjax()) {
        return json(['item' => $item]);
    }
    
    return view('items/show', ['item' => $item]);
}
```

### Secure Session Handling

```php
// After successful login
$session->regenerate();
$session->set('user_id', $user->id);

// On logout
$session->destroy();
```

---

## See Also

- [Session Manager](./session-manager.md) — Detailed session documentation
- [Security Overview](../security/index.md) — CSRF and fingerprinting
- [Middlewares](../middlewares/index.md) — HTTP middleware

```
