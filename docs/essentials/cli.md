# CLI Commands

Lalaz includes a powerful command-line interface (CLI) for common development tasks. The CLI is available through the `lalaz` binary in your project root.

## Running Commands

```bash
php lalaz <command> [arguments] [options]
```

List all available commands:

```bash
php lalaz list
```

Get help for a specific command:

```bash
php lalaz help <command>
```

## Development Server

### `serve`

Start the built-in PHP development server:

```bash
php lalaz serve
```

Options:
- `--port`, `-p` - Port to serve on (default: 8000)
- `--host`, `-H` - Host to bind to (default: localhost)

```bash
# Start on custom port
php lalaz serve --port=3000

# Bind to all interfaces
php lalaz serve --host=0.0.0.0
```

::: tip
If the default port is in use, the server automatically finds the next available port.
:::

## Code Generation

### `craft:controller`

Generate a new controller class:

```bash
php lalaz craft:controller User
php lalaz craft:controller UserController  # Suffix added automatically
```

Options:
- `--invokable` - Generate an invokable controller with `__invoke` method

```bash
php lalaz craft:controller CreateOrder --invokable
```

Generated file: `app/Controllers/UserController.php`

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

### `craft:model`

Generate a new ORM model class:

```bash
php lalaz craft:model User
php lalaz craft:model Post --table=blog_posts
```

Options:
- `--table` - Specify a custom table name

Generated file: `app/Models/User.php`

### `craft:middleware`

Generate a new middleware class:

```bash
php lalaz craft:middleware Auth
php lalaz craft:middleware AuthMiddleware  # Suffix added automatically
```

Generated file: `app/Middleware/AuthMiddleware.php`

```php
<?php declare(strict_types=1);

namespace App\Middleware;

use Lalaz\Web\Http\Contracts\MiddlewareInterface;
use Lalaz\Web\Http\Request;
use Lalaz\Web\Http\Response;

final class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Response $response, callable $next): void
    {
        // Before request handling
        $next($request, $response);
        // After request handling
    }
}
```

### `craft:command`

Generate a new CLI command:

```bash
php lalaz craft:command SendEmails
```

Generated file: `app/Commands/SendEmailsCommand.php`

### `craft:provider`

Generate a new service provider:

```bash
php lalaz craft:provider Payment
```

Generated file: `app/Providers/PaymentServiceProvider.php`

### `craft:route`

Generate a new route file:

```bash
php lalaz craft:route admin
```

Generated file: `routes/admin.php`

## Route Commands

### `routes:list`

Display all registered routes:

```bash
php lalaz routes:list
```

Options:
- `--method` - Filter by HTTP method (GET, POST, etc.)
- `--path` - Filter by URI substring
- `--controller` - Filter by handler/controller name
- `--middleware` - Filter by middleware name
- `--format` - Output format: `table`, `json`, or `md`

```bash
# Show only GET routes
php lalaz routes:list --method=GET

# Filter by path
php lalaz routes:list --path=/api

# Export as JSON
php lalaz routes:list --format=json

# Export as Markdown
php lalaz routes:list --format=md > routes.md
```

### `routes:validate`

Validate route definitions for errors:

```bash
php lalaz routes:validate
```

Checks for:
- Duplicate route definitions
- Invalid handler references
- Missing controller methods
- Invalid middleware classes

### `route:cache`

Cache routes for production:

```bash
php lalaz route:cache
```

### `route:cache:clear`

Clear the route cache:

```bash
php lalaz route:cache:clear
```

## Configuration Commands

### `config:cache`

Cache configuration for production:

```bash
php lalaz config:cache
```

### `config:cache:clear`

Clear the configuration cache:

```bash
php lalaz config:cache:clear
```

### `config:inspect`

Display current configuration values:

```bash
php lalaz config:inspect
```

## Package Management

### `package:list`

List installed Lalaz packages:

```bash
php lalaz package:list
```

### `package:add`

Add a Lalaz package:

```bash
php lalaz package:add lalaz/auth
```

### `package:remove`

Remove a Lalaz package:

```bash
php lalaz package:remove lalaz/auth
```

### `package:info`

Show information about a package:

```bash
php lalaz package:info lalaz/auth
```

### `package:discover`

Discover and register packages:

```bash
php lalaz package:discover
```

## Other Commands

### `version`

Show Lalaz version:

```bash
php lalaz version
```

### `help`

Get help for a command:

```bash
php lalaz help serve
php lalaz help craft:controller
```

## Command Summary

| Category | Command | Description |
|----------|---------|-------------|
| **Server** | `serve` | Start development server |
| **Generate** | `craft:controller` | Generate controller |
| | `craft:model` | Generate model |
| | `craft:middleware` | Generate middleware |
| | `craft:command` | Generate CLI command |
| | `craft:provider` | Generate service provider |
| | `craft:route` | Generate route file |
| **Routes** | `routes:list` | List all routes |
| | `routes:validate` | Validate routes |
| | `route:cache` | Cache routes |
| | `route:cache:clear` | Clear route cache |
| **Config** | `config:cache` | Cache configuration |
| | `config:cache:clear` | Clear config cache |
| | `config:inspect` | Show configuration |
| **Packages** | `package:list` | List packages |
| | `package:add` | Add package |
| | `package:remove` | Remove package |
| | `package:info` | Show package info |
| | `package:discover` | Discover packages |
| **Info** | `version` | Show version |
| | `help` | Command help |
| | `list` | List commands |

## Creating Custom Commands

Create custom commands in `app/Commands/`:

```php
<?php declare(strict_types=1);

namespace App\Commands;

use Lalaz\Console\Contracts\CommandInterface;
use Lalaz\Console\Input;
use Lalaz\Console\Output;

final class GreetCommand implements CommandInterface
{
    public function name(): string
    {
        return 'greet';
    }

    public function description(): string
    {
        return 'Greet a user by name';
    }

    public function arguments(): array
    {
        return [
            [
                'name' => 'name',
                'description' => 'Name to greet',
                'optional' => true,
            ],
        ];
    }

    public function options(): array
    {
        return [
            [
                'name' => 'uppercase',
                'description' => 'Output in uppercase',
            ],
        ];
    }

    public function handle(Input $input, Output $output): int
    {
        $name = $input->argument(0) ?? 'World';
        $message = "Hello, {$name}!";

        if ($input->hasFlag('uppercase')) {
            $message = strtoupper($message);
        }

        $output->writeln($message);
        return 0;
    }
}
```

Run your custom command:

```bash
php lalaz greet John
php lalaz greet John --uppercase
```

## Next Steps

- [Configuration](/essentials/configuration) - Configuration management
- [Routing](/essentials/routing) - Route definitions
- [Controllers](/essentials/controllers) - Controller patterns
