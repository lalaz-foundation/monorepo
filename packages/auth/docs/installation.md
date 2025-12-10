# Installation & Configuration

This guide walks you through installing and configuring Lalaz Auth in your application.

## Requirements

Before installing, make sure you have:

- **PHP 8.2** or higher
- **Lalaz Framework 1.0** or higher
- **Composer** for package management

## Installation

### Step 1: Install the Package

Run the following command in your project root:

```bash
php lalaz package:add lalaz/auth
```

### Step 2: Install Optional Packages

Depending on your authentication needs:

```bash
# For session-based authentication (web apps)


# For ORM-based user models
php lalaz package:add lalaz/orm
```

## Configuration

### Step 1: Create the Configuration File

Create a file at `config/auth.php`:

```php
<?php declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard" and password
    | reset "broker" for your application.
    |
    */
    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'provider' => 'users',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Here you may define every authentication guard for your application.
    | A guard defines how users are authenticated for each request.
    |
    | Supported drivers: "session", "jwt", "api_key"
    |
    */
    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        'api' => [
            'driver' => 'jwt',
            'provider' => 'users',
        ],
        'api-key' => [
            'driver' => 'api_key',
            'provider' => 'users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | User providers define how users are retrieved from your database or
    | other storage system.
    |
    | Supported drivers: "model", "generic"
    |
    */
    'providers' => [
        'users' => [
            'driver' => 'model',
            'model' => env('AUTH_MODEL', 'App\\Models\\User'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | JWT Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for JSON Web Token authentication.
    |
    */
    'jwt' => [
        // Secret key for signing tokens (KEEP THIS SECRET!)
        'secret' => env('JWT_SECRET', ''),
        
        // Algorithm for signing (HS256, HS384, HS512, RS256)
        'algorithm' => env('JWT_ALGORITHM', 'HS256'),
        
        // Access token lifetime in seconds (default: 1 hour)
        'ttl' => env('JWT_TTL', 3600),
        
        // Refresh token lifetime in seconds (default: 7 days)
        'refresh_ttl' => env('JWT_REFRESH_TTL', 604800),
        
        // Enable token blacklisting for logout
        'blacklist_enabled' => env('JWT_BLACKLIST_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for session-based authentication.
    |
    */
    'session' => [
        // Session key for storing user ID
        'key' => env('AUTH_SESSION_KEY', '__auth_user'),
        
        // Remember me cookie name
        'remember_key' => env('AUTH_REMEMBER_KEY', 'remember_token'),
        
        // Remember me duration in seconds (default: 30 days)
        'remember_ttl' => env('AUTH_REMEMBER_TTL', 2592000),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Key Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for API key authentication.
    |
    */
    'api_key' => [
        // Header name for API key
        'header' => env('API_KEY_HEADER', 'X-API-Key'),
        
        // Query parameter name (fallback)
        'query_param' => env('API_KEY_QUERY_PARAM', 'api_key'),
        
        // Hash algorithm for storing keys
        'hash_algorithm' => 'sha256',
    ],

    /*
    |--------------------------------------------------------------------------
    | Redirect Paths
    |--------------------------------------------------------------------------
    |
    | Where to redirect users for various authentication events.
    |
    */
    'redirects' => [
        'login' => '/login',
        'logout' => '/',
        'home' => '/dashboard',
        'unauthorized' => '/unauthorized',
    ],
];
```

### Step 2: Set Environment Variables

Add these to your `.env` file:

```env
# Default authentication guard
AUTH_GUARD=web

# User model class
AUTH_MODEL=App\Models\User

# JWT Configuration
# Generate with: php -r "echo bin2hex(random_bytes(32));"
JWT_SECRET=your-super-secret-key-at-least-32-characters
JWT_ALGORITHM=HS256
JWT_TTL=3600
JWT_REFRESH_TTL=604800
JWT_BLACKLIST_ENABLED=true

# Session Configuration
AUTH_SESSION_KEY=__auth_user
AUTH_REMEMBER_KEY=remember_token
AUTH_REMEMBER_TTL=2592000

# API Key Configuration
API_KEY_HEADER=X-API-Key
API_KEY_QUERY_PARAM=api_key
```

### Step 3: Generate a JWT Secret

For security, generate a strong random secret:

```bash
php -r "echo bin2hex(random_bytes(32));"
```

Copy the output and set it as your `JWT_SECRET` in `.env`.

> ⚠️ **Important**: Never commit your JWT secret to version control!

## Setting Up Your User Model

Your User model should implement `AuthenticatableInterface`:

```php
<?php declare(strict_types=1);

namespace App\Models;

use Lalaz\Auth\Contracts\AuthenticatableInterface;
use Lalaz\Auth\Concerns\Authenticatable;
use Lalaz\Auth\Concerns\Authorizable;
use Lalaz\Data\Model;

class User extends Model implements AuthenticatableInterface
{
    // Add authentication capabilities
    use Authenticatable;
    
    // Add authorization (roles/permissions) capabilities
    use Authorizable;

    /**
     * The database table name.
     */
    protected static string $tableName = 'users';

    /**
     * The field used for login (email, username, etc.)
     */
    protected static function usernamePropertyName(): string
    {
        return 'email';
    }

    /**
     * The field that stores the password hash.
     */
    protected static function passwordPropertyName(): string
    {
        return 'password';
    }

    /**
     * Get the unique identifier (usually the primary key).
     */
    public function getAuthIdentifier(): mixed
    {
        return $this->id;
    }

    /**
     * Get the name of the unique identifier column.
     */
    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    /**
     * Get the hashed password.
     */
    public function getAuthPassword(): string
    {
        return $this->password;
    }

    /**
     * Fetch user roles from database.
     * Override this method to load roles from your database.
     */
    protected function fetchRoles(): array
    {
        // Example: return $this->roles()->pluck('name')->toArray();
        return [];
    }

    /**
     * Fetch user permissions from database.
     * Override this method to load permissions from your database.
     */
    protected function fetchPermissions(): array
    {
        // Example: return $this->permissions()->pluck('name')->toArray();
        return [];
    }
}
```

## Database Schema

Here's a typical users table migration:

```php
<?php declare(strict_types=1);

use Lalaz\Data\Migrations\Migration;
use Lalaz\Data\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('remember_token')->nullable();
            $table->string('api_key_hash')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('users');
    }
};
```

## Verifying Installation

Create a simple test to verify everything is working:

```php
<?php

use Lalaz\Auth\AuthManager;
use Lalaz\Auth\AuthContext;

// Check if classes can be loaded
$manager = resolve(AuthManager::class);
$context = resolve(AuthContext::class);

echo "Auth package installed successfully!";
echo "Default guard: " . $manager->getDefaultGuard();
```

## Troubleshooting

### Common Issues

**1. Class not found errors**

Make sure you ran `composer dump-autoload`:

```bash
composer dump-autoload
```

**2. JWT Secret not set**

Check your `.env` file has `JWT_SECRET` defined. Generate one with:

```bash
php -r "echo bin2hex(random_bytes(32));"
```

**3. Session not working**

Make sure `lalaz/web` is installed and sessions are started:

```bash
composer require lalaz/web
```

**4. User model not found**

Verify `AUTH_MODEL` in `.env` matches your actual User class path.

## Next Steps

Now that you have Lalaz Auth installed:

1. Learn about [Core Concepts](./concepts.md)
2. Choose your [Authentication Guard](./guards/index.md)
3. Set up [User Providers](./providers/index.md)
4. Protect routes with [Middlewares](./middlewares/index.md)
