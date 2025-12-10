# Controllers

Controllers handle HTTP requests and return responses. In Lalaz, controllers are simple PHP classes with methods that receive request data and return responses.

## Generating Controllers

Use the CLI to generate a new controller:

```bash
php lalaz craft:controller User
```

This creates `app/Controllers/UserController.php`:

```php
<?php declare(strict_types=1);

namespace App\Controllers;

use Lalaz\Web\Http\Response;
use Lalaz\Web\Routing\Attribute\Route;

final class UserController
{
    #[Route(path: '/example', method: 'GET')]
    public function handle(Response $response): void
    {
        $response->json(['message' => 'UserController works!']);
    }
}
```

### Invokable Controllers

Generate a single-action controller with `--invokable`:

```bash
php lalaz craft:controller CreateUser --invokable
```

```php
<?php declare(strict_types=1);

namespace App\Controllers;

use Lalaz\Web\Http\Response;
use Lalaz\Web\Routing\Attribute\Route;

final class CreateUserController
{
    #[Route(path: '/example', method: 'GET')]
    public function __invoke(Response $response): void
    {
        $response->json(['message' => 'CreateUserController works!']);
    }
}
```

## Controller Structure

A controller is a plain PHP class. Methods can receive dependencies via injection:

```php
<?php declare(strict_types=1);

namespace App\Controllers;

use Lalaz\Web\Http\Request;
use Lalaz\Web\Http\Response;

class UserController
{
    public function index(): array
    {
        return ['users' => []];
    }

    public function show(Request $request): array
    {
        $id = $request->routeParam('id');
        return ['user' => ['id' => $id]];
    }

    public function store(Request $request, Response $response): void
    {
        $data = $request->json();
        $response->json(['created' => $data], 201);
    }
}
```

## Routing to Controllers

### String Syntax

```php
$router->get('/users', UserController::class . '@index');
$router->get('/users/{id}', UserController::class . '@show');
$router->post('/users', UserController::class . '@store');
```

### Array Syntax

```php
$router->get('/users', [UserController::class, 'index']);
```

### Attribute Routing

```php
use Lalaz\Web\Routing\Attribute\Route;

class UserController
{
    #[Route('/users', method: 'GET')]
    public function index(): array
    {
        return ['users' => []];
    }
}
```

## Dependency Injection

The container automatically injects dependencies into controller methods. Simply type-hint what you need:

### Request Injection

```php
use Lalaz\Web\Http\Request;

public function store(Request $request): array
{
    $data = $request->json();
    return ['data' => $data];
}
```

### Response Injection

```php
use Lalaz\Web\Http\Response;

public function index(Response $response): void
{
    $response->json(['users' => []]);
}
```

### Multiple Dependencies

```php
use Lalaz\Web\Http\Request;
use Lalaz\Web\Http\Response;
use App\Services\UserService;

public function store(
    Request $request,
    Response $response,
    UserService $userService
): void {
    $user = $userService->create($request->json());
    $response->json(['user' => $user], 201);
}
```

### Constructor Injection

For dependencies used across multiple methods, inject them in the constructor:

```php
<?php declare(strict_types=1);

namespace App\Controllers;

use App\Services\UserService;
use Lalaz\Web\Http\Request;

class UserController
{
    public function __construct(
        private UserService $userService
    ) {}

    public function index(): array
    {
        return ['users' => $this->userService->all()];
    }

    public function show(Request $request): array
    {
        $id = (int) $request->routeParam('id');
        return ['user' => $this->userService->find($id)];
    }

    public function store(Request $request): array
    {
        $user = $this->userService->create($request->json());
        return json(['user' => $user], 201);
    }
}
```

## Return Types

Controllers can return data in different ways:

### Array Return (JSON)

Return an array to automatically send a JSON response:

```php
public function index(): array
{
    return ['users' => [], 'count' => 0];
}
```

### Using Response Object

For more control, inject and use the Response object:

```php
public function index(Response $response): void
{
    $response->json(['users' => []], 200);
}

public function download(Response $response): void
{
    $response->download('/path/to/file.pdf', 'report.pdf');
}

public function redirect(Response $response): void
{
    $response->redirect('/dashboard');
}
```

### Using Helper Functions

Use the `json()` helper for JSON responses:

```php
public function index(): mixed
{
    return json(['users' => []], 200);
}

public function store(Request $request): mixed
{
    // Create user...
    return json_success(['user' => $user], 'User created');
}

public function error(): mixed
{
    return json_error('Something went wrong', 500);
}
```

## Controller Best Practices

### Keep Controllers Thin

Controllers should coordinate between request handling and business logic. Move complex logic to service classes:

```php
// ❌ Don't put business logic in controllers
public function store(Request $request): array
{
    $data = $request->json();
    
    // Validation
    if (empty($data['email'])) {
        return json_error('Email is required', 422);
    }
    
    // Database operations
    $user = new User();
    $user->email = $data['email'];
    $user->password = password_hash($data['password'], PASSWORD_DEFAULT);
    $user->save();
    
    // Send email
    mail($user->email, 'Welcome', '...');
    
    return ['user' => $user];
}

// ✅ Use services for business logic
public function store(Request $request, UserService $userService): array
{
    return $userService->create($request->json());
}
```

### One Action Per Method

Each controller method should handle one action:

```php
class UserController
{
    public function index(): array { /* List users */ }
    public function show(Request $request): array { /* Show one user */ }
    public function store(Request $request): array { /* Create user */ }
    public function update(Request $request): array { /* Update user */ }
    public function destroy(Request $request): array { /* Delete user */ }
}
```

### Group Related Actions

Keep related actions in the same controller:

```php
// UserController - User CRUD operations
// UserSettingsController - User settings
// UserNotificationsController - User notifications
```

## Resource Controllers

For RESTful resources, implement standard actions:

```php
<?php declare(strict_types=1);

namespace App\Controllers;

use Lalaz\Web\Http\Request;

class PostController
{
    public function index(): array
    {
        // GET /posts - List all posts
        return ['posts' => []];
    }

    public function store(Request $request): mixed
    {
        // POST /posts - Create a post
        return json(['post' => $request->json()], 201);
    }

    public function show(Request $request): array
    {
        // GET /posts/{postId} - Show a post
        $id = $request->routeParam('postId');
        return ['post' => ['id' => $id]];
    }

    public function update(Request $request): array
    {
        // PUT/PATCH /posts/{postId} - Update a post
        $id = $request->routeParam('postId');
        return ['post' => array_merge(['id' => $id], $request->json())];
    }

    public function destroy(Request $request): array
    {
        // DELETE /posts/{postId} - Delete a post
        $id = $request->routeParam('postId');
        return ['message' => "Post {$id} deleted"];
    }
}
```

Register with the `resource()` method:

```php
$router->resource('posts', PostController::class);
```

## Next Steps

- [Requests](/essentials/requests) - Accessing request data
- [Responses](/essentials/responses) - Sending responses
- [Routing](/essentials/routing) - Route definitions and groups
