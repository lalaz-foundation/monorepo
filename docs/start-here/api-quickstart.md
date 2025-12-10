# API Quickstart

This guide will help you create a REST API using the `lalaz/api` starter in under 5 minutes.

## Create Your Project

```bash
composer create-project lalaz/api my-api
cd my-api
```

## Start the Development Server

```bash
php lalaz serve
```

Your API is now running at `http://localhost:8000`.

::: tip
The server automatically finds an available port if 8000 is in use. You can specify a port with `php lalaz serve --port=3000`.
:::

## Test Your API

Open your browser or use curl:

```bash
curl http://localhost:8000/
```

You should see:

```json
{
    "name": "Lalaz API",
    "message": "Welcome to your new API!",
    "version": "1.0.0",
    "docs": "https://lalaz.dev/docs"
}
```

## Project Structure

After installation, your project looks like this:

```
my-api/
├── app/
│   └── Controllers/
│       └── WelcomeController.php
├── config/
│   └── app.php
├── public/
│   └── index.php
├── routes/
│   └── api.php
├── storage/
├── .env
├── .env.example
├── composer.json
└── lalaz
```

## How It Works

### Entry Point

The entry point is `public/index.php`:

```php
<?php declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Lalaz\Runtime\Http\HttpApplication;

HttpApplication::boot(dirname(__DIR__))->run();
```

`HttpApplication::boot()` loads your configuration, routes, and starts the application.

### Routes

Routes are defined in `routes/api.php`:

```php
<?php declare(strict_types=1);

use Lalaz\Web\Routing\Router;
use App\Controllers\WelcomeController;

return function (Router $router): void {
    $router->get('/health', function (): array {
        return [
            'status' => 'ok',
            'timestamp' => date('c'),
        ];
    });

    $router->get('/', WelcomeController::class . '@index');
};
```

The file returns a closure that receives a `Router` instance. Register routes using methods like `get()`, `post()`, `put()`, `patch()`, and `delete()`.

### Configuration

Configuration is in `config/app.php`:

```php
<?php declare(strict_types=1);

return [
    'app' => [
        'name' => env('APP_NAME', 'Lalaz API'),
        'env' => env('APP_ENV', 'development'),
        'debug' => env('APP_DEBUG', false),
        'url' => env('APP_URL', 'http://localhost'),
        'timezone' => 'UTC',
    ],

    'router' => [
        'files' => [
            __DIR__ . '/../routes/api.php',
        ],
    ],
];
```

Environment variables are loaded from the `.env` file. Use the `env()` helper to read them with optional defaults.

## Create Your First Endpoint

### 1. Generate a Controller

```bash
php lalaz craft:controller User
```

This creates `app/Controllers/UserController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use Lalaz\Web\Routing\Attribute\Route;

class UserController
{
    #[Route('GET', '/users')]
    public function index(): array
    {
        return ['message' => 'UserController@index'];
    }
}
```

### 2. Add Routes

Update `routes/api.php`:

```php
<?php declare(strict_types=1);

use Lalaz\Web\Routing\Router;
use App\Controllers\WelcomeController;
use App\Controllers\UserController;

return function (Router $router): void {
    $router->get('/', WelcomeController::class . '@index');
    $router->get('/health', fn() => ['status' => 'ok']);

    // User routes
    $router->get('/users', UserController::class . '@index');
    $router->get('/users/{id}', UserController::class . '@show');
    $router->post('/users', UserController::class . '@store');
    $router->put('/users/{id}', UserController::class . '@update');
    $router->delete('/users/{id}', UserController::class . '@destroy');
};
```

### 3. Implement the Controller

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use Lalaz\Web\Http\Request;

class UserController
{
    private array $users = [
        1 => ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'],
        2 => ['id' => 2, 'name' => 'Bob', 'email' => 'bob@example.com'],
    ];

    public function index(): array
    {
        return ['users' => array_values($this->users)];
    }

    public function show(Request $request): array
    {
        $id = (int) $request->routeParam('id');
        
        if (!isset($this->users[$id])) {
            return json_error('User not found', 404);
        }

        return ['user' => $this->users[$id]];
    }

    public function store(Request $request): array
    {
        $data = $request->json();
        
        return json([
            'message' => 'User created',
            'user' => [
                'id' => 3,
                'name' => $data['name'] ?? '',
                'email' => $data['email'] ?? '',
            ]
        ], 201);
    }

    public function update(Request $request): array
    {
        $id = (int) $request->routeParam('id');
        $data = $request->json();

        return [
            'message' => 'User updated',
            'user' => array_merge(['id' => $id], $data),
        ];
    }

    public function destroy(Request $request): array
    {
        $id = (int) $request->routeParam('id');
        
        return ['message' => "User {$id} deleted"];
    }
}
```

### 4. Test Your Endpoints

```bash
# List users
curl http://localhost:8000/users

# Get a user
curl http://localhost:8000/users/1

# Create a user
curl -X POST http://localhost:8000/users \
  -H "Content-Type: application/json" \
  -d '{"name": "Charlie", "email": "charlie@example.com"}'

# Update a user
curl -X PUT http://localhost:8000/users/1 \
  -H "Content-Type: application/json" \
  -d '{"name": "Alice Updated"}'

# Delete a user
curl -X DELETE http://localhost:8000/users/1
```

## Using Route Groups

Group related routes with a prefix:

```php
$router->group('/api/v1', function (Router $router): void {
    $router->get('/users', UserController::class . '@index');
    $router->get('/users/{id}', UserController::class . '@show');
});
```

Routes will be accessible at `/api/v1/users` and `/api/v1/users/{id}`.

## Using Resource Routes

For RESTful resources, use the `resource()` method:

```php
$router->resource('users', UserController::class);
```

This registers all standard resource routes:

| Method | Path | Action |
|--------|------|--------|
| GET | /users | index |
| GET | /users/create | create |
| POST | /users | store |
| GET | /users/{userId} | show |
| GET | /users/{userId}/edit | edit |
| PUT | /users/{userId} | update |
| PATCH | /users/{userId} | update |
| DELETE | /users/{userId} | destroy |

Filter actions with `only` or `except`:

```php
// Only index and show
$router->resource('users', UserController::class, only: ['index', 'show']);

// All except create and edit (useful for APIs)
$router->resource('users', UserController::class, except: ['create', 'edit']);
```

## Next Steps

- [Routing](/essentials/routing) - Learn about advanced routing features
- [Controllers](/essentials/controllers) - Controller patterns and dependency injection
- [Requests](/essentials/requests) - Working with request data
- [Responses](/essentials/responses) - JSON responses and status codes
- [CLI Commands](/essentials/cli) - Available CLI commands
