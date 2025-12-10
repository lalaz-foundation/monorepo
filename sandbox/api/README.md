# Lalaz API

A minimal REST API starter built with Lalaz Framework.

## Requirements

- PHP 8.3+

## Quick Start

```bash
# Install dependencies
composer install

# Start the server
php lalaz serve
```

Visit `http://localhost:8000` to see your API running.

## Endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/` | Welcome message |
| GET | `/health` | Health check |

## Project Structure

```
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
│   ├── cache/
│   └── logs/
├── .env.example
├── composer.json
└── lalaz
```

## Adding More Routes

Edit `routes/api.php`:

```php
return function (Router $router): void {
    // Closure route
    $router->get('/ping', function (): array {
        return ['pong' => true];
    });

    // Controller route
    $router->get('/users', UserController::class . '@index');
    $router->post('/users', UserController::class . '@store');
};
```

## Adding Packages

Need authentication? Database? Just add what you need:

```bash
composer require lalaz/auth
composer require lalaz/database
composer require lalaz/orm
```

## Documentation

- [Lalaz Documentation](https://lalaz.dev/docs)
- [GitHub Repository](https://github.com/lalaz-foundation/framework)
