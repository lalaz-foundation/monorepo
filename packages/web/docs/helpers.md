```markdown
# Helper Functions

The Lalaz Web package provides convenient global helper functions for common tasks.

---

## View Helpers

### `view()`

Render a template with data.

```php
// Simple view
return view('home');

// With data
return view('users/profile', [
    'user' => $user,
    'posts' => $posts,
]);

// With nested path
return view('admin/dashboard/index', $data);
```

**Signature:**
```php
function view(string $template, array $data = []): ViewResponse
```

---

### `partial()`

Render a partial template and return as string.

```php
// Render a partial
$html = partial('partials/user-card', ['user' => $user]);

// Use in another template
return view('users/index', [
    'userCards' => $html,
]);
```

**Signature:**
```php
function partial(string $template, array $data = []): string
```

---

## Redirect Helpers

### `redirect()`

Create a redirect response.

```php
// Simple redirect
return redirect('/dashboard');

// With flash data
return redirect('/posts')
    ->with('success', 'Post created!');

// With multiple flash values
return redirect('/users')
    ->with('message', 'Welcome!')
    ->with('type', 'success');
```

**Signature:**
```php
function redirect(string $url = ''): RedirectResponse
```

---

### `back()`

Redirect to the previous URL.

```php
// Simple back
return back();

// With flash data
return back()->with('error', 'Validation failed');

// With old input
return back()->withInput();

// With errors
return back()
    ->withInput()
    ->withErrors([
        'email' => ['Invalid email format'],
    ]);
```

**Signature:**
```php
function back(): RedirectResponse
```

---

## Form Helpers

### `old()`

Get old input from the previous request.

```php
// Get single value
$email = old('email');

// With default
$status = old('status', 'draft');

// In templates
<input type="text" name="title" value="{{ old('title') }}">
```

**Signature:**
```php
function old(string $key, mixed $default = null): mixed
```

---

### `errors()`

Get the error bag instance.

```php
// Get error bag
$errors = errors();

// Check for errors
if ($errors->has('email')) {
    echo $errors->first('email');
}

// In templates
{% if errors.any %}
    {% for message in errors.all %}
        <li>{{ message }}</li>
    {% endfor %}
{% endif %}
```

**Signature:**
```php
function errors(): ErrorBag
```

---

## Session Helpers

### `session()`

Access the session manager.

```php
// Get session manager
$session = session();

// Set value
session()->set('user_id', 123);

// Get value
$userId = session()->get('user_id');

// Get with default
$theme = session()->get('theme', 'light');

// Check if exists
if (session()->has('user_id')) {
    // User is logged in
}

// Delete value
session()->delete('temporary_data');
```

**Signature:**
```php
function session(): SessionManager
```

---

### `flash()`

Set or get a flash message.

```php
// Set a flash message (for next request)
flash('success', 'Operation completed!');
flash('error', 'Something went wrong');

// Get a flash message
$message = flash('success');

// Check if flash exists
if (flash('error')) {
    echo flash('error');
}
```

**Signature:**
```php
function flash(string $key, mixed $value = null): mixed
```

---

## Security Helpers

### `csrf_token()`

Get the current CSRF token.

```php
// Get token for AJAX requests
$token = csrf_token();

// In meta tag for JavaScript
<meta name="csrf-token" content="{{ csrf_token() }}">

// In JavaScript
const token = document.querySelector('meta[name="csrf-token"]').content;
fetch('/api/data', {
    headers: {
        'X-CSRF-TOKEN': token
    }
});
```

**Signature:**
```php
function csrf_token(): string
```

---

### `csrf_field()`

Generate a hidden input with the CSRF token.

```php
// In forms
<form method="POST">
    {{ csrf_field() }}
    <!-- Other fields -->
</form>

// Output:
<input type="hidden" name="_token" value="abc123...">
```

**Signature:**
```php
function csrf_field(): string
```

---

## Environment Helpers

### `is_secure()`

Check if the current request is over HTTPS.

```php
if (is_secure()) {
    // Using HTTPS
} else {
    // Using HTTP - consider redirecting
}
```

**Signature:**
```php
function is_secure(): bool
```

---

### `is_ajax()`

Check if the current request is an AJAX request.

```php
if (is_ajax()) {
    return json(['status' => 'ok']);
} else {
    return view('page');
}
```

**Signature:**
```php
function is_ajax(): bool
```

---

### `client_ip()`

Get the client's IP address.

```php
$ip = client_ip();

// Handles proxies and load balancers
// Checks X-Forwarded-For, X-Real-IP headers
```

**Signature:**
```php
function client_ip(): string
```

---

## Usage in Templates

All helper functions are available as Twig functions:

```twig
{# Views #}
{% include 'partials/header.twig' %}

{# Forms #}
<form method="POST">
    {{ csrf_field() }}
    <input type="text" name="title" value="{{ old('title') }}">
    
    {% if errors.has('title') %}
        <span class="error">{{ errors.first('title') }}</span>
    {% endif %}
    
    <button type="submit">Submit</button>
</form>

{# Flash Messages #}
{% if flash('success') %}
    <div class="alert alert-success">
        {{ flash('success') }}
    </div>
{% endif %}

{# CSRF for AJAX #}
<meta name="csrf-token" content="{{ csrf_token() }}">
```

---

## Complete Example

Here's a controller using multiple helpers:

```php
<?php

namespace App\Controllers;

use Lalaz\Http\Request;

class PostController
{
    public function index(Request $request)
    {
        // Check for success message
        $success = flash('success');
        
        return view('posts/index', [
            'posts' => Post::all(),
            'success' => $success,
        ]);
    }

    public function create()
    {
        return view('posts/create', [
            'errors' => errors(),
        ]);
    }

    public function store(Request $request)
    {
        $validator = new Validator($request->all(), [
            'title' => 'required|min:3',
            'content' => 'required',
        ]);

        if ($validator->fails()) {
            return back()
                ->withInput()
                ->withErrors($validator->errors());
        }

        Post::create([
            'title' => $request->input('title'),
            'content' => $request->input('content'),
        ]);

        flash('success', 'Post created successfully!');

        return redirect('/posts');
    }

    public function destroy(Request $request, $id)
    {
        // CSRF is validated by middleware
        
        Post::destroy($id);

        flash('success', 'Post deleted!');

        return back();
    }
}
```

And the corresponding template:

```twig
{# posts/create.twig #}
{% extends 'layouts/app.twig' %}

{% block content %}
<h1>Create Post</h1>

<form action="/posts" method="POST">
    {{ csrf_field() }}
    
    <div class="form-group">
        <label for="title">Title</label>
        <input 
            type="text" 
            id="title" 
            name="title" 
            value="{{ old('title') }}"
            class="{% if errors.has('title') %}is-invalid{% endif %}"
        >
        {% if errors.has('title') %}
            <span class="error">{{ errors.first('title') }}</span>
        {% endif %}
    </div>
    
    <div class="form-group">
        <label for="content">Content</label>
        <textarea 
            id="content" 
            name="content"
            class="{% if errors.has('content') %}is-invalid{% endif %}"
        >{{ old('content') }}</textarea>
        {% if errors.has('content') %}
            <span class="error">{{ errors.first('content') }}</span>
        {% endif %}
    </div>
    
    <button type="submit">Create Post</button>
</form>
{% endblock %}
```

---

## See Also

- [View System](./view/index.md) — Template rendering
- [Session Manager](./http/session-manager.md) — Session handling
- [Error Bag](./view/error-bag.md) — Validation errors
- [CSRF Protection](./security/csrf-protection.md) — Security tokens

```
