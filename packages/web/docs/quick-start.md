```markdown
# Quick Start Guide

Get web features working in your Lalaz application in under 5 minutes.

---

## Prerequisites

Before you begin, make sure you have:

- A Lalaz Framework project (v1.0+)
- PHP 8.2 or higher
- Sessions enabled in PHP

---

## Step 1: Install the Package

```bash
php lalaz package:add lalaz/web
```

This will:
- Download the web package
- Register the service provider
- Copy default configuration files

---

## Step 2: Configure Sessions

Sessions are automatically configured via `config/session.php`:

```php
<?php

return [
    'name' => 'lalaz_session',
    'lifetime' => 120,  // Minutes
    'path' => '/',
    'domain' => null,
    'secure' => true,   // HTTPS only
    'httponly' => true, // No JavaScript access
    'samesite' => 'Lax',
];
```

---

## Step 3: Set Up Views

Create a layout template in `resources/views/layouts/app.twig`:

```twig
<!DOCTYPE html>
<html>
<head>
    <title>{% block title %}My App{% endblock %}</title>
</head>
<body>
    {% if flash('success') %}
        <div class="alert success">{{ flash('success') }}</div>
    {% endif %}
    
    {% if flash('error') %}
        <div class="alert error">{{ flash('error') }}</div>
    {% endif %}
    
    {% block content %}{% endblock %}
</body>
</html>
```

---

## Step 4: Create a Form

Create a form view in `resources/views/posts/create.twig`:

```twig
{% extends 'layouts/app.twig' %}

{% block title %}Create Post{% endblock %}

{% block content %}
<h1>Create New Post</h1>

<form action="/posts" method="POST">
    {{ csrf_field() }}
    
    <div>
        <label>Title</label>
        <input type="text" name="title" value="{{ old('title') }}">
        {% if errors.has('title') %}
            <span class="error">{{ errors.first('title') }}</span>
        {% endif %}
    </div>
    
    <div>
        <label>Content</label>
        <textarea name="content">{{ old('content') }}</textarea>
        {% if errors.has('content') %}
            <span class="error">{{ errors.first('content') }}</span>
        {% endif %}
    </div>
    
    <button type="submit">Create Post</button>
</form>
{% endblock %}
```

---

## Step 5: Handle the Form

Create a controller to handle form submission:

```php
<?php
// app/Controllers/PostController.php

namespace App\Controllers;

use Lalaz\Web\View\View;
use Lalaz\Web\Http\RedirectResponse;

class PostController
{
    public function create($request, $response)
    {
        return View::make('posts/create');
    }

    public function store($request, $response)
    {
        $title = $request->input('title');
        $content = $request->input('content');

        // Validate
        $errors = [];
        if (empty($title)) {
            $errors['title'] = ['Title is required'];
        }
        if (empty($content)) {
            $errors['content'] = ['Content is required'];
        }

        if (!empty($errors)) {
            // Store errors and old input
            errors()->fromArray($errors);
            
            return back()
                ->withInput()
                ->withErrors($errors);
        }

        // Save post...
        Post::create([
            'title' => $title,
            'content' => $content,
        ]);

        // Flash success message
        flash('success', 'Post created successfully!');

        return redirect('/posts');
    }
}
```

---

## Step 6: Set Up Routes

Add routes with CSRF protection:

```php
<?php
// routes/web.php

use Lalaz\Web\Security\Middlewares\CsrfMiddleware;
use App\Controllers\PostController;

// Apply CSRF middleware to all routes
$router->middleware(new CsrfMiddleware());

$router->get('/posts/create', [PostController::class, 'create']);
$router->post('/posts', [PostController::class, 'store']);
```

---

## You're Done! 🎉

Your application now has:

- ✅ Secure session management
- ✅ CSRF protection
- ✅ Flash messages
- ✅ Form validation with error display
- ✅ Old input preservation

---

## What's Next?

<table>
<tr>
<td width="50%">

### Add More Features

- [Security Headers](./security/security-headers.md) for XSS protection
- [Components](./view/components.md) for reusable UI
- [Form Builder](./view/form-builder.md) for easier forms

</td>
<td width="50%">

### Learn More

- [Core Concepts](./concepts.md) — Understand Sessions & Views
- [HTTP Features](./http/index.md) — Session management
- [Helpers](./helpers.md) — Available helper functions

</td>
</tr>
</table>

---

## Common Issues

### "Session not starting"

Make sure session is started before any output. The framework handles this automatically, but check for:
- Whitespace before `<?php`
- BOM characters in files

### "CSRF token mismatch"

Ensure you have:
1. `{{ csrf_field() }}` in your form
2. CSRF middleware applied to the route

### "Flash message not showing"

Flash messages only persist for one request. Make sure:
1. You're not redirecting twice
2. The view checks for flash messages

### "Old input not preserved"

Use `withInput()` when redirecting back:

```php
return back()->withInput();
```

---

## Complete Example

Here's a minimal working example:

```
app/
├── Controllers/
│   └── PostController.php
config/
│   └── session.php
routes/
│   └── web.php
resources/
│   └── views/
│       ├── layouts/
│       │   └── app.twig
│       └── posts/
│           ├── create.twig
│           └── index.twig
```

**PostController.php:**

```php
<?php

namespace App\Controllers;

use Lalaz\Web\View\View;
use App\Models\Post;

class PostController
{
    public function index($request, $response)
    {
        return View::make('posts/index', [
            'posts' => Post::all(),
        ]);
    }

    public function create($request, $response)
    {
        return View::make('posts/create');
    }

    public function store($request, $response)
    {
        // Validation...
        
        Post::create([
            'title' => $request->input('title'),
            'content' => $request->input('content'),
        ]);

        flash('success', 'Post created!');

        return redirect('/posts');
    }
}
```

**resources/views/posts/index.twig:**

```twig
{% extends 'layouts/app.twig' %}

{% block content %}
<h1>Posts</h1>

<a href="/posts/create">Create New Post</a>

<ul>
{% for post in posts %}
    <li>{{ post.title }}</li>
{% endfor %}
</ul>
{% endblock %}
```

---

<p align="center">
  <a href="./installation.md">← Installation</a> •
  <a href="./concepts.md">Core Concepts →</a>
</p>

```
