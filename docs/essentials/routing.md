# Routing

Lalaz provides a powerful and expressive routing system. Routes are defined in files inside the `routes/` directory and are automatically loaded based on your configuration.

## Defining Routes

Routes are defined in a route file that returns a closure receiving a `Router` instance:

```php
<?php declare(strict_types=1);

use Lalaz\Web\Routing\Router;

return function (Router $router): void {
    $router->get('/', fn() => ['message' => 'Hello, World!']);
};
```

## Basic Routes

The router provides methods for all standard HTTP verbs:

```php
$router->get('/users', UserController::class . '@index');
$router->post('/users', UserController::class . '@store');
$router->put('/users/{id}', UserController::class . '@update');
$router->patch('/users/{id}', UserController::class . '@update');
$router->delete('/users/{id}', UserController::class . '@destroy');
$router->options('/users', UserController::class . '@options');
$router->head('/users', UserController::class . '@head');
```

### Any Method

Register a route that responds to any HTTP method:

```php
$router->any('/webhook', WebhookController::class . '@handle');
```

### Multiple Methods

Register a route for specific HTTP methods:

```php
$router->methods(['GET', 'POST'], '/form', FormController::class . '@handle');
```

## Route Parameters

### Required Parameters

Use curly braces to define route parameters:

```php
$router->get('/users/{id}', function (Request $request): array {
    $id = $request->routeParam('id');
    return ['user_id' => $id];
});

$router->get('/posts/{postId}/comments/{commentId}', function (Request $request): array {
    $postId = $request->routeParam('postId');
    $commentId = $request->routeParam('commentId');
    return ['post' => $postId, 'comment' => $commentId];
});
```

### Parameter Patterns

Add regex constraints to parameters:

```php
// Only numeric IDs
$router->get('/users/{id:\d+}', UserController::class . '@show');

// Alphanumeric slugs
$router->get('/posts/{slug:[a-z0-9-]+}', PostController::class . '@show');

// Catch-all path
$router->get('/files/{path:.+}', FileController::class . '@serve');
```

## Route Handlers

Routes accept several handler formats:

### Closure Handlers

```php
$router->get('/health', function (): array {
    return ['status' => 'ok', 'timestamp' => date('c')];
});
```

### Controller String Syntax

```php
$router->get('/users', UserController::class . '@index');
$router->get('/users/{id}', 'App\Controllers\UserController@show');
```

### Controller Array Syntax

```php
$router->get('/users', [UserController::class, 'index']);
```

## Route Groups

Group routes with shared attributes like prefixes and middleware:

### Simple Prefix

```php
$router->group('/api', function (Router $router): void {
    $router->get('/users', UserController::class . '@index');     // /api/users
    $router->get('/posts', PostController::class . '@index');     // /api/posts
});
```

### With Attributes Array

```php
$router->group([
    'prefix' => '/admin',
    'middleware' => [AuthMiddleware::class, AdminMiddleware::class]
], function (Router $router): void {
    $router->get('/dashboard', AdminController::class . '@dashboard');
    $router->get('/users', AdminController::class . '@users');
});
```

### Nested Groups

```php
$router->group('/api', function (Router $router): void {
    $router->group('/v1', function (Router $router): void {
        $router->get('/users', UserControllerV1::class . '@index');  // /api/v1/users
    });
    
    $router->group('/v2', function (Router $router): void {
        $router->get('/users', UserControllerV2::class . '@index');  // /api/v2/users
    });
});
```

## Resource Routes

The `resource()` method registers standard RESTful routes for a controller:

```php
$router->resource('users', UserController::class);
```

This creates the following routes:

| Method | Path | Action | Description |
|--------|------|--------|-------------|
| GET | /users | index | List all users |
| GET | /users/create | create | Show create form |
| POST | /users | store | Create a user |
| GET | /users/{userId} | show | Show a user |
| GET | /users/{userId}/edit | edit | Show edit form |
| PUT | /users/{userId} | update | Update a user |
| PATCH | /users/{userId} | update | Partial update |
| DELETE | /users/{userId} | destroy | Delete a user |

::: tip Parameter Naming
The parameter name is derived from the resource name in singular form. For `users`, the parameter is `userId`. For `posts`, it would be `postId`.
:::

### Partial Resources

Use `only` or `except` to limit which routes are registered:

```php
// Only these actions
$router->resource('users', UserController::class, only: ['index', 'show']);

// All except these actions (useful for APIs without HTML forms)
$router->resource('users', UserController::class, except: ['create', 'edit']);
```

## Attribute-Based Routing

Define routes directly on controller methods using PHP 8 attributes:

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use Lalaz\Web\Routing\Attribute\Route;

class UserController
{
    #[Route('/users', method: 'GET')]
    public function index(): array
    {
        return ['users' => []];
    }

    #[Route('/users/{id}', method: 'GET')]
    public function show(int $id): array
    {
        return ['user' => ['id' => $id]];
    }

    #[Route('/users', method: 'POST', middlewares: ['auth'])]
    public function store(): array
    {
        return ['message' => 'User created'];
    }
}
```

### Register Controllers

Register controllers with attribute routes in your configuration:

```php
// config/app.php
return [
    'router' => [
        'controllers' => [
            App\Controllers\UserController::class,
            App\Controllers\PostController::class,
        ],
    ],
];
```

Or register them manually:

```php
$router->registerControllers([
    App\Controllers\UserController::class,
    App\Controllers\PostController::class,
]);
```

### Route Attribute Options

```php
#[Route(
    path: '/users/{id}',           // URL path (required)
    method: 'GET',                  // Single HTTP method
    methods: ['GET', 'HEAD'],       // Multiple HTTP methods
    middlewares: ['auth', 'log']    // Middleware to apply
)]
```

## Route Middleware

Apply middleware to individual routes:

```php
$router->get('/admin', AdminController::class . '@index', [
    AuthMiddleware::class,
    AdminMiddleware::class
]);
```

Or to a group:

```php
$router->group([
    'prefix' => '/admin',
    'middleware' => [AuthMiddleware::class]
], function (Router $router): void {
    // All routes here require authentication
    $router->get('/dashboard', AdminController::class . '@dashboard');
});
```

## Route Configuration

Routes are configured in `config/app.php`:

```php
return [
    'router' => [
        // Route files to load
        'files' => [
            __DIR__ . '/../routes/api.php',
            __DIR__ . '/../routes/web.php',
        ],
        
        // Controllers with attribute routes
        'controllers' => [
            App\Controllers\UserController::class,
        ],
    ],
];
```

## Inspecting Routes

Use the CLI to list all registered routes:

```bash
php lalaz routes:list
```

Validate route definitions:

```bash
php lalaz routes:validate
```

## Route Caching

For production, cache your routes for better performance:

```bash
# Generate route cache
php lalaz route:cache

# Clear route cache
php lalaz route:cache:clear
```

::: warning
Remember to clear the route cache after making changes to your route files.
:::
