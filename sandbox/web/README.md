# Lalaz Web Starter

A full-stack web application starter with Twig templates, session management, and CSRF protection.

## Requirements

- PHP 8.3+
- Composer 2.x

## Quick Start

```bash
# Clone or create from starter
composer create-project lalaz/web-starter my-app

# Navigate to project
cd my-app

# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Start development server
php lalaz serve
```

Open http://localhost:8000 in your browser.

## Project Structure

```
my-app/
├── app/
│   └── Controllers/       # Application controllers
├── config/
│   └── app.php           # Application configuration
├── public/
│   └── index.php         # Entry point
├── resources/
│   └── views/            # Twig templates
│       ├── layouts/      # Layout templates
│       └── home/         # Page templates
├── routes/
│   └── web.php           # Route definitions
├── storage/
│   ├── cache/            # Cache files
│   └── logs/             # Log files
├── .env.example          # Environment template
├── composer.json
└── lalaz                 # CLI tool
```

## Creating Views

Views are Twig templates stored in `resources/views/`:

```php
// In your controller
use function view;

public function index(): ViewResponse
{
    return view('home/index', [
        'title' => 'Welcome',
        'users' => User::all(),
    ]);
}
```

### Layout System

Create a layout in `resources/views/layouts/app.twig`:

```twig
<!DOCTYPE html>
<html>
<head>
    <title>{{ title }} - My App</title>
</head>
<body>
    {% block content %}{% endblock %}
</body>
</html>
```

Extend it in your pages:

```twig
{% extends "layouts/app.twig" %}

{% block content %}
    <h1>{{ title }}</h1>
{% endblock %}
```

## Routing

Define routes in `routes/web.php`:

```php
use Lalaz\Web\Routing\Router;
use App\Controllers\HomeController;
use App\Controllers\UserController;

return function (Router $router): void {
    $router->get('/', HomeController::class . '@index');
    
    // Route with parameter
    $router->get('/users/{id}', UserController::class . '@show');
    
    // Form routes
    $router->get('/contact', ContactController::class . '@form');
    $router->post('/contact', ContactController::class . '@submit');
};
```

## Forms & CSRF Protection

All forms must include a CSRF token:

```twig
<form method="POST" action="/contact">
    {{ csrfField() | raw }}
    
    <input type="text" name="name" value="{{ old('name') }}">
    {% if hasError('name') %}
        <span class="error">{{ error('name') }}</span>
    {% endif %}
    
    <button type="submit">Send</button>
</form>
```

Handle form submission:

```php
use function redirect;
use function back;

public function submit(Request $request): RedirectResponse
{
    $validator = new Validator($request->post());
    
    if (!$validator->validate(['name' => 'required'])) {
        return back()
            ->withInput()
            ->withErrors($validator->errors());
    }
    
    // Process form...
    
    return redirect('/')
        ->with('success', 'Message sent!');
}
```

## Flash Messages

Display flash messages in your layout:

```twig
{% if flash('success') %}
    <div class="alert alert-success">{{ flash('success') }}</div>
{% endif %}

{% if flash('error') %}
    <div class="alert alert-error">{{ flash('error') }}</div>
{% endif %}
```

## CLI Commands

```bash
# Start development server
php lalaz serve

# Create controller
php lalaz craft:controller UserController

# Create view
php lalaz craft:view users/index

# List routes
php lalaz routes:list
```

## Documentation

Full documentation: https://docs.lalaz.dev

## License

MIT
