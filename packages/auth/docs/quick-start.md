# Quick Start Guide

Get authentication working in your Lalaz application in under 5 minutes.

---

## Prerequisites

Before you begin, make sure you have:

- A Lalaz Framework project (v1.0+)
- PHP 8.2 or higher
- A database configured (for storing users)

---

## Step 1: Install the Package

```bash
php lalaz package:add lalaz/auth
```

This will:
- Download the auth package
- Register the service provider
- Copy default configuration files

---

## Step 2: Create Your User Model

Create a `User` model that implements the `Authenticatable` trait:

```php
<?php
// app/Models/User.php

namespace App\Models;

use Lalaz\Data\Model;
use Lalaz\Auth\Concerns\Authenticatable;

class User extends Model
{
    use Authenticatable;

    protected static string $tableName = 'users';

    protected static array $fillable = [
        'name',
        'email',
        'password',
    ];

    protected static array $hidden = [
        'password',
    ];
}
```

---

## Step 3: Create the Users Table

Create a migration for your users table:

```bash
php lalaz make:migration create_users_table
```

Edit the migration file:

```php
<?php
// database/migrations/YYYY_MM_DD_create_users_table.php

use Lalaz\Database\Migration;
use Lalaz\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('users');
    }
};
```

Run the migration:

```bash
php lalaz migrate
```

---

## Step 4: Configure Authentication

Edit `config/auth.php`:

```php
<?php

return [
    'defaults' => [
        'guard' => 'web',
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'model',
            'model' => App\Models\User::class,
        ],
    ],
];
```

---

## Step 5: Protect Your Routes

Add the authentication middleware to routes that require login:

```php
<?php
// routes/web.php

use Lalaz\Auth\Middlewares\AuthenticationMiddleware;
use App\Controllers\DashboardController;
use App\Controllers\AuthController;

// Public routes
$router->get('/', [HomeController::class, 'index']);
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);

// Protected routes - require authentication
$router->group('/dashboard', function ($router) {
    $router->get('/', [DashboardController::class, 'index']);
    $router->get('/profile', [DashboardController::class, 'profile']);
})->middleware(AuthenticationMiddleware::web('/login'));
```

---

## Step 6: Create Login/Logout Logic

```php
<?php
// app/Controllers/AuthController.php

namespace App\Controllers;

use Lalaz\Http\Request;
use Lalaz\Http\Response;

class AuthController
{
    public function showLogin(Request $request, Response $response): Response
    {
        return $response->view('auth/login');
    }

    public function login(Request $request, Response $response): Response
    {
        $credentials = [
            'email' => $request->input('email'),
            'password' => $request->input('password'),
        ];

        $user = auth()->attempt($credentials);

        if ($user) {
            // Login successful!
            return $response->redirect('/dashboard');
        }

        // Login failed
        return $response->redirect('/login?error=invalid');
    }

    public function logout(Request $request, Response $response): Response
    {
        auth()->logout();

        return $response->redirect('/');
    }
}
```

---

## Step 7: Use Authentication in Views

```php
<!-- resources/views/layouts/app.php -->

<nav>
    <?php if (authenticated()): ?>
        <span>Hello, <?= user()->name ?>!</span>
        <form action="/logout" method="POST">
            <button type="submit">Logout</button>
        </form>
    <?php else: ?>
        <a href="/login">Login</a>
    <?php endif; ?>
</nav>
```

---

## You're Done! 🎉

Your application now has:

- ✅ User authentication
- ✅ Protected routes
- ✅ Login/logout functionality
- ✅ Session-based security

---

## What's Next?

<table>
<tr>
<td width="50%">

### Add More Features

- [JWT Authentication](./guards/jwt.md) for APIs
- [Roles & Permissions](./authorization.md) for access control
- [API Key Auth](./guards/api-key.md) for integrations

</td>
<td width="50%">

### Learn More

- [Core Concepts](./concepts.md) — Understand Guards & Providers
- [Middlewares](./middlewares/index.md) — Route protection options
- [Helpers](./helpers.md) — Available helper functions

</td>
</tr>
</table>

---

## Common Issues

### "Class User not found"

Make sure your `User` model namespace matches the configuration in `config/auth.php`.

### "Session not starting"

Ensure the `lalaz/web` package is installed and session middleware is registered.

### "Password not matching"

Passwords must be hashed using `password_hash()`. When creating users:

```php
$user = User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => password_hash('secret123', PASSWORD_DEFAULT),
]);
```

### "Redirect loop on login page"

Check that your login route is NOT protected by the authentication middleware.

---

## Complete Example

Here's a minimal working example:

```
app/
├── Controllers/
│   ├── AuthController.php
│   └── DashboardController.php
├── Models/
│   └── User.php
config/
│   └── auth.php
routes/
│   └── web.php
resources/
│   └── views/
│       ├── auth/
│       │   └── login.php
│       └── dashboard/
│           └── index.php
```

**DashboardController.php:**

```php
<?php

namespace App\Controllers;

use Lalaz\Http\Request;
use Lalaz\Http\Response;

class DashboardController
{
    public function index(Request $request, Response $response): Response
    {
        return $response->view('dashboard/index', [
            'user' => user(),
        ]);
    }
}
```

**resources/views/auth/login.php:**

```html
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
</head>
<body>
    <h1>Login</h1>
    
    <?php if (isset($_GET['error'])): ?>
        <p style="color: red;">Invalid credentials. Please try again.</p>
    <?php endif; ?>
    
    <form action="/login" method="POST">
        <div>
            <label>Email:</label>
            <input type="email" name="email" required>
        </div>
        <div>
            <label>Password:</label>
            <input type="password" name="password" required>
        </div>
        <button type="submit">Login</button>
    </form>
</body>
</html>
```

**resources/views/dashboard/index.php:**

```html
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
</head>
<body>
    <h1>Welcome, <?= htmlspecialchars($user->name) ?>!</h1>
    <p>You are logged in.</p>
    
    <form action="/logout" method="POST">
        <button type="submit">Logout</button>
    </form>
</body>
</html>
```

---

<p align="center">
  <a href="./installation.md">← Installation</a> •
  <a href="./concepts.md">Core Concepts →</a>
</p>
