# Configuration

Lalaz uses a simple configuration system based on environment variables and PHP configuration files. The `.env` file holds environment-specific settings, while PHP files in the `config/` directory define application configuration.

## Environment Variables

### The `.env` File

Environment variables are stored in a `.env` file in your project root:

```dotenv
APP_NAME="Lalaz API"
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8000
```

### The `env()` Helper

Access environment variables using the `env()` helper:

```php
$appName = env('APP_NAME', 'My App');
$debug = env('APP_DEBUG', false);
$url = env('APP_URL', 'http://localhost');
```

The second parameter is a default value used when the variable doesn't exist.

### Environment File Example

```dotenv
# Application Settings
APP_NAME="My API"
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=myapp
DB_USERNAME=root
DB_PASSWORD=secret

# Cache
CACHE_DRIVER=file
CACHE_PREFIX=myapp_

# Custom Settings
API_KEY=your-api-key
RATE_LIMIT=100
```

::: warning Security
Never commit your `.env` file to version control. Add it to `.gitignore` and provide a `.env.example` with placeholder values.
:::

## Configuration Files

### Configuration Directory

Configuration files are PHP files in the `config/` directory that return arrays:

```php
// config/app.php
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

### The `config()` Helper

Access configuration values using the `config()` helper with dot notation:

```php
$appName = config('app.name');
$debug = config('app.debug', false);
$timezone = config('app.timezone', 'UTC');
```

## Using the Config Class

For more control, use the `Config` facade directly:

```php
use Lalaz\Config\Config;

// Get values
$name = Config::get('app.name');
$debug = Config::get('app.debug', false);

// Set values at runtime
Config::set('app.timezone', 'America/New_York');
```

### Typed Getters

Get configuration values with type coercion:

```php
use Lalaz\Config\Config;

$port = Config::getInt('server.port', 8000);
$debug = Config::getBool('app.debug', false);
$rate = Config::getFloat('api.rate_limit', 1.5);
$name = Config::getString('app.name', 'App');
$hosts = Config::getArray('app.allowed_hosts', []);
```

### Environment Checks

```php
use Lalaz\Config\Config;

if (Config::isDevelopment()) {
    // Development-only code
}

if (Config::isProduction()) {
    // Production-only code
}

if (Config::isDebug()) {
    // Debug mode enabled
}

if (Config::isEnv('staging')) {
    // Running in staging environment
}
```

## Configuration Structure

A typical configuration file with multiple sections:

```php
// config/app.php
<?php declare(strict_types=1);

return [
    /**
     * Application Settings
     */
    'app' => [
        'name' => env('APP_NAME', 'Lalaz API'),
        'env' => env('APP_ENV', 'development'),
        'debug' => env('APP_DEBUG', false),
        'url' => env('APP_URL', 'http://localhost'),
        'timezone' => 'UTC',
    ],

    /**
     * Router Configuration
     */
    'router' => [
        // Route files to load
        'files' => [
            __DIR__ . '/../routes/api.php',
        ],
        
        // Controllers with attribute routes (optional)
        'controllers' => [
            // App\Controllers\UserController::class,
        ],
    ],

    /**
     * Logging Configuration
     */
    'logging' => [
        'level' => env('LOG_LEVEL', 'debug'),
        'path' => __DIR__ . '/../storage/logs',
    ],
];
```

Access nested values with dot notation:

```php
$logLevel = config('logging.level');
$routeFiles = config('router.files');
```

## Multiple Configuration Files

You can split configuration into multiple files:

```
config/
├── app.php       # Application settings
├── database.php  # Database connections
├── cache.php     # Cache settings
└── mail.php      # Email settings
```

Each file defines its own namespace:

```php
// config/database.php
return [
    'database' => [
        'default' => env('DB_CONNECTION', 'mysql'),
        'connections' => [
            'mysql' => [
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', 3306),
                'database' => env('DB_DATABASE', 'app'),
                'username' => env('DB_USERNAME', 'root'),
                'password' => env('DB_PASSWORD', ''),
            ],
        ],
    ],
];
```

Access with dot notation:

```php
$host = config('database.connections.mysql.host');
$default = config('database.default');
```

## Configuration Caching

For production environments, cache your configuration for better performance:

```bash
# Generate config cache
php lalaz config:cache

# Clear config cache
php lalaz config:cache:clear

# Inspect current configuration
php lalaz config:inspect
```

::: warning
After caching configuration, changes to `.env` or config files won't take effect until you clear the cache.
:::

## Best Practices

### Use Environment Variables for Sensitive Data

```php
// ✅ Good - Uses environment variable
'api_key' => env('STRIPE_API_KEY'),

// ❌ Bad - Hardcoded secret
'api_key' => 'sk_live_abc123...',
```

### Provide Sensible Defaults

```php
// ✅ Good - Safe default for development
'debug' => env('APP_DEBUG', false),

// ✅ Good - Reasonable default
'timezone' => env('APP_TIMEZONE', 'UTC'),
```

### Group Related Settings

```php
return [
    'mail' => [
        'driver' => env('MAIL_DRIVER', 'smtp'),
        'host' => env('MAIL_HOST', 'localhost'),
        'port' => env('MAIL_PORT', 587),
        'from' => [
            'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
            'name' => env('MAIL_FROM_NAME', 'My App'),
        ],
    ],
];
```

### Use Type Coercion

```php
// Environment variables are strings, use typed getters
$port = Config::getInt('server.port', 8000);
$enabled = Config::getBool('feature.enabled', false);
```

## Using Configuration in Controllers

```php
<?php declare(strict_types=1);

namespace App\Controllers;

class AppController
{
    public function info(): array
    {
        return [
            'name' => config('app.name'),
            'version' => config('app.version', '1.0.0'),
            'environment' => config('app.env'),
        ];
    }
}
```

## Config Method Summary

| Method | Description |
|--------|-------------|
| `Config::get($key, $default)` | Get a configuration value |
| `Config::set($key, $value)` | Set a configuration value at runtime |
| `Config::getInt($key, $default)` | Get value as integer |
| `Config::getBool($key, $default)` | Get value as boolean |
| `Config::getFloat($key, $default)` | Get value as float |
| `Config::getString($key, $default)` | Get value as string |
| `Config::getArray($key, $default)` | Get value as array |
| `Config::isEnv($name)` | Check if current environment matches |
| `Config::isDebug()` | Check if debug mode is enabled |
| `Config::isDevelopment()` | Check if in development environment |
| `Config::isProduction()` | Check if in production environment |
| `Config::all()` | Get all configuration values |

## Next Steps

- [CLI Commands](/essentials/cli) - Configuration CLI commands
- [Routing](/essentials/routing) - Route configuration
- [Controllers](/essentials/controllers) - Using config in controllers
