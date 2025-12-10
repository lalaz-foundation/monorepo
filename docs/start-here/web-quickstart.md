# Web Quickstart

Build a full-stack web application with Twig templates, sessions, and CSRF protection.

## Prerequisites

- PHP 8.3 or higher
- Composer 2.x

## Create Your Project

```bash
# Create new project
composer create-project lalaz/web-starter my-app

# Navigate to project
cd my-app

# Start development server
php lalaz serve
```

Open http://localhost:8000 — you should see the welcome page.

## Project Structure

```
my-app/
├── app/
│   └── Controllers/         # Your controllers
├── config/
│   └── app.php             # Configuration
├── public/
│   └── index.php           # Entry point
├── resources/
│   └── views/              # Twig templates
│       ├── layouts/        # Layout templates
│       └── home/           # Page templates
├── routes/
│   └── web.php             # Route definitions
├── storage/
│   ├── cache/              # Cache files
│   └── logs/               # Log files
├── .env                    # Environment config
└── lalaz                   # CLI tool
```

## Creating Pages

### 1. Add a Route

Edit `routes/web.php`:

```php
use App\Controllers\ContactController;

return function (Router $router): void {
    // Existing routes...
    
    $router->get('/contact', ContactController::class . '@form');
    $router->post('/contact', ContactController::class . '@submit');
};
```

### 2. Create the Controller

```bash
php lalaz craft:controller ContactController
```

Edit `app/Controllers/ContactController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use Lalaz\Http\Request;
use Lalaz\Web\Http\RedirectResponse;
use Lalaz\Web\View\ViewResponse;
use Lalaz\Validator\Validator;

use function view;
use function redirect;
use function back;

class ContactController
{
    public function form(): ViewResponse
    {
        return view('contact/form', [
            'title' => 'Contact Us',
        ]);
    }

    public function submit(Request $request): RedirectResponse
    {
        $validator = new Validator($request->post());
        
        if (!$validator->validate([
            'name' => 'required|min:2',
            'email' => 'required|email',
            'message' => 'required|min:10',
        ])) {
            return back()
                ->withInput()
                ->withErrors($validator->errors());
        }

        // Process the form (send email, save to DB, etc.)
        
        return redirect('/')
            ->with('success', 'Thanks for your message!');
    }
}
```

### 3. Create the View

Create `resources/views/contact/form.twig`:

```twig
{% extends "layouts/app.twig" %}

{% block content %}
<section style="max-width: 600px; margin: 0 auto; padding: 2rem 0;">
    <h1>{{ title }}</h1>
    
    <form method="POST" action="/contact" style="margin-top: 1.5rem;">
        {{ csrfField() | raw }}
        
        <div style="margin-bottom: 1rem;">
            <label for="name">Name</label>
            <input type="text" 
                   id="name" 
                   name="name" 
                   value="{{ old('name') }}"
                   style="width: 100%; padding: 0.5rem; {% if hasError('name') %}border-color: red;{% endif %}">
            {% if hasError('name') %}
                <span style="color: red; font-size: 0.875rem;">{{ error('name') }}</span>
            {% endif %}
        </div>
        
        <div style="margin-bottom: 1rem;">
            <label for="email">Email</label>
            <input type="email" 
                   id="email" 
                   name="email" 
                   value="{{ old('email') }}"
                   style="width: 100%; padding: 0.5rem; {% if hasError('email') %}border-color: red;{% endif %}">
            {% if hasError('email') %}
                <span style="color: red; font-size: 0.875rem;">{{ error('email') }}</span>
            {% endif %}
        </div>
        
        <div style="margin-bottom: 1rem;">
            <label for="message">Message</label>
            <textarea id="message" 
                      name="message" 
                      rows="5"
                      style="width: 100%; padding: 0.5rem; {% if hasError('message') %}border-color: red;{% endif %}">{{ old('message') }}</textarea>
            {% if hasError('message') %}
                <span style="color: red; font-size: 0.875rem;">{{ error('message') }}</span>
            {% endif %}
        </div>
        
        <button type="submit" style="background: #68d391; padding: 0.75rem 1.5rem; border: none; cursor: pointer;">
            Send Message
        </button>
    </form>
</section>
{% endblock %}
```

## Key Concepts

### View Responses

Return views from controllers using the `view()` helper:

```php
use function view;

// Simple view
return view('home/index');

// View with data
return view('users/show', ['user' => $user]);

// View with custom status
return view('errors/not-found', [], 404);
```

### Layouts

Create a base layout in `resources/views/layouts/app.twig`:

```twig
<!DOCTYPE html>
<html>
<head>
    <title>{{ title | default('My App') }}</title>
    {% block head %}{% endblock %}
</head>
<body>
    <header>
        <nav>
            <a href="/">Home</a>
            <a href="/about">About</a>
            <a href="/contact">Contact</a>
        </nav>
    </header>
    
    <main>
        {% block content %}{% endblock %}
    </main>
    
    <footer>
        &copy; {{ "now"|date("Y") }} My App
    </footer>
    
    {% block scripts %}{% endblock %}
</body>
</html>
```

Extend it in page templates:

```twig
{% extends "layouts/app.twig" %}

{% block content %}
    <h1>Welcome!</h1>
{% endblock %}
```

### CSRF Protection

All POST forms must include a CSRF token:

```twig
<form method="POST" action="/submit">
    {{ csrfField() | raw }}
    <!-- form fields -->
</form>
```

### Flash Messages

Set flash messages in controllers:

```php
return redirect('/dashboard')
    ->with('success', 'Profile updated!');
```

Display them in views:

```twig
{% if flash('success') %}
    <div class="alert alert-success">{{ flash('success') }}</div>
{% endif %}
```

### Old Input & Validation Errors

After validation fails, repopulate form fields:

```twig
<input type="text" name="email" value="{{ old('email') }}">

{% if hasError('email') %}
    <span class="error">{{ error('email') }}</span>
{% endif %}
```

### Method Spoofing

HTML forms only support GET and POST. For PUT/PATCH/DELETE:

```twig
<form method="POST" action="/users/5">
    {{ csrfField() | raw }}
    {{ methodField('DELETE') | raw }}
    <button>Delete User</button>
</form>
```

## Available CLI Commands

```bash
# Start development server
php lalaz serve

# Create controller
php lalaz craft:controller UserController

# Create view template
php lalaz craft:view users/index

# List all routes
php lalaz routes:list
```

## What's Included

| Feature | Description |
|---------|-------------|
| **Twig Templates** | Powerful templating with layouts, includes, and inheritance |
| **Session Management** | Secure sessions with flash messages |
| **CSRF Protection** | Automatic form protection |
| **Form Helpers** | `old()`, `error()`, `hasError()` for validation UX |
| **Redirects** | Fluent redirect API with `withInput()`, `withErrors()` |
| **View Composers** | Automatic data injection into views |

## Next Steps

- [Routing](/essentials/routing) — Route parameters, groups, and resources
- [Controllers](/essentials/controllers) — Controller patterns and DI
- [Requests](/essentials/requests) — Access request data
- [Configuration](/essentials/configuration) — Environment and config

